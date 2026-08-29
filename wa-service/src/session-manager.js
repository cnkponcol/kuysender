const fs = require('node:fs');
const fsp = require('node:fs/promises');
const path = require('node:path');
const pino = require('pino');
const QRCode = require('qrcode');
const {
  default: makeWASocket,
  useMultiFileAuthState,
  DisconnectReason,
  fetchLatestBaileysVersion,
  jidNormalizedUser,
  getContentType,
} = require('@whiskeysockets/baileys');
const config = require('./config');
const { useDatabaseAuthState, deleteDatabaseAuth, listDatabaseAuthSessionIds } = require('./db-auth-state');
const { report } = require('./event-reporter');
const { cleanPhone, fetchRemoteBuffer, readSafeLocalFile, validateSessionId } = require('./security');

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });
const silent = pino({ level: 'silent' });
const INTERNAL_MESSAGE_TYPES = new Set(['protocolMessage', 'senderKeyDistributionMessage']);

function contactSnapshot(contact = {}) {
  const jid = String(contact.id || contact.jid || '').trim();
  if (!jid || jid === 'status@broadcast' || jid.endsWith('@g.us')) return null;
  if (!jid.endsWith('@s.whatsapp.net') && !jid.endsWith('@lid')) return null;
  const name = contact.name || contact.notify || contact.verifiedName || contact.verified_name || null;
  return {
    jid,
    number: jid.split('@')[0] || null,
    name: name ? String(name) : null,
    notify: contact.notify ? String(contact.notify) : null,
    verified_name: (contact.verifiedName || contact.verified_name) ? String(contact.verifiedName || contact.verified_name) : null,
    jid_type: jid.endsWith('@lid') ? 'lid' : 'phone',
  };
}

function cacheContacts(holder, contacts = []) {
  for (const raw of contacts || []) {
    const item = contactSnapshot(raw);
    if (!item) continue;
    const previous = holder.contacts.get(item.jid) || {};
    holder.contacts.set(item.jid, { ...previous, ...item });
  }
}

function extractText(message = {}) {
  const type = getContentType(message);
  const content = type ? message[type] : null;
  if (!type) return { type: 'unknown', body: '' };
  if (type === 'conversation') return { type: 'text', body: String(content || '') };
  if (type === 'extendedTextMessage') return { type: 'text', body: content?.text || '' };
  if (type === 'imageMessage') return { type: 'image', body: content?.caption || '' };
  if (type === 'videoMessage') return { type: 'video', body: content?.caption || '' };
  if (type === 'documentMessage') return { type: 'document', body: content?.caption || content?.fileName || '' };
  if (type === 'audioMessage') return { type: 'audio', body: '' };
  if (type === 'stickerMessage') return { type: 'sticker', body: '' };
  if (type === 'buttonsResponseMessage') return { type: 'text', body: content?.selectedDisplayText || content?.selectedButtonId || '' };
  if (type === 'listResponseMessage') return { type: 'text', body: content?.singleSelectReply?.selectedRowId || content?.title || '' };
  return { type: type.replace(/Message$/, ''), body: content?.text || content?.caption || '' };
}

class SessionManager {
  constructor() {
    this.sessions = new Map();
    this.reconnectAttempts = new Map();
    this.reconnectTimers = new Map();
    this.connectPromises = new Map();
    this.reconcileTimer = null;
  }

  sessionPath(sessionId) {
    return path.join(config.sessionDir, validateSessionId(sessionId));
  }

  async startExisting() {
    if (config.authStorage === 'database') {
      const sessionIds = await listDatabaseAuthSessionIds();
      for (const sessionId of sessionIds) {
        this.connect(sessionId).catch((error) => logger.warn({ sessionId, error: error.message }, 'Failed to restore database session'));
      }
      this.startReconciler();
      return;
    }

    await fsp.mkdir(config.sessionDir, { recursive: true, mode: 0o700 });
    const entries = await fsp.readdir(config.sessionDir, { withFileTypes: true });
    for (const entry of entries) {
      if (!entry.isDirectory()) continue;
      try {
        validateSessionId(entry.name);
        if (fs.existsSync(path.join(config.sessionDir, entry.name, 'creds.json'))) {
          this.connect(entry.name).catch((error) => logger.warn({ sessionId: entry.name, error: error.message }, 'Failed to restore file session'));
        }
      } catch {}
    }
  }

  clearReconnectTimer(sessionId) {
    const timer = this.reconnectTimers.get(sessionId);
    if (timer) clearTimeout(timer);
    this.reconnectTimers.delete(sessionId);
  }

