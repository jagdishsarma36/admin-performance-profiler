<?php
/**
 * Admin Performance Profiler Core Class
 *
 * @package Admin_Perf_Profiler
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Performance Profiler
 *
 * Handles performance profiling for WordPress admin area.
 */
class Admin_Perf_Profiler {

    /**
     * Plugin instance
     *
     * @var Admin_Perf_Profiler|null
     */
    private static $instance = null;

    /**
     * Performance data storage
     *
     * @var array
     */
    private $data = [];

    /**
     * Get singleton instance
     *
     * @return Admin_Perf_Profiler
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
        // Start profiling immediately (muplugins_loaded already fired)
        $this->start_profiling();

        // Capture HTTP requests
        add_action('http_api_debug', [$this, 'capture_http_request'], 10, 5);

        // Measure plugin load times
        add_action('plugin_loaded', [$this, 'measure_plugin_load_time'], 1, 1);

        // Record core milestones
        $this->register_milestone_hooks();

        // Render output in admin footer
        add_action('admin_footer', [$this, 'render_output'], 9999);
    }

    /**
     * Start profiling at the earliest possible moment
     */
    public function start_profiling() {
        $this->data = [
            'start_time'         => microtime(true),
            'last_checkpoint'    => microtime(true),
            'plugin_timings'     => [],
            'milestones'         => [],
            'http_requests'      => [],
            'query_count_start'  => get_num_queries(),
        ];
    }

    /**
     * Capture external HTTP requests
     *
     * @param mixed    $response Response object or WP_Error.
     * @param string   $context  Context.
     * @param string   $class    HTTP transport class.
     * @param array    $args     Request arguments.
     * @param string   $url      Request URL.
     */
    public function capture_http_request($response, $context, $class, $args, $url) {
        if (empty($this->data)) {
            $this->start_profiling();
        }
        $this->data['http_requests'][] = [
            'url'    => $url,
            'status' => is_wp_error($response)
                ? 'ERROR: ' . $response->get_error_message()
                : (wp_remote_retrieve_response_code($response) ?: 'OK'),
            'time'   => microtime(true),
        ];
    }

    /**
     * Measure individual plugin load times
     *
     * @param string $plugin_file Plugin file path.
     */
    public function measure_plugin_load_time($plugin_file) {
        if (empty($this->data)) {
            $this->start_profiling();
        }
        $now = microtime(true);
        $last = $this->data['last_checkpoint'] ?? $now;
        $time_taken = round($now - $last, 4);
        $this->data['last_checkpoint'] = $now;

        // Extract plugin info
        $plugin_info = $this->get_plugin_info($plugin_file);

        // Store ALL plugin timings with details
        $this->data['plugin_timings'][$plugin_file] = [
            'time'      => $time_taken,
            'name'      => $plugin_info['name'],
            'slug'      => $plugin_info['slug'],
            'file'      => $plugin_file,
            'is_slow'   => false,
        ];

        // Mark as slow if >= threshold
        $threshold = get_option('admin_perf_prof_slow_threshold', 0.2);
        if ($time_taken >= $threshold) {
            $this->data['plugin_timings'][$plugin_file]['is_slow'] = true;
        }
    }

    /**
     * Extract plugin info from file path
     *
     * @param string $plugin_file Plugin file path.
     * @return array
     */
    private function get_plugin_info($plugin_file) {
        // Normalize path
        $plugin_file = ltrim($plugin_file, '/');

        // Get plugin directory name (slug)
        $parts = explode('/', $plugin_file);
        $slug = $parts[0] ?? 'unknown';

        // Try to get plugin name from headers
        $name = $slug;
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
        if (file_exists($plugin_path)) {
            $headers = get_file_data($plugin_path, [
                'Plugin Name' => 'Plugin Name',
                'Name'        => 'Name',
            ], 'plugin');
            if (!empty($headers['Plugin Name'])) {
                $name = $headers['Plugin Name'];
            } elseif (!empty($headers['Name'])) {
                $name = $headers['Name'];
            }
        }

        return [
            'name' => $name,
            'slug' => $slug,
        ];
    }

