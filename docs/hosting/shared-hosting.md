# Shared hosting

Enverif supports ordinary PHP/MySQL hosting without Redis or a persistent worker. Requirements are PHP 8.3+, required PHP extensions, writable Laravel storage/cache paths, MySQL, outbound HTTPS for providers/connectors and either CLI cron or the signed Web Cron fallback.

## Preferred layout

Point the domain/subdomain document root to Enverif's `public/` directory. `public/.htaccess` explicitly uses `DirectoryIndex index.php` and routes application paths to Laravel.

When a host cannot change document root, keep the secure root `.htaccess`. It denies access to `.env`, VCS data, vendor/storage/database/application internals and sends the bare root directly to `public/index.php`. Non-empty application paths are forwarded into `public/` and then handled by Laravel.

The explicit bare-root rule matters on Hostinger/LiteSpeed: rewriting `/` to the `public/` **directory** can inherit the wrong `DirectoryIndex` and produce a 403 before Laravel runs.

## Runtime mode

Choose **Shared Hosting** during installation when Redis/persistent workers are unavailable. Enverif uses MySQL-backed queue/cache and the bounded tick runner.

Add the installer-generated cron command once per minute:

```bash
php /absolute/path/to/enverif/artisan enverif:tick
```

The tick acquires a lock, dispatches due schedules, drains a bounded amount of queue work, records scheduler/queue heartbeat state and exits. Long-running durable agent/workflow operations continue on later ticks.

If CLI cron is unavailable, configure the signed Web Cron endpoint shown in System Health/Settings. It executes the same bounded tick service; it does not expose arbitrary Artisan execution.

## Upgrading from 1.1.x to 1.2.0

Back up the database and `.env`. A full GitHub no-SSH release may be extracted as documented by the release notes. If using the separately named 1.2.0 **shared-hosting update** archive, preserve your existing `vendor/` and `.env`, overwrite the application files, remove a stale `bootstrap/cache/config.php` if present, then run the database migration/recovery flow.

The 1.2 migration adds persistent chat defaults/history execution fields, private attachment records, agent avatar/default-effort fields and workflow run mode/retry fields.

## Installer recovery

Installation state is database-authoritative. A stale `storage/app/installed` marker does not permanently block recovery after a database reset, and an incomplete migrated database can return to installer recovery rather than pretending installation completed.

Before schema creation Enverif temporarily uses file sessions/cache and a sync queue, so a fresh empty database does not need `sessions`, `cache` or `jobs` tables to render `/install`. After validation/migration it promotes the configured runtime into `.env`.

## Health checks

Before enabling autonomous schedules confirm System Health shows:

- expected runtime mode;
- correct queue/cache drivers;
- recent scheduler and queue heartbeats;
- writable storage/cache paths;
- database connectivity/schema health;
- Redis state (informational in Shared Hosting Mode);
- correct public/base URL and rewrite routing.
