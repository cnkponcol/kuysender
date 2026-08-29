# KuySender

KuySender adalah WhatsApp gateway/sender dengan dashboard Laravel dan Node.js/Baileys WA service. Baseline ini mempertahankan fitur existing sambil memperbaiki stabilitas, credential UX, Inbox/Auto Reply, Contacts, Broadcast, API/Webhook, dan WhatsApp AI.

## Struktur
- `dashboard/` - Laravel 12: dashboard, Inbox, Contacts, Broadcast, Auto Reply, AI, REST API, webhook, log.
- `wa-service/` - Node.js + Baileys, internal `127.0.0.1:5570`.
- `deploy/` - referensi deployment.
- Dokumen resmi: `PROJECT_RULES.md`, `WORK_PROGRESS.md`, `NEXT_STEPS.md`, `SECURITY.md`, `CHANGELOG.md`.

Path aktif: `/home/openclaw/apps/kuysender`  
Backup: `/home/openclaw/kuysender-backups`

## Runtime
Dashboard saat ini: `Nginx :8081 -> PHP-FPM -> Laravel`  
WA service: `127.0.0.1:5570`

```bash
systemctl --user status kuysender-wa.service
systemctl --user status kuysender-queue.service
systemctl --user status kuysender-scheduler.timer
systemctl --user status kuysender-wa-health.timer
```

Restart:

```bash
systemctl --user restart kuysender-wa.service kuysender-queue.service
systemctl --user restart kuysender-scheduler.timer kuysender-wa-health.timer
```

Health:

```bash
curl http://127.0.0.1:5570/health
```

## Dependency
WA service memakai exact pin `@whiskeysockets/baileys` **6.7.24**.

```bash
cd /home/openclaw/apps/kuysender/wa-service
rm -rf node_modules
npm ci --omit=dev
npm run check
npm audit --omit=dev --audit-level=high
```

Laravel:

```bash
cd /home/openclaw/apps/kuysender/dashboard
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
```

## Device / WhatsApp
1. Login dashboard.
2. Buat/select device.
3. Device ID tersedia di halaman detail dan bisa dicopy.
4. Start / Generate QR lalu scan WhatsApp.
5. Setelah connected, lakukan live test Inbox dan Auto Reply.

Auth Baileys baseline disimpan pada database terenkripsi.

## Contacts
Phonebook mendukung manual contact, XLSX import/export existing, Sync WhatsApp Contacts, Sync WhatsApp Groups, dan Auto Contacts dari incoming chat. Hasil import/sync tetap `consent=unknown`; broadcast hanya untuk explicit opt-in. `wa_jid` disimpan bila tersedia agar phone JID maupun LID dapat dipakai benar.

## Inbox / Auto Reply
Incoming message masuk melalui queue ke Inbox. Auto Reply wajib memanggil WA send dan menyimpan outbound message, bukan hanya log.

## WhatsApp AI
Dashboard AI mendukung provider/model/API key, edit/clear prompt, dan knowledge add/edit/delete/enable-disable/category/search/import/export JSON. Provider API key tersimpan terenkripsi dan tidak dikirim kembali ke browser.

## API / Webhook
API Client memiliki scope, device assignment, rate limit, webhook URL, dan secret. Dashboard menyediakan show/hide/copy API key, webhook secret, copy webhook URL, serta Device ID. Token API divalidasi dengan hash; salinan revealable disimpan terenkripsi sesuai kebutuhan dashboard.

## Auto cleanup

```bash
cd /home/openclaw/apps/kuysender/dashboard
php artisan kuysender:cleanup --dry-run
```

Cleanup otomatis berjalan 03:20. Inbox message history tidak dihapus otomatis (`KUYSENDER_MESSAGE_RETENTION_DAYS=0`).

## Permission
Source dan user-service dikelola `openclaw`. HTTP dashboard masih memakai generic PHP-FPM `www-data`; migrasi full runtime ke `openclaw` membutuhkan root dan tercatat di `NEXT_STEPS.md`. Jangan gunakan `chmod 777`.

Sebelum menyatakan stable, ikuti live verification di `NEXT_STEPS.md`.
