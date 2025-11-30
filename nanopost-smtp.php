<?php
/**
 * Plugin Name: nanoPost SMTP
 * Plugin URI: https://nanopo.st
 * Description: Zero-config email delivery for WordPress
 * Version: 0.6.0
 * Author: nanoPost
 * Author URI: https://nanopo.st
 * Text Domain: nanopost-smtp
 */

defined('ABSPATH') || exit;

// Plugin constants
define('NANOPOST_VERSION', '0.6.0');
define('NANOPOST_API_BASE', 'https://api-master-ja5zao.laravel.cloud/api');
define('NANOPOST_PLUGIN_DIR', __DIR__);

// Load core functionality
require_once __DIR__ . '/includes/rest-api.php';
require_once __DIR__ . '/includes/registration.php';
require_once __DIR__ . '/includes/mail-handler.php';
require_once __DIR__ . '/includes/admin-page.php';

// Load WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/cli.php';
}

/**
 * Debug logging - only logs when debug mode is enabled.
 */
function nanopost_debug($message) {
    if (get_option('nanopost_debug_mode', false)) {
        error_log('nanoPost: ' . $message);
    }
}

/**
 * Plugin activation hook.
 */
register_activation_hook(__FILE__, 'nanopost_activate');
