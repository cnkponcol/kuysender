const crypto = require('node:crypto');
const config = require('./config');

function safeEqual(a, b) {
  const left = Buffer.from(String(a || ''));
  const right = Buffer.from(String(b || ''));
  return left.length === right.length && crypto.timingSafeEqual(left, right);
}

function internalAuth(req, res, next) {
  const bearer = String(req.headers.authorization || '').replace(/^Bearer\s+/i, '');
  const token = bearer || String(req.headers['x-wa-internal-token'] || '');
  if (!safeEqual(token, config.internalToken)) {
    return res.status(401).json({ status: 'error', message: 'Unauthorized.' });
  }
  next();
}

module.exports = { internalAuth };
