const dns = require('node:dns').promises;
const net = require('node:net');
const config = require('./config');
const fsp = require('node:fs/promises');
const path = require('node:path');

function isPrivateIPv4(ip) {
  const p = ip.split('.').map(Number);
  if (p.length !== 4 || p.some((n) => !Number.isInteger(n) || n < 0 || n > 255)) return false;
  return p[0] === 10 || p[0] === 127 || p[0] === 0 ||
    (p[0] === 169 && p[1] === 254) ||
    (p[0] === 172 && p[1] >= 16 && p[1] <= 31) ||
    (p[0] === 192 && p[1] === 168) ||
    (p[0] === 100 && p[1] >= 64 && p[1] <= 127) ||
    p[0] >= 224;
}

function isPrivateIPv6(ip) {
  const lower = ip.toLowerCase();
  return lower === '::1' || lower === '::' || lower.startsWith('fc') || lower.startsWith('fd') || lower.startsWith('fe80:');
}

async function assertSafeRemoteUrl(rawUrl) {
  let url;
  try { url = new URL(rawUrl); } catch { throw new Error('Invalid media URL.'); }
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error('Only http/https media URLs are allowed.');
  if (url.username || url.password) throw new Error('Credentials in media URLs are not allowed.');
  if (['localhost', 'localhost.localdomain'].includes(url.hostname.toLowerCase())) throw new Error('Local media URLs are not allowed.');

  const records = await dns.lookup(url.hostname, { all: true, verbatim: true });
  if (!records.length) throw new Error('Media host could not be resolved.');
  for (const record of records) {
    if (net.isIPv4(record.address) && isPrivateIPv4(record.address)) throw new Error('Private network media URLs are not allowed.');
    if (net.isIPv6(record.address) && isPrivateIPv6(record.address)) throw new Error('Private network media URLs are not allowed.');
  }
  return url.toString();
}

async function fetchRemoteBuffer(rawUrl) {
  const safeUrl = await assertSafeRemoteUrl(rawUrl);
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 30000);
  try {
    const response = await fetch(safeUrl, { redirect: 'error', signal: controller.signal });
    if (!response.ok) throw new Error(`Media fetch failed with HTTP ${response.status}.`);
    const contentLength = Number(response.headers.get('content-length') || 0);
    if (contentLength > config.maxMediaBytes) throw new Error('Media exceeds configured size limit.');
    const buffer = Buffer.from(await response.arrayBuffer());
    if (buffer.length > config.maxMediaBytes) throw new Error('Media exceeds configured size limit.');
    return { buffer, contentType: response.headers.get('content-type') || 'application/octet-stream' };
  } finally {
    clearTimeout(timer);
  }
}


async function readSafeLocalFile(rawPath) {
  const resolved = path.resolve(String(rawPath || ''));
  const root = path.resolve(config.dashboardStoragePath) + path.sep;
  if (!resolved.startsWith(root)) throw new Error('Local media path is outside the allowed storage root.');
  const stat = await fsp.stat(resolved);
  if (!stat.isFile()) throw new Error('Local media path is not a file.');
  if (stat.size > config.maxMediaBytes) throw new Error('Media exceeds configured size limit.');
  return { buffer: await fsp.readFile(resolved), contentType: 'application/octet-stream' };
}

function cleanPhone(value) {
  const raw = String(value || '').trim();
  if (raw.endsWith('@g.us') || raw.endsWith('@s.whatsapp.net') || raw.endsWith('@lid')) return raw;
  const digits = raw.replace(/\D+/g, '');
  if (digits.length < 7 || digits.length > 18) throw new Error('Invalid WhatsApp number.');
  return `${digits}@s.whatsapp.net`;
}

function validateSessionId(value) {
  const id = String(value || '');
  if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(id)) {
    throw new Error('Invalid session id.');
  }
  return id;
}

module.exports = { assertSafeRemoteUrl, fetchRemoteBuffer, readSafeLocalFile, cleanPhone, validateSessionId };
