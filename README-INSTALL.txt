ENVERIF — SHARED HOSTING INSTALLATION
=====================================
Enverif by Codefreex · MIT licensed

This package is built for standard Apache shared hosting and contains production Composer dependencies.
You do not need npm, Composer, Redis, Docker, Supervisor, or SSH to complete a browser installation.

1. Upload this ZIP to your hosting account and extract it.
2. BEST: point your domain/subdomain document root to this package's /public directory.
   FALLBACK: if the host cannot change the document root, keep the included root .htaccess exactly as shipped.
3. Create an empty MySQL database and user.
4. Open https://your-domain.example/install (or /your-subfolder/install).
5. Complete the installer and choose Shared Hosting Mode when Redis is unavailable.
6. Sign in, open Settings → System health, and copy the generated cron command.
7. Run that command once per minute from Hostinger hPanel, cPanel, Plesk, or your host's cron manager.
8. If the host provides no CLI cron at all, install with Compatibility Mode and copy the protected Web Cron URL from Settings → System health into a trusted HTTPS cron service.
9. Wait for the Scheduler heartbeat to show Healthy before relying on recurring agents/workflows.
10. Connect an AI model, then create your first agent.

SECURITY
--------
- HTTPS is required for production, Gmail/Outlook OAuth, webhooks, and Web Cron.
- Never expose .env, storage, vendor, app, config, database, resources, routes, or tests to HTTP.
- The shipped root/public/storage/database .htaccess files are security controls. Do not delete them to work around routing.
- Email send/reply actions require approval by default. Enable autonomous external writes only on agents/workflows you intentionally trust.
- Treat the Web Cron URL as a password because the derived bearer token appears in the URL.

DOCUMENTATION
-------------
GitHub Pages: https://shubhamtuts.github.io/enverif/
Repository:   https://github.com/ShubhamTuts/enverif
Shared host:  https://shubhamtuts.github.io/enverif/docs/hosting/shared-hosting.html
Hostinger:    https://shubhamtuts.github.io/enverif/docs/hosting/hostinger.html
Troubleshoot: https://shubhamtuts.github.io/enverif/docs/operations/troubleshooting.html
