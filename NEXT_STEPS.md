# KuySender Next Steps

## Wajib sebelum dinyatakan live-stable
1. Connect satu WhatsApp device baru melalui QR dashboard.
2. Uji end-to-end nyata: inbound Inbox, reply Inbox, Auto Reply real send, delivery/read status, Auto Contacts, Sync WhatsApp Contacts, Sync Groups, dan broadcast hanya ke explicit opt-in.
3. Uji WhatsApp AI: prompt, knowledge enable-disable, import/export, suggest/auto mode, dan human takeover.
4. Buat API Client setelah device tersedia lalu uji show/hide/copy, Device ID, send API, webhook signature/delivery, dan rotate token.

## Root-level deployment cleanup
Saat akses root tersedia:
- pindahkan runtime PHP KuySender dari generic PHP-FPM `www-data` ke dedicated pool/service dengan user `openclaw`;
- update Nginx secara atomik dan verifikasi dashboard sebelum mematikan konfigurasi lama;
- rapikan dua cache directory yang masih bisa dibuat `www-data`;
- pindahkan/hapus folder legacy `/home/openclaw/apps/kuysender/backups` yang root-owned setelah isinya diverifikasi.

Jangan sekadar melakukan `chown` cache berulang karena PHP-FPM `www-data` akan membuat ownership tersebut kembali.

## Setelah live test lulus
- Backup database + source checkpoint baru.
- Update `WORK_PROGRESS.md` dengan hasil test nyata.
- Tandai baseline stabil di `CHANGELOG.md`.
- Push repository ke remote backup/version control agar kehilangan source terbaru tidak terulang.

## Current outbound state
- The old 463 blocker is resolved on the fresh Baileys rc14 session.
- Current API/outbound path is healthy; latest verified outbound status is `server_ack`.
- If a future outbound failure appears, verify the specific contact/session and error before changing Baileys or reconnect logic.

## Session recovery after conflict fix
- Pair device `kuskuskuy` satu kali lagi melalui QR dashboard karena auth lama sudah terhapus sebelum patch 2026-08-30.
- Setelah pairing, verifikasi restart service dapat restore session dari database tanpa QR ulang.
- Pantau bila muncul `connectionReplaced`/440; auth sekarang harus tetap ada dan reconnect harus bounded, bukan dihapus.
- Jika conflict berulang sampai auto-reconnect berhenti, cari sumber socket/session ganda sebelum melakukan reconnect manual.
