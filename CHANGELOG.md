# Changelog

All notable Enverif changes are documented here.

## 1.2.1 — 2026-08-06

- Fixed the authenticated chat home page failing with a Blade `unexpected token "endif"` parse error after login.
- Rewrote `resources/views/chat/index.blade.php` from compressed inline directives into explicit Blade control blocks to prevent compiler ambiguity and make the template auditable.
- Clarified shared-hosting recovery: compiled Blade views in `storage/framework/views` must be cleared after replacing a broken chat template; the root `.htaccess` does not need to change for this error.

## 1.2.0 — 2026-08-06

- Rebuilt the agentic chat around persistent thread defaults: each conversation can retain its default agent, model connection, model and Fast/Standard/Deep effort while individual messages can use one-shot overrides.
- Added searchable and grouped chat history, rename/archive/delete controls, durable final-response records, live run stage polling and stop/cancel controls.
- Added structured `@agent`, `@plugin`, `@skill`, `@workflow`, `@lead` and `@campaign` context selection instead of relying on decorative mention text.
- Added private chat attachments with workspace/user authorization, model-aware text/image payloads, and private per-agent avatar uploads.
- Added immutable agent run snapshots for instructions, model selection, effort, limits, policy, skills and allowed connector actions so later edits cannot silently mutate an in-flight run.
- Added immutable workflow definition/settings snapshots, runtime validation, test/dry-run execution, retry semantics, node-step inspection and clearer failed-run recovery.
- Fixed the Laravel 13 `HasUuids` / workspace route-binding trait collision that caused fatal errors on agent and workflow pages.
- Added real plugin identity presentation, Codefreex developer links for first-party plugins, safe plugin-local icons for third-party extensions and expanded plugin manifest metadata.
- Hardened installer recovery, dynamic installed-version stamping and root/public Apache routing for Hostinger/cPanel/Plesk shared hosting.
- Expanded release verification to cover the production regressions above and added a deterministic dependency-lock/release contract for GitHub CI.
- Expanded the public PRD, chat/agent/workflow/plugin/shared-hosting/developer documentation and generated GitHub Pages site.

## 1.1.2 — 2026-08-06

- Fixed installer ownership setup using the wrong implicit `user_workspace` pivot name while the migration correctly creates `workspace_user`.
- Made installation state database-authoritative: stale `storage/app/installed` markers no longer block recovery after a database reset, and missing markers no longer expose the installer when a valid owner workspace exists.
- Made incomplete installations safely resumable: owner/workspace creation now runs transactionally and reuses partial user/workspace rows from a failed setup instead of creating duplicates.
- Added post-migration schema validation and owner-membership verification before reporting installation success.
- Replaced free-text AI model fields with provider-aware model dropdowns in the installer, AI model settings, and agent model overrides, while preserving an explicit custom-model option.
- Added installer recovery messaging and regression coverage for stale markers, incomplete installs, pivot naming, and model selection UI.

## 1.1.1 — 2026-08-06

- Fixed fresh shared-hosting installs failing before migrations when `SESSION_DRIVER=database` referenced a missing `sessions` table.
- Added a pre-install framework bootstrap that forces file sessions/cache and a sync queue until installation is complete.
- Added an atomic, private bootstrap application key so CSRF/cookies work even when `APP_KEY` has not yet been created; the same key is promoted into `.env` and the temporary file is removed after installation.
- Changed `.env.example` to safe pre-install framework defaults while preserving database/Redis promotion inside the installer.
- Added regression tests and troubleshooting documentation for empty-database/no-SSH installations.

## 1.1.0 — 2026-08-05

- Rebuilt the primary product shell around a ChatGPT-style agentic workspace with tagged agents, plugins, skills and workflows.
- Added durable visual workflow automation with agents, connector actions, conditions, delays, approvals, leads, campaigns and outputs.
- Added first-party Gmail, Microsoft Outlook and SMTP plugins by Codefreex with approval-first send/reply behavior.
- Added shared-hosting and compatibility runtimes: database queue/cache, bounded `enverif:tick`, scheduler health and signed Web Cron fallback.
- Added secure root/public Apache routing for document-root and subfolder installations without exposing Laravel internals.
- Added schedule calendar/detail views and agent/workflow recurring schedules.
- Expanded Settings with profile, password, AI/API, email, integrations and system-health sections.
- Rebuilt Enverif branding, operator docs, hosting guides, contributor/developer docs and GitHub Pages marketing/documentation site.
- Added production release packaging for source, no-SSH shared-hosting and standalone websites.

## 1.0.0 — 2026-08-05

- Initial open-source Laravel 13 platform.
- Durable queued agent execution with resumable approval checkpoints.
- OpenAI, Anthropic, Gemini and DeepSeek BYOK providers.
- Apify, Apollo, Search Console, GA4, Google Maps research, Calendly and automation webhook connectors.
- Agent skills, remote Git skill installation, security scanning and starter sales/GTM skills.
- Streamable HTTP MCP support.
- Leads, research, campaigns, schedules, approval inbox and hash-linked audit history.
- English, French and Dutch UI.
- First-time browser installer and Docker deployment.
- Manifest-driven connector plugin SDK and contributor documentation.
