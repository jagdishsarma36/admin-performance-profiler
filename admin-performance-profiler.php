<?php
/**
 * Plugin Name: Admin Performance Profiler
 * Plugin URI: https://github.com/jagdishsarma36/admin-performance-profiler
 * Description: Real-time execution time, memory usage, query counter, milestone breakdown, and slow plugin detector for WordPress admin.
 * Version: 1.1.1
 * Author: Jagdish Sarma
 * Author URI: https://github.com/jagdishsarma36
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: admin-performance-profiler
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/jagdishsarma36/admin-performance-profiler
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ADMIN_PERF_PROF_VERSION', '1.1.1');
define('ADMIN_PERF_PROF_PLUGIN_FILE', __FILE__);
define('ADMIN_PERF_PROF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADMIN_PERF_PROF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADMIN_PERF_PROF_TEXT_DOMAIN', 'admin-performance-profiler');
define('ADMIN_PERF_PROF_GITHUB_REPO', 'jagdishsarma36/admin-performance-profiler');

/**
 * Load plugin text domain for translations
 */
function admin_perf_prof_load_textdomain() {
    load_plugin_textdomain(
        ADMIN_PERF_PROF_TEXT_DOMAIN,
        false,
        dirname(plugin_basename(ADMIN_PERF_PROF_PLUGIN_FILE)) . '/languages'
    );
}
add_action('init', 'admin_perf_prof_load_textdomain');

/**
 * Include required files
 */
require_once ADMIN_PERF_PROF_PLUGIN_DIR . 'includes/class-admin-perf-profiler.php';
require_once ADMIN_PERF_PROF_PLUGIN_DIR . 'includes/class-admin-perf-profiler-admin.php';

// Include GitHub updater if not in development mode
if (!defined('ADMIN_PERF_PROF_DEV_MODE') || !ADMIN_PERF_PROF_DEV_MODE) {
    require_once ADMIN_PERF_PROF_PLUGIN_DIR . 'includes/class-github-plugin-updater.php';
}

/**
 * Initialize the plugin
 */
function admin_perf_prof_init() {
    // Initialize the main profiler class
    Admin_Perf_Profiler::get_instance();

    // Initialize admin interface
    if (is_admin()) {
        Admin_Perf_Profiler_Admin::get_instance();
    }

    // Initialize GitHub updater
    if (class_exists('Admin_Perf_Profiler_GitHub_Updater')) {
        Admin_Perf_Profiler_GitHub_Updater::get_instance();
    }
}
add_action('plugins_loaded', 'admin_perf_prof_init', 5);

/**
 * Activation hook
 */
function admin_perf_prof_activate() {
    // Set default options
    add_option('admin_perf_prof_enabled', true);
    add_option('admin_perf_prof_show_badge', true);
    add_option('admin_perf_prof_slow_threshold', 0.2);
    add_option('admin_perf_prof_warning_threshold', 1.0);
    add_option('admin_perf_prof_critical_threshold', 3.0);

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(ADMIN_PERF_PROF_PLUGIN_FILE, 'admin_perf_prof_activate');

/**
 * Deactivation hook
 */
function admin_perf_prof_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(ADMIN_PERF_PROF_PLUGIN_FILE, 'admin_perf_prof_deactivate');

/**
 * Uninstall hook
 */
function admin_perf_prof_uninstall() {
    // Check if uninstall is called from WordPress
    if (!current_user_can('activate_plugins')) {
        return;
    }

    // Delete plugin options
    delete_option('admin_perf_prof_enabled');
    delete_option('admin_perf_prof_show_badge');
    delete_option('admin_perf_prof_slow_threshold');
    delete_option('admin_perf_prof_warning_threshold');
    delete_option('admin_perf_prof_critical_threshold');

    // Delete transient data
    delete_transient('admin_perf_prof_update_check');
}
register_uninstall_hook(ADMIN_PERF_PROF_PLUGIN_FILE, 'admin_perf_prof_uninstall');