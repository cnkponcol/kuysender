# KuySender Security

## Secrets
- Jangan commit `.env`, WhatsApp auth, database dump, API token, webhook secret, AI key, atau `WA_INTERNAL_TOKEN`.
- `WA_INTERNAL_TOKEN` hanya untuk Laravel <-> WA service dan tidak boleh masuk browser.
- API token divalidasi melalui SHA-256 hash; salinan revealable disimpan terenkripsi dengan Laravel application encryption.
- Webhook secret dan AI provider key disimpan terenkripsi.

## Network
```text
Client / trusted access
        |
Nginx :8081
        |
Laravel / PHP-FPM
        |
127.0.0.1:5570  KuySender WA service
```
Port 5570 harus tetap localhost/internal.

## Broadcast
- Imported/synced/incoming contacts tidak otomatis opt-in.
- Broadcast hanya untuk explicit `opted_in`, tidak opted-out, dan tidak blocklisted.
- STOP/BERHENTI/UNSUBSCRIBE/UNSUB tetap dihormati oleh flow existing.

## Files / permission
- Jangan `chmod 777`.
- Source/service dikelola `openclaw`.
- PHP-FPM `www-data` adalah deployment exception sampai dedicated runtime dapat diubah dengan root access.
- Backup disimpan di luar source tree dan dibatasi permission.

## Data retention
Auto cleanup default tidak menghapus Inbox history. Ia memangkas data teknis/transient sesuai `config/kuysender.php` dan orphan runtime rows.

## Operasional
- Backup sebelum migration/destructive cleanup.
- Jangan `migrate:fresh` di environment aktif.
- Jangan upgrade major/RC Baileys tanpa regression test.
- Fresh QR pairing wajib diikuti end-to-end test sebelum deployment dianggap stabil.
