# Hostinger

Enverif is designed to run on Hostinger shared hosting with MySQL and database queue/cache; Redis is optional.

## Deployment

1. Create a subdomain/domain and MySQL database/user.
2. Upload the **GitHub shared-hosting release** that contains real `vendor/` dependencies. If upgrading an existing install with the separately named update archive, keep your current `.env` and `vendor/`.
3. Prefer setting the domain document root to the package `public/` folder. If Hostinger does not permit that layout, keep the included root `.htaccess` fallback.
4. Open `/install`, choose Shared Hosting Mode and complete setup.
5. Add the generated `enverif:tick` command in Hostinger cron jobs once per minute. Use the signed Web Cron fallback only where CLI cron is unavailable.
6. Check System Health before enabling schedules/autonomous workflows.

## 403 after login or at `/`

Do not remove Enverif's root security rules. Enverif 1.2 sends the bare application root directly to `public/index.php`, while `public/.htaccess` declares `DirectoryIndex index.php`. This avoids LiteSpeed/Apache trying to list the `public/` directory or resolving a nested `public/public/index.php` path.

If an old cached rule persists, overwrite both `.htaccess` files from the current package, clear Hostinger/LiteSpeed cache, and delete only `bootstrap/cache/config.php` if it exists.

## Cron

Use the PHP binary shown by Hostinger for your PHP 8.3+ installation, for example:

```bash
/usr/bin/php /home/USER/domains/example.com/enverif/artisan enverif:tick
```

The exact absolute path differs per account; use the path emitted by Enverif/System Health rather than copying an example literally.
