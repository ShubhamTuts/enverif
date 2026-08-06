# External connector plugins

Place reviewed connector plugins in a subdirectory here. Each plugin must contain an `enverif.json` manifest using `enverif.plugin/v1`. A plugin may declare a safe relative `bootstrap` PHP file and a `driver` class that implements `App\Core\Connectors\Contracts\ConnectorDriver`.

Third-party plugins execute server-side PHP with the application process privileges. Review source code before deployment and never install an untrusted plugin directly on a production host.
