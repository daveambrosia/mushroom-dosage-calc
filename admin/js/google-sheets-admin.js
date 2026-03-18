/**
 * Google Sheets Admin — Preview & Import AJAX
 * @since 2.12.0
 */
(function($) {
    'use strict';

    const API_BASE = adcSheetsAdmin.restUrl;
    const NONCE = adcSheetsAdmin.nonce;

    /**
     * Make a REST API request.
     */
    function apiRequest(endpoint, data, method) {
        return $.ajax({
            url: API_BASE + endpoint,
            method: method || 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', NONCE);
            },
            data: JSON.stringify(data),
            contentType: 'application/json',
        });
    }

    /**
     * Show a status message.
     */
    function showStatus(containerId, type, message) {
        const $container = $(containerId);
        $container.html(
            '<div class="notice notice-' + type + ' inline"><p>' + message + '</p></div>'
        ).show();
    }

    /**
     * Build a preview table from headers and rows.
     */
    function buildPreviewTable(data, dbType) {
        if (!data.headers || !data.rows) return '<p>No data found.</p>';

        let html = '<p><strong>' + data.total_count + ' total rows</strong> in sheet (showing first ' + data.rows.length + '):</p>';

        // Column mapping info
        if (data.column_map) {
            html += '<div class="adc-column-map">';
            html += '<h4>Detected Column Mapping:</h4><ul>';
            for (const [dbCol, sheetCol] of Object.entries(data.column_map)) {
                html += '<li><code>' + dbCol + '</code> ← "' + sheetCol + '"</li>';
            }
            html += '</ul>';
            const unmapped = data.headers.filter(h => !Object.values(data.column_map).includes(h));
            if (unmapped.length) {
                html += '<p class="description">Unmapped columns (ignored): ' + unmapped.join(', ') + '</p>';
            }
            html += '</div>';
        }

        // Data table
        html += '<table class="widefat striped"><thead><tr>';
        data.headers.forEach(function(h) {
            const mapped = data.column_map && Object.values(data.column_map).includes(h);
            html += '<th' + (mapped ? ' style="background:#e7f5e7"' : '') + '>' + h + '</th>';
        });
        html += '</tr></thead><tbody>';

        data.rows.forEach(function(row) {
            html += '<tr>';
            data.headers.forEach(function(h) {
                html += '<td>' + (row[h] || '') + '</td>';
            });
            html += '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    // ---- Preview Buttons ----

    $('#adc-preview-strains').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const url = $('#strains_url').val();

        if (!url) {
            showStatus('#adc-strains-preview-status', 'error', 'Please enter a Google Sheets URL first.');
            return;
        }

        $btn.prop('disabled', true).text('Loading...');
        $('#adc-strains-preview').empty();
        $('#adc-strains-preview-status').empty();

        apiRequest('preview', {
            url: url,
            gid: $('#strains_gid').val(),
            type: 'strain'
        }).done(function(resp) {
            if (resp.success) {
                $('#adc-strains-preview').html(buildPreviewTable(resp.data, 'strain')).show();
            } else {
                showStatus('#adc-strains-preview-status', 'error', resp.message || 'Preview failed.');
            }
        }).fail(function(xhr) {
            const msg = xhr.responseJSON?.message || 'Request failed.';
            showStatus('#adc-strains-preview-status', 'error', msg);
        }).always(function() {
            $btn.prop('disabled', false).text('Preview');
        });
    });

    $('#adc-preview-edibles').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const url = $('#edibles_url').val();

        if (!url) {
            showStatus('#adc-edibles-preview-status', 'error', 'Please enter a Google Sheets URL first.');
            return;
        }

        $btn.prop('disabled', true).text('Loading...');
        $('#adc-edibles-preview').empty();
        $('#adc-edibles-preview-status').empty();

        apiRequest('preview', {
            url: url,
            gid: $('#edibles_gid').val(),
            type: 'edible'
        }).done(function(resp) {
            if (resp.success) {
                $('#adc-edibles-preview').html(buildPreviewTable(resp.data, 'edible')).show();
            } else {
                showStatus('#adc-edibles-preview-status', 'error', resp.message || 'Preview failed.');
            }
        }).fail(function(xhr) {
            const msg = xhr.responseJSON?.message || 'Request failed.';
            showStatus('#adc-edibles-preview-status', 'error', msg);
        }).always(function() {
            $btn.prop('disabled', false).text('Preview');
        });
    });

    // ---- Import Buttons ----

    $('#adc-import-strains').on('click', function(e) {
        e.preventDefault();
        const url = $('#strains_url').val();
        if (!url) {
            showStatus('#adc-strains-import-status', 'error', 'Enter a Google Sheets URL first.');
            return;
        }

        const mode = $('#import_mode').val();
        if (mode === 'replace' && !confirm('Replace mode will deactivate ALL existing strains before importing. Continue?')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text('Importing...');
        $('#adc-strains-import-status').empty();

        apiRequest('import', {
            url: url,
            gid: $('#strains_gid').val(),
            type: 'strain',
            mode: mode
        }).done(function(resp) {
            if (resp.success) {
                const d = resp.data;
                let msg = 'Import complete! ';
                msg += d.imported + ' imported, ';
                msg += d.updated + ' updated, ';
                msg += d.skipped + ' skipped.';
                if (d.deactivated) msg += ' ' + d.deactivated + ' deactivated.';
                if (d.errors && d.errors.length) {
                    msg += '<br>Errors: ' + d.errors.join('<br>');
                }
                showStatus('#adc-strains-import-status', d.errors?.length ? 'warning' : 'success', msg);
            } else {
                showStatus('#adc-strains-import-status', 'error', resp.message || 'Import failed.');
            }
        }).fail(function(xhr) {
            showStatus('#adc-strains-import-status', 'error', xhr.responseJSON?.message || 'Request failed.');
        }).always(function() {
            $btn.prop('disabled', false).text('Import Strains Now');
        });
    });

    $('#adc-import-edibles').on('click', function(e) {
        e.preventDefault();
        const url = $('#edibles_url').val();
        if (!url) {
            showStatus('#adc-edibles-import-status', 'error', 'Enter a Google Sheets URL first.');
            return;
        }

        const mode = $('#import_mode').val();
        if (mode === 'replace' && !confirm('Replace mode will deactivate ALL existing edibles before importing. Continue?')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text('Importing...');
        $('#adc-edibles-import-status').empty();

        apiRequest('import', {
            url: url,
            gid: $('#edibles_gid').val(),
            type: 'edible',
            mode: mode
        }).done(function(resp) {
            if (resp.success) {
                const d = resp.data;
                let msg = 'Import complete! ';
                msg += d.imported + ' imported, ';
                msg += d.updated + ' updated, ';
                msg += d.skipped + ' skipped.';
                if (d.deactivated) msg += ' ' + d.deactivated + ' deactivated.';
                if (d.errors && d.errors.length) {
                    msg += '<br>Errors: ' + d.errors.join('<br>');
                }
                showStatus('#adc-edibles-import-status', d.errors?.length ? 'warning' : 'success', msg);
            } else {
                showStatus('#adc-edibles-import-status', 'error', resp.message || 'Import failed.');
            }
        }).fail(function(xhr) {
            showStatus('#adc-edibles-import-status', 'error', xhr.responseJSON?.message || 'Request failed.');
        }).always(function() {
            $btn.prop('disabled', false).text('Import Edibles Now');
        });
    });

    // ---- Auto-sync toggle visibility ----
    function toggleSyncOptions() {
        const checked = $('#auto_sync').is(':checked');
        $('.adc-sync-options').toggle(checked);
    }
    $('#auto_sync').on('change', toggleSyncOptions);
    toggleSyncOptions();

})(jQuery);
