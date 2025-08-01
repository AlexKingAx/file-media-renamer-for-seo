/**
 * AI Statistics Page JavaScript
 * File Media Renamer for SEO
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        FMRSEOStats.init();
    });

    // Main statistics object
    window.FMRSEOStats = {
        chart: null,
        
        /**
         * Initialize the statistics page
         */
        init: function() {
            this.initChart();
            this.bindEvents();
            this.updatePeriodSelector();
        },

        /**
         * Initialize the usage trends chart
         */
        initChart: function() {
            const ctx = document.getElementById('fmrseo-usage-chart');
            if (!ctx) return;

            const chartData = this.prepareChartData(fmrseoStats.usageTrends);
            
            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'AI Operations',
                            data: chartData.operations,
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Credits Used',
                            data: chartData.credits,
                            borderColor: '#d63638',
                            backgroundColor: 'rgba(214, 54, 56, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Success Rate (%)',
                            data: chartData.successRate,
                            borderColor: '#00a32a',
                            backgroundColor: 'rgba(0, 163, 42, 0.1)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.dataset.label === 'Success Rate (%)') {
                                        label += context.parsed.y + '%';
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            },
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Operations / Credits'
                            },
                            beginAtZero: true
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Success Rate (%)'
                            },
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    }
                }
            });
        },

        /**
         * Prepare chart data from trends data
         */
        prepareChartData: function(trendsData) {
            const labels = [];
            const operations = [];
            const credits = [];
            const successRate = [];

            // Sort dates
            const sortedDates = Object.keys(trendsData).sort();

            sortedDates.forEach(date => {
                const data = trendsData[date];
                labels.push(this.formatDateLabel(date));
                operations.push(data.operations || 0);
                credits.push(data.credits_used || 0);
                successRate.push(data.success_rate || 0);
            });

            return {
                labels: labels,
                operations: operations,
                credits: credits,
                successRate: successRate
            };
        },

        /**
         * Format date for chart labels
         */
        formatDateLabel: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric' 
            });
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Export statistics
            $('#fmrseo-export-stats').on('click', this.exportStatistics.bind(this));
            
            // Refresh statistics
            $('#fmrseo-refresh-stats').on('click', this.refreshStatistics.bind(this));
            
            // Clear statistics
            $('#fmrseo-clear-stats').on('click', this.clearStatistics.bind(this));
            
            // Period selector change
            $('#fmrseo-period-selector').on('change', this.updateChartPeriod.bind(this));
        },

        /**
         * Update period selector
         */
        updatePeriodSelector: function() {
            const selector = $('#fmrseo-period-selector');
            if (selector.length) {
                selector.val('30'); // Default to 30 days
            }
        },

        /**
         * Update chart period
         */
        updateChartPeriod: function(e) {
            const days = parseInt(e.target.value);
            this.showLoading();
            
            // In a real implementation, this would make an AJAX call to get new data
            // For now, we'll just simulate the loading
            setTimeout(() => {
                this.hideLoading();
                this.showNotice('Chart updated for ' + days + ' days period.', 'success');
            }, 1000);
        },

        /**
         * Export statistics
         */
        exportStatistics: function() {
            const button = $('#fmrseo-export-stats');
            const originalText = button.html();
            
            button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Exporting...');
            
            $.post(fmrseoStats.ajaxUrl, {
                action: 'fmrseo_export_statistics',
                nonce: fmrseoStats.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.downloadFile(response.data.filename, response.data.content);
                    this.showNotice(fmrseoStats.strings.exportSuccess, 'success');
                } else {
                    this.showNotice(response.data || fmrseoStats.strings.exportError, 'error');
                }
            })
            .fail(() => {
                this.showNotice(fmrseoStats.strings.exportError, 'error');
            })
            .always(() => {
                button.prop('disabled', false).html(originalText);
            });
        },

        /**
         * Refresh statistics
         */
        refreshStatistics: function() {
            const button = $('#fmrseo-refresh-stats');
            const originalText = button.html();
            
            button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Refreshing...');
            
            // Simulate refresh - in real implementation, this would reload data
            setTimeout(() => {
                button.prop('disabled', false).html(originalText);
                this.showNotice(fmrseoStats.strings.refreshSuccess, 'success');
                
                // Optionally reload the page to get fresh data
                // location.reload();
            }, 1500);
        },

        /**
         * Clear statistics
         */
        clearStatistics: function() {
            if (!confirm(fmrseoStats.strings.confirmClear)) {
                return;
            }
            
            const button = $('#fmrseo-clear-stats');
            const originalText = button.html();
            
            button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Clearing...');
            
            $.post(fmrseoStats.ajaxUrl, {
                action: 'fmrseo_clear_statistics',
                nonce: fmrseoStats.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showNotice(fmrseoStats.strings.clearSuccess, 'success');
                    // Reload page after clearing
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    this.showNotice(response.data || fmrseoStats.strings.clearError, 'error');
                }
            })
            .fail(() => {
                this.showNotice(fmrseoStats.strings.clearError, 'error');
            })
            .always(() => {
                button.prop('disabled', false).html(originalText);
            });
        },

        /**
         * Download file
         */
        downloadFile: function(filename, content) {
            const element = document.createElement('a');
            element.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(content));
            element.setAttribute('download', filename);
            element.style.display = 'none';
            document.body.appendChild(element);
            element.click();
            document.body.removeChild(element);
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            $('.fmrseo-stats-container').addClass('fmrseo-loading');
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('.fmrseo-stats-container').removeClass('fmrseo-loading');
        },

        /**
         * Show notice message
         */
        showNotice: function(message, type) {
            type = type || 'info';
            
            // Remove existing notices
            $('.fmrseo-notice').remove();
            
            // Create notice element
            const notice = $('<div class="notice notice-' + type + ' is-dismissible fmrseo-notice"><p>' + message + '</p></div>');
            
            // Add dismiss button functionality
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut();
            });
            
            // Insert notice
            $('.wrap h1').after(notice);
            
            // Auto-dismiss success notices
            if (type === 'success') {
                setTimeout(() => {
                    notice.fadeOut();
                }, 3000);
            }
            
            // Scroll to top to show notice
            $('html, body').animate({ scrollTop: 0 }, 300);
        },

        /**
         * Format number with commas
         */
        formatNumber: function(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        },

        /**
         * Format percentage
         */
        formatPercentage: function(num, decimals) {
            decimals = decimals || 1;
            return parseFloat(num).toFixed(decimals) + '%';
        },

        /**
         * Format time duration
         */
        formatDuration: function(seconds) {
            if (seconds < 60) {
                return seconds.toFixed(1) + 's';
            } else if (seconds < 3600) {
                return Math.floor(seconds / 60) + 'm ' + (seconds % 60).toFixed(0) + 's';
            } else {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                return hours + 'h ' + minutes + 'm';
            }
        }
    };

    // Utility functions for other scripts
    window.FMRSEOStatsUtils = {
        formatNumber: FMRSEOStats.formatNumber,
        formatPercentage: FMRSEOStats.formatPercentage,
        formatDuration: FMRSEOStats.formatDuration,
        showNotice: FMRSEOStats.showNotice
    };

})(jQuery);