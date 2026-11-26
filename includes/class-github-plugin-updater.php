<?php
/**
 * GitHub Plugin Updater
 *
 * Handles plugin updates from a GitHub repository.
 *
 * @package Admin_Perf_Profiler
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * GitHub Plugin Updater
 *
 * Checks for updates from GitHub and integrates with WordPress update API.
 */
class Admin_Perf_Profiler_GitHub_Updater {

    /**
     * Plugin instance
     *
     * @var Admin_Perf_Profiler_GitHub_Updater|null
     */
    private static $instance = null;

    /**
     * GitHub repository (owner/repo)
     *
     * @var string
     */
    private $github_repo = ADMIN_PERF_PROF_GITHUB_REPO;

    /**
     * Plugin slug
     *
     * @var string
     */
    private $plugin_slug = 'admin-performance-profiler';

    /**
     * Plugin file
     *
     * @var string
     */
    private $plugin_file = ADMIN_PERF_PROF_PLUGIN_FILE;

    /**
     * Current version
     *
     * @var string
     */
    private $current_version = ADMIN_PERF_PROF_VERSION;

    /**
     * Update check transient name
     *
     * @var string
     */
    private $transient_name = 'admin_perf_prof_update_check';

    /**
     * Get singleton instance
     *
     * @return Admin_Perf_Profiler_GitHub_Updater
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
        // Add filter for plugin update check
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_updates']);

        // Add filter for plugin information
        add_filter('plugins_api', [$this, 'plugin_information'], 10, 3);

        // Add filter for plugin row links
        add_filter('plugin_row_meta', [$this, 'add_github_link'], 10, 2);

        // Schedule daily update check
        add_action('admin_perf_prof_daily_update_check', [$this, 'clear_update_cache']);
        if (!wp_next_scheduled('admin_perf_prof_daily_update_check')) {
            wp_schedule_event(time(), 'daily', 'admin_perf_prof_daily_update_check');
        }

        // Add settings link to plugin action links
        add_filter('plugin_action_links_' . plugin_basename($this->plugin_file), [$this, 'add_settings_link']);
    }

    /**
     * Check for updates from GitHub
     *
     * @param object $transient Update transient object.
     * @return object
     */
    public function check_for_updates($transient) {
        // Only check if we have the plugins data
        if (!isset($transient->checked)) {
            return $transient;
        }

        // Get cached update data
        $cached = get_transient($this->transient_name);

        if (false !== $cached) {
            $this->apply_update_data($transient, $cached);
            return $transient;
        }

        // Fetch latest release from GitHub
        $latest_release = $this->fetch_latest_release();

        if ($latest_release && version_compare($this->current_version, $latest_release['version'], '<')) {
            $update_data = $this->format_update_data($latest_release);
            $this->apply_update_data($transient, $update_data);
            set_transient($this->transient_name, $update_data, HOUR_IN_SECONDS * 12); // Cache for 12 hours
        } else {
            // Cache negative result for 1 hour
            set_transient($this->transient_name, false, HOUR_IN_SECONDS);
        }

        return $transient;
    }

