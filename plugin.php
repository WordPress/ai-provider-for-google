<?php

/**
 * Plugin Name: AI Provider for Google
 * Plugin URI: https://github.com/WordPress/ai-provider-for-google
 * Description: AI Provider for Google for the WordPress AI Client.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 1.1.0
 * Author: WordPress AI Team
 * Author URI: https://make.wordpress.org/ai/
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-google
 *
 * @package WordPress\GoogleAiProvider
 */

declare(strict_types=1);

namespace WordPress\GoogleAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\GoogleAiProvider\Provider\GoogleProvider;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Registers the AI Provider for Google with the AI Client.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(GoogleProvider::class)) {
        return;
    }

    $registry->registerProvider(GoogleProvider::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);
