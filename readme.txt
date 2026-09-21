=== AI Provider for Google ===
Contributors:      wordpressdotorg
Tags:              ai, google, gemini, artificial-intelligence, connector
Requires at least: 6.9
Tested up to:      7.1
Stable tag:        1.2.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Google AI (Gemini) provider for the PHP AI Client SDK.

== Description ==

This plugin provides Google AI (Gemini) integration for the PHP AI Client SDK. It enables WordPress sites to use Google's Gemini models for text generation, image generation, text to speech, and other AI capabilities.

**Features:**

* Text generation with Gemini models
* Image generation with Imagen models
* Text to Speech conversion with Gemini models
* Function calling support
* Automatic provider registration

Available models are dynamically discovered from the Google AI API, including Gemini models for text generation and Imagen models for image generation.

**Requirements:**

* PHP 7.4 or higher
* For WordPress 6.9, the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package must be installed
* For WordPress 7.0 and above, no additional changes are required
* Google Gemini API key

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-google/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your Google API key via the `GOOGLE_API_KEY` environment variable or constant

== Frequently Asked Questions ==

= How do I get a Google API key? =

Visit the [Google AI Studio](https://aistudio.google.com/) to create an API key for the Gemini API.

= Does this plugin work without the PHP AI Client? =

No, this plugin requires the PHP AI Client plugin to be installed and activated. It provides the Google-specific implementation that the PHP AI Client uses.

== Changelog ==

= 1.2.0 - 2026-09-21 =

**Added**

* Support for text to speech conversion ([#31](https://github.com/WordPress/ai-provider-for-google/pull/31)).
* WordPress Playground preview blueprint that installs and activates the AI plugin and Google provider, enables AI and image generation/editing settings, logs in automatically, opens the Connectors screen, and configures Playground networking for provider requests after credentials are set ([#43](https://github.com/WordPress/ai-provider-for-google/pull/43)).

**Changed**

* Updated the supported development baseline to PHP AI Client 1.3.1 and added automated compatibility testing across PHP 7.4–8.4 with the lowest and latest supported dependencies ([#40](https://github.com/WordPress/ai-provider-for-google/pull/40)).

**Fixed**

* Google thought signature round-tripping and thought token usage compatibility for tool-calling requests ([#26](https://github.com/WordPress/ai-provider-for-google/pull/26)).
* Gemini tool-calling by preserving `thoughtSignature` values across turns so function calls remain valid in multi-step conversations ([#36](https://github.com/WordPress/ai-provider-for-google/pull/36)).
* Gemini usage parsing to use Google’s authoritative `totalTokenCount` when available, ensuring prompt tokens are included in reported totals while keeping a backward-compatible fallback for older responses ([#44](https://github.com/WordPress/ai-provider-for-google/pull/44)).

= 1.1.1 - 2026-08-17 =

**Changed**

* Bumped WordPress tested-up-to version 7.1 ([#39](https://github.com/WordPress/ai-provider-for-google/pull/39)).

**Fixed**

* Prevented PHP warning and error-log spam when processing base64-encoded images returned by Google AI ([#29](https://github.com/WordPress/ai-provider-for-google/pull/29)).

= 1.1.0 =

* Add support for aspect ratios with Gemini (multimodal) image generation ([#13](https://github.com/WordPress/ai-provider-for-google/pull/13)).
* Add a provider logo to the metadata if the ai client version > 1.3.0 ([#20](https://github.com/WordPress/ai-provider-for-google/pull/20)).
* Fix text and image multimodal support so that it properly works regardless of capability chosen ([#14](https://github.com/WordPress/ai-provider-for-google/pull/14)).
* Remove `additionalProperties` from the JSON response schema ([#18](https://github.com/WordPress/ai-provider-for-google/pull/18)).

= 1.0.3 =

* Fix critical bug that prevent use of Gemini image models because of lacking file type support annotation.

= 1.0.2 =

* Add plugin directory assets by @shaunandrews in https://github.com/WordPress/ai-provider-for-google/pull/7
* Update tags in readme.txt by @jeffpaul in https://github.com/WordPress/ai-provider-for-google/pull/9
* Fix missing input and output modality combinations, fixing usage of Nano Banana (among other problems) by @felixarntz in https://github.com/WordPress/ai-provider-for-google/pull/11
* Add provider description by @felixarntz in https://github.com/WordPress/ai-provider-for-google/pull/12

= 1.0.1 =

* Initial release of the plugin
* Support for Gemini text and image generation models
* Support for Imagen image generation models
* Function calling support

= 1.0.0 =

* Initial release of the Composer package
