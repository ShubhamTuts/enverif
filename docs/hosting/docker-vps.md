# Docker and VPS deployment

Docker Compose starts the app, Nginx, MySQL, Redis, a queue worker and scheduler. Copy `.env.example` to `.env`, change production credentials, then run `./install.sh` and open `/install`.

For a conventional VPS, point Nginx/Apache to `public/`, use PHP-FPM, MySQL and Redis, and manage queue workers with Supervisor/systemd. Run at least one worker for `agents,default`. Use `schedule:work` or a system cron invoking `php artisan schedule:run` once per minute.

Keep `APP_DEBUG=false`, serve HTTPS, restrict database/Redis to private interfaces, rotate credentials, back up MySQL and `storage/`, and review the approval/audit logs regularly.