    /**
     * Register milestone hooks
     */
    private function register_milestone_hooks() {
        $milestones = [
            'muplugins_loaded'    => 'muplugins_loaded',
            'plugins_loaded'      => 'plugins_loaded',
            'setup_theme'         => 'setup_theme',
            'after_setup_theme'   => 'after_setup_theme',
            'init'                => 'init',
            'wp_loaded'           => 'wp_loaded',
            'admin_menu'          => 'admin_menu',
            'admin_init'          => 'admin_init',
            'admin_enqueue_scripts' => 'admin_enqueue_scripts',
            'admin_head'          => 'admin_head',
            'admin_footer'        => 'admin_footer_early',
        ];

        foreach ($milestones as $hook => $name) {
            add_action($hook, function() use ($name) {
                $this->record_milestone($name);
            }, 9999);
        }
    }

    /**
     * Record a milestone timestamp
     *
     * @param string $name Milestone name.
     */
    public function record_milestone($name) {
        // Initialize data if not already done
        if (empty($this->data)) {
            $this->start_profiling();
        }
        $start = $this->data['start_time'] ?? microtime(true);
        $this->data['milestones'][$name] = round(microtime(true) - $start, 4);
    }

    /**
     * Get performance data
     *
     * @return array
     */
    public function get_data() {
        // Initialize defaults if profiling hasn't started yet (e.g., on settings page load)
        $start = $this->data['start_time'] ?? microtime(true);
        $http_requests = $this->data['http_requests'] ?? [];
        $plugin_timings = $this->data['plugin_timings'] ?? [];
        $milestones = $this->data['milestones'] ?? [];

        // Process plugin timings - separate slow and all plugins
        $slow_plugins = [];
        $all_plugins = [];
        foreach ($plugin_timings as $file => $info) {
            if (is_array($info)) {
                $time = $info['time'];
                $all_plugins[$file] = $info;
                if ($info['is_slow']) {
                    $slow_plugins[$file] = $time; // Backward compat
                }
            } else {
                // Backward compat: old format was just time value
                $all_plugins[$file] = [
                    'time'    => $info,
                    'name'    => basename(dirname($file)) . '/' . basename($file),
                    'slug'    => basename(dirname($file)),
                    'file'    => $file,
                    'is_slow' => $info >= (get_option('admin_perf_prof_slow_threshold', 0.2)),
                ];
                if ($info >= (get_option('admin_perf_prof_slow_threshold', 0.2))) {
                    $slow_plugins[$file] = $info;
                }
            }
        }

        $total_time = round(microtime(true) - $start, 3);
        $queries = get_num_queries() - ($this->data['query_count_start'] ?? 0);
        $peak_memory = round(memory_get_peak_usage() / 1024 / 1024, 2);
        $http_count = count($http_requests);

        return [
            'total_time'      => $total_time,
            'queries'         => $queries,
            'peak_memory'     => $peak_memory,
            'http_count'      => $http_count,
            'http_requests'   => $http_requests,
            'slow_plugins'    => $slow_plugins,
            'all_plugins'     => $all_plugins,
            'milestones'      => $milestones,
        ];
    }

    /**
     * Check if profiler should display for current user
     *
     * @return bool
     */
    private function should_display() {
        if (!is_admin()) {
            return false;
        }

        if (!current_user_can('manage_options')) {
            return false;
        }

        if (!get_option('admin_perf_prof_enabled', true)) {
            return false;
        }

        return true;
    }

    /**
     * Render output in admin footer
     */
    public function render_output() {
        if (!$this->should_display()) {
            return;
        }

        if (!get_option('admin_perf_prof_show_badge', true)) {
            // Still output HTML comment for debugging
            $this->output_html_comment();
            return;
        }

        $data = $this->get_data();

        // Output HTML comment for source viewing
        $this->output_html_comment($data);

        // Output the interactive badge
        $this->render_badge($data);
    }

