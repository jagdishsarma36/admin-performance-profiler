<?php
/**
 * Admin Performance Profiler Admin Class
 *
 * @package Admin_Perf_Profiler
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Performance Profiler Admin
 *
 * Handles admin settings page and related functionality.
 */
class Admin_Perf_Profiler_Admin {

    /**
     * Plugin instance
     *
     * @var Admin_Perf_Profiler_Admin|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Admin_Perf_Profiler_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Initialize hooks
     */
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_options_page(
            esc_html__('Admin Performance Profiler', 'admin-performance-profiler'),
            esc_html__('Admin Perf Profiler', 'admin-performance-profiler'),
            'manage_options',
            'admin-performance-profiler',
            [$this, 'render_settings_page'],
            'dashicons-chart-line',
            80
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'admin_perf_prof_options',
            'admin_perf_prof_enabled',
            [
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        );

        register_setting(
            'admin_perf_prof_options',
            'admin_perf_prof_show_badge',
            [
                'type'              => 'boolean',
                'default'           => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
            ]
        );

        register_setting(
            'admin_perf_prof_options',
            'admin_perf_prof_slow_threshold',
            [
                'type'              => 'number',
                'default'           => 0.2,
                'sanitize_callback' => [$this, 'sanitize_positive_float'],
            ]
        );

        register_setting(
            'admin_perf_prof_options',
            'admin_perf_prof_warning_threshold',
            [
                'type'              => 'number',
                'default'           => 1.0,
                'sanitize_callback' => [$this, 'sanitize_positive_float'],
            ]
        );

        register_setting(
            'admin_perf_prof_options',
            'admin_perf_prof_critical_threshold',
            [
                'type'              => 'number',
                'default'           => 3.0,
                'sanitize_callback' => [$this, 'sanitize_positive_float'],
            ]
        );

        // Add settings sections
        add_settings_section(
            'admin_perf_prof_general',
            esc_html__('General Settings', 'admin-performance-profiler'),
            [$this, 'render_general_section'],
            'admin_perf_prof_options'
        );

        add_settings_section(
            'admin_perf_prof_thresholds',
            esc_html__('Performance Thresholds', 'admin-performance-profiler'),
            [$this, 'render_thresholds_section'],
            'admin_perf_prof_options'
        );

        add_settings_section(
            'admin_perf_prof_about',
            esc_html__('About', 'admin-performance-profiler'),
            [$this, 'render_about_section'],
            'admin_perf_prof_options'
        );

        // Add settings fields
        add_settings_field(
            'admin_perf_prof_enabled',
            esc_html__('Enable Profiler', 'admin-performance-profiler'),
            [$this, 'render_enabled_field'],
            'admin_perf_prof_options',
            'admin_perf_prof_general'
        );

        add_settings_field(
            'admin_perf_prof_show_badge',
            esc_html__('Show Interactive Badge', 'admin-performance-profiler'),
            [$this, 'render_show_badge_field'],
            'admin_perf_prof_options',
            'admin_perf_prof_general'
        );

        add_settings_field(
            'admin_perf_prof_slow_threshold',
            esc_html__('Slow Plugin Threshold (seconds)', 'admin-performance-profiler'),
            [$this, 'render_slow_threshold_field'],
            'admin_perf_prof_options',
            'admin_perf_prof_thresholds'
        );

        add_settings_field(
            'admin_perf_prof_warning_threshold',
            esc_html__('Warning Threshold (seconds)', 'admin-performance-profiler'),
            [$this, 'render_warning_threshold_field'],
            'admin_perf_prof_options',
            'admin_perf_prof_thresholds'
        );

        add_settings_field(
            'admin_perf_prof_critical_threshold',
            esc_html__('Critical Threshold (seconds)', 'admin-performance-profiler'),
            [$this, 'render_critical_threshold_field'],
            'admin_perf_prof_options',
            'admin_perf_prof_thresholds'
        );
    }

