# Connector plugin development

Enverif connector plugins are manifest-driven PHP extensions. First-party plugins are developed by **Codefreex**. Reviewed third-party plugins live under `plugins/external/<slug>/` and can expose their own developer identity, website, docs and icon.

## Manifest

```json
{
  "schema": "enverif.plugin/v1",
  "name": "My CRM",
  "slug": "my-crm",
  "version": "1.0.0",
  "type": "connector",
  "bootstrap": "src/MyCrmConnector.php",
  "driver": "Vendor\\MyCrm\\MyCrmConnector",
  "capabilities": ["read", "network", "external_write"],
  "license": "MIT",
  "developer": "Acme Labs",
  "developer_url": "https://example.com/",
  "homepage": "https://example.com/my-crm",
  "docs_url": "https://example.com/docs/enverif",
  "category": "CRM",
  "icon": "assets/icon.svg"
}
```

Optional presentation fields are validated. `developer_url`, `homepage` and `docs_url` must be HTTPS. `icon` can be HTTPS or a safe plugin-relative SVG/PNG/WebP/JPG file. Local icon files are served through Enverif's constrained plugin-asset controller rather than exposing the entire plugin directory.

The plugin catalog renders the developer name as a hyperlink when `developer_url` is present. All built-in connector manifests use **Codefreex** and `https://codefreex.com/`.

## Driver contract

Implement `App\Core\Connectors\Contracts\ConnectorDriver`:

- `id()` — stable machine identifier;
- `label()` — user-facing name;
- `actions()` — action definitions with JSON Schema and risk classification;
- `configurationSchema()` — credentials and non-secret configuration fields;
- `test()` — inexpensive connection verification;
- `execute()` — action execution.

`bootstrap` is optional for Composer-autoloaded classes. If supplied it must be a safe relative PHP file inside the plugin directory.

## Recommended layout

```text
plugins/external/my-crm/
├── enverif.json
├── assets/
│   └── icon.svg
├── src/
│   └── MyCrmConnector.php
├── README.md
└── LICENSE
```

Third-party PHP executes in the application process. Manifest validation is not a sandbox. Review source before installation.

## Configuration and secrets

Keep API keys, OAuth secrets, passwords and long-lived tokens in encrypted `credentials`. Keep account IDs, regions, sender names and non-secret base URLs in configuration. Blank secret fields preserve the existing encrypted value; never render stored secrets back into forms.

## Action design and risk

Prefer narrow actions such as `contact.search`, `contact.create` and `deal.update_stage` over a generic arbitrary-request endpoint.

| Action behavior | Risk |
|---|---|
| read/search/list/get | `read` or `network` |
| create local draft/internal record | `internal_write` |
| send email, mutate CRM, trigger remote automation | `external_write` |
| reveal secret material | `secrets` |
| irreversible remote deletion/revocation | `destructive` |

Do not misclassify mutations to avoid approvals. Email send/reply remain external writes.

## Agent and workflow use

An agent attachment can restrict a connector to an `allowed_actions` list. New agent runs snapshot that allowed-action set, so changing the agent later cannot grant extra actions to an in-flight run. Workflows validate the connector/action before starting and apply the same risk/approval engine during live execution.

## Testing

At minimum test manifest validation, asset-path safety, required configuration, risk classification, argument validation and provider error normalization. OAuth plugins must document callback paths/scopes; webhook plugins should require HTTPS and sign payloads when supported.

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/standalone/run.php
php scripts/verify.php
php artisan test
```
