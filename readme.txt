=== AI Provider for OpenRouter by rtCamp ===
Contributors:      rtcamp, milindmore22, vishal4669, aishwarryapande, aviralmittal89
Tags:              ai, openrouter, llm, connector, image-generation
Requires at least: 7.0
Requires PHP:      7.4
Stable tag:        1.1.1
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tested up to:      7.1

OpenRouter connector for the WordPress AI Client.

== Description ==

AI Provider for OpenRouter by rtCamp registers OpenRouter as an AI provider for the WordPress AI Client. It enables WordPress to access hundreds of AI models for text and image generation (including OpenAI GPT, Anthropic Claude, Google Gemini, and open-source models) through a single unified API and API key.

**Requirements:**

* **WordPress AI Client**: This plugin requires the [WordPress AI Client](https://wordpress.org/plugins/ai/) (`ai`) plugin (or core AI Client features in WordPress 7.0+) to be installed and active.
* **OpenRouter Account & API Key**: An API key from [OpenRouter](https://openrouter.ai/) is required to connect to models.

**Features:**

* OpenRouter provider registration for the WordPress AI Client.
* Configure different default models for text and image generation.
* Model selection displays live pricing (cost per 1M tokens or per image generation).
* Text generation using OpenRouter's chat completions endpoint, supporting all chat modalities.
* Image generation using OpenRouter's chat completions image modality.
* Autocomplete model selection dynamically populated from OpenRouter's model catalog.

== External services ==

This plugin connects to third-party services provided by OpenRouter, Inc. to retrieve available AI model listings and process AI generation requests.

* **Model Catalog Discovery:**
  * **What it is used for:** Fetching available text and image models and their pricing metadata for the settings screen.
  * **What data is sent and when:** An HTTP GET request is sent to OpenRouter (`https://openrouter.ai/api/v1/models`) when an administrator visits the OpenRouter Settings page or queries the plugin's REST API endpoints (`/wp-json/ai-openrouter/v1/models` and `/wp-json/ai-openrouter/v1/image-models`). No personal data, site data, or API keys are transmitted during this request. Retrieved models are cached locally in WordPress transients.
* **Content & Image Generation:**
  * **What it is used for:** Generating text completions and images requested through the WordPress AI Client.
  * **What data is sent and when:** Prompts, context text/inputs, and the configured OpenRouter API key (sent in the HTTP Authorization header) are sent via HTTPS POST to OpenRouter (`https://openrouter.ai/api/v1/chat/completions`) only when a user explicitly initiates an AI generation action.

This service is provided by OpenRouter, Inc.
* OpenRouter Terms of Service: https://openrouter.ai/terms
* OpenRouter Privacy Policy: https://openrouter.ai/privacy

== Installation ==

1. Ensure the **WordPress AI Client** plugin (`ai`) is installed and activated.
2. Upload plugin files to `/wp-content/plugins/ai-provider-for-openrouter-by-rtcamp/` or install via the WordPress Plugins menu.
3. Activate **AI Provider for OpenRouter by rtCamp** through the Plugins menu in WordPress.
4. Add your OpenRouter API key in **Settings > Connectors**.
5. Configure default text and image models in **Settings > OpenRouter Settings**.

== Screenshots ==
1. Connector settings page showing OpenRouter API key configuration.
2. OpenRouter settings page showing model selection and cost information.
3. Example of generating post excerpt using OpenRouter in the WordPress editor.
4. Example of generating an image using OpenRouter in the WordPress editor.

== Frequently Asked Questions ==

= How do I get an OpenRouter API key? =

Visit [OpenRouter](https://openrouter.ai/) to create an account and generate an API key at https://openrouter.ai/settings/keys.

= Does this plugin work without the WordPress AI Client? =

No, this plugin requires the WordPress AI Client plugin (`ai` or `wordpress/php-ai-client`) to be installed and activated. It registers OpenRouter as an available provider for the WordPress AI Client.

= Where do I set the OpenRouter API key? =

Once the WordPress AI Client is installed, set your API key in **Settings > Connectors** under the OpenRouter section.

= Where do I choose the text and image models? =

Use **Settings > OpenRouter Settings** to choose default models for text and image generation.

= How much does it cost to generate text and images? =

The cost depends on the models you choose. The settings page displays the cost per 1M tokens for text models and per image for image models.

= Which OpenRouter models are selected by default? =

The plugin defaults to `openrouter/free` for text and `openrouter/auto` for images as there are no free image options available, but you can change this in the settings.

== Source Code ==

The source code is available on <a href="https://github.com/rtCamp/ai-provider-for-openrouter-by-rtcamp">GitHub</a>.

== Changelog ==

= 1.1.1 =
* Renamed the plugin to "AI Provider for OpenRouter by rtCamp" for better clarity and branding.
* Updated the plugin description to reflect the new name and provide a clearer overview of its functionality.
* Updated slug, constants, and text domain to match the new plugin name for consistency across the codebase.

= 1.1.0 =
* Updated overall settings page UI for better user experience and added tooltips for each setting to provide more context and guidance to users.
* Added badges to indicate the different cost of each type of request such as input, output, image read, web search, cache read, and cache write to help users make informed decisions when selecting models and generating content.
* Refactored code to convert plugins js files to TypeScript for better maintainability and type safety.
* Updated dependencies to ensure compatibility with the latest WordPress and AI plugin versions.

= 1.0.0 =

* Initial release of the OpenRouter provider plugin.
* Added text and image model settings.
* Added OpenRouter image generation support via chat completions image modality.

== Upgrade Notice ==
= 1.1.0 =
Updated overall settings page UI, added tooltips, badges for cost indication, and refactored code to TypeScript.