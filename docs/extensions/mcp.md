# MCP servers

Enverif can connect to remote MCP Streamable HTTP servers and expose their tools to authorized agents/workflows.

MCP endpoints and bearer credentials are workspace scoped; bearer tokens are encrypted. Enverif uses protocol `2025-11-25` by default and supports the opted-in stateless `2026-07-28` behavior where configured.

## Safety model

Remote MCP tools are untrusted capabilities. Tool discovery does not mean automatic execution. Enverif maps every remote tool to a conservative risk classification and applies the same capability/approval path used by local and connector tools. Destructive, secret-bearing or external-write behavior must not be disguised as a read.

## Developer guidance

A server should expose narrow tools with descriptive names and JSON schemas, return bounded structured errors and avoid requiring the model to construct arbitrary HTTP requests. Document authentication, rate limits and side effects. Use HTTPS in production.

For extensions that need a branded catalog entry, build a connector plugin and use MCP behind that plugin; the plugin manifest can then declare icon, developer URL, homepage and docs metadata.
