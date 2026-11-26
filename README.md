# Admin Performance Profiler

A lightweight WordPress plugin that provides real-time performance metrics for the WordPress admin area. Track execution time, memory usage, database queries, HTTP requests, and identify slow-loading plugins.

## Features

- **Real-time Performance Metrics**: Execution time, memory usage, database query count
- **Milestone Breakdown**: Detailed timing for each WordPress core milestone (plugins_loaded, init, wp_loaded, etc.)
- **Slow Plugin Detection**: Identifies plugins taking longer than a configurable threshold to load
- **External HTTP Request Tracking**: Monitors all wp_remote_* and cURL requests
- **Interactive Admin Badge**: Clickable badge in admin footer with expandable details
- **Color-coded Alerts**: Green/Orange/Red badges based on configurable thresholds
- **Settings Page**: Configure thresholds and display options
- **GitHub Updates**: Automatic update checks from GitHub releases

## Requirements

- WordPress 5.0+
- PHP 7.4+

## Installation

### From WordPress Admin

1. Download the latest release ZIP from [GitHub Releases](https://github.com/jagdishsarma36/admin-performance-profiler/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and activate

### From GitHub (Manual)

1. Clone or download this repository
2. Upload the `admin-performance-profiler` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu

## Usage

Once activated, the plugin automatically starts profiling for administrators. You'll see a badge in the bottom-right corner of the admin area showing:

- Total execution time
- Database query count
- Peak memory usage
- External HTTP request count

Click the badge to expand and see:
- Detailed milestone breakdown with delta times
- Slow plugins (configurable threshold)
- External HTTP requests with status codes

## Settings

Navigate to **Settings → Admin Perf Profiler** to configure:

- **Enable Profiler**: Turn profiling on/off
- **Show Interactive Badge**: Display/hide the admin footer badge
- **Slow Plugin Threshold**: Time (seconds) after which a plugin is flagged as slow
- **Warning Threshold**: Total time for orange warning badge
- **Critical Threshold**: Total time for red critical badge

## Data Output

Performance data is also available as an HTML comment in the page source:

```html
<!--
================ ADMIN PERFORMANCE PROFILER ================
Version: 1.1.0
Author: Jagdish Sarma
Total Execution Time: 0.847s
Database Queries:     42
Peak Memory:          18.45 MB

Milestone Breakdown:
  muplugins_loaded      :  0.001s (+0.001s)
  plugins_loaded        :  0.234s (+0.233s)
  setup_theme           :  0.289s (+0.055s)
  after_setup_theme     :  0.312s (+0.023s)
  init                  :  0.567s (+0.255s)
  wp_loaded             :  0.678s (+0.111s)
  admin_menu            :  0.723s (+0.045s)
  admin_init            :  0.801s (+0.078s)
  admin_enqueue_scripts :  0.834s (+0.033s)
  admin_head            :  0.842s (+0.008s)
  admin_footer_early    :  0.847s (+0.005s)

Slow Plugins Loaded (>= 0.2s):
  - slow-plugin/slow-plugin.php: 0.234s

External HTTP Requests (2):
  - [200] https://api.example.com/data
  - [404] https://api.example.com/missing
============================================================
-->
```

## Updates

The plugin checks for updates from GitHub releases automatically (daily). When a new version is available, it will appear in the standard WordPress **Updates** page and plugin list.

### Creating a Release

1. Tag a new version: `git tag v1.2.0`
2. Push tags: `git push origin --tags`
3. Create a GitHub Release from the tag
4. The plugin will detect the update within 12 hours (or immediately after clearing cache)

## Development

### Local Development

```bash
# Clone the repository
git clone https://github.com/jagdishsarma36/admin-performance-profiler.git

# Define development constant in wp-config.php to skip GitHub updater
define('ADMIN_PERF_PROF_DEV_MODE', true);
```

### File Structure

```
admin-performance-profiler/
├── admin-performance-profiler.php    # Main plugin file
├── README.md                         # This file
├── assets/
│   ├── admin.css                     # Admin styles
│   └── admin.js                      # Admin JavaScript
├── includes/
│   ├── class-admin-perf-profiler.php     # Core profiling logic
│   ├── class-admin-perf-profiler-admin.php # Admin settings page
│   └── class-github-plugin-updater.php   # GitHub update handling
└── languages/                        # Translation files
```

## Hooks & Filters

### Actions

- `admin_perf_prof_daily_update_check` - Daily cron for update checks

### Filters

- `plugin_row_meta` - Adds GitHub link to plugin row
- `plugin_action_links` - Adds Settings link to plugin actions
- `pre_set_site_transient_update_plugins` - Injects update data
- `plugins_api` - Provides plugin info for update modal

## Performance Impact

The profiler is designed to have minimal overhead:
- Uses PHP's native `microtime()` and `memory_get_peak_usage()`
- Only tracks data for administrators
- No database writes during profiling
- Data collected in memory, output only in admin footer

## License

GPL-2.0-or-later

## Author

**Jagdish Sarma**
- GitHub: [@jagdishsarma36](https://github.com/jagdishsarma36)

## Changelog

### 1.1.0 (Latest)
- Added settings page with configurable thresholds
- Implemented GitHub updater for automatic updates
- Improved milestone tracking with more checkpoints
- Added color-coded badges (Good/Warning/Critical)
- Added interactive drawer with detailed breakdown
- Added HTTP request tracking
- Added uninstall hook for clean removal
- Improved accessibility (ARIA labels, keyboard navigation)
- Added translation support

### 1.0.0
- Initial release
- Basic performance profiling
- Admin footer badge
- HTML comment output