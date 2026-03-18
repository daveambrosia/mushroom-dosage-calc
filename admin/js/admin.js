/**
 * Ambrosia Dosage Calculator - Admin JS
 * Version 2.8.0
 */

(function($) {
    'use strict';

    // ========================================================================
    // A-003: TABLE SEARCH FILTER
    // ========================================================================
    
    $(document).ready(function() {
        // Add search box to Strains and Edibles list tables
        var $table = $('.adc-admin .wp-list-table').not('#adc-submissions-table');
        if ($table.length && !$('.adc-table-search').length) {
            var searchHtml = '<div class="adc-table-search" style="margin-bottom: 15px;">' +
                '<input type="search" id="adc-table-filter" placeholder="🔍 Search..." ' +
                'style="padding: 8px 12px; width: 250px; border: 1px solid #c3c4c7; border-radius: 4px;">' +
                '</div>';
            $table.before(searchHtml);
            
            // Filter table rows on input
            $('#adc-table-filter').on('input', function() {
                var query = $(this).val().toLowerCase().trim();
                $table.find('tbody tr').each(function() {
                    var $row = $(this);
                    var text = $row.text().toLowerCase();
                    var match = !query || text.indexOf(query) !== -1;
                    $row.toggle(match);
                });
                
                // Show "no results" message if needed
                var visible = $table.find('tbody tr:visible').length;
                var $noResults = $table.find('.adc-no-results');
                if (visible === 0 && query) {
                    if (!$noResults.length) {
                        var safeQuery = $('<span>').text(query).html();
                        $table.find('tbody').append(
                            '<tr class="adc-no-results"><td colspan="10" style="text-align:center; padding: 20px; color: #666;">No items match "' + safeQuery + '"</td></tr>'
                        );
                    }
                } else {
                    $noResults.remove();
                }
            });
        }
    });

    // ========================================================================
    // UTILITIES
    // ========================================================================

    /**
     * Show a temporary notice
     */
    function showNotice(message, type) {
        type = type || 'success';
        var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p></p></div>');
        $notice.find('p').text(message);
        $('.adc-admin h1').after($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Add dismiss button functionality
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(function() { $(this).remove(); });
        });
    }

    /**
     * Confirm action with custom message
     */
    async function confirmAction(message, callback, options) {
        var opts = options || { danger: true, title: "Confirm" };
        if (await adcConfirm(message, opts)) {
            callback();
        }
    }

    // ========================================================================
    // DELETE HANDLERS
    // ========================================================================

    /**
     * Handle strain deletion
     */
    $(document).on('click', '.adc-delete-strain', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var $row = $link.closest('tr');
        var id = $link.data('id');
        var name = $link.data('name');
        
        confirmAction('Delete "' + name + '"?\n\nThis will permanently remove this strain from your database.', function() {
            $row.addClass('deleting');
            $link.text('Deleting...');
            
            $.ajax({
                url: adcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'adc_delete_strain',
                    id: id,
                    nonce: adcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            updateEmptyState('strain');
                        });
                        showNotice('"' + name + '" has been deleted.');
                    } else {
                        $row.removeClass('deleting');
                        $link.text('Delete');
                        showNotice('Failed to delete: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    $row.removeClass('deleting');
                    $link.text('Delete');
                    showNotice('Network error. Please try again.', 'error');
                }
            });
        });
    });

    /**
     * Handle edible deletion
     */
    $(document).on('click', '.adc-delete-edible', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var $row = $link.closest('tr');
        var id = $link.data('id');
        var name = $link.data('name');
        
        confirmAction('Delete "' + name + '"?\n\nThis will permanently remove this edible from your database.', function() {
            $row.addClass('deleting');
            $link.text('Deleting...');
            
            $.ajax({
                url: adcAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'adc_delete_edible',
                    id: id,
                    nonce: adcAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                            updateEmptyState('edible');
                        });
                        showNotice('"' + name + '" has been deleted.');
                    } else {
                        $row.removeClass('deleting');
                        $link.text('Delete');
                        showNotice('Failed to delete: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    $row.removeClass('deleting');
                    $link.text('Delete');
                    showNotice('Network error. Please try again.', 'error');
                }
            });
        });
    });

    /**
     * Check and show empty state if no items left
     */
    function updateEmptyState(type) {
        var $table = $('.wp-list-table');
        if ($table.find('tbody tr').length === 0) {
            var addUrl = adcAdmin.adminUrl + '?page=dosage-calculator-add-' + type;
            $table.find('tbody').html(
                '<tr class="no-items"><td colspan="8">No ' + type + 's found. <a href="' + addUrl + '">Add one</a>.</td></tr>'
            );
        }
    }

    // ========================================================================
    // TABLE SEARCH & FILTER
    // ========================================================================

    /**
     * Add search box to strain/edible tables
     */
    function initTableSearch() {
        var $table = $('.adc-admin .wp-list-table');
        if ($table.length === 0 || $table.find('tbody tr').length < 5) {
            return; // Don't add search for small tables
        }
        
        // Create search box
        var $search = $('<div class="adc-table-search">' +
            '<input type="search" placeholder="Search..." class="adc-search-input">' +
            '<span class="adc-search-count"></span>' +
            '</div>');
        
        $search.css({
            'margin-bottom': '12px',
            'display': 'flex',
            'align-items': 'center',
            'gap': '10px'
        });
        
        $search.find('input').css({
            'padding': '6px 12px',
            'border': '1px solid #8c8f94',
            'border-radius': '4px',
            'width': '250px'
        });
        
        $table.before($search);
        
        // Handle search input
        var $input = $search.find('input');
        var $count = $search.find('.adc-search-count');
        
        $input.on('input', function() {
            var query = $(this).val().toLowerCase().trim();
            var total = 0;
            var visible = 0;
            
            $table.find('tbody tr').each(function() {
                var $row = $(this);
                if ($row.hasClass('no-items')) return;
                
                total++;
                var text = $row.text().toLowerCase();
                
                if (query === '' || text.indexOf(query) !== -1) {
                    $row.show();
                    visible++;
                } else {
                    $row.hide();
                }
            });
            
            // Update count
            if (query && visible !== total) {
                $count.text(visible + ' of ' + total + ' shown').css('color', '#646970');
            } else {
                $count.text('');
            }
        });
    }

    // ========================================================================
    // FORM ENHANCEMENTS
    // ========================================================================

    /**
     * Auto-generate short code from name
     */
    function initAutoShortCode() {
        var $name = $('#name');
        var $code = $('#short_code');
        
        if ($name.length && $code.length && !$code.val()) {
            $name.on('blur', function() {
                if (!$code.val() && $name.val()) {
                    // Generate slug-like code
                    var name = $name.val();
                    var code = name
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-|-$/g, '')
                        .substring(0, 20);
                    $code.attr('placeholder', 'e.g., ' + code);
                }
            });
        }
    }

    /**
     * Live mcg/piece calculation for edibles
     */
    function initMcgCalculation() {
        var $psilocybin = $('#psilocybin');
        var $psilocin = $('#psilocin');
        var $display = $('#mcg_per_piece_display');
        
        if ($psilocybin.length && $display.length) {
            function updateTotal() {
                var psilocybin = parseInt($psilocybin.val()) || 0;
                var psilocin = parseInt($psilocin.val()) || 0;
                var total = psilocybin + psilocin;
                $display.text(total.toLocaleString());
                
                // Color coding
                if (total === 0) {
                    $display.css('color', '#d63638');
                } else if (total < 1000) {
                    $display.css('color', '#dba617');
                } else {
                    $display.css('color', '#00a32a');
                }
            }
            
            $psilocybin.on('input', updateTotal);
            $psilocin.on('input', updateTotal);
            updateTotal();
        }
    }

    /**
     * Unsaved changes warning
     */
    function initUnsavedWarning() {
        var $form = $('.adc-admin form');
        if ($form.length === 0) return;
        
        var formChanged = false;
        
        $form.on('change input', 'input, select, textarea', function() {
            formChanged = true;
        });
        
        $form.on('submit', function() {
            formChanged = false;
        });
        
        $(window).on('beforeunload', function() {
            if (formChanged) {
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    }

    // ========================================================================
    // DASHBOARD ENHANCEMENTS
    // ========================================================================

    /**
     * Make dashboard cards clickable
     */
    function initClickableCards() {
        $('.adc-dashboard-cards .adc-card').each(function() {
            var $card = $(this);
            var text = $card.find('p').text().toLowerCase();
            var url = null;
            
            if (text.indexOf('strain') !== -1) {
                url = adcAdmin.adminUrl + '?page=dosage-calculator-strains';
            } else if (text.indexOf('edible') !== -1) {
                url = adcAdmin.adminUrl + '?page=dosage-calculator-edibles';
            } else if (text.indexOf('submission') !== -1) {
                url = adcAdmin.adminUrl + '?page=dosage-calculator-submissions';
            }
            
            if (url) {
                $card.css('cursor', 'pointer').on('click', function() {
                    window.location.href = url;
                });
            }
        });
    }

    // ========================================================================
    // COPY TO CLIPBOARD
    // ========================================================================

    /**
     * Copy shortcode to clipboard
     */
    $(document).on('click', '.adc-shortcode-help > code', function() {
        var $code = $(this);
        var text = $code.text();
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                var original = $code.text();
                $code.text('Copied!').css('background', '#d4edda');
                setTimeout(function() {
                    $code.text(original).css('background', '');
                }, 1500);
            });
        }
    });

    // ========================================================================
    // INITIALIZATION
    // ========================================================================

    $(document).ready(function() {
        initTableSearch();
        initAutoShortCode();
        initMcgCalculation();
        initUnsavedWarning();
        initClickableCards();
        
        // Add copy hint to shortcode
        $('.adc-shortcode-help > code').attr('title', 'Click to copy').css('cursor', 'pointer');
    });

})(jQuery);

