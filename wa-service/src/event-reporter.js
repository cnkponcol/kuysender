const config = require('./config');
const pino = require('pino');
const logger = pino({ level: process.env.LOG_LEVEL || 'info' });

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function report(type, sessionId, data = {}) {
  const delays = [0, 350, 1000];

  for (let attempt = 0; attempt < delays.length; attempt += 1) {
    if (delays[attempt]) await sleep(delays[attempt]);

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 12000);
    try {
      const response = await fetch(config.laravelEventUrl, {
        method: 'POST',
        headers: {
          'content-type': 'application/json',
          'accept': 'application/json',
          'authorization': `Bearer ${config.internalToken}`,
        },
        body: JSON.stringify({ type, session_id: sessionId, data }),
        signal: controller.signal,
      });

      if (response.ok) return true;
      logger.warn({ type, sessionId, attempt: attempt + 1, status: response.status }, 'Laravel event delivery failed');
    } catch (error) {
      logger.warn({ type, sessionId, attempt: attempt + 1, error: error.message }, 'Laravel event delivery error');
    } finally {
      clearTimeout(timer);
    }
  }

  return false;
}

module.exports = { report };
