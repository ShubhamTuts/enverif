# Changelog

All notable Enverif changes are documented here.

## 1.3.1 — 2026-08-06

- Fixed `/install` returning a Laravel 500 because the installer Blade template referenced `$installModelCatalogJson` without the controller supplying it.
- The installer controller now serializes the provider model catalog explicitly, and the Blade view has a defensive fallback for partially overwritten shared-hosting updates.
- Added a Laravel installer-page feature test and expanded release verification with controller-to-view data-contract checks across primary screens.
- Added release-version cache busting to guest/login/installer assets so Hostinger/LiteSpeed cannot keep stale CSS or JavaScript after an update.
- Retained all 1.3.0 chat, workflow, shared-hosting, UUID/workspace, UI and plugin hardening fixes.

## 1.3.0 — 2026-08-06

- Chat submissions now stay on the chat surface: Fetch transport, canonical thread URLs via History API, live server-rendered transcript replacement, polling and composer run locking; the legacy `/chats/send` endpoint is removed.
- Shared/Compatibility interactive actions now register a bounded post-response `WebQueueKick` through the same `TickRunner` lock while cron/Web Cron remains authoritative for unattended work.
- Conversation truncation preserves a leading user turn instead of starting model context with an orphaned assistant message.
- Workflow definitions reject duplicate outgoing ports and require explicit `true`/`false` condition branches; runtime validation rejects agent/skill executors without an enabled model connection.

- Rebuilt the desktop agentic shell to remove the duplicate sidebar offset and restore full-width content.
- Added release-version cache busting for application CSS/JavaScript so Hostinger/LiteSpeed cannot serve stale UI assets after upgrades.
- Reworked chat submission into an asynchronous in-place transport with validation/error rendering and direct thread navigation.
- Polished the chat composer, history search, model/agent/effort controls, attachment picker and responsive layout.
- Removed "by Codefreex" from the Enverif product lockup while retaining Codefreex as first-party plugin developer attribution.
- Replaced remote favicon dependencies with bundled integration SVG assets and tightened icon sizing.
- Fixed workflow form Blade compilation by serializing builder resources safely outside the directive parser.
- Hardened Blade/release verification against unsafe inline JSON directives and stale generated assets.
- Improved plugin developer hyperlinks, card alignment, accessibility and external plugin icon fallback.
- Added regression coverage for the production UI, chat transport and workflow Blade failures.

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
