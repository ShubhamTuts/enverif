# Release process

Enverif releases are built from a committed semantic-version tag and deterministic dependency locks. A source checkout without `composer.lock` and `package-lock.json` can be developed, but it is **not eligible for a production/no-SSH release** until those locks are generated, reviewed and committed.

## Version contract

1. Update `VERSION` to `X.Y.Z`.
2. Add the release entry to `CHANGELOG.md`.
3. Generate/review `composer.lock` and `package-lock.json` whenever dependency manifests changed or the locks are absent.
4. Merge only after CI passes.
5. Tag the exact verified commit as `vX.Y.Z`.
6. The tag-driven Release workflow checks that the tag matches `VERSION`, reruns the release gates, builds artifacts from that exact source and refuses to retarget an existing release.

## Required gates

A release is blocked by any failure in:

- standalone policy/runtime/regression contracts;
- repository verifier and PHP lint;
- Laravel application boot and route list;
- MySQL fresh migrations;
- Laravel feature/unit tests;
- PHP 8.3/8.4/8.5 matrix;
- Redis performance runtime;
- database queue/cache runtime with Redis extension absent;
- frontend dependency install, syntax/check and production build;
- generated documentation build and internal-link/assets validation;
- first-party Codefreex plugin metadata/icon checks;
- shared-host archive inspection.

The CI suite should exercise the fresh installer against MySQL and the authenticated root/agent/workflow routes because installer/session/pivot/rewrite/UUID regressions can pass source-only tests while still failing on a real host.

## Artifacts

The production GitHub Release should publish:

```text
enverif-X.Y.Z-source.zip
enverif-X.Y.Z-shared-hosting.zip
enverif-X.Y.Z-websites.zip
SHA256SUMS.txt
```

The source ZIP is the tagged Git tree. The websites ZIP contains the standalone marketing/docs sites. The **shared-hosting ZIP** must include real production Composer dependencies (`vendor/autoload.php`) and compiled frontend assets while excluding `.env`, `.git`, `node_modules`, tests, private logs/caches and internal build state.

## Local update archive vs fresh package

A source-only environment that cannot install Composer dependencies may create a clearly named **shared-hosting update** archive for an existing Enverif installation that already has `vendor/`. That archive must tell operators to preserve their existing `.env` and `vendor/` and must never be advertised as the fresh no-SSH package.

## Archive inspection

Before publishing the fresh no-SSH artifact, extract the ZIP and assert at least:

- `artisan`
- `vendor/autoload.php`
- root `.htaccess`
- `public/.htaccess`
- `storage/.htaccess`
- `database/.htaccess`
- `README-INSTALL.txt`
- `VERSION`
- compiled `public/build` assets (when Vite build outputs them)

Also assert that `.env`, `.git`, development caches, tests and `node_modules` are absent. Boot `php artisan --version` from the extracted archive before release.
