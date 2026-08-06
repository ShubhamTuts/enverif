# Plesk installation

Create the target database, choose PHP 8.3+, and upload the shared-hosting release. Under **Hosting Settings**, point the document root at `enverif/public` when possible. Otherwise retain the root `.htaccess` fallback.

After `/install`, use **Scheduled Tasks** to run:

```bash
php /var/www/vhosts/example.com/enverif/artisan enverif:tick
```

Select **Run a command** rather than requesting a public URL when Plesk allows it. Verify the heartbeat in **Settings → System health**.
