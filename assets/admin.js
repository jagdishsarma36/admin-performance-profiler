/**
 * Admin Performance Profiler - Admin JavaScript
 *
 * @package Admin_Perf_Profiler
 * @since 1.1.0
 */

(function($) {
    'use strict';

    /**
     * Admin Performance Profiler Admin
     */
    var AdminPerfProfilerAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.updatePreview();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Update preview when settings change
            $('input[name="admin_perf_prof_enabled"], input[name="admin_perf_prof_show_badge"]').on('change', function() {
                AdminPerfProfilerAdmin.updatePreview();
            });

            $('input[name="admin_perf_prof_slow_threshold"], input[name="admin_perf_prof_warning_threshold"], input[name="admin_perf_prof_critical_threshold"]').on('input', function() {
                AdminPerfProfilerAdmin.updatePreview();
            });
        },

        /**
         * Update preview with current performance data
         */
        updatePreview: function() {
            var $preview = $('#admin-perf-prof-preview-data');
            var profiler = window.adminPerfProfilerData;

            if (!profiler) {
                $preview.html('<p style="color:#666;">' + adminPerfProfilerL10n.noData + '</p>');
                return;
            }

            var totalTime = profiler.total_time || 0;
            var warningThreshold = parseFloat($('input[name="admin_perf_prof_warning_threshold"]').val()) || 1.0;
            var criticalThreshold = parseFloat($('input[name="admin_perf_prof_critical_threshold"]').val()) || 3.0;

            var statusClass = 'good';
            var statusText = adminPerfProfilerL10n.good;

            if (totalTime > criticalThreshold) {
                statusClass = 'critical';
                statusText = adminPerfProfilerL10n.critical;
            } else if (totalTime > warningThreshold) {
                statusClass = 'warning';
                statusText = adminPerfProfilerL10n.warning;
            }

            var html = '<div class="perf-stat ' + statusClass + '">';
            html += '<strong>' + totalTime.toFixed(3) + 's</strong> ' + adminPerfProfilerL10n.totalTime;
            html += ' <span style="color:#888;">(' + statusText + ')</span>';
            html += '</div>';

            html += '<div class="perf-stat">';
            html += '<strong>' + (profiler.queries || 0) + '</strong> ' + adminPerfProfilerL10n.queries;
            html += '</div>';

            html += '<div class="perf-stat">';
            html += '<strong>' + (profiler.peak_memory || 0).toFixed(1) + ' MB</strong> ' + adminPerfProfilerL10n.memory;
            html += '</div>';

            if (profiler.http_count > 0) {
                html += '<div class="perf-stat">';
                html += '<strong>' + profiler.http_count + '</strong> ' + adminPerfProfilerL10n.httpRequests;
                html += '</div>';
            }

            $preview.html(html);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AdminPerfProfilerAdmin.init();
    });

})(jQuery);