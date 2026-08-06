# Translation contributions

The initial UI locales are:

- `en` — English
- `fr` — French
- `nl` — Dutch

Translation files live in `lang/<locale>/ui.php`. Add a new locale only after the full primary UI string set is translated. Preserve route names, model IDs, connector IDs, capability identifiers and placeholders exactly.

For a new locale, also update installer validation, settings language options and user-facing documentation navigation.

## Translation workflow

Use `lang/en/ui.php` as the canonical key set. Copy new keys into French and Dutch in the same pull request, preserving replacement parameters such as `:name`, `:count` or `:field` exactly. Never translate machine identifiers such as connector slugs, route names, cron expressions, environment-variable names or JSON keys.

Run:

```bash
php scripts/verify.php
```

The verifier compares the complete key sets and fails on missing or extra keys. After changing navigation, forms or chat labels, manually inspect at least one narrow viewport because translations can be substantially longer than English.

For a future new locale, add the locale to `config/enverif.php`, installer validation, user/workspace settings validation, language selectors, locale middleware behavior and documentation. A partially translated locale should not be exposed as a production option.
