# Security and approval model

Enverif treats autonomous sales work as a privileged business system rather than an unrestricted chatbot.

## Capability decisions

Tools declare `read`, `internal_write`, `network`, `external_write`, `secrets` or `destructive`. Explicit deny has highest priority. External writes ask by default. Secret-bearing operations always ask. Destructive capability is disabled by default and still requires approval when enabled.

## Email

Gmail, Outlook and SMTP `send`/`reply` actions are always external writes. Agents and workflows can opt into autonomous external writes, but the default is approval-first. OAuth tokens, SMTP passwords and API keys are encrypted in connector/model records and must never be inserted into prompts, memory or audit payloads.

## Shared hosting

Prefer a document root at `public/`. The fallback root `.htaccess` blocks sensitive files/directories before routing requests. Keep hidden files when uploading the package. Use HTTPS for OAuth callbacks, webhooks and web cron.

## Plugins

First-party plugins are developed by Codefreex. Third-party plugins execute server-side PHP and should be reviewed before installation. Manifest capabilities are metadata for validation and documentation; the runtime action risk remains authoritative.

## Audit and workspace boundaries

Workspace-scoped models use workspace query scopes and route binding checks. Sensitive agent/workflow transitions are persisted and approvals identify the deciding user. Do not weaken workspace filters in convenience APIs or background jobs.
