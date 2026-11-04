# rtCamp’s AI Provider for OpenRouter - Access Hundreds of AI Models in WordPress

**Contributors:** [rtCamp](https://profiles.wordpress.org/rtcamp/), [milindmore22](https://profiles.wordpress.org/milindmore22), [vishal4669](https://profiles.wordpress.org/vishal4669/), [aishwarryapande](https://profiles.wordpress.org/aishwarryapande/), [aviralmittal89](https://profiles.wordpress.org/aviralmittal89/)

**Tags:** WordPress, AI, OpenRouter, Text Generation, Image Generation, Text to Speech, TTS, AI Models, ChatGPT, Claude, Gemini

This plugin is licensed under the GPL v2 or later.

## Overview

rtCamp’s AI Provider for OpenRouter is a WordPress plugin that registers OpenRouter as an AI provider for the WordPress AI Client. It gives you access to hundreds of AI models for text, image, and speech generation through a single, unified API — configurable directly from your WordPress admin.

## Description

**rtCamp’s AI Provider for OpenRouter** bridges the WordPress AI Client with OpenRouter's model marketplace, allowing you to:

* **Access hundreds of AI models** including GPT-4, Claude, Gemini, and more through a single API key
* **Generate text** using any OpenRouter-supported language model
* **Generate images** using OpenRouter-supported image models
* **Synthesize speech (TTS)** using OpenRouter-supported speech models with custom voice selection
* **Configure default models** for text, image, and text-to-speech generation from WordPress admin settings
* **Switch models** without changing any code — just update your admin settings

This makes it easy to experiment with and deploy different AI models on your WordPress site without being locked into a single provider.

## Why rtCamp’s AI Provider for OpenRouter?

Managing multiple AI providers in WordPress typically requires separate plugins, multiple API keys, and custom integrations for each model. This leads to:

- **Vendor lock-in** with individual AI providers
- **Integration overhead** when switching or adding new models
- **Duplicate configuration** scattered across multiple plugins
- **Maintenance burden** as each provider's API evolves independently

rtCamp’s AI Provider for OpenRouter solves this by:

- **Unified Access:** One API key, hundreds of models from leading AI providers
- **Standardized Interface:** All models exposed through the WordPress AI Client's consistent API
- **Flexible Configuration:** Switch models from admin settings — no code changes required
- **Separation of Concerns:** Provider logic isolated from your application code

### Key Benefits

- **Model freedom:** Not locked into a single AI vendor — swap models anytime
- **Cost efficiency:** Route requests to the most cost-effective model for each task
- **Future-proof:** New models added to OpenRouter become immediately available
- **Simple setup:** One API key and intuitive settings fields to get started

## Features

### Core Functionality

- **Text Generation:** Text generation support via OpenRouter's chat completions endpoint
- **Image Generation:** Image generation via OpenRouter's chat completions image modality
- **Text to Speech (TTS):** Speech synthesis via OpenRouter's audio speech endpoint (`/api/v1/audio/speech`)
- **Voice Selection:** Dynamic voice dropdown populated from each TTS model's supported voices
- **Model Discovery:** Settings autocomplete backed by live model discovery
- **Multi-Model Config:** Separate default model settings for text, image, and text-to-speech generation
- **Smart Exposure:** If multiple settings point to the same model, it is exposed once with combined capabilities
- **Environment Overrides:** `OPENROUTER_BASE_URL` and `RTCAMPS_AI_PROVIDER_FOR_OPENROUTER_API_KEY` constants for advanced deployments

### API Integration & External Services

The plugin communicates with OpenRouter's API provided by OpenRouter, Inc. at `https://openrouter.ai/api/v1`:

| Endpoint | Purpose | When Data is Sent |
|---|---|---|
| `GET /models` | Populate text model autocomplete in settings | Admin views Settings or queries REST models endpoint |
| `GET /models?output_modality=image` | Populate image model autocomplete in settings | Admin views Settings or queries REST image-models endpoint |
| `GET /models?output_modalities=speech` | Populate TTS model autocomplete and voices in settings | Admin views Settings or queries REST tts-models endpoint |
| `POST /chat/completions` | Text generation requests | User explicitly initiates text generation via AI Client |
| `POST /chat/completions` + `modalities: ["image"]` | Image generation requests | User explicitly initiates image generation via AI Client |
| `POST /audio/speech` | Text-to-speech conversion requests | User explicitly initiates speech synthesis via AI Client |

For more information, please review the [OpenRouter Terms of Service](https://openrouter.ai/terms) and [OpenRouter Privacy Policy](https://openrouter.ai/privacy).

### Limitations

- Only models available through OpenRouter are supported; models exclusive to other providers require separate plugins.
- Image generation and speech synthesis depend on OpenRouter's supported models — availability and pricing may vary.
- The plugin exposes the models configured in settings (text, image, and speech); dynamic per-request model selection is not supported at this time.

## System Requirements

- **WordPress:** 7.0 or higher
- **Requires at least:** 7.0
- **Tested up to:** 7.1
- **Stable tag:** 1.1.1
- **PHP:** 7.4 or higher
- **Requires PHP:** 7.4
- **Required Plugin:** [WordPress AI Client](https://wordpress.org/plugins/ai/) (`ai`) must be active

## Installation & Setup

### As a WordPress Plugin

1. Ensure the **WordPress AI** plugin (`ai`) is installed and activated.
2. Download or clone this plugin into `wp-content/plugins/rtcamps-ai-provider-for-openrouter`.
3. Activate **rtCamp’s AI Provider for OpenRouter** from the Plugins screen.
4. Add your OpenRouter API key in **Settings > Connectors**.
5. Configure your default models in **Settings > OpenRouter Settings**.

### As a Composer Package

```bash
composer require rtcamp/rtcamps-ai-provider-for-openrouter
```

## Usage Guide

### Accessing the Settings

Navigate to **Settings > OpenRouter Settings** in your WordPress admin to configure rtCamp’s AI Provider for OpenRouter.

### Configuring rtCamp’s AI Provider for OpenRouter

#### Setting Up Your API Key

1. Obtain an API key from [OpenRouter](https://openrouter.ai/).
2. In WordPress admin, go to **Settings > Connectors**.
3. Enter your OpenRouter API key and save.

![Screenshot-1](./wp-assets/screenshot-1.png)

#### Selecting Default Models

1. Navigate to **Settings > OpenRouter Settings**.
2. Select your **Default Model** for text generation (autocompleted from live OpenRouter model list).
3. Select your **Image Generation Model** for image generation tasks.
4. Select your **Default Text to Speech (TTS) Model** for speech generation tasks.
5. Select your preferred **Voice** (dynamically populated based on the chosen TTS model's supported voices).
6. Save your settings.

![Screenshot-2](./wp-assets/screenshot-2.png)

### Text Generation
Text generation requests are routed through OpenRouter's chat completions endpoint:

```json
{
	"model": "your-text-model",
	"messages": [
		{
			"role": "user",
			"content": "Write a short poem about WordPress plugins."
		}
	]
}
```
#### Supported WordPress AI Capabilities
You can use OpenRouter for any WordPress AI Client feature that supports text generation, such as:
- Title generation
- Excerpt generation
- Content Summarization

![Screenshot-3](./wp-assets/screenshot-3.png)

### Image Generation

Image generation is routed through OpenRouter chat completions with the image modality:

```json
{
    "model": "your-image-model",
    "messages": [
        {
            "role": "user",
            "content": "A serene mountain landscape at sunrise"
        }
    ],
    "modalities": ["image"]
}
```
#### Supported WordPress AI Capabilities
Use OpenRouter for any AI Client feature that supports image generation, such as:
- Featured image generation
- Media library image generation

![Screenshot-4](./wp-assets/screenshot-4.png)

### Text to Speech (TTS) Generation

Text-to-speech generation requests are routed through OpenRouter's audio speech endpoint (`/audio/speech`):

```json
{
    "model": "heygen/heygen-voice-1-turbo:free",
    "input": "Welcome to our WordPress website.",
    "voice": "nadine",
    "response_format": "mp3"
}
```

#### Supported WordPress AI Capabilities
Use OpenRouter for any AI Client feature that supports text-to-speech conversion or audio generation, such as:
- Generating audio narrations for posts and pages
- Accessible audio descriptions and read-aloud capabilities

### Environment Overrides

For advanced deployments, you can override defaults using PHP constants or environment variables:

- `OPENROUTER_BASE_URL` — Override the API base URL (default: `https://openrouter.ai/api/v1`)
- `RTCAMPS_AI_PROVIDER_FOR_OPENROUTER_API_KEY` — Inject an API key directly when the Connectors registry is not yet initialized

### Developer Filters

The plugin provides filter hooks allowing developers to inspect or modify request payloads before they are dispatched to OpenRouter:

#### Text Generation Parameters Filter

```php
add_filter(
    'rtcamps_ai_provider_for_openrouter_text_generation_params',
    function( array $params ): array {
        // Customize text generation parameters (e.g. temperature, max_tokens, etc.)
        return $params;
    }
);
```

#### Image Generation Parameters Filter

```php
add_filter(
    'rtcamps_ai_provider_for_openrouter_image_generation_params',
    function( array $params ): array {
        // Customize image generation parameters
        return $params;
    }
);
```

#### Text to Speech Generation Parameters Filter

```php
add_filter(
    'rtcamps_ai_provider_for_openrouter_tts_params',
    function( array $params ): array {
        // Customize speech generation parameters (e.g. speed, voice, response_format)
        return $params;
    }
);
```

## Development & Contributing

rtCamp’s AI Provider for OpenRouter is actively developed and maintained by [rtCamp](https://rtcamp.com/).

- **Repository:** [https://github.com/rtcamp/rtcamps-ai-provider-for-openrouter](https://github.com/rtcamp/rtcamps-ai-provider-for-openrouter)

We welcome contributions! Please open an issue or pull request on GitHub.

### PHP

```bash
composer run-script lint
composer run-script format
composer run-script phpstan
```

### JavaScript

```bash
npm run lint:js
npm run lint:js:fix
```

### Combined Lint

```bash
npm run lint
```

### Build Release Zip

```bash
npm run plugin-zip
```

This creates `rtcamps-ai-provider-for-openrouter.zip` in the plugin root, excluding all development-only files.

## Frequently Asked Questions

### What is OpenRouter?

[OpenRouter](https://openrouter.ai/) is a unified API gateway that provides access to hundreds of AI models from providers like OpenAI, Anthropic, Google, Meta, and many others through a single, OpenAI-compatible API.

### Do I need separate API keys for each AI model?

No. You only need a single OpenRouter API key. OpenRouter handles routing your requests to the appropriate underlying model provider.

### Does this plugin require the WordPress AI Client plugin?

Yes. The **WordPress AI** plugin (`ai`) must be installed and activated. This plugin registers OpenRouter as a provider within that framework.

### Can I use text, image, and text-to-speech generation?

Yes. Configure your choices in **Settings > OpenRouter Settings**:
- **Default Model** for text generation
- **Image Generation Model** for image generation
- **Default Text to Speech (TTS) Model** and **Voice** for speech synthesis

### Can I use the same model for both text and image generation?

Yes. If you set multiple fields to the same model ID, the plugin registers that model once with combined capabilities.

### What happens if I don't configure an API key?

The connector will be registered but inactive. AI Client requests routed to OpenRouter will fail until valid credentials are set in **Settings > Connectors**.

### Which models are selected by default?

The plugin defaults to `openrouter/free` for text generation, `openrouter/auto` for image generation, and `heygen/heygen-voice-1-turbo:free` for speech synthesis, all of which can be customized in **Settings > OpenRouter Settings**.

### How do voice options work for text to speech?

When you select a TTS model in **Settings > OpenRouter Settings**, the plugin retrieves that model's supported voice options directly from OpenRouter's model catalog and populates the Voice dropdown. You can pick any supported voice for the model, or use the default voice.

### How do I switch to a different AI model?

Update the **Default Model**, **Image Generation Model**, or **Default Text to Speech (TTS) Model** fields in **Settings > OpenRouter Settings**. No code changes are required.

### Does this affect front-end performance?

No. All AI provider logic runs server-side and only when AI Client requests are made. There is no front-end JavaScript impact from this plugin.

### Is multisite supported?

The plugin can be network-activated on multisite. Each site's settings are managed independently via their own **Settings > OpenRouter Settings** page.

## Troubleshooting

### No models appearing in the settings dropdowns

- Confirm your WordPress host allows outbound HTTPS requests to `https://openrouter.ai`.
- Ensure the **WordPress AI** plugin (`ai`) is installed and active.
- Check the browser network tab to ensure the REST API endpoints (`/wp-json/rtcamps-ai-provider-for-openrouter/v1/models`, `/wp-json/rtcamps-ai-provider-for-openrouter/v1/image-models`, `/wp-json/rtcamps-ai-provider-for-openrouter/v1/tts-models`) return a 200 status code.
- If OpenRouter's public endpoint was temporarily slow or unavailable, wait a few minutes and reload (results are cached in WordPress transients for 1 hour).

### Text, image, or speech generation failing

- Verify your OpenRouter API key is saved in **Settings > Connectors** and has sufficient credit or quota on [openrouter.ai](https://openrouter.ai/).
- Ensure the selected model exists and is available in your OpenRouter account tier.
- Check the browser console and PHP error log for detailed error messages.
- Verify the `OPENROUTER_BASE_URL` constant (if set) points to the correct endpoint.

### Settings not saving

- Check file permissions and database write access.
- Look for conflicting plugins that may be overriding the settings page.

### Common Issues

- **Model not found errors:** The selected model may have been deprecated or renamed by OpenRouter — re-select a model from the settings dropdown.
- **Authentication errors:** Double-check the API key in **Settings > Connectors** and ensure it is not expired.
- **Image generation returning no output:** Confirm the selected image model supports image generation via `modalities: ["image"]`.
- **Speech generation errors:** Confirm the selected TTS model supports the speech modality and the specified voice is supported by that model.

## Support & Community

- **Issues & Bug Reports:** [GitHub Issues](https://github.com/rtcamp/rtcamps-ai-provider-for-openrouter/issues)
- **Source Code:** [GitHub Repository](https://github.com/rtcamp/rtcamps-ai-provider-for-openrouter)

## License

This project is licensed under the GPL v2 or later — see the [GNU General Public License v2](https://www.gnu.org/licenses/gpl-2.0.html) for details.

---

**Made with ❤️ by [rtCamp](https://rtcamp.com/)**