    /**
     * Sanitize positive float
     *
     * @param mixed $value Input value.
     * @return float
     */
    public function sanitize_positive_float($value) {
        $float = floatval($value);
        return max(0.001, $float);
    }

    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure the Admin Performance Profiler behavior.', 'admin-performance-profiler') . '</p>';
    }

    /**
     * Render thresholds section description
     */
    public function render_thresholds_section() {
        echo '<p>' . esc_html__('Set performance thresholds for color-coded badges and slow plugin detection.', 'admin-performance-profiler') . '</p>';
    }

    /**
     * Render about section description
     */
    public function render_about_section() {
        printf(
            '<p>%s</p>',
            sprintf(
                esc_html__('Admin Performance Profiler v%s by %s. %s', 'admin-performance-profiler'),
                ADMIN_PERF_PROF_VERSION,
                '<a href="https://github.com/jagdishsarma36" target="_blank" rel="noopener">Jagdish Sarma</a>',
                esc_html__('View source on GitHub.', 'admin-performance-profiler')
            )
        );
    }

    /**
     * Render enabled field
     */
    public function render_enabled_field() {
        $value = get_option('admin_perf_prof_enabled', true);
        ?>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="admin_perf_prof_enabled" value="1" <?php checked($value, true); ?>>
            <span><?php echo esc_html__('Enable performance profiling for administrators', 'admin-performance-profiler'); ?></span>
        </label>
        <p class="description"><?php echo esc_html__('When disabled, no profiling data will be collected or displayed.', 'admin-performance-profiler'); ?></p>
        <?php
    }

    /**
     * Render show badge field
     */
    public function render_show_badge_field() {
        $value = get_option('admin_perf_prof_show_badge', true);
        ?>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="admin_perf_prof_show_badge" value="1" <?php checked($value, true); ?>>
            <span><?php echo esc_html__('Display interactive badge in admin footer', 'admin-performance-profiler'); ?></span>
        </label>
        <p class="description"><?php echo esc_html__('When disabled, performance data will still be available in HTML source comments.', 'admin-performance-profiler'); ?></p>
        <?php
    }

    /**
     * Render slow threshold field
     */
    public function render_slow_threshold_field() {
        $value = get_option('admin_perf_prof_slow_threshold', 0.2);
        ?>
        <input type="number" name="admin_perf_prof_slow_threshold" value="<?php echo esc_attr($value); ?>" step="0.01" min="0.01" max="10" style="width:80px;">
        <p class="description"><?php echo esc_html__('Plugins loading slower than this threshold will be flagged as slow.', 'admin-performance-profiler'); ?></p>
        <?php
    }

    /**
     * Render warning threshold field
     */
    public function render_warning_threshold_field() {
        $value = get_option('admin_perf_prof_warning_threshold', 1.0);
        ?>
        <input type="number" name="admin_perf_prof_warning_threshold" value="<?php echo esc_attr($value); ?>" step="0.1" min="0.1" max="30" style="width:80px;">
        <p class="description"><?php echo esc_html__('Total execution time above this shows a warning (orange) badge.', 'admin-performance-profiler'); ?></p>
        <?php
    }

    /**
     * Render critical threshold field
     */
    public function render_critical_threshold_field() {
        $value = get_option('admin_perf_prof_critical_threshold', 3.0);
        ?>
        <input type="number" name="admin_perf_prof_critical_threshold" value="<?php echo esc_attr($value); ?>" step="0.1" min="0.1" max="60" style="width:80px;">
        <p class="description"><?php echo esc_html__('Total execution time above this shows a critical (red) badge.', 'admin-performance-profiler'); ?></p>
        <?php
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets($hook) {
        if ('settings_page_admin-performance-profiler' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'admin-perf-prof-admin',
            ADMIN_PERF_PROF_PLUGIN_URL . 'assets/admin.css',
            [],
            ADMIN_PERF_PROF_VERSION
        );

        wp_enqueue_script(
            'admin-perf-prof-admin',
            ADMIN_PERF_PROF_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            ADMIN_PERF_PROF_VERSION,
            true
        );

        // Localize script with translation strings and current performance data
        $profiler = Admin_Perf_Profiler::get_instance();
        $data = $profiler->get_data();

        wp_localize_script('admin-perf-prof-admin', 'adminPerfProfilerL10n', [
            'noData'        => esc_html__('Performance data not available on settings page.', 'admin-performance-profiler'),
            'totalTime'     => esc_html__('Total Time', 'admin-performance-profiler'),
            'queries'       => esc_html__('Queries', 'admin-performance-profiler'),
            'memory'        => esc_html__('Memory', 'admin-performance-profiler'),
            'httpRequests'  => esc_html__('HTTP Requests', 'admin-performance-profiler'),
            'good'          => esc_html__('Good', 'admin-performance-profiler'),
            'warning'       => esc_html__('Warning', 'admin-performance-profiler'),
            'critical'      => esc_html__('Critical', 'admin-performance-profiler'),
        ]);

        // Pass performance data to JavaScript
        wp_add_inline_script('admin-perf-prof-admin', 'window.adminPerfProfilerData = ' . wp_json_encode($data) . ';', 'before');
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap admin-perf-prof-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form action="options.php" method="post" novalidate="novalidate">
                <?php
                settings_fields('admin_perf_prof_options');
                do_settings_sections('admin_perf_prof_options');
                submit_button();
                ?>
            </form>

            <!-- Current Performance Data Preview -->
            <div class="admin-perf-prof-preview" style="margin-top:30px;padding:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,0.04);">
                <h2><?php echo esc_html__('Current Page Performance', 'admin-performance-profiler'); ?></h2>
                <div id="admin-perf-prof-preview-data">
                    <p><?php echo esc_html__('Performance data will appear here after the page loads.', 'admin-performance-profiler'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
}