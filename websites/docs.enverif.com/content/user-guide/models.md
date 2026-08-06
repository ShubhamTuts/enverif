# AI models

Enverif uses a provider-neutral model contract and supports BYOK connections for OpenAI, Anthropic Claude, Google Gemini and DeepSeek.

Each connection stores:

- provider;
- encrypted API key;
- optional compatible base URL;
- default model identifier;
- optional input/output prices per million tokens for run cost estimates.

The model field on an individual agent can override the connection default, which allows newly released model identifiers to be used without an Enverif code change when the provider API remains compatible.