  startReconciler() {
    if (this.reconcileTimer || config.authStorage !== 'database') return;
    this.reconcileTimer = setInterval(async () => {
      try {
        const ids = await listDatabaseAuthSessionIds();
        for (const sessionId of ids) {
          const holder = this.sessions.get(sessionId);
          if (!holder || holder.state === 'disconnected') {
            this.connect(sessionId).catch((error) => logger.warn({ sessionId, error: error.message }, 'DB session reconcile failed'));
            continue;
          }
          if (holder.state === 'connected' && holder.socket?.ws && !holder.socket.ws.isOpen) {
            logger.warn({ sessionId }, 'Connected session has a closed WebSocket; forcing reconnect');
            this.sessions.delete(sessionId);
            try { holder.socket.end?.(new Error('WebSocket health check failed')); } catch {}
            this.scheduleReconnect(sessionId);
            continue;
          }
          if (holder.state === 'connecting' && Date.now() - holder.startedAt > config.connectTimeoutMs + 30000) {
            logger.warn({ sessionId }, 'Connection attempt exceeded watchdog timeout; retrying');
            this.sessions.delete(sessionId);
            try { holder.socket?.end?.(new Error('Connection watchdog timeout')); } catch {}
            this.scheduleReconnect(sessionId);
          }
        }
      } catch (error) {
        logger.warn({ error: error.message }, 'DB session reconcile scan failed');
      }
    }, config.reconcileIntervalMs);
    this.reconcileTimer.unref?.();
  }

  health() {
    const values = [...this.sessions.values()];
    return {
      active_sessions: values.length,
      connected_sessions: values.filter((item) => item.state === 'connected' && (!item.socket?.ws || item.socket.ws.isOpen)).length,
      connecting_sessions: values.filter((item) => item.state === 'connecting').length,
      qr_sessions: values.filter((item) => item.state === 'qr').length,
    };
  }

  get(sessionId) {
    return this.sessions.get(validateSessionId(sessionId));
  }

  status(sessionId) {
    const current = this.get(sessionId);
    if (!current) return { connection_state: 'disconnected', whatsapp_number: null, push_name: null };
    return {
      connection_state: current.state,
      whatsapp_number: current.number || null,
      push_name: current.pushName || null,
      qr_code: current.qr || null,
      qr_expires_at: current.qrExpiresAt || null,
    };
  }

  async connect(sessionId) {
    sessionId = validateSessionId(sessionId);
    const existing = this.sessions.get(sessionId);
    if (["connected", "connecting", "qr"].includes(existing?.state)) return this.status(sessionId);
    const running = this.connectPromises.get(sessionId);
    if (running) return running;
    const task = this.connectInternal(sessionId).finally(() => this.connectPromises.delete(sessionId));
    this.connectPromises.set(sessionId, task);
    return task;
  }

