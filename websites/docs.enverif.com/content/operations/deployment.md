# Deployment overview

Enverif is designed for both conventional servers and ordinary shared hosting.

- **Shared hosting:** ready-to-upload release ZIP, MySQL, database queue/cache, `.htaccess`, browser installer and bounded cron.
- **Performance:** PHP-FPM, MySQL, Redis, persistent queue workers and scheduler.
- **Docker:** application, Nginx, MySQL, Redis, worker and scheduler services from one Compose project.

Always serve HTTPS, keep `APP_DEBUG=false`, prefer `public/` as the document root, keep `.env` outside public access, back up MySQL/storage, and monitor Settings → System health.

Start with [Installation](../getting-started/installation.md), then choose [Shared hosting](../hosting/shared-hosting.md), [Hostinger](../hosting/hostinger.md), [cPanel](../hosting/cpanel.md), [Plesk](../hosting/plesk.md) or [Docker/VPS](../hosting/docker-vps.md).
