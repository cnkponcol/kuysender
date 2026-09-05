# KuySender Work Progress

## 2026-08-29 - Stabilization baseline

### Audit dan checkpoint
- Project: `/home/openclaw/apps/kuysender` pada `mcp.kuskuskuy.com`.
- Checkpoint pre-cleanup: `/home/openclaw/kuysender-backups/20260829-004031-pre-cleanup/` (source, DB dump, systemd user units, manifest, SHA-256).
- Checkpoint fitur dibuat sebelum implementasi lanjutan.

### Reset account/session
- Semua WhatsApp device/session lama, auth DB, contacts/messages/autoresponder/AI/campaign/bulk/jobs terkait device lama dibersihkan sesuai reset.
- WA filesystem session bersih dan service kembali sehat dengan 0 session.

### Dependency / Baileys
- `node_modules` dibangun ulang dengan `npm ci --omit=dev`.
- Baileys production version **7.0.0-rc14** after live LID/tctoken compatibility testing.
- `npm audit --omit=dev --audit-level=high`: 0 vulnerabilities.
- `npm run check`: lulus.

### Credential dashboard
- API token dapat direveal dari dashboard karena salinan token disimpan terenkripsi; SHA-256 hash tetap dipakai untuk validasi.
- API key: hidden/show/copy/rotate.
- Webhook secret: hidden/show/copy. Webhook URL: copy.
- Device ID: tampil dan copy.

### WhatsApp AI
- Prompt: edit/save/clear.
- Knowledge: add/edit/delete/enable-disable/category/search.
- Knowledge: import/export JSON (append atau replace).
- AI provider key tetap terenkripsi dan tidak direveal kembali.

### WhatsApp contacts
- WA service menangkap `contacts.upsert`, `contacts.update`, dan contact dari `messaging-history.set`.
- Phonebook ditambah `Sync WhatsApp Contacts`; Sync WhatsApp Groups tetap ada.
- Contact menyimpan `wa_jid` asli untuk phone JID/LID.
- Incoming chat membuat/update contact serta JID.
- Broadcast memilih `wa_jid`, fallback ke nomor lama.
- Semua hasil sync tetap consent `unknown`.

### Auto cleanup
- Command `kuysender:cleanup` dan schedule harian 03:20 ditambahkan.
- Default prune: gateway/API logs, webhook delivery lama, failed jobs, orphan runtime rows, temp files.
- Inbox/message retention default 0 (tidak dihapus otomatis).
- Dry-run berhasil.

### Repository cleanup
- Working `.backup*` lama dan MD tumpang tindih dihapus setelah checkpoint eksternal tersedia.
- Dokumentasi resmi dikonsolidasikan menjadi README, SECURITY, PROJECT_RULES, WORK_PROGRESS, NEXT_STEPS, CHANGELOG.
- Folder `backups/` legacy di root project masih root-owned dan belum dapat dipindahkan oleh user `openclaw`.

### Permission finding
- Mayoritas project milik `openclaw:openclaw`.
- HTTP dashboard masih Nginx -> generic PHP-FPM pool `www-data`, sehingga sebagian runtime cache bisa dibuat oleh `www-data`.
- Perubahan pool/Nginx membutuhkan akses root dan tidak dipaksakan tanpa privilege.

### Validation hasil stabilisasi
- PHP syntax sweep untuk `app/`, `routes/`, `config/`, dan migration: PASS.
- Semua migration termasuk migration 2026-08-29: Ran.
- Route baru AI/contact/API: registered.
- Blade compile: PASS.
- `composer validate --no-check-publish`: PASS.
- Deployment tidak membawa dev test runner, sehingga `php artisan test` tidak tersedia.
- Smoke test transaksional: encrypted API reveal PASS, encrypted webhook secret PASS, contact JID PASS, AI knowledge model PASS; seluruh test row di-rollback.
- Dashboard `/login`: HTTP 200; `/api-clients` tanpa auth: HTTP 302.
- WA service health: OK, 0 session sesuai clean baseline.
- Queue, scheduler, WA service, WA health timer: active.
- `npm run check`: PASS; `npm audit --omit=dev --audit-level=high`: 0 vulnerabilities.
- `kuysender:cleanup` real run: PASS.

