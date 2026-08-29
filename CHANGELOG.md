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
