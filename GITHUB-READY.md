# Enverif 1.2.0 — GitHub-ready repository

This repository contains the complete Enverif 1.2.0 application source prepared for GitHub.

Included:

- Laravel 13 application source and 1.2 migrations;
- persistent agentic chat with thread defaults, one-message overrides, structured `@` context, private attachments and agent avatars;
- durable agents/workflows with immutable execution snapshots, dry runs, retries, approvals and inspection;
- first-party Codefreex plugins plus external plugin icon/developer metadata support;
- skills and MCP extension layers;
- shared-hosting/Hostinger/cPanel/Plesk runtime and Apache routing;
- `.github/` CI, Pages, dependency-lock and tag-driven release workflows;
- standalone `enverif.com` and `docs.enverif.com` websites;
- full Markdown documentation and `docs/PRODUCT-REQUIREMENTS.md`;
- tests, release verifier, static-site generator and release builder.

## Dependency boundary

This source repository intentionally does not vendor Composer/npm dependencies. The imported 1.1.x source did not contain `composer.lock` or `package-lock.json`, and this execution environment cannot resolve the public Composer/npm registries. Do not fabricate those files.

Before creating the production `v1.2.0` tag:

1. run the manual **Generate dependency lockfiles** GitHub workflow;
2. review and merge its lockfile pull request after CI passes;
3. confirm the MySQL/Redis/database-runtime Laravel matrix passes;
4. create `v1.2.0` on that exact verified commit.

The tag-driven Release workflow then creates the real fresh no-SSH shared-hosting package containing production `vendor/autoload.php` and compiled assets.

A separately named local **shared-hosting update** archive can be used to upgrade an existing Enverif installation that already has a real `vendor/` directory. It must preserve the server's existing `.env` and `vendor/`.

## Portable verification performed before packaging

- 58/58 standalone regression tests passed;
- 328 release checks passed;
- 219 PHP files linted;
- 457 localization keys verified;
- 29 generated documentation pages plus homepage;
- 1,095 generated site asset/page references validated;
- JavaScript source/public mirrors and syntax checks passed;
- all GitHub workflow YAML parsed successfully.
