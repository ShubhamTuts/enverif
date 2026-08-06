# cPanel installation

1. In **MultiPHP Manager**, select PHP 8.3+ for the target domain.
2. In **MySQL Databases**, create a database/user and grant the user all privileges required for schema migration.
3. Upload `enverif-<version>-shared-hosting.zip` in **File Manager** and extract it.
4. Prefer a subdomain document root ending in `/public`. If that is unavailable, keep the included root `.htaccess` in Enverif's extracted root.
5. Open `/install`, complete the wizard and select Shared Hosting Mode when Redis is absent.
6. In **Cron Jobs**, add `php /absolute/path/to/enverif/artisan enverif:tick` once per minute. If `php` resolves to an old version, use cPanel's PHP 8.3 CLI path.
7. Confirm the Scheduler heartbeat in Settings before relying on schedules, workflow delays or outbound automation.

Do not run `queue:work` as a permanent process on ordinary shared hosting. `enverif:tick` is intentionally finite and safe for cron-managed execution.