  async connectInternal(sessionId) {
    sessionId = validateSessionId(sessionId);
    this.clearReconnectTimer(sessionId);
    const existing = this.sessions.get(sessionId);
    if (['connected', 'connecting', 'qr'].includes(existing?.state)) {
      return this.status(sessionId);
    }

    let state;
    let saveCreds;
    if (config.authStorage === 'database') {
      ({ state, saveCreds } = await useDatabaseAuthState(sessionId));
      await saveCreds();
    } else {
      const dir = this.sessionPath(sessionId);
      await fsp.mkdir(dir, { recursive: true, mode: 0o700 });
      ({ state, saveCreds } = await useMultiFileAuthState(dir));
    }
    const { version } = await fetchLatestBaileysVersion();

    const holder = { socket: null, state: 'connecting', number: null, pushName: null, qr: null, qrExpiresAt: null, manualLogout: false, startedAt: Date.now(), contacts: new Map() };
    this.sessions.set(sessionId, holder);

    const socket = makeWASocket({
      version,
      auth: state,
      logger: silent,
      printQRInTerminal: false,
      markOnlineOnConnect: false,
      keepAliveIntervalMs: config.keepAliveIntervalMs,
      connectTimeoutMs: config.connectTimeoutMs,
      defaultQueryTimeoutMs: config.queryTimeoutMs,
      retryRequestDelayMs: 250,
      syncFullHistory: false,
      generateHighQualityLinkPreview: false,
      browser: ['KuySender', 'Ubuntu', '5.0.0'],
    });
    holder.socket = socket;

    socket.ev.on('creds.update', saveCreds);
    socket.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;
      if (qr) {
        holder.state = 'qr';
        holder.qr = await QRCode.toDataURL(qr, { margin: 1, width: 420 });
        holder.qrExpiresAt = new Date(Date.now() + config.qrTtlSeconds * 1000).toISOString();
        await report('device.qr', sessionId, { qr_code: holder.qr, qr_expires_at: holder.qrExpiresAt });
      }

      if (connection === 'open' && this.sessions.get(sessionId) === holder) {
        this.reconnectAttempts.delete(sessionId);
        this.clearReconnectTimer(sessionId);
        holder.state = 'connected';
        holder.qr = null;
        holder.qrExpiresAt = null;
        holder.number = socket.user?.id ? jidNormalizedUser(socket.user.id).split('@')[0] : null;
        holder.pushName = socket.user?.name || null;
        await report('device.connected', sessionId, { whatsapp_number: holder.number, push_name: holder.pushName });
      }

      if (connection === 'close') {
        // Ignore close events from an older socket that has already been replaced.
        if (this.sessions.get(sessionId) !== holder) return;

        const statusCode = lastDisconnect?.error?.output?.statusCode || lastDisconnect?.error?.statusCode || 0;
        const loggedOut = statusCode === DisconnectReason.loggedOut || holder.manualLogout;
        this.sessions.delete(sessionId);
        holder.state = 'disconnected';
        await report('device.disconnected', sessionId, {
          logged_out: loggedOut,
          status_code: statusCode || null,
          error: lastDisconnect?.error?.message || null,
        });
        if (!loggedOut) this.scheduleReconnect(sessionId);
      }
    });

    socket.ev.on('contacts.upsert', (contacts) => cacheContacts(holder, contacts));
    socket.ev.on('contacts.update', (contacts) => cacheContacts(holder, contacts));
    socket.ev.on('messaging-history.set', ({ contacts = [] } = {}) => cacheContacts(holder, contacts));

    socket.ev.on('messages.upsert', async ({ type, messages }) => {
      if (type !== 'notify') return;
      for (const msg of messages || []) {
        if (!msg?.message || msg.key?.fromMe) continue;
        const chatJid = msg.key?.remoteJid;
        const senderPn = msg.key?.senderPn || msg.key?.participantPn || null;
        const senderLid = msg.key?.senderLid || msg.key?.participantLid || (String(chatJid || '').endsWith('@lid') ? chatJid : null);
        if (senderPn && senderLid && holder.socket?.signalRepository?.lidMapping) {
          try {
            await holder.socket.signalRepository.lidMapping.storeLIDPNMappings([{ pn: senderPn, lid: senderLid }]);
          } catch (error) {
            logger.warn({ sessionId, senderPn, senderLid, error: error.message }, 'Failed to persist inbound PN-LID mapping');
          }
        }
        if (!chatJid || chatJid === 'status@broadcast') continue;
        const isGroup = chatJid.endsWith('@g.us');
        const senderJid = msg.key?.participant || chatJid;
        const rawPhoneJid = msg.key?.participantPn || msg.key?.senderPn || null;
        const phoneJid = rawPhoneJid ? jidNormalizedUser(rawPhoneJid) : null;
        const lidJid = msg.key?.participantLid || msg.key?.senderLid || (String(senderJid).endsWith('@lid') ? senderJid : null);
        const replyJid = !isGroup && phoneJid?.endsWith('@s.whatsapp.net') ? phoneJid : chatJid;
        const rawType = getContentType(msg.message);
        if (INTERNAL_MESSAGE_TYPES.has(rawType)) continue;
        const parsed = extractText(msg.message);
        await report('message.incoming', sessionId, {
          wa_message_id: msg.key?.id || null,
          chat_jid: chatJid,
          reply_jid: replyJid,
          sender_jid: senderJid,
          sender_phone_jid: phoneJid,
          sender_lid: lidJid,
          sender_name: msg.pushName || null,
          message_type: parsed.type,
          body: parsed.body,
          is_group: isGroup,
          message_at: msg.messageTimestamp ? new Date(Number(msg.messageTimestamp) * 1000).toISOString() : new Date().toISOString(),
          raw_type: rawType,
        });
      }
    });

    socket.ev.on('messages.update', async (updates) => {
      const statusMap = new Map([
        [0, 'error'],
        [1, 'pending'],
        [2, 'server_ack'],
        [3, 'delivered'],
        [4, 'read'],
        [5, 'played'],
      ]);

      for (const item of updates || []) {
        const messageId = item?.key?.id;
        if (!messageId || item?.update?.status === undefined || item?.update?.status === null) continue;
        const rawStatus = Number(item.update.status);
        await report('message.status', sessionId, {
          wa_message_id: messageId,
          status: statusMap.get(rawStatus) || String(item.update.status),
        });
      }
    });

    return this.status(sessionId);
  }

  scheduleReconnect(sessionId) {
    sessionId = validateSessionId(sessionId);
    if (this.reconnectTimers.has(sessionId)) return;
    const attempts = (this.reconnectAttempts.get(sessionId) || 0) + 1;
    this.reconnectAttempts.set(sessionId, attempts);
    const delay = Math.min(config.reconnectMaxMs, config.reconnectMinMs * 2 ** Math.min(attempts - 1, 6));
    const timer = setTimeout(() => {
      this.reconnectTimers.delete(sessionId);
      this.connect(sessionId).catch((error) => {
        logger.warn({ sessionId, error: error.message, attempts }, 'Reconnect failed');
        this.scheduleReconnect(sessionId);
      });
    }, delay);
    timer.unref?.();
    this.reconnectTimers.set(sessionId, timer);
  }

  async logout(sessionId) {
    sessionId = validateSessionId(sessionId);
    this.clearReconnectTimer(sessionId);
    this.reconnectAttempts.delete(sessionId);
    const holder = this.get(sessionId);
    if (holder?.socket) {
      holder.manualLogout = true;
      try { await holder.socket.logout(); } catch {}
    }
    sessionId = validateSessionId(sessionId);
    this.sessions.delete(sessionId);
    if (config.authStorage === 'database') await deleteDatabaseAuth(sessionId);
    else await fsp.rm(this.sessionPath(sessionId), { recursive: true, force: true });
    await report('device.disconnected', sessionId, { logged_out: true });
  }

  async remove(sessionId) {
    sessionId = validateSessionId(sessionId);
    this.clearReconnectTimer(sessionId);
    this.reconnectAttempts.delete(sessionId);
    const holder = this.get(sessionId);
    if (holder?.socket) {
      holder.manualLogout = true;
      try { holder.socket.end?.(new Error('Session removed')); } catch {}
    }
    sessionId = validateSessionId(sessionId);
    this.sessions.delete(sessionId);
    if (config.authStorage === 'database') await deleteDatabaseAuth(sessionId);
    else await fsp.rm(this.sessionPath(sessionId), { recursive: true, force: true });
  }

  async send(sessionId, receiver, messageType, data = {}) {
    const holder = this.get(sessionId);
    if (!holder?.socket || holder.state !== 'connected') throw new Error('WhatsApp device is not connected.');
    const jid = cleanPhone(receiver);
    let content;

    if (messageType === 'text') {
      if (!String(data.message || '').trim()) throw new Error('Message is required.');
      content = { text: String(data.message) };
    } else if (messageType === 'media') {
      const { buffer, contentType } = data.local_path ? await readSafeLocalFile(data.local_path) : await fetchRemoteBuffer(data.url);
      const mediaType = String(data.media_type || '').toLowerCase();
      const caption = String(data.caption || '');
      if (mediaType === 'image') content = { image: buffer, caption };
      else if (mediaType === 'video') content = { video: buffer, caption };
      else if (mediaType === 'audio') content = { audio: buffer, mimetype: contentType, ptt: Boolean(data.ptt) };
      else content = { document: buffer, mimetype: contentType, fileName: String(data.filename || 'attachment'), caption };
    } else if (messageType === 'button') {
      const labels = Array.isArray(data.buttons) ? data.buttons.map((b, i) => `${i + 1}. ${b.display || b.id || 'Option'}`).join('\n') : '';
      content = { text: [data.message || '', labels, data.footer || ''].filter(Boolean).join('\n\n') };
    } else if (messageType === 'list') {
      const rows = [];
      for (const section of data.sections || []) for (const row of section.rows || []) rows.push(`• ${row.title || row.rowId || 'Option'}`);
      content = { text: [data.title || '', data.message || '', rows.join('\n'), data.footer || ''].filter(Boolean).join('\n\n') };
    } else {
      throw new Error('Unsupported message type.');
    }

    const sent = await holder.socket.sendMessage(jid, content);
    return { message_id: sent?.key?.id || null, chat_jid: jid };
  }

  contacts(sessionId) {
    const holder = this.get(sessionId);
    if (!holder?.socket || holder.state !== 'connected') throw new Error('WhatsApp device is not connected.');
    return [...holder.contacts.values()].sort((a, b) => String(a.name || a.number || '').localeCompare(String(b.name || b.number || '')));
  }

  async groups(sessionId) {
    const holder = this.get(sessionId);
    if (!holder?.socket || holder.state !== 'connected') throw new Error('WhatsApp device is not connected.');
    const groups = await holder.socket.groupFetchAllParticipating();
    return Object.values(groups).map((g) => ({ id: g.id, subject: g.subject, size: g.participants?.length || 0 }));
  }

  async groupMembers(sessionId, groupJid) {
    const holder = this.get(sessionId);
    if (!holder?.socket || holder.state !== 'connected') throw new Error('WhatsApp device is not connected.');
    if (!String(groupJid || '').endsWith('@g.us')) throw new Error('Invalid group id.');
    const meta = await holder.socket.groupMetadata(groupJid);
    return (meta.participants || []).map((p) => ({ id: p.id, admin: p.admin || null }));
  }
}

module.exports = { SessionManager };
