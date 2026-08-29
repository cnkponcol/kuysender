module.exports = {
  apps: [{
    name: 'kuysender-wa',
    script: 'src/index.js',
    cwd: __dirname,
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '700M',
    env: { NODE_ENV: 'production' }
  }]
};
