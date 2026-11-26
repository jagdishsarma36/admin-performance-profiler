# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2024-XX-XX

### Added
- Settings page under **Settings → Admin Perf Profiler**
- Configurable thresholds for slow plugin detection (default: 0.2s)
- Configurable warning threshold for total execution time (default: 1.0s)
- Configurable critical threshold for total execution time (default: 3.0s)
- Option to enable/disable profiler
- Option to show/hide interactive badge
- GitHub updater for automatic plugin updates
- Additional milestone checkpoints (admin_enqueue_scripts, admin_head, admin_footer_early)
- External HTTP request tracking (wp_remote_*, cURL)
- Color-coded badges: Green (Good), Orange (Warning), Red (Critical)
- Interactive drawer with detailed performance breakdown
- Summary stats grid in expanded view
- Accessibility improvements (ARIA labels, keyboard navigation, focus management)
- Translation support (text domain: admin-performance-profiler)
- Uninstall hook for clean option removal
- Daily cron job for update checks
- Settings link in plugin action links
- GitHub link in plugin row meta
- Preview of current page performance on settings page

### Changed
- Refactored code into separate classes (Profiler, Admin, GitHub Updater)
- Improved HTML output with better structure and styling
- Enhanced milestone display with delta times
- Better error handling for HTTP requests
- Optimized performance data collection

### Fixed
- Plugin load time measurement now uses configurable threshold
- Milestone timing precision improved to 4 decimal places
- Badge color logic uses configurable thresholds
- Memory usage displayed in MB with 2 decimal places

## [1.0.0] - 2024-XX-XX

### Added
- Initial release
- Real-time execution time measurement
- Database query counting
- Peak memory usage tracking
- Milestone breakdown for core WordPress hooks
- Slow plugin detection (≥ 0.2s)
- Interactive admin footer badge
- HTML comment output in page source
- Admin-only display (manage_options capability)

---

## Release Process

1. Update version in:
   - `admin-performance-profiler.php` (plugin header)
   - `composer.json`
   - `CHANGELOG.md`

2. Create and push tag:
   ```bash
   git tag v1.2.0
   git push origin --tags
   ```

3. GitHub Actions will:
   - Build release ZIP
   - Create GitHub Release with auto-generated notes
   - Attach ZIP for WordPress update system

4. Plugin will check for updates daily (cached for 12 hours)