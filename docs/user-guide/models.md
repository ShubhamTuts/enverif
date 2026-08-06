# AI models

Enverif uses a provider-neutral model contract and supports BYOK connections for OpenAI, Anthropic Claude, Google Gemini and DeepSeek.

Each connection stores:

- provider;
- encrypted API key;
- optional compatible base URL;
- default model identifier;
- optional input/output prices per million tokens for run cost estimates.

The model field on an individual agent (and the chat composer) can override the connection default. Custom model IDs are always allowed so newly released provider identifiers can be used without an Enverif code change when the provider API remains compatible.

## Suggested model catalogs (1.3.3)

These are the dropdown defaults. They track the current public Chat Completions / Messages / generateContent IDs and are updated in release notes when providers retire names.

| Provider | Suggested IDs | Default API base |
|---|---|---|
| OpenAI | `gpt-5.4`, `gpt-5.2`, `gpt-5`, `gpt-5-mini`, `gpt-4.1`, `gpt-4.1-mini`, `gpt-4o`, `gpt-4o-mini`, `o3`, `o4-mini`, `o3-mini`, `o1`, `o1-mini` | `https://api.openai.com` |
| Anthropic | `claude-opus-5`, `claude-sonnet-5`, `claude-haiku-4-5`, `claude-opus-4-8`, `claude-sonnet-4-6`, `claude-opus-4-5`, `claude-sonnet-4-5`, `claude-fable-5` | `https://api.anthropic.com` |
| Gemini | `gemini-3.6-flash`, `gemini-3.5-flash`, `gemini-3.1-pro-preview`, `gemini-3.1-flash-lite`, `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-2.5-flash-lite` | `https://generativelanguage.googleapis.com` |
| DeepSeek | `deepseek-v4-flash`, `deepseek-v4-pro` | `https://api.deepseek.com` |

### Compatibility remaps

- DeepSeek legacy IDs (`deepseek-chat`, `deepseek-reasoner`, `deepseek-coder`, `deepseek-v3`, …) are remapped onto `deepseek-v4-flash` / `deepseek-v4-pro` before the request is sent.
- Shut-down Gemini 2.0 / 1.5 IDs are remapped onto current Flash / Pro models.

### Effort

Chat and agent **Fast / Standard / Deep** map onto provider-native effort where supported (OpenAI reasoning models, Anthropic `output_config.effort`, DeepSeek V4 `reasoning_effort`).

### Troubleshooting

If chat shows a provider error:

1. Re-open **AI Models**, edit the connection, pick a suggested model from the dropdown (or a verified custom ID), save, and **Test**.
2. Confirm the API key is valid for that provider and that the base URL is blank unless you intentionally use a compatible gateway.
3. Read the failure text in chat — 1.3.3 surfaces the HTTP status and provider error body instead of a generic message.
