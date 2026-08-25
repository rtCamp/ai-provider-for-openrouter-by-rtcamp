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

This plugin provides OpenRouter integration for the WordPress AI Client. It allows you to use OpenRouter's text and image generation capabilities directly from WordPress, with configurable model defaults and seamless image generation support.

**Features:**

* OpenRouter provider registration for WordPress AI Client.
* Configure different default models for text and image generation.
* The Modal selection also displays the cost per 1M tokens or images for the selected model.
* Text generation using OpenRouter's chat completions endpoint, supporting all chat modalities
* Image generation using OpenRouter's chat completions image modality.

== Installation ==
1. Upload plugin files to `/wp-content/plugins/ai-provider-for-openrouter-by-rtcamp/`.
2. Activate **AI Provider for OpenRouter by rtCamp** through the Plugins menu in WordPress.
3. Add your OpenRouter API key in **Settings > Connectors**.
4. Configure defaults in **Settings > OpenRouter Settings**.

== Screenshots ==
1. Connector settings page showing OpenRouter API key configuration.
2. OpenRouter settings page showing model selection and cost information.
3. Example of generating post excerpt using OpenRouter in the WordPress editor.
4. Example of generating an image using OpenRouter in the WordPress editor.

== Frequently Asked Questions ==

= Where do I set the OpenRouter API key? =

Set it in **Settings > Connectors** for the OpenRouter connector.

= Where do I choose the text and image models? =

Use **Settings > OpenRouter Settings**.

= How much does it cost to generate text and images? =

The cost depends on the models you choose. The settings page shows the cost per 1M tokens for text models and per image for image models.

= Which OpenRouter are selected by default? =

The plugin defaults to `openrouter/free` for text and `openrouter/auto` for images as there are no free options available, but you can change this in the settings.

== Source Code ==

The source code is available on <a href="https://github.com/rtCamp/ai-provider-for-openrouter-by-rtcamp">GitHub</a>.

== Changelog ==

= 1.1.1 =
* Renamed the plugin to "AI Provider for OpenRouter by rtCamp" for better clarity and branding.
* Updated the plugin description to reflect the new name and provide a clearer overview of its functionality.
* Updated slug, constants, and text domain to match the new plugin name for consistency across the codebase.

= 1.1.0 =
* Updated overall settings page UI for better user experience and added tooltips for each setting to provide more context and guidance to users.
* Added badges to indicate the different cost of each type of requsest such as input, output, image read, web search, cache read, and cache write to help users make informed decisions when selecting models and generating content.
* Refactored code to convert plugins js files to TypeScript for better maintainability and type safety.
* Updated dependencies to ensure compatibility with the latest WordPress and AI plugin versions.

= 1.0.0 =

* Initial release of the OpenRouter provider plugin.
* Added text and image model settings.
* Added OpenRouter image generation support via chat completions image modality.

== Upgrade Notice ==
= 1.1.0 =
Updated overall settings page UI, added tooltips, badges for cost indication, and refactored code to TypeScript.