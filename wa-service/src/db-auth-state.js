const crypto = require('node:crypto');
const mysql = require('mysql2/promise');
const { BufferJSON, initAuthCreds, proto } = require('@whiskeysockets/baileys');
const config = require('./config');

let pool;
const authLocks = new Map();

function withAuthLock(sessionId, fn) {
  const previous = authLocks.get(sessionId) || Promise.resolve();
  const current = previous.catch(() => {}).then(fn);
  authLocks.set(sessionId, current);
  return current.finally(() => {
    if (authLocks.get(sessionId) === current) authLocks.delete(sessionId);
  });
}

function db() {
  if (!pool) {
    pool = mysql.createPool({
      host: config.db.host,
      port: config.db.port,
      user: config.db.user,
      password: config.db.password,
      database: config.db.database,
      waitForConnections: true,
      connectionLimit: 4,
      enableKeepAlive: true,
    });
  }
  return pool;
}
function encryptionKey() {
  if (!/^[a-f0-9]{64}$/i.test(config.authEncryptionKey || '')) {
    throw new Error('WA_AUTH_ENCRYPTION_KEY must be a 64-character hex key.');
  }
  return Buffer.from(config.authEncryptionKey, 'hex');
}

function encode(value) {
  const iv = crypto.randomBytes(12);
  const cipher = crypto.createCipheriv('aes-256-gcm', encryptionKey(), iv);
  const plain = Buffer.from(JSON.stringify(value, BufferJSON.replacer), 'utf8');
  const encrypted = Buffer.concat([cipher.update(plain), cipher.final()]);
  const tag = cipher.getAuthTag();
  return ['v1', iv.toString('base64'), tag.toString('base64'), encrypted.toString('base64')].join('.');
}

function decode(payload) {
  const [version, iv64, tag64, data64] = String(payload || '').split('.');
  if (version !== 'v1' || !iv64 || !tag64 || !data64) throw new Error('Unsupported encrypted auth payload.');
  const decipher = crypto.createDecipheriv('aes-256-gcm', encryptionKey(), Buffer.from(iv64, 'base64'));
  decipher.setAuthTag(Buffer.from(tag64, 'base64'));
  const plain = Buffer.concat([decipher.update(Buffer.from(data64, 'base64')), decipher.final()]);
  return JSON.parse(plain.toString('utf8'), BufferJSON.reviver);
}
async function saveSessionCreds(sessionId, creds) {
  return withAuthLock(sessionId, async () => {
    const payload = encode(creds);
    await db().execute(
      `INSERT INTO wa_auth_sessions (session_id,payload,cipher,format_version,created_at,updated_at)
       VALUES (?,?,'aes-256-gcm',1,NOW(),NOW())
       ON DUPLICATE KEY UPDATE payload=VALUES(payload),cipher=VALUES(cipher),format_version=VALUES(format_version),updated_at=NOW()`,
      [sessionId, payload],
    );
  });
}

async function getKeys(sessionId, type, ids) {
  if (!Array.isArray(ids) || ids.length === 0) return {};
  return withAuthLock(sessionId, async () => {
    const placeholders = ids.map(() => '?').join(',');
    const [rows] = await db().execute(
      `SELECT key_id,payload FROM wa_auth_keys
       WHERE session_id=? AND key_type=? AND key_id IN (${placeholders})`,
      [sessionId, type, ...ids],
    );
    const result = Object.fromEntries(ids.map((id) => [id, null]));
    for (const row of rows) {
      let value = decode(row.payload);
      if (type === 'app-state-sync-key' && value) {
        value = proto.Message.AppStateSyncKeyData.fromObject(value);
      }
      result[row.key_id] = value;
    }
    return result;
  });
}
async function setKeys(sessionId, data) {
  return withAuthLock(sessionId, async () => {
    const connection = await db().getConnection();
    try {
      await connection.beginTransaction();
      for (const [type, entries] of Object.entries(data || {})) {
        for (const [keyId, value] of Object.entries(entries || {})) {
          if (value == null) {
            await connection.execute(
              'DELETE FROM wa_auth_keys WHERE session_id=? AND key_type=? AND key_id=?',
              [sessionId, type, keyId],
            );
          } else {
            await connection.execute(
              `INSERT INTO wa_auth_keys (session_id,key_type,key_id,payload,created_at,updated_at)
               VALUES (?,?,?,?,NOW(),NOW())
               ON DUPLICATE KEY UPDATE payload=VALUES(payload),updated_at=NOW()`,
              [sessionId, type, keyId, encode(value)],
            );
          }
        }
      }
      await connection.commit();
    } catch (error) {
      await connection.rollback();
      throw error;
    } finally {
      connection.release();
    }
  });
}
async function useDatabaseAuthState(sessionId) {
  const [rows] = await withAuthLock(sessionId, () =>
    db().execute('SELECT payload FROM wa_auth_sessions WHERE session_id=? LIMIT 1', [sessionId])
  );
  const creds = rows[0] ? decode(rows[0].payload) : initAuthCreds();
  return {
    state: {
      creds,
      keys: {
        get: (type, ids) => getKeys(sessionId, type, ids),
        set: (data) => setKeys(sessionId, data),
      },
    },
    saveCreds: () => saveSessionCreds(sessionId, creds),
  };
}

async function deleteDatabaseAuth(sessionId) {
  return withAuthLock(sessionId, async () => {
    const connection = await db().getConnection();
    try {
      await connection.beginTransaction();
      await connection.execute('DELETE FROM wa_auth_keys WHERE session_id=?', [sessionId]);
      await connection.execute('DELETE FROM wa_auth_sessions WHERE session_id=?', [sessionId]);
      await connection.commit();
    } catch (error) {
      await connection.rollback();
      throw error;
    } finally {
      connection.release();
    }
  });
}
async function hasDatabaseAuth(sessionId) {
  const [rows] = await db().execute(
    'SELECT 1 FROM wa_auth_sessions WHERE session_id=? LIMIT 1',
    [sessionId],
  );
  return rows.length > 0;
}

async function listDatabaseAuthSessionIds() {
  const [rows] = await db().execute(
    'SELECT session_id FROM wa_auth_sessions ORDER BY updated_at DESC'
  );
  return rows.map((row) => row.session_id);
}

module.exports = {
  useDatabaseAuthState,
  deleteDatabaseAuth,
  hasDatabaseAuth,
  listDatabaseAuthSessionIds,
};