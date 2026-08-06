# External connector plugins

Place reviewed connector plugins in a subdirectory here. Each plugin must contain an `enverif.json` manifest using `enverif.plugin/v1`. A plugin may declare a safe relative `bootstrap` PHP file and a `driver` class that implements `App\Core\Connectors\Contracts\ConnectorDriver`.

## Minimal scaffold

```text
plugins/external/my-crm/
├── enverif.json
├── assets/icon.svg
├── src/MyCrmConnector.php
└── README.md
```

After adding a plugin:

1. Visit **Plugins** — the catalog lists it with your `developer` attribution and local icon.
2. Click **+ New connection**, enter credentials (stored encrypted) and non-secret configuration.
3. Click **Test**, then attach the connection to an agent or tag it with `@plugin` in chat.
4. Workflows can call the same actions through Plugin nodes.

See `docs/extensions/plugins.md` for the full manifest contract, risk classifications and agent snapshot rules.

Third-party plugins execute server-side PHP with the application process privileges. Review source code before deployment and never install an untrusted plugin directly on a production host.