    /**
     * Output HTML comment with performance data
     *
     * @param array $data Performance data.
     */
    private function output_html_comment($data = []) {
        if (empty($data)) {
            $data = $this->get_data();
        }

        $output = "\n<!--\n";
        $output .= "================ ADMIN PERFORMANCE PROFILER ================\n";
        $output .= sprintf("Version: %s\n", ADMIN_PERF_PROF_VERSION);
        $output .= sprintf("Author: Jagdish Sarma\n");
        $output .= sprintf("Total Execution Time: %.3fs\n", $data['total_time']);
        $output .= sprintf("Database Queries:     %d\n", $data['queries']);
        $output .= sprintf("Peak Memory:          %.2f MB\n\n", $data['peak_memory']);

        $output .= "Milestone Breakdown:\n";
        $prev = 0;
        foreach ($data['milestones'] as $milestone => $t) {
            $delta = round($t - $prev, 4);
            $output .= sprintf("  %-25s: %6.3fs (+%6.3fs)\n", $milestone, $t, $delta);
            $prev = $t;
        }

        if (!empty($data['all_plugins'])) {
            $output .= "\nAll Plugin Load Times:\n";
            $sorted_plugins = $data['all_plugins'];
            uasort($sorted_plugins, function($a, $b) {
                return $b['time'] <=> $a['time'];
            });
            foreach ($sorted_plugins as $plugin => $info) {
                $time = is_array($info) ? $info['time'] : $info;
                $is_slow = is_array($info) ? ($info['is_slow'] ?? false) : ($time >= get_option('admin_perf_prof_slow_threshold', 0.2));
                $marker = $is_slow ? ' [SLOW]' : '';
                $output .= sprintf("  - %s: %.4fs%s\n", $plugin, $time, $marker);
            }
        }

        if ($data['http_count'] > 0) {
            $output .= sprintf("\nExternal HTTP Requests (%d):\n", $data['http_count']);
            foreach ($data['http_requests'] as $req) {
                $output .= sprintf("  - [%s] %s\n", $req['status'], $req['url']);
            }
        }

        $output .= "============================================================\n-->\n";

        echo $output;
    }