// ============================================================================
// MODAL FUNCTIONS (Moved from inline scripts)
// ============================================================================

// Global escapeHtml function for XSS prevention
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    if (typeof str !== 'string') str = String(str);
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Show notes modal
 */
function adcShowNotesModal(notes) {
    var el = document.getElementById("adc-notes-content");
    if (el) el.textContent = notes;
    var modal = document.getElementById("adc-notes-modal");
    if (modal) modal.classList.add("show");
}

/**
 * Close notes modal
 */
function adcCloseNotesModal() {
    var modal = document.getElementById("adc-notes-modal");
    if (modal) modal.classList.remove("show");
}

/**
 * Show details modal with submission data
 */
function adcShowDetailsModal(sub) {
    var data = sub.data || {};
    var html = "";
    
    // Section 1: Sacrament Data
    html += "<div class='adc-detail-section'>";
    html += "<div class='adc-detail-section-header'>Sacrament Data</div>";
    html += "<div class='adc-detail-section-body'>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Name:</span> " + escapeHtml(data.name || "-") + "</div>";
    if (data.brand) html += "<div class='adc-detail-row'><span class='adc-detail-label'>Brand:</span> " + escapeHtml(data.brand) + "</div>";
    if (data.piecesPerPackage || data.pieces_per_package) html += "<div class='adc-detail-row'><span class='adc-detail-label'>Pieces/Package:</span> " + escapeHtml(data.piecesPerPackage || data.pieces_per_package) + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Psilocybin:</span> " + (data.psilocybin ? data.psilocybin.toLocaleString() + " mcg" : "0 mcg") + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Psilocin:</span> " + (data.psilocin ? data.psilocin.toLocaleString() + " mcg" : "0 mcg") + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Baeocystin:</span> " + (data.baeocystin ? data.baeocystin.toLocaleString() + " mcg" : "0 mcg") + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Norbaeocystin:</span> " + (data.norbaeocystin ? data.norbaeocystin.toLocaleString() + " mcg" : "0 mcg") + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Norpsilocin:</span> " + (data.norpsilocin ? data.norpsilocin.toLocaleString() + " mcg" : "0 mcg") + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Aeruginascin:</span> " + (data.aeruginascin ? data.aeruginascin.toLocaleString() + " mcg" : "0 mcg") + "</div>";
    html += "</div></div>";
    
    // Section 2: Submitter Info
    html += "<div class='adc-detail-section'>";
    html += "<div class='adc-detail-section-header'>Submitter Info</div>";
    html += "<div class='adc-detail-section-body'>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Name:</span> " + escapeHtml(sub.submitter_name || "-") + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Email:</span> " + escapeHtml(sub.submitter_email || "-") + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>IP Address:</span> " + escapeHtml(sub.ip_address || "-") + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>User Agent:</span> <span class='adc-detail-useragent'>" + escapeHtml(sub.user_agent || "-") + "</span></div>";
    if (sub.submitter_notes) html += "<div class='adc-detail-row'><span class='adc-detail-label'>Notes:</span> " + escapeHtml(sub.submitter_notes) + "</div>";
    html += "</div></div>";
    
    // Section 3: System Info
    html += "<div class='adc-detail-section'>";
    html += "<div class='adc-detail-section-header'>System Info</div>";
    html += "<div class='adc-detail-section-body'>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>ID:</span> " + escapeHtml(sub.id) + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Type:</span> " + escapeHtml(sub.type || "-") + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Status:</span> " + escapeHtml(sub.status || "-") + "</div>";
    html += "<div class='adc-detail-row'><span class='adc-detail-label'>Submitted:</span> " + escapeHtml(sub.created_at || "-") + "</div>";
    html += "</div></div>";
    
    var content = document.getElementById("adc-details-content");
    if (content) content.innerHTML = html;
    var modal = document.getElementById("adc-details-modal");
    if (modal) modal.classList.add("show");
}