    /**
     * Fetch latest release from GitHub API
     *
     * @return array|false Release data or false on failure.
     */
    private function fetch_latest_release() {
        $url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';

        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'Admin-Performance-Profiler-Updater',
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['tag_name'])) {
            return false;
        }

        // Extract version from tag (e.g., "v1.2.0" -> "1.2.0")
        $version = ltrim($data['tag_name'], 'vV');

        // Find the zipball URL for the release
        $download_url = '';
        if (!empty($data['zipball_url'])) {
            $download_url = $data['zipball_url'];
        } elseif (!empty($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (str_ends_with($asset['name'], '.zip')) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        return [
            'version'       => $version,
            'download_url'  => $download_url,
            'release_url'   => $data['html_url'] ?? '',
            'changelog'     => $data['body'] ?? '',
            'published_at'  => $data['published_at'] ?? '',
        ];
    }

    /**
     * Format update data for WordPress
     *
     * @param array $release Release data from GitHub.
     * @return array
     */
    private function format_update_data($release) {
        $plugin_data = get_plugin_data($this->plugin_file);
        $plugin_name = $plugin_data['Name'] ?? $this->plugin_slug;

        return [
            'slug'          => $this->plugin_slug,
            'plugin'        => plugin_basename($this->plugin_file),
            'new_version'   => $release['version'],
            'url'           => $release['release_url'],
            'package'       => $release['download_url'],
            'tested'        => get_bloginfo('version'),
            'requires'      => '5.0',
            'requires_php'  => '7.4',
            'upgrade_notice' => $this->format_changelog($release['changelog']),
        ];
    }

    /**
     * Format changelog for upgrade notice
     *
     * @param string $changelog Raw changelog from GitHub.
     * @return string
     */
    private function format_changelog($changelog) {
        if (empty($changelog)) {
            return '';
        }

        // Truncate if too long
        if (strlen($changelog) > 500) {
            $changelog = substr($changelog, 0, 500) . '...';
        }

        // Convert markdown to basic HTML
        $changelog = preg_replace('/^#+\s*(.+)$/m', '<strong>$1</strong>', $changelog);
        $changelog = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $changelog);
        $changelog = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $changelog);
        $changelog = nl2br($changelog);

        return wp_kses($changelog, [
            'strong' => [],
            'em'     => [],
            'br'     => [],
            'ul'     => [],
            'ol'     => [],
            'li'     => [],
            'p'      => [],
            'a'      => ['href' => [], 'target' => [], 'rel' => []],
        ]);
    }

    /**
     * Apply update data to transient
     *
     * @param object $transient Update transient.
     * @param array  $update_data Update data.
     */
    private function apply_update_data($transient, $update_data) {
        $plugin_file = plugin_basename($this->plugin_file);

        if (!isset($transient->response[$plugin_file])) {
            $transient->response[$plugin_file] = (object) [];
        }

        $transient->response[$plugin_file] = (object) array_merge(
            (array) $transient->response[$plugin_file],
            $update_data
        );
    }

    /**
     * Provide plugin information for the update modal
     *
     * @param false|object $result Current result.
     * @param string       $action API action.
     * @param object       $args API arguments.
     * @return object|false
     */
    public function plugin_information($result, $action, $args) {
        if ('plugin_information' !== $action) {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        // Get cached data
        $cached = get_transient($this->transient_name);
        if (false === $cached) {
            return $result;
        }

        $plugin_data = get_plugin_data($this->plugin_file);

        $info = (object) [
            'name'            => $plugin_data['Name'] ?? $this->plugin_slug,
            'slug'            => $this->plugin_slug,
            'version'         => $cached['new_version'],
            'author'          => $plugin_data['Author'] ?? 'Jagdish Sarma',
            'author_profile'  => 'https://github.com/jagdishsarma36',
            'requires'        => '5.0',
            'requires_php'    => '7.4',
            'tested'          => get_bloginfo('version'),
            'downloaded'      => 0,
            'rating'          => 100,
            'num_ratings'     => 0,
            'last_updated'    => $cached['published_at'] ?? date('Y-m-d'),
            'added'           => '2024-01-01',
            'download_link'   => $cached['package'],
            'trunk'           => $cached['package'],
            'sections'        => [
                'description' => $plugin_data['Description'] ?? '',
                'installation' => '',
                'changelog'   => $this->format_changelog($cached['changelog']),
            ],
            'homepage'        => 'https://github.com/' . $this->github_repo,
        ];

        return $info;
    }

    /**
     * Add GitHub link to plugin row meta
     *
     * @param array  $links Plugin meta links.
     * @param string $file  Plugin file.
     * @return array
     */
    public function add_github_link($links, $file) {
        if (plugin_basename($this->plugin_file) !== $file) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener">%s</a>',
            esc_url('https://github.com/' . $this->github_repo),
            esc_html__('GitHub', 'admin-performance-profiler')
        );

        return $links;
    }

    /**
     * Add settings link to plugin action links
     *
     * @param array $links Plugin action links.
     * @return array
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=admin-performance-profiler')),
            esc_html__('Settings', 'admin-performance-profiler')
        );

        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Clear update cache (called by cron)
     */
    public function clear_update_cache() {
        delete_transient($this->transient_name);
    }
}