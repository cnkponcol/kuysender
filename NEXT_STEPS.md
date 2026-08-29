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

## Current blocker: outbound private-message error 463
- Do not repeatedly retry failed private sends while WhatsApp returns 463.
- Verify the connected WhatsApp account can manually reply to the same warm contact from the official WhatsApp app.
- If manual app reply works, treat this as Baileys protocol blocker and track upstream warm-contact 463 fixes before another library change.
- If manual app reply also fails, the WhatsApp account itself is reach-out restricted and must recover before KuySender outbound can be validated.
