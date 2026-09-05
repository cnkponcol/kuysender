# Changelog

## Unreleased - Stabilization 2026-08-29

### Changed
- Clean rebuild Node dependencies.
- Baileys upgraded to 7.0.0-rc14 for current LID/tctoken behavior.
- Reset old WhatsApp session/auth/device runtime data.
- Broadcast prefers stored WhatsApp JID when available.

### Added
- Android-friendly mobile-only application shell with bottom navigation, scrollable More drawer, safe-area handling, and responsive Inbox.
- Encrypted revealable API token for authenticated dashboard show/hide/copy.
- Copy controls for API key, webhook URL/secret, and Device ID.
- AI knowledge edit, enable/disable, category, search, JSON import/export, and Clear Prompt.
- Personal WhatsApp contact synchronization and `wa_jid` support.
- Scheduled `kuysender:cleanup` maintenance.
- Official project/process documentation.

### Preserved
- Existing Inbox, Auto Reply, group sync, broadcast consent controls, API/webhook architecture, and AI modes remain the baseline.

### Live verification
- Fresh rc14 QR/device pairing verified end-to-end: inbound, AutoResponder outbound, and manual Inbox outbound PASS.
- Internal WhatsApp protocol events are filtered from Inbox.
- Full PHP runtime ownership migration from `www-data` to `openclaw` requires root access.

### Fixed - 2026-08-30
- Normalized Laravel MySQL session timezone to `+07:00` so DB `NOW()` matches Asia/Jakarta application time.
- Restarted the queue worker after clearing config cache; outbound processing remains healthy with latest status `server_ack`.

### Fixed - 2026-08-30 WhatsApp conflict persistence
- `connectionReplaced` / status 440 no longer deletes persisted WhatsApp auth.
- Conflict reconnect now uses bounded exponential backoff and stops after repeated conflicts while preserving credentials.
- Terminal logout handling for 401/403/411/500 remains unchanged.
- Disconnect telemetry now reports whether auth was preserved and whether reconnect was actually scheduled.

### Added - 2026-08-30 Public KuySender landing
- Added public `/home` landing page to `wa.kuskuskuy.my.id` using the Next.js landing source as a static production export.
- Kept the existing authenticated dashboard and login flow unchanged.
- Landing assets are isolated under `/landing-home` to avoid collisions with Laravel dashboard assets.