### Version-control baseline
- Local Git repository initialized after cleanup.
- `.env`, auth/session, dependencies, runtime uploads/cache, DB/source backup archives, and legacy `backups/` are excluded from Git.
- Staged secret-prefix scan passed before first commit.
- Baseline tag: `kuysender-stabilization-20260829`.

## 2026-08-29 - Live WhatsApp test fixes
- Device `Test-kuysender` paired successfully and restored automatically from database auth after service restart.
- Fixed Inbox Blade corruption that caused HTTP 500 (`$errors` expression was malformed).
- Hardened dashboard/login/inbox error-bag rendering and recompiled all Blade templates.
- Confirmed existing AutoResponder keyword `tes` matched and created an outbound `testtttt` message.
- Identified new WhatsApp private-chat addressing via `@lid`; prior reply was accepted by Baileys but remained without server ACK.
- WA service now captures Baileys `senderPn` / `participantPn` and LID fields from incoming MessageKey.
- Incoming job now keeps LID as logical Inbox chat while preferring the phone JID as the actual delivery target when available.
- Contact number is upgraded to the phone number when Baileys supplies `senderPn`; original `wa_jid` remains available for chat identity.
- Inbox manual reply, AutoResponder, AI reply, and broadcast now use a LID-aware delivery address.
- Incoming WhatsApp timestamps are normalized to `Asia/Jakarta` before persistence.
- Node syntax check and modified PHP syntax checks: PASS.
- WA service, queue, scheduler, health timer: active; device remains CONNECTED after restart.

### Outbound 463 resolution and clean-pair validation
- Live test confirms Inbox receive works; messages `tes` and manual Inbox messages are persisted normally.
- AutoResponder and manual Inbox reply both reach Baileys send path but WhatsApp server rejects them with ACK error `463` (`NackCallerReachoutTimelocked` / missing trusted-contact token).
- Error 463 was reproduced on the old session even after the library upgrade; the old auth/session state had been created before the rc14 migration.
- Upgraded WA service to 7.0.0-rc14 because current WhatsApp LID/tctoken handling is absent from 6.7.24.
- Persist inbound PN<->LID mapping via Baileys `signalRepository.lidMapping` and allow metadata history sync required for LID mapping; `syncFullHistory` remains false.
- Old WhatsApp auth was logged out and a brand-new device/session was paired directly on Baileys 7.0.0-rc14.
- Fresh-session validation PASS: incoming messages, AutoResponder (`tes1` -> `balas-tes1`), and manual Inbox reply all delivered to the real WhatsApp recipient.
- Error 463 did not recur on the clean rc14 session during controlled live tests.
- Internal Baileys `protocolMessage` and `senderKeyDistributionMessage` events are now ignored before they reach Inbox. Existing two protocol-only Inbox rows were removed.
- WA service restarted after the filter change and restored the fresh session automatically: health OK, 1/1 connected.
## 2026-08-29 - Android-friendly mobile UI
- UI backup dibuat sebelum perubahan: `/home/openclaw/kuysender-backups/20260829-040944-pre-mobile-ui`.
- Desktop layout sengaja tidak diubah; style baru aktif hanya pada viewport <= 991.98px.
- Mobile mendapat bottom navigation: Home, Inbox, Kirim, Kontak, dan Lainnya.
- Sidebar lama menjadi drawer Lainnya yang dibatasi viewport dan memiliki scroll vertikal sampai Logout.
- Ditambahkan safe-area Android, pencegahan horizontal overflow, touch target lebih besar, form/modal/card mobile-friendly.
- Inbox mobile dibuat full-width seperti chat app dengan composer dan bubble yang mengikuti viewport.
- Blade compile PASS, CSS brace validation PASS, git diff check PASS, mobile stylesheet HTTP 200.

