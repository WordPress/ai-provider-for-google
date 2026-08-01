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

/**
 * Renders an admin notice if the required WordPress AI Client SDK is missing.
 *
 * @since 1.1.1
 *
 * @return void
 */
function render_missing_client_notice(): void
{
    if (class_exists(AiClient::class)) {
        return;
    }

    if (!function_exists('is_admin') || !is_admin()) {
        return;
    }

    if (!function_exists('current_user_can') || !current_user_can('activate_plugins')) {
        return;
    }

    $message = function_exists('__')
        ? __(
            'AI Provider for Google requires the WordPress AI Client library to be installed and active.',
            'ai-provider-for-google'
        )
        : 'AI Provider for Google requires the WordPress AI Client library to be installed and active.';

    $aria_label = function_exists('__')
        ? __('Plugin Dependency Warning', 'ai-provider-for-google')
        : 'Plugin Dependency Warning';

    if (function_exists('esc_attr') && function_exists('esc_html')) {
        printf(
            '<div class="notice notice-warning is-dismissible" role="region" aria-label="%1$s"><p>%2$s</p></div>',
            esc_attr($aria_label),
            esc_html($message)
        );
    }
}

add_action('admin_notices', __NAMESPACE__ . '\\render_missing_client_notice');
