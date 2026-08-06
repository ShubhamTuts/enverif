# Contributing to Enverif

Thank you for improving Enverif. Contributions are organized around four tracks so specialists can work independently without weakening the runtime contract.

## Contribution tracks

### Core
Changes under `app/Core`, persistence, queueing, authentication, tenancy, audit or installer code. Core changes must include tests for security boundaries and state transitions.

### Plugins
Connector drivers under `plugins/external` plus any reusable driver code. Plugins must declare capabilities accurately in `enverif.json`, never log secrets, and classify third-party writes as `external_write` or `destructive` as appropriate.

### Skills
Procedural `SKILL.md` contributions. Keep instructions provider-neutral, document required capabilities, include source/license metadata when adapting prior work, and avoid embedding credentials.

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