/**
 * Close details modal
 */
function adcCloseDetailsModal() {
    var modal = document.getElementById("adc-details-modal");
    if (modal) modal.classList.remove("show");
}

// Global escape key handler for modals
document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        adcCloseNotesModal();
        adcCloseDetailsModal();
    }
});

// ============================================================================
// EDIBLE FORM - Total mcg calculator
// ============================================================================
jQuery(function($) {
    function updateAllCalculations() {
        var pieces = parseInt($("#pieces_per_package").val()) || 1;
        if (pieces < 1) pieces = 1;
        
        // Get all compound values
        var psilocybin = parseInt($("#psilocybin").val()) || 0;
        var psilocin = parseInt($("#psilocin").val()) || 0;
        var norpsilocin = parseInt($("#norpsilocin").val()) || 0;
        var baeocystin = parseInt($("#baeocystin").val()) || 0;
        var norbaeocystin = parseInt($("#norbaeocystin").val()) || 0;
        var aeruginascin = parseInt($("#aeruginascin").val()) || 0;
        
        // Calculate total (psilocybin + psilocin only)
        var total = psilocybin + psilocin;
        
        // Update total per package display
        $("#total_mcg_pkg").text(total.toLocaleString());
        
        // Update per-piece breakdown
        $("#pp_psilocybin").text(Math.round(psilocybin / pieces).toLocaleString());
        $("#pp_psilocin").text(Math.round(psilocin / pieces).toLocaleString());
        $("#pp_norpsilocin").text(Math.round(norpsilocin / pieces).toLocaleString());
        $("#pp_baeocystin").text(Math.round(baeocystin / pieces).toLocaleString());
        $("#pp_norbaeocystin").text(Math.round(norbaeocystin / pieces).toLocaleString());
        $("#pp_aeruginascin").text(Math.round(aeruginascin / pieces).toLocaleString());
        $("#pp_total").text(Math.round(total / pieces).toLocaleString());
    }
    
    // Attach to all relevant inputs
    if ($("#psilocybin").length) {
        $("#psilocybin, #psilocin, #norpsilocin, #baeocystin, #norbaeocystin, #aeruginascin, #pieces_per_package").on("input", updateAllCalculations);
    }
});
