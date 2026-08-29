const path = require('node:path');
require('dotenv').config({ path: process.env.WA_ENV_FILE || path.join(process.cwd(), '.env') });

function int(name, fallback) {
  const value = Number.parseInt(process.env[name] || '', 10);
  return Number.isFinite(value) ? value : fallback;
}

const config = Object.freeze({
  env: process.env.NODE_ENV || 'production',
  host: process.env.WA_HOST || '127.0.0.1',
  port: int('WA_PORT', 5570),
  internalToken: process.env.WA_INTERNAL_TOKEN || '',
  laravelEventUrl: process.env.LARAVEL_EVENT_URL || 'http://127.0.0.1/api/internal/wa/events',
  sessionDir: path.resolve(process.env.WA_SESSION_DIR || './storage/sessions'),
  dashboardStoragePath: path.resolve(process.env.WA_DASHBOARD_STORAGE_PATH || '../dashboard/storage/app'),
  qrTtlSeconds: Math.max(30, int('WA_QR_TTL_SECONDS', 60)),
  reconnectMinMs: Math.max(1000, int('WA_RECONNECT_MIN_MS', 2000)),
  reconnectMaxMs: Math.max(5000, int('WA_RECONNECT_MAX_MS', 30000)),
  maxMediaBytes: Math.max(1024 * 1024, int('WA_MAX_MEDIA_BYTES', 15 * 1024 * 1024)),
  keepAliveIntervalMs: Math.max(15000, int('WA_KEEPALIVE_INTERVAL_MS', 25000)),
  connectTimeoutMs: Math.max(30000, int('WA_CONNECT_TIMEOUT_MS', 60000)),
  queryTimeoutMs: Math.max(30000, int('WA_QUERY_TIMEOUT_MS', 60000)),
  reconcileIntervalMs: Math.max(30000, int('WA_RECONCILE_INTERVAL_MS', 60000)),
  authStorage: process.env.WA_AUTH_STORAGE || 'database',
  authEncryptionKey: process.env.WA_AUTH_ENCRYPTION_KEY || '',
  db: Object.freeze({
    host: process.env.WA_DB_HOST || '127.0.0.1',
    port: int('WA_DB_PORT', 3306),
    database: process.env.WA_DB_DATABASE || 'kuysender',
    user: process.env.WA_DB_USERNAME || 'kuysender',
    password: process.env.WA_DB_PASSWORD || '',
  }),
});

if (config.internalToken.length < 32) {
  throw new Error('WA_INTERNAL_TOKEN must be configured with at least 32 characters.');
}
if (config.authStorage === 'database' && !/^[a-f0-9]{64}$/i.test(config.authEncryptionKey)) {
  throw new Error('WA_AUTH_ENCRYPTION_KEY must be configured as a 64-character hex key.');
}

module.exports = config;
