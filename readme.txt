=== AI Provider for Google ===
Contributors: wordpressdotorg
Tags: ai, google, gemini, artificial-intelligence, connector
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google AI (Gemini) provider for the PHP AI Client SDK.

== Description ==

This plugin provides Google AI (Gemini) integration for the PHP AI Client SDK. It enables WordPress sites to use Google's Gemini models for text generation, image generation, and other AI capabilities.

**Features:**

* Text generation with Gemini models
* Image generation with Imagen models
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