## 2026-08-29 - Safe reconnect hardening
- Audit menemukan risiko reconnect loop: session terminal yang sudah hilang dari memory masih dapat dihidupkan kembali oleh DB reconciler karena auth database tetap ada.
- Reconciler sekarang menghormati reconnect timer dan cooldown; tidak lagi mem-bypass backoff.
- Reconnect transient diubah menjadi 15s -> 30s -> 60s -> 120s, maksimum 5 percobaan per siklus, lalu cooldown 15 menit.
- Disconnect terminal 401/403/411/440/500 menghentikan auto-reconnect dan membuang auth lama sehingga session revoked/suspended/bad tidak direplay.
- Logout/remove manual sekarang memasang reconnect block sebelum socket/auth dibersihkan.
- `connection === open` mereset attempt, timer, dan reconnect block.
- `npm run check`: PASS. Safe reconnect cooldown logic test: PASS.
- Service restart: PASS; health OK dengan 0 active/connected session sesuai kondisi logout saat ini.
- Backup sebelum perubahan: `/home/openclaw/kuysender-backups/20260829-233833-pre-safe-reconnect`.

## 2026-08-30 - Timezone normalization
- Found MySQL session `NOW()` did not match Laravel `now()` / WIB because MySQL was still using a stale SYSTEM timezone from an earlier server start.
- Added MySQL connection timezone `+07:00` in `dashboard/config/database.php` with `DB_TIMEZONE` override support.
- Cleared Laravel config cache and restarted `kuysender-queue.service` by terminating the worker process; systemd restored it automatically.
- Verification PASS: Laravel `now()` and DB `NOW()` match exactly in WIB; DB session timezone reports `+07:00`.
- Latest outbound message status after the change is `server_ack`.
- Checkpoint: `/home/openclaw/kuysender-backups/20260830-0223-timezone/`.

## 2026-08-30 - Conflict-safe WhatsApp session persistence
- Root cause disconnect ditemukan: Baileys `Stream Errored (conflict)` dipetakan ke `connectionReplaced` / status 440.
- Logic sebelumnya menganggap 440 sebagai terminal disconnect dan menghapus `wa_auth_sessions` + `wa_auth_keys`, sehingga session tidak dapat dipulihkan.
- Status 440 sekarang dikeluarkan dari terminal-disconnect set; auth database dipertahankan.
- Conflict memakai bounded reconnect dengan backoff existing 15s -> 30s -> 60s -> 120s.
- Jika conflict terus berulang melewati batas retry, reconnect otomatis dihentikan in-memory tanpa menghapus auth.
- Terminal logout/protection untuk 401, 403, 411, dan 500 tetap dipertahankan.
- Payload disconnect sekarang menyertakan `auth_preserved` dan nilai `auto_reconnect` aktual.
- `npm run check`: PASS. Logic test bounded conflict reconnect: PASS.
- `kuysender-wa.service` restarted: PASS; `/health` OK.
- Auth lama sudah terhapus oleh behavior sebelum patch, jadi perlu satu kali QR pairing baru.
- Checkpoint: `/home/openclaw/kuysender-backups/20260830-140446-pre-conflict-session-fix/`.

## 2026-08-30 - KuySender public landing at /home
- Deployed the Next.js KuySender landing page from `/home/openclaw/dev/kuysender-landing` into the production KuySender app.
- Landing is exposed publicly at `https://wa.kuskuskuy.my.id/home` without changing the authenticated dashboard root flow.
- Next.js is built as a static export for production; static runtime assets live under `dashboard/public/landing-home` and the HTML entry is served by a dedicated public Laravel route.
- Existing `/` behavior remains protected and redirects unauthenticated users to `/login`; `/login` remains healthy.
- Verification PASS: `/home` HTTP 200, CSS HTTP 200, Kuskuskuy logo HTTP 200, Laravel route syntax PASS, route registration PASS.
- Deployment checkpoint: `/home/openclaw/kuysender-backups/20260830-2345-home-landing`.
