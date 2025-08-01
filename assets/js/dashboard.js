/**
 * FMRSEO Dashboard JavaScript
 * Handles dashboard interactions, charts, and real-time updates
 */

(function($) {
    'use strict';
    
    let charts = {};
    let refreshInterval;
    
    $(document).ready(function() {
        initializeDashboard();
        setupEventHandlers();
        initializeCharts();
        startAutoRefresh();
    });
    
    function initializeDashboard() {
        // Add loading states
        $('.fmrseo-dashboard-card').each(function() {
            $(this).prepend('<div class="fmrseo-loading-overlay"><span class="spinner is-active"></span></div>');
        });
        
        // Hide loading overlays after initial load
        setTimeout(function() {
            $('.fmrseo-loading-overlay').fadeOut();
        }, 1000);
    }
    
    function setupEventHandlers() {
        // Refresh dashboard
        $('#fmrseo-refresh-dashboard').on('click', function() {
            refreshDashboard();
        });
        
        // Export report
        $('#fmrseo-export-report').on('click', function() {
            exportReport();
        });
        
        // Period change
        $('#fmrseo-dashboard-period').on('change', function() {
            const period = $(this).val();
            refreshDashboard(period);
        });
        
        // Auto-refresh toggle
        $(document).on('keydown', function(e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshDashboard();
            }
        });
    }
    
    function refreshDashboard(days = null) {
        const $refreshBtn = $('#fmrseo-refresh-dashboard');
        const $spinner = $refreshBtn.find('.dashicons');
        
        if (!days) {
            days = $('#fmrseo-dashboard-period').val();
        }
        
        // Show loading state
        $refreshBtn.prop('disabled', true);
        $spinner.addClass('spin');
        
        $.ajax({
            url: fmrseoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fmrseo_refresh_dashboard',
                nonce: fmrseoAjax.nonce,
                days: days
            },
            success: function(response) {
                if (response.success) {
                    updateDashboardData(response.data);
                    showNotification('Dashboard updated successfully', 'success');
                } else {
                    showNotification('Failed to refresh dashboard', 'error');
                }
            },
            error: function() {
                showNotification('Error refreshing dashboard', 'error');
            },
            complete: function() {
                $refreshBtn.prop('disabled', false);
                $spinner.removeClass('spin');
            }
        });
    }
    
    function updateDashboardData(data) {
        // Update global data
        window.fmrseoData = data;
        
        // Update stat cards
        updateStatCards(data.stats);
        
        // Update charts
        updateCharts(data.stats);
        
        // Update issues
        updateIssues(data.issues);
        
        // Update system info
        updateSystemInfo(data.system_info);
    }
    
    function updateStatCards(stats) {
        const $cards = $('.fmrseo-stat-card');
        
        $cards.eq(0).find('h3').text(numberFormat(stats.successful_operations));
        $cards.eq(1).find('h3').text(numberFormat(stats.failed_operations));
        $cards.eq(2).find('h3').text(stats.avg_response_time.toFixed(2) + 's');
        
        // Add animation
        $cards.addClass('updated');
        setTimeout(() => $cards.removeClass('updated'), 500);
    }
    
    function initializeCharts() {
        if (!window.fmrseoData) return;
        
        const data = window.fmrseoData.stats;
        
        // Operations over time chart
        createOperationsChart(data);
        
        // Success rate chart
        createSuccessChart(data);
        
        // Methods chart
        createMethodsChart(data);
    }
    
    function createOperationsChart(data) {
        const ctx = document.getElementById('fmrseo-operations-chart');
        if (!ctx) return;
        
        const dailyStats = data.daily_stats;
        const labels = Object.keys(dailyStats).sort();
        const operationsData = labels.map(date => dailyStats[date].operations);
        const successData = labels.map(date => dailyStats[date].successful);
        const failedData = labels.map(date => dailyStats[date].failed);
        
        charts.operations = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.map(date => formatDate(date)),
                datasets: [{
                    label: 'Total Operations',
                    data: operationsData,
                    borderColor: '#0073aa',
                    backgroundColor: 'rgba(0, 115, 170, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Successful',
                    data: successData,
                    borderColor: '#46b450',
                    backgroundColor: 'rgba(70, 180, 80, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Failed',
                    data: failedData,
                    borderColor: '#dc3232',
                    backgroundColor: 'rgba(220, 50, 50, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    }
    
    function createSuccessChart(data) {
        const ctx = document.getElementById('fmrseo-success-chart');
        if (!ctx) return;
        
        const successRate = data.total_operations > 0 ? 
            (data.successful_operations / data.total_operations) * 100 : 0;
        const failureRate = 100 - successRate;
        
        charts.success = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Success', 'Failed'],
                datasets: [{
                    data: [successRate, failureRate],
                    backgroundColor: ['#46b450', '#dc3232'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    function createMethodsChart(data) {
        const ctx = document.getElementById('fmrseo-methods-chart');
        if (!ctx) return;
        
        const methods = data.rename_operations;
        const labels = Object.keys(methods);
        const totals = labels.map(method => methods[method].total);
        const colors = ['#0073aa', '#46b450', '#f39c12', '#dc3232', '#9b59b6'];
        
        charts.methods = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels.map(method => method.charAt(0).toUpperCase() + method.slice(1)),
                datasets: [{
                    label: 'Operations',
                    data: totals,
                    backgroundColor: colors.slice(0, labels.length),
                    borderColor: colors.slice(0, labels.length),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    function updateCharts(data) {
        // Update operations chart
        if (charts.operations) {
            const dailyStats = data.daily_stats;
            const labels = Object.keys(dailyStats).sort();
            
            charts.operations.data.labels = labels.map(date => formatDate(date));
            charts.operations.data.datasets[0].data = labels.map(date => dailyStats[date].operations);
            charts.operations.data.datasets[1].data = labels.map(date => dailyStats[date].successful);
            charts.operations.data.datasets[2].data = labels.map(date => dailyStats[date].failed);
            charts.operations.update();
        }
        
        // Update success chart
        if (charts.success) {
            const successRate = data.total_operations > 0 ? 
                (data.successful_operations / data.total_operations) * 100 : 0;
            const failureRate = 100 - successRate;
            
            charts.success.data.datasets[0].data = [successRate, failureRate];
            charts.success.update();
        }
        
        // Update methods chart
        if (charts.methods) {
            const methods = data.rename_operations;
            const labels = Object.keys(methods);
            const totals = labels.map(method => methods[method].total);
            
            charts.methods.data.labels = labels.map(method => method.charAt(0).toUpperCase() + method.slice(1));
            charts.methods.data.datasets[0].data = totals;
            charts.methods.update();
        }
    }
    
    function updateIssues(issues) {
        const $issuesList = $('.fmrseo-issues-list');
        
        if (issues.length === 0) {
            $issuesList.closest('.fmrseo-dashboard-card').hide();
            return;
        }
        
        $issuesList.closest('.fmrseo-dashboard-card').show();
        $issuesList.empty();
        
        issues.forEach(function(issue) {
            const $issueItem = $(`
                <div class="fmrseo-issue-item severity-${issue.severity}">
                    <div class="fmrseo-issue-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="fmrseo-issue-content">
                        <h4>${escapeHtml(issue.message)}</h4>
                        <p>${escapeHtml(issue.type)}</p>
                        ${issue.action ? `<p class="fmrseo-issue-action">${escapeHtml(issue.action)}</p>` : ''}
                    </div>
                </div>
            `);
            
            $issuesList.append($issueItem);
        });
    }
    
    function updateSystemInfo(systemInfo) {
        // Update memory usage
        $('.fmrseo-system-info').find('li').each(function() {
            const $li = $(this);
            const text = $li.text();
            
            if (text.includes('Current:')) {
                $li.html(`<strong>Current:</strong> ${systemInfo.current_memory_usage}`);
            } else if (text.includes('Peak:')) {
                $li.html(`<strong>Peak:</strong> ${systemInfo.peak_memory_usage}`);
            }
        });
    }
    
    function exportReport() {
        const $exportBtn = $('#fmrseo-export-report');
        const days = $('#fmrseo-dashboard-period').val();
        
        $exportBtn.prop('disabled', true);
        
        $.ajax({
            url: fmrseoAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'fmrseo_export_settings',
                nonce: fmrseoAjax.nonce,
                days: days
            },
            success: function(response) {
                if (response.success) {
                    downloadCSV(response.data.csv, response.data.filename);
                    showNotification('Report exported successfully', 'success');
                } else {
                    showNotification('Failed to export report', 'error');
                }
            },
            error: function() {
                showNotification('Error exporting report', 'error');
            },
            complete: function() {
                $exportBtn.prop('disabled', false);
            }
        });
    }
    
    function downloadCSV(csvContent, filename) {
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
    
    function startAutoRefresh() {
        // Auto-refresh every 5 minutes
        refreshInterval = setInterval(function() {
            refreshDashboard();
        }, 300000);
    }
    
    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
    }
    
    function showNotification(message, type = 'info') {
        const $notification = $(`
            <div class="notice notice-${type} is-dismissible fmrseo-notification">
                <p>${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.fmrseo-dashboard').prepend($notification);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual dismiss
        $notification.find('.notice-dismiss').on('click', function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    // Utility functions
    function numberFormat(num) {
        return new Intl.NumberFormat().format(num);
    }
    
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        stopAutoRefresh();
        
        // Destroy charts
        Object.values(charts).forEach(chart => {
            if (chart) chart.destroy();
        });
    });
    
})(jQuery);

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    .spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .fmrseo-stat-card.updated {
        transform: scale(1.05);
        transition: transform 0.3s ease;
    }
    
    .fmrseo-loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
    }
    
    .fmrseo-notification {
        margin-bottom: 15px;
    }
`;
document.head.appendChild(style);