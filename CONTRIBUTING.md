# Contributing to Enverif

Thank you for improving Enverif. Contributions are organized around four tracks so specialists can work independently without weakening the runtime contract.

## Contribution tracks

### Core
Changes under `app/Core`, persistence, queueing, authentication, tenancy, audit or installer code. Core changes must include tests for security boundaries and state transitions.

## Plugins
Connector drivers under `plugins/external` plus any reusable driver code. Plugins must declare capabilities accurately in `enverif.json`, never log secrets, and classify third-party writes as `external_write` or `destructive` as appropriate.

**Quick start**

1. Copy `plugins/external/` layout from `docs/extensions/plugins.md`.
2. Add `enverif.json` + driver implementing `ConnectorDriver`.
3. Drop a local `assets/icon.svg` (or PNG/WebP) — remote favicons are not used.
4. Restart PHP / clear caches; the catalog picks up the plugin automatically.
5. Open **Plugins → New connection**, fill credentials, **Test**, then attach the connection to an agent or tag `@plugin` in chat.

### Skills
Procedural `SKILL.md` contributions. Keep instructions provider-neutral, document required capabilities, include source/license metadata when adapting prior work, and avoid embedding credentials. See `docs/extensions/skills.md` and `skills/builtin/` for examples.

### Models / MCP
- New BYOK providers belong under `app/Core/Models/Providers` and must be registered in `ProviderManager`. Keep suggested model IDs current with the vendor API docs; support custom IDs.
- Remote MCP servers are configured in the UI (`docs/extensions/mcp.md`); protocol versions are configuration, not hard-coded product secrets.

### Translations
UI strings under `lang/<locale>` and documentation translations. Preserve placeholder names and technical identifiers. Native-speaker review is preferred.

## Development setup

1. PHP 8.3+, Composer 2, MySQL 8+, Redis 7+.
2. `cp .env.example .env`
3. `composer install`
4. `php artisan key:generate`
5. Configure MySQL/Redis and run `php artisan migrate --seed`.
6. Run `php artisan serve`, a Redis queue worker and the scheduler.

Docker contributors can use `./install.sh` and the browser installer.

## Required checks

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/standalone/run.php
composer verify
composer test
```

Run Pint before opening a pull request when Composer dependencies are installed:

```bash
vendor/bin/pint
```

## Pull requests

Keep one coherent change per PR. Explain the user-facing behavior, risk classification, data migration implications, and verification performed. Security-sensitive changes should include a negative test showing the prohibited path stays prohibited.

## Compatibility

Core targets PHP 8.3–8.5 and Laravel 13. MySQL and Redis are required runtime dependencies. New connectors should not force optional SDKs into core when a standards-based HTTP implementation is sufficient.

## License

By submitting a contribution, you agree that your contribution may be distributed under the repository's MIT License. Do not submit code you do not have the right to license.
