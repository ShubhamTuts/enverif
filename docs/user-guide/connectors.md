# Plugins and connectors

Plugins connect agents/workflows to external systems. Connections are workspace-scoped and credentials are encrypted at rest. Every action declares a business risk used by agent/workflow approval policy.

First-party plugins developed by **Codefreex** include Apify Dynamic, Apollo, Google Search Console, Google Analytics 4, Google Maps Research, Calendly, n8n/Zapier/Make/custom webhook, Gmail, Microsoft Outlook and SMTP.

A connector can be permanently attached to an agent or temporarily tagged from the chat composer. Temporary tags expose only that workspace connection for the current immutable run.

External connector plugins use `enverif.plugin/v1` manifests. Review third-party PHP before installation because plugin bootstrap code executes inside the Enverif server process.

For email setup see [Email automation](email-automation.md). For authoring a plugin see [Plugin development](../extensions/plugins.md).
