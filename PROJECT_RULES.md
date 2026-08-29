# KuySender Project Rules

Dokumen ini adalah aturan utama pengerjaan KuySender. Jika ada catatan lama yang bertentangan, dokumen ini yang dipakai.

## 1. Prinsip produk
- Pertahankan fitur existing. Jangan redesign, mengganti alur, atau menghapus fitur yang sudah bekerja tanpa permintaan eksplisit.
- Prioritas: stabilitas WhatsApp, Inbox, Auto Reply, Contacts, Broadcast, API/Webhook, dan WhatsApp AI.
- Perbaikan harus kompatibel dengan data/schema aktif dan memiliki rollback/checkpoint.
- Jangan menggunakan `migrate:fresh` pada environment aktif.

## 2. WhatsApp / Baileys
- Baseline stabil: `@whiskeysockets/baileys` **6.7.24** (exact pin).
- Jangan pindah ke release candidate/major baru tanpa audit breaking changes dan regression test.
- Auth WhatsApp disimpan di database terenkripsi; session file lama tidak dipakai untuk baseline baru.
- Auto Reply dianggap berhasil hanya jika benar-benar memanggil WA send dan menghasilkan outbound message record; log saja tidak cukup.
- Inbox harus menerima inbound, menyimpan status, dan dapat mengirim reply nyata.

## 3. Contacts dan broadcast
- Incoming chat otomatis boleh membuat/update contact dengan consent `unknown`.
- Sync WhatsApp personal contacts dan sync group dipertahankan sebagai fitur berbeda.
- Simpan JID asli (`@s.whatsapp.net` atau `@lid`) ketika tersedia agar pengiriman tidak salah memetakan LID sebagai nomor telepon.
- Import/sync contact tidak otomatis berarti opt-in broadcast.
- Broadcast hanya untuk contact yang eksplisit `opted_in`, tidak blocklisted, dan tidak opted-out.

## 4. API, webhook, Device ID
- Device ID, webhook URL, API credential, dan webhook secret harus mudah dicopy dari dashboard.
- Secret tampil hidden secara default dan hanya dibuka melalui kontrol UI.
- API secret yang perlu direveal disimpan terenkripsi oleh Laravel; hash tetap menjadi sumber validasi token.
- WA internal token tidak boleh pernah diekspos ke browser atau API publik.

## 5. WhatsApp AI
- Prompt dikelola dari dashboard dan dapat diedit/dikosongkan.
- Knowledge dapat tambah, edit, hapus, aktif/nonaktif, cari, kategorikan, import, dan export.
- API key provider AI tetap terenkripsi dan tidak dikirim kembali ke browser setelah disimpan.
- AI tidak boleh melewati human takeover atau policy existing.

## 6. Cleanup dan data retention
- Cleanup otomatis boleh menghapus cache/temp, log teknis lama, webhook delivery lama, failed jobs, dan orphan runtime data.
- Inbox/message history tidak dihapus otomatis secara default (`KUYSENDER_MESSAGE_RETENTION_DAYS=0`).
- Knowledge, contacts, campaign/broadcast penting, user, credential aktif, dan konfigurasi tidak boleh dianggap cache.

## 7. Ownership dan permission
- Source, Node service, systemd user service, dan file kerja KuySender dikelola oleh user `openclaw`.
- Jangan gunakan `chmod 777`.
- Deployment saat ini masih memakai Nginx -> PHP-FPM pool `www-data`; migrasi penuh runtime PHP ke `openclaw` memerlukan akses root dan dicatat di `NEXT_STEPS.md`.
- Jangan mengubah konfigurasi root/Nginx/PHP-FPM tanpa checkpoint dan validasi akses web.

## 8. Repository hygiene
- Jangan simpan `.env`, credential, auth WhatsApp, database dump, runtime cache, `vendor`, atau `node_modules` di repository.
- Jangan meninggalkan `.backup`, `.bak`, `.patch`, `.diff`, file eksperimen, atau MD sementara setelah perubahan selesai.
- Lockfile dependency harus dipertahankan untuk reproducible build.
- Backup project disimpan di luar source tree: `/home/openclaw/kuysender-backups/`.

## 9. Proses pengerjaan wajib
1. Audit kondisi aktual.
2. Buat checkpoint sebelum perubahan destruktif.
3. Ubah bagian sekecil mungkin.
4. Gunakan migration baru untuk perubahan schema.
5. Jalankan syntax/lint/test/build yang tersedia.
6. Validasi service/runtime/health.
7. Untuk fitur WhatsApp, lakukan end-to-end test setelah device tersambung.
8. Update `WORK_PROGRESS.md`, `NEXT_STEPS.md`, dan `CHANGELOG.md`.
9. Commit checkpoint Git hanya setelah secret/runtime artifact dipastikan tidak ikut.
