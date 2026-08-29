# Backup Scope

Repository ini adalah snapshot source aman KuySender.

Tidak disertakan: `.env`, API key/token, credential, session WhatsApp, cache/log, storage runtime, `vendor`, `node_modules`, database lokal, serta arsip backup.

Untuk restore, clone repository lalu install dependency dari `composer.lock` dan `package-lock.json`, buat `.env` baru dari `.env.example`, dan isi credential secara terpisah.
