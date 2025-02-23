# Multi-LLM Chatbot for WordPress

A WordPress plugin that integrates multiple Language Learning Models (LLMs) into a customizable chatbot interface.

## Features

- Support for multiple LLM providers:
  - OpenAI (GPT-4 Turbo, both standard and Assistant API)
  - Anthropic Claude 3
  - Perplexity
  - Google Gemini
  - Mistral (both standard and Agent API*)
- Markdown support for rich text responses
- Streaming responses for real-time interaction
- Context-aware conversations using page content
- Conversation history support
- Admin-only mode option

> *Note: The Mistral Agent API currently has limited support for conversation history. For full conversation history support, use the standard Mistral API instead.

## Installation

1. Download the plugin zip file
2. Go to WordPress admin → Plugins → Add New → Upload Plugin
3. Upload the zip file and click "Install Now"
4. Activate the plugin
5. Go to "Chatbot Settings" in the WordPress admin menu

## Provider Setup

### OpenAI
1. Visit [OpenAI API Keys](https://platform.openai.com/api-keys)
2. Create a new API key
3. Copy the key (starts with "sk-")
4. Paste it in the plugin settings

Optional: To use OpenAI Assistant
1. Go to [OpenAI Assistants](https://platform.openai.com/assistants)
2. Create a new assistant
3. Copy the Assistant ID (starts with "asst_")
4. Enable "Use Assistant API" in plugin settings
5. Paste the Assistant ID

### Anthropic (Claude)
1. Visit [Anthropic Console](https://console.anthropic.com/)
2. Create an account if needed
3. Go to API Keys section
4. Create a new API key
5. Copy the key (starts with "sk-ant-")
6. Paste it in the plugin settings

### Perplexity
1. Visit [Perplexity API](https://www.perplexity.ai/settings/api)
2. Create an account if needed
3. Generate new API key
4. Copy the key (starts with "pplx-")
5. Paste it in the plugin settings

### Google Gemini
1. Visit [Google AI Studio](https://makersuite.google.com/app/apikey)
2. Create or select a project
3. Enable the Gemini API
4. Create API key
5. Copy the key (starts with "AIzaSy")
6. Paste it in the plugin settings

### Mistral
1. Visit [Mistral Platform](https://console.mistral.ai/)
2. Create an account if needed
3. Go to API Keys section
4. Generate new API key
5. Copy the key
6. Paste it in the plugin settings

Optional: To use Mistral Agent
1. Go to [Mistral Agents](https://console.mistral.ai/agents/)
2. Create a new agent
3. Copy the Agent ID (starts with "ag:")
4. Enable "Use Agent API" in plugin settings
5. Paste the Agent ID

## Configuration

1. Select your preferred provider
2. Enter the corresponding API key
3. For OpenAI and Mistral, optionally enable Assistant/Agent mode
4. Add custom instructions to define the chatbot's behavior
5. Optionally enable "Admin only" mode to restrict access

## ⚠️ Warning

The following integrations have not been tested yet:
- Claude (Anthropic)
- Google Gemini
- Perplexity

Please report any issues on the plugin's GitHub repository.

## Support

For issues and feature requests, please use the GitHub repository's issue tracker.

## License

Apache License 2.0. See [LICENSE.md](LICENSE.md) for details.