    /**
     * Render the interactive badge
     *
     * @param array $data Performance data.
     */
    private function render_badge($data) {
        $total_time = $data['total_time'];
        $warning_threshold = get_option('admin_perf_prof_warning_threshold', 1.0);
        $critical_threshold = get_option('admin_perf_prof_critical_threshold', 3.0);

        // Determine badge color based on thresholds
        if ($total_time > $critical_threshold) {
            $badge_bg = '#e74c3c';
            $badge_label = esc_html__('Critical', 'admin-performance-profiler');
        } elseif ($total_time > $warning_threshold) {
            $badge_bg = '#e67e22';
            $badge_label = esc_html__('Warning', 'admin-performance-profiler');
        } else {
            $badge_bg = '#27ae60';
            $badge_label = esc_html__('Good', 'admin-performance-profiler');
        }

        // Nonce for AJAX if needed
        $nonce = wp_create_nonce('admin_perf_prof_nonce');
        ?>
        <div id="admin-perf-profiler"
             style="position:fixed;bottom:12px;right:12px;background:#1e1e1e;color:#fff;border-radius:6px;box-shadow:0 4px 18px rgba(0,0,0,0.45);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,monospace;font-size:12px;z-index:999999;border:1px solid rgba(255,255,255,0.1);user-select:none;max-width:420px;overflow:hidden;"
             data-nonce="<?php echo esc_attr($nonce); ?>">
            <!-- Collapsed Bar -->
            <div id="admin-perf-bar"
                 style="padding:7px 12px;display:flex;align-items:center;gap:10px;cursor:pointer;background:#252525;border-bottom:1px solid transparent;"
                 role="button"
                 tabindex="0"
                 aria-label="<?php echo esc_attr(sprintf(__('Admin Performance Profiler: %s, %d queries, %.1fMB', 'admin-performance-profiler'), $total_time . 's', $data['queries'], $data['peak_memory'])); ?>"
                 aria-expanded="false">
                <span style="background:<?php echo esc_attr($badge_bg); ?>;color:#fff;padding:2px 7px;border-radius:3px;font-weight:700;font-size:11px;">
                    ⏱️ <?php echo esc_html($total_time); ?>s
                </span>
                <span style="color:#bbb;" title="<?php echo esc_attr(__('Database Queries', 'admin-performance-profiler')); ?>">🗄️ <?php echo esc_html($data['queries']); ?></span>
                <span style="color:#bbb;" title="<?php echo esc_attr(__('Peak Memory Usage', 'admin-performance-profiler')); ?>">💾 <?php echo esc_html($data['peak_memory']); ?>MB</span>
                <?php if ($data['http_count'] > 0): ?>
                    <span style="color:#f39c12;" title="<?php echo esc_attr(__('External HTTP Requests', 'admin-performance-profiler')); ?>">🌐 <?php echo esc_html($data['http_count']); ?></span>
                <?php endif; ?>
                <span style="margin-left:auto;font-size:10px;color:#888;" aria-hidden="true">▼</span>
            </div>

            <!-- Expanded Details Drawer -->
            <div id="admin-perf-drawer"
                 style="display:none;padding:12px;background:#181818;border-top:1px solid #333;max-height:380px;overflow-y:auto;font-size:11px;line-height:1.5;"
                 role="region"
                 aria-label="<?php echo esc_attr(__('Performance Details', 'admin-performance-profiler')); ?>">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <div>
                        <div style="font-weight:bold;color:#27ae60;font-size:12px;"><?php echo esc_html__('Admin Performance Profiler', 'admin-performance-profiler'); ?></div>
                        <div style="color:#888;font-size:10px;"><?php printf(esc_html__('By Jagdish Sarma • v%s', 'admin-performance-profiler'), ADMIN_PERF_PROF_VERSION); ?></div>
                    </div>
                    <button id="admin-perf-close"
                            style="background:none;border:none;color:#888;font-size:16px;cursor:pointer;padding:0;line-height:1;"
                            aria-label="<?php echo esc_attr__('Close', 'admin-performance-profiler'); ?>">
                        ×
                    </button>
                </div>

                <!-- Summary Stats -->
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:10px;padding:8px;background:#222;border-radius:4px;">
                    <div>
                        <div style="color:#888;font-size:10px;"><?php echo esc_html__('Total Time', 'admin-performance-profiler'); ?></div>
                        <div style="font-weight:bold;color:<?php echo esc_attr($badge_bg); ?>;font-size:14px;"><?php echo esc_html($total_time); ?>s</div>
                    </div>
                    <div>
                        <div style="color:#888;font-size:10px;"><?php echo esc_html__('Queries', 'admin-performance-profiler'); ?></div>
                        <div style="font-weight:bold;color:#ddd;"><?php echo esc_html($data['queries']); ?></div>
                    </div>
                    <div>
                        <div style="color:#888;font-size:10px;"><?php echo esc_html__('Memory', 'admin-performance-profiler'); ?></div>
                        <div style="font-weight:bold;color:#ddd;"><?php echo esc_html($data['peak_memory']); ?> MB</div>
                    </div>
                    <div>
                        <div style="color:#888;font-size:10px;"><?php echo esc_html__('HTTP Requests', 'admin-performance-profiler'); ?></div>
                        <div style="font-weight:bold;color:#ddd;"><?php echo esc_html($data['http_count']); ?></div>
                    </div>
                </div>

                <!-- Milestones -->
                <div style="font-weight:bold;color:#ddd;margin-top:6px;border-bottom:1px solid #333;padding-bottom:2px;">
                    <?php echo esc_html__('Milestones', 'admin-performance-profiler'); ?>
                </div>
                <table style="width:100%;margin-top:4px;border-collapse:collapse;color:#ccc;font-size:11px;">
                    <?php
                    $p = 0;
                    foreach ($data['milestones'] as $m => $t):
                        $d = round($t - $p, 3);
                        $color = ($d >= $critical_threshold) ? '#e74c3c;font-weight:bold;' : (($d >= $warning_threshold) ? '#f39c12;' : '#aaa;');
                    ?>
                        <tr>
                            <td style="padding:2px 0;color:#999;"><?php echo esc_html($m); ?></td>
                            <td style="padding:2px 0;text-align:right;color:<?php echo $color; ?>"><?php echo esc_html($t); ?>s (+<?php echo esc_html($d); ?>s)</td>
                        </tr>
                    <?php
                        $p = $t;
                    endforeach;
                    ?>
                </table>

                <!-- All Plugins Load Times -->
                <?php if (!empty($data['all_plugins'])): ?>
                    <div style="font-weight:bold;color:#ddd;margin-top:10px;border-bottom:1px solid #333;padding-bottom:2px;display:flex;justify-content:space-between;align-items:center;">
                        <span><?php echo esc_html__('Plugin Load Times', 'admin-performance-profiler'); ?></span>
                        <span style="font-size:10px;color:#888;"><?php echo esc_html__('Click to expand', 'admin-performance-profiler'); ?></span>
                    </div>
                    <table style="width:100%;margin-top:4px;border-collapse:collapse;color:#ccc;font-size:11px;">
                        <?php
                        // Sort plugins by time descending
                        $sorted_plugins = $data['all_plugins'];
                        uasort($sorted_plugins, function($a, $b) {
                            return $b['time'] <=> $a['time'];
                        });
                        foreach ($sorted_plugins as $plug => $info):
                            $time = $info['time'];
                            $name = $info['name'] ?? basename(dirname($plug)) . '/' . basename($plug);
                            $slug = $info['slug'] ?? basename(dirname($plug));
                            $is_slow = $info['is_slow'] ?? false;
                            $color = ($time >= 1.0) ? '#e74c3c' : (($time >= 0.3) ? '#f39c12' : (($time >= 0.1) ? '#3498db' : '#27ae60'));
                        ?>
                        <tr class="plugin-row" data-plugin="<?php echo esc_attr($plug); ?>" style="cursor:pointer;">
                            <td style="padding:4px 8px 4px 0;color:#ccc;white-space:nowrap;">
                                <span style="display:inline-flex;align-items:center;gap:4px;">
                                    <span class="plugin-toggle" style="font-size:10px;color:#888;cursor:pointer;user-select:none;width:14px;text-align:center;">▶</span>
                                    <span style="font-weight:<?php echo $is_slow ? 'bold' : 'normal'; ?>;"><?php echo esc_html($name); ?></span>
                                    <?php if ($is_slow): ?>
                                        <span style="background:#e74c3c;color:#fff;padding:1px 4px;border-radius:2px;font-size:8px;"><?php echo esc_html__('SLOW', 'admin-performance-profiler'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td style="padding:4px 0;text-align:right;color:<?php echo $color; ?>;font-weight:<?php echo $is_slow ? 'bold' : 'normal'; ?>;"><?php echo esc_html(number_format($time, 4)); ?>s</td>
                        </tr>
                        <!-- Expandable details row -->
                        <tr class="plugin-details" data-plugin="<?php echo esc_attr($plug); ?>" style="display:none;">
                            <td colspan="2" style="padding:8px;background:#111;border-top:1px solid #333;">
                                <div style="font-size:10px;line-height:1.8;color:#999;">
                                    <div><strong style="color:#ccc;"><?php echo esc_html__('File:', 'admin-performance-profiler'); ?></strong> <?php echo esc_html($plug); ?></div>
                                    <div><strong style="color:#ccc;"><?php echo esc_html__('Slug:', 'admin-performance-profiler'); ?></strong> <?php echo esc_html($slug); ?></div>
                                    <div><strong style="color:#ccc;"><?php echo esc_html__('Load Time:', 'admin-performance-profiler'); ?></strong> <span style="color:<?php echo $color; ?>;"><?php echo esc_html(number_format($time, 4)); ?>s</span></div>
                                    <div><strong style="color:#ccc;"><?php echo esc_html__('Status:', 'admin-performance-profiler'); ?></strong>
                                        <?php if ($is_slow): ?>
                                            <span style="color:#e74c3c;"><?php echo esc_html__('Slow (≥ threshold)', 'admin-performance-profiler'); ?></span>
                                        <?php elseif ($time >= 0.1): ?>
                                            <span style="color:#f39c12;"><?php echo esc_html__('Moderate', 'admin-performance-profiler'); ?></span>
                                        <?php elseif ($time > 0): ?>
                                            <span style="color:#3498db;"><?php echo esc_html__('Fast', 'admin-performance-profiler'); ?></span>
                                        <?php else: ?>
                                            <span style="color:#27ae60;"><?php echo esc_html__('Negligible', 'admin-performance-profiler'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>

                <!-- HTTP Requests -->
                <?php if ($data['http_count'] > 0): ?>
                    <div style="font-weight:bold;color:#f39c12;margin-top:10px;border-bottom:1px solid #333;padding-bottom:2px;">
                        <?php echo esc_html__('External HTTP Requests', 'admin-performance-profiler'); ?>
                    </div>
                    <ul style="margin:4px 0 0 14px;padding:0;color:#ffbe76;">
                        <?php foreach ($data['http_requests'] as $req): ?>
                            <li style="word-break:break-all;margin-bottom:4px;">
                                <span style="color:#888;">[<?php echo esc_html($req['status']); ?>]</span>
                                <span style="margin-left:4px;"><?php echo esc_html($req['url']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <script>
        (function() {
            'use strict';

            var container = document.getElementById('admin-perf-profiler');
            var bar = document.getElementById('admin-perf-bar');
            var drawer = document.getElementById('admin-perf-drawer');
            var closeBtn = document.getElementById('admin-perf-close');

            if (!container || !bar || !drawer) {
                return;
            }

            var isOpen = false;

            function toggleDrawer(forceClose) {
                isOpen = forceClose ? false : !isOpen;
                drawer.style.display = isOpen ? 'block' : 'none';
                bar.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                var arrow = bar.querySelector('span[aria-hidden="true"]');
                if (arrow) {
                    arrow.textContent = isOpen ? '▲' : '▼';
                }
            }

            function handleClick(e) {
                if (e.target === closeBtn || closeBtn.contains(e.target)) {
                    toggleDrawer(true);
                    return;
                }
                if (e.target === bar || bar.contains(e.target)) {
                    toggleDrawer();
                }
            }

            function handleKeydown(e) {
                if (e.key === 'Escape') {
                    toggleDrawer(true);
                } else if (e.key === 'Enter' || e.key === ' ') {
                    if (e.target === bar) {
                        e.preventDefault();
                        toggleDrawer();
                    }
                }
            }

            // Plugin row expand/collapse
            function handlePluginToggle(e) {
                var toggle = e.target.closest('.plugin-toggle');
                if (!toggle) return;

                var row = toggle.closest('.plugin-row');
                if (!row) return;

                var plugin = row.getAttribute('data-plugin');
                var detailsRow = drawer.querySelector('.plugin-details[data-plugin="' + plugin + '"]');
                var isExpanded = detailsRow.style.display !== 'none';

                if (detailsRow) {
                    detailsRow.style.display = isExpanded ? 'none' : 'table-row';
                    toggle.textContent = isExpanded ? '▶' : '▼';
                    toggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
                }
            }

            bar.addEventListener('click', handleClick);
            bar.addEventListener('keydown', handleKeydown);
            closeBtn.addEventListener('click', function() { toggleDrawer(true); });
            document.addEventListener('keydown', handleKeydown);

            // Delegate click for plugin toggles (since they're dynamically added)
            drawer.addEventListener('click', handlePluginToggle);

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (isOpen && !container.contains(e.target)) {
                    toggleDrawer(true);
                }
            });
        })();
        </script>
        <?php
    }
}