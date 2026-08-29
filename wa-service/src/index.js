const express = require('express');
const helmet = require('helmet');
const pino = require('pino');
const config = require('./config');
const { internalAuth } = require('./auth');
const { SessionManager } = require('./session-manager');

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });
const manager = new SessionManager();
const app = express();

app.disable('x-powered-by');
app.use(helmet({ contentSecurityPolicy: false }));
app.use(express.json({ limit: '1mb' }));

app.get('/health', (_req, res) => res.json({ status: 'ok', service: 'kuysender-wa', version: '5.0.0', ...manager.health() }));

app.post('/internal/devices/:id/connect', internalAuth, async (req, res, next) => {
  try { res.json({ status: 'success', data: await manager.connect(req.params.id) }); } catch (e) { next(e); }
});
app.get('/internal/devices/:id', internalAuth, (req, res, next) => {
  try { res.json({ status: 'success', data: manager.status(req.params.id) }); } catch (e) { next(e); }
});
app.post('/internal/devices/:id/logout', internalAuth, async (req, res, next) => {
  try { await manager.logout(req.params.id); res.json({ status: 'success' }); } catch (e) { next(e); }
});
app.delete('/internal/devices/:id', internalAuth, async (req, res, next) => {
  try { await manager.remove(req.params.id); res.json({ status: 'success' }); } catch (e) { next(e); }
});
app.post('/internal/messages/send', internalAuth, async (req, res, next) => {
  try {
    const { session_id, receiver, message_type = 'text', data = {} } = req.body || {};
    if (!session_id || !receiver) return res.status(422).json({ status: 'error', message: 'session_id and receiver are required.' });
    res.json({ status: 'success', data: await manager.send(session_id, receiver, message_type, data) });
  } catch (e) { next(e); }
});
app.get('/internal/devices/:id/contacts', internalAuth, (req, res, next) => {
  try { res.json({ status: 'success', data: manager.contacts(req.params.id) }); } catch (e) { next(e); }
});
app.get('/internal/devices/:id/groups', internalAuth, async (req, res, next) => {
  try { res.json({ status: 'success', data: await manager.groups(req.params.id) }); } catch (e) { next(e); }
});
app.get('/internal/devices/:id/groups/:jid/members', internalAuth, async (req, res, next) => {
  try { res.json({ status: 'success', data: await manager.groupMembers(req.params.id, decodeURIComponent(req.params.jid)) }); } catch (e) { next(e); }
});

app.use((_req, res) => res.status(404).json({ status: 'error', message: 'Not found.' }));
app.use((error, _req, res, _next) => {
  logger.warn({ error: error.message }, 'Request failed');
  const clientError = /invalid|required|unsupported|not connected|not allowed|exceeds/i.test(error.message || '');
  res.status(clientError ? 422 : 500).json({ status: 'error', message: clientError ? error.message : 'WA service error.' });
});

(async () => {
  await manager.startExisting();
  app.listen(config.port, config.host, () => logger.info({ host: config.host, port: config.port }, 'KuySender WA service started'));
})().catch((error) => {
  logger.fatal({ error: error.message }, 'Unable to start WA service');
  process.exit(1);
});
