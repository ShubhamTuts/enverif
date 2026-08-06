# Troubleshooting

## Installer cannot connect to MySQL

Verify host/port/database/user, then ensure the database user can CREATE, ALTER, INDEX, SELECT, INSERT, UPDATE and DELETE. The installer intentionally creates and drops a temporary probe table.

## `/install` fails because the `sessions` table does not exist

This means the deployment is running an older bootstrap configuration that selects the database session driver before migrations have created the schema. In Enverif 1.1.1 and later, uninstalled requests force file sessions/cache and a synchronous queue until installation succeeds, so an empty database is valid.

For an older extracted package, the immediate recovery is: set `SESSION_DRIVER=file`, `CACHE_STORE=file`, and `QUEUE_CONNECTION=sync` in `.env`; remove stale generated PHP files from `bootstrap/cache/` except `.gitignore`; then reload `/install`. Do **not** create the `sessions` table by hand. After a successful install Enverif writes the production database/Redis runtime settings and migrations create the required tables consistently.

If `APP_KEY` is empty, use the updated installer files rather than inventing a key in a browser or public support ticket. The fixed bootstrap creates a private stable key under `storage/app/` and promotes it into `.env` during installation.

## Redis is missing

This is not an installation failure. Select Shared Hosting Mode. Enverif will use database queue/cache.

## 404 or 403 on shared hosting

Prefer a document root pointing at `public/`. In file-manager-only layouts verify both root and `public/.htaccess` were extracted. Do not expose `app`, `vendor`, `storage`, `config`, `routes`, `.env` or database directories to work around routing.

## Agents remain queued

Open **Settings → System health**. If the heartbeat is stale, fix cron/worker execution. If pending jobs grow while heartbeat is healthy, inspect failed jobs/logs and confirm the configured queue connection matches the runtime mode.

## Gmail/Outlook OAuth fails

Confirm the exact HTTPS callback URI, client ID/secret, requested permissions and tenant. OAuth providers compare redirect URIs exactly, including subfolders and HTTPS.

## Assets fail under `/enverif/`

Confirm `APP_URL` contains the subfolder, `SESSION_PATH` matches it, and no reverse proxy strips the path. Clear application/CDN/browser caches after moving an installation.

## Workflow is waiting

`awaiting_approval` requires a decision in Approvals. `waiting_delay` requires queue/scheduler execution after the delay. `waiting_agent` means a delegated agent run has not reached a terminal state.

## Web Cron returns 403 or 404

A 404 normally means Web Cron is disabled or the route token format is invalid. A 403 means the derived bearer token does not match the current `ENVERIF_WEB_CRON_SECRET`. Re-copy the URL from **Settings → System health** instead of constructing it manually. If you rotate the server secret, every previously copied Web Cron URL becomes invalid.

## Web Cron returns 200 but schedules still do not run

Check the System health heartbeat and failed-job count. A successful HTTP response proves the endpoint authenticated and entered the runtime tick; it does not make a third-party model/API request immune to the hosting provider's PHP execution limit. Increase the hosting limit or reduce `ENVERIF_TICK_BUDGET` so a tick exits cleanly before the hard timeout.

## Installer says `user_workspace` does not exist

Enverif 1.1.2 fixes an installer bug where Eloquent's implicit many-to-many table name (`user_workspace`) did not match the migration's real pivot table (`workspace_user`). Do not create a `user_workspace` table manually.

Upgrade the application files to 1.1.2 and reopen `/install`. The installer now resumes an incomplete installation safely: it reuses the existing admin/workspace rows, writes the owner membership to `workspace_user`, verifies the owner relationship, and only then reports success.

If you recreated the database but Enverif redirects away from `/install`, 1.1.2 also fixes stale `storage/app/installed` markers. Installation state is now validated against the actual database schema and owner membership rather than trusting the marker file alone.

## Model selection is free text instead of a dropdown

Enverif 1.1.2 uses provider-aware dropdowns for model selection during installation, AI connection setup, and agent model overrides. Choose **Custom model ID…** only when you intentionally need a compatible model ID that is not in the built-in provider catalog.
