# Enverif web properties

This directory contains two dependency-free PHP sites that can be deployed separately from the Laravel application.

## `enverif.com`

Marketing/product site. Point the virtual host document root at `websites/enverif.com` and serve `index.php`.

## `docs.enverif.com`

Documentation portal with its own copy of the public Markdown documentation under `content/`.

Apache: `.htaccess` is included.

Nginx: adapt `nginx.conf.example` to your PHP-FPM socket and document root.

Local preview:

```bash
php -S 127.0.0.1:8081 -t websites/enverif.com
php -S 127.0.0.1:8082 -t websites/docs.enverif.com websites/docs.enverif.com/router.php
```

The documentation package has no Composer or database dependency.
