# Install Enverif

Enverif supports two equally complete production profiles: **Performance Mode** for Redis/VPS deployments and **Shared Hosting Mode** for cPanel, Hostinger, Plesk and Apache hosting without Redis or SSH. The agent engine, workflows, email connectors, approvals and audit trail are the same in both modes.

## Requirements

Required: PHP 8.3+, MySQL 8+/compatible MariaDB, HTTPS, PDO MySQL, OpenSSL, mbstring, cURL, ZIP, writable `storage/` and `bootstrap/cache/`. Redis is optional. A 256 MB PHP memory limit is recommended; 512 MB is preferred for larger workflows. Hosts with a hard PHP execution limit below 20 seconds are not recommended for autonomous agents.

## Shared hosting: upload and install

1. Download `enverif-<version>-shared-hosting.zip` from GitHub Releases. This package contains production Composer dependencies and compiled assets; Composer, npm and SSH are not required.
2. Create an empty MySQL database and user in your hosting control panel.
3. Upload and extract the ZIP. Prefer pointing the domain/subdomain document root at Enverif's `public/` directory. If your host cannot change the document root, keep Enverif in a folder such as `public_html/enverif/`; the root `.htaccess` securely fronts requests through `public/`.
4. Open `https://your-domain.example/install` or `https://your-domain.example/enverif/install`.
5. Complete Environment → Database → Runtime → Owner → AI Provider → Review.
6. Choose **Shared Hosting** when Redis is unavailable. Enverif uses the database queue/cache automatically.
7. After installation, open **Settings → System health** and add the generated `php /absolute/path/artisan enverif:tick` command to your hosting cron once per minute.
8. Wait for the Scheduler heartbeat to become healthy, then create/connect your first revenue agent.

## Performance mode

For Docker, a VPS or a dedicated server, install dependencies normally and choose Performance Mode when Redis is reachable.

```bash
git clone https://github.com/ShubhamTuts/enverif.git
cd enverif
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

Point the web root at `public/`, visit `/install`, then run persistent workers:

```bash
php artisan queue:work redis --queue=agents,default --sleep=1 --tries=3 --timeout=900
php artisan schedule:work
```

## Subfolder installations

Subfolders are supported. If installation is opened at `https://example.com/tools/enverif/install`, the wizard writes `APP_URL=https://example.com/tools/enverif` and a matching session cookie path. Do not hard-code `/` in reverse-proxy rules or custom frontend code.

## Verify the installation

A production installation is ready only when **Settings → System health** reports a writable storage directory, connected MySQL, the expected queue/cache driver and a recent scheduler heartbeat. Create a test agent, start a read-only run, and verify its run trace before enabling external actions.

See [Shared hosting](../hosting/shared-hosting.md), [Hostinger](../hosting/hostinger.md), [cPanel](../hosting/cpanel.md), [Docker/VPS](../hosting/docker-vps.md), and [Troubleshooting](../operations/troubleshooting.md).
