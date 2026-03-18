/**
 * ADC Template Builder - Edit View JS
 *
 * Handles: wp-color-picker init, accordion toggle, live preview,
 * "Start from..." template loading, dirty-state tracking,
 * template picker modal, structured controls, WCAG contrast checker.
 *
 * @since 2.14.0
 * @updated 2.15.0 — modal, range sliders, shadow/border/font controls, contrast checker
 */
(function($) {
    'use strict';

    // ---- Helpers ----
    function adcDebounce(fn, ms) {
        var timer;
        return function() {
            var args = arguments, ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function() { fn.apply(ctx, args); }, ms);
        };
    }

    // ---- Dirty-state tracking ----
    var formDirty = false;
    window.addEventListener('beforeunload', function(e) {
        if (formDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ---- Data from wp_localize_script ----
    var builderData = window.adcTemplateBuilderData || {};

    // ---- Load Built-in Template ----
    function adcLoadBuiltinTemplate(slug, skipConfirm) {
        if (!slug) return;
        if (!skipConfirm && !confirm('This will overwrite all current values with the selected template. Continue?')) {
            $('#adc_start_from').val('');
            return;
        }
        var vars = builderData.builtinTemplates ? builderData.builtinTemplates[slug] : null;
        if (!vars) return;

        // Clear all fields
        $('.adc-color-picker-input').each(function() {
            $(this).val('');
            try { $(this).wpColorPicker('color', ''); } catch(e) {}
        });
        $('.adc-tb-text-input').each(function() { $(this).val(''); });

        // Fill from template
        Object.keys(vars).forEach(function(key) {
            var postKey = 'var_' + key.replace(/-/g, '_');
            var $input = $('#' + postKey);
            if ($input.length) {
                $input.val(vars[key]);
                if (/^#[0-9a-fA-F]{3,8}$/.test(vars[key])) {
                    try { $input.wpColorPicker('color', vars[key]); } catch(e) {}
                }
            }

            // Sync range sliders
            var $slider = $('.adc-range-slider[data-target="' + postKey + '"]');
            if ($slider.length) {
                var numVal = parseFloat(vars[key]) || 0;
                $slider.val(numVal);
                $slider.siblings('.adc-range-value').text(vars[key] || '');
            }

            // Sync shadow selects
            var $shadowSelect = $('.adc-shadow-select[data-target="' + postKey + '"]');
            if ($shadowSelect.length) {
                var optionExists = $shadowSelect.find('option[value="' + vars[key] + '"]').length;
                if (optionExists) {
                    $shadowSelect.val(vars[key]);
                    $shadowSelect.siblings('.adc-shadow-custom-input').hide();
                } else {
                    $shadowSelect.val('custom');
                    $shadowSelect.siblings('.adc-shadow-custom-input').show();
                }
            }

            // Sync font selects
            var $fontSelect = $('.adc-font-select[data-target="' + postKey + '"]');
            if ($fontSelect.length) {
                var fontMatch = $fontSelect.find('option[value="' + vars[key] + '"]').length;
                $fontSelect.val(fontMatch ? vars[key] : '');
            }
        });

        $('#adc_start_from').val('');
        adcUpdatePreview();
        adcUpdateContrastCheck();
    }
    window.adcLoadBuiltinTemplate = adcLoadBuiltinTemplate;

    // ============================================================
    // LIVE PREVIEW — iframe + postMessage
    // ============================================================

    var previewIframe = document.getElementById('adc-preview-iframe');
    var previewReady = false;
    var pendingUpdate = false;
    var $previewStatus = $('#adc-preview-status');
    var $previewLoading = $('.adc-preview-loading').hide(); // hidden by default, only shown on manual refresh

    // Called when iframe signals it's ready
    function onPreviewReady() {
        previewReady = true;
        $previewStatus.text('Ready');
        $previewLoading.hide();
        // Send current values immediately
        adcSendPreviewVars();
    }

    // Collect all current form values and send to iframe
    function adcSendPreviewVars() {
        if (!previewIframe || !previewReady) {
            pendingUpdate = true;
            return;
        }

        var vars = {};

        // Collect color picker values
        $('.adc-color-picker-input').each(function() {
            var key = $(this).data('key');
            var val = $(this).val();
            if (key && val && /^#[0-9a-fA-F]{3,8}$/.test(val)) {
                vars[key] = val;
            }
        });

        // Collect text input values (layout, fonts, shadows, borders)
        $('.adc-tb-text-input').each(function() {
            var key = $(this).data('key');
            var val = $(this).val();
            if (key && val) {
                vars[key] = val;
            }
        });

        try {
            previewIframe.contentWindow.postMessage({
                type: 'adc_preview_vars',
                vars: vars,
            }, window.location.origin);
        } catch(e) {
            console.warn('ADC preview postMessage failed:', e);
        }
    }

    // Expose globally for "Start from..." loader to call after filling values
    window.adcUpdatePreview = adcSendPreviewVars;

    var debouncedPreview = adcDebounce(adcSendPreviewVars, 150);

    // Listen for iframe messages (ready signal + auto-height)
    window.addEventListener('message', function(e) {
        if (e.origin !== window.location.origin) return;
        if (!e.data) return;

        // Auto-size iframe to content height
        if (e.data.type === 'adc_preview_height' && e.data.height) {
            var $iframe = $('#adc-preview-iframe');
            if ($iframe.length) {
                $iframe.css('height', e.data.height + 'px');
            }
        }

        if (e.data.type !== 'adc_preview_ready') return;
        onPreviewReady();
        if (pendingUpdate) {
            pendingUpdate = false;
            adcSendPreviewVars();
        }
    });

    // Iframe load event fallback (in case postMessage ready signal is missed)
    if (previewIframe) {
        previewIframe.addEventListener('load', function() {
            // Hide loading overlay as soon as iframe content loads
            $previewLoading.hide();
            // If postMessage hasn't fired yet, mark ready after a short delay
            setTimeout(function() {
                if (!previewReady) {
                    onPreviewReady();
                }
            }, 300);
        });
    }

    // ---- WCAG Contrast Checker ----
    function adcHexToRgb(hex) {
        hex = hex.replace('#', '');
        if (hex.length === 3) hex = hex.split('').map(function(c){ return c+c; }).join('');
        var r = parseInt(hex.substr(0,2),16);
        var g = parseInt(hex.substr(2,2),16);
        var b = parseInt(hex.substr(4,2),16);
        return isNaN(r) ? null : {r:r, g:g, b:b};
    }

    function adcRelativeLuminance(rgb) {
        var vals = [rgb.r, rgb.g, rgb.b].map(function(v) {
            v = v / 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * vals[0] + 0.7152 * vals[1] + 0.0722 * vals[2];
    }

    function adcContrastRatio(hex1, hex2) {
        var rgb1 = adcHexToRgb(hex1);
        var rgb2 = adcHexToRgb(hex2);
        if (!rgb1 || !rgb2) return null;
        var l1 = adcRelativeLuminance(rgb1);
        var l2 = adcRelativeLuminance(rgb2);
        var lighter = Math.max(l1, l2);
        var darker = Math.min(l1, l2);
        return (lighter + 0.05) / (darker + 0.05);
    }

    var adcContrastPairs = [
        { fg: 'text',            bg: 'bg',             label: 'Body text' },
        { fg: 'header-text',     bg: 'header-bg',      label: 'Header' },
        { fg: 'tab-text',        bg: 'tab-bg',         label: 'Inactive tab' },
        { fg: 'tab-active-text', bg: 'tab-active-bg',  label: 'Active tab' },
        { fg: 'btn-primary-text',bg: 'btn-primary-bg', label: 'Primary button' },
        { fg: 'btn-text',        bg: 'btn-bg',         label: 'Button' },
    ];

    function adcUpdateContrastCheck() {
        var $results = $('#adc-contrast-results');
        if (!$results.length) return;

        var colors = {};
        $('.adc-color-picker-input').each(function() {
            var key = $(this).data('key');
            if (key) colors[key] = $(this).val();
        });

        var html = '<table class="adc-contrast-table">';
        html += '<thead><tr><th>Pair</th><th>Ratio</th><th>AA</th><th>AAA</th></tr></thead><tbody>';

        var anyFail = false;
        var anyPair = false;
        adcContrastPairs.forEach(function(pair) {
            var fg = colors[pair.fg];
            var bg = colors[pair.bg];
            if (!fg || !bg) return;
            var ratio = adcContrastRatio(fg, bg);
            if (!ratio) return;
            anyPair = true;
            var ratioStr = ratio.toFixed(2) + ':1';
            var passAA  = ratio >= 4.5;
            var passAAA = ratio >= 7.0;
            if (!passAA) anyFail = true;
            html += '<tr class="' + (passAA ? 'adc-contrast-pass' : 'adc-contrast-fail') + '">';
            html += '<td>' + pair.label + '</td>';
            html += '<td><strong>' + ratioStr + '</strong></td>';
            html += '<td>' + (passAA  ? '✅' : '❌') + '</td>';
            html += '<td>' + (passAAA ? '✅' : '—') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        if (!anyPair) {
            $results.html('<p style="color:#888;font-size:12px;">Set foreground and background colors to see contrast ratios.</p>');
            return;
        }

        if (anyFail) {
            html = '<div class="adc-contrast-warning">⚠️ Some color pairs may be hard to read. Check highlighted rows.</div>' + html;
        }

        $results.html(html);
    }

    // ---- DOM Ready ----
    $(document).ready(function() {

        // ---- Template Picker Modal (list view) ----
        var $modal = $('#adc-picker-modal');
        var $createBtn = $('#adc-create-new-btn');
        var baseNewUrl = builderData.newTemplateUrl || '';

        if ($createBtn.length && $modal.length) {
            $createBtn.on('click', function() {
                $modal.fadeIn(150);
                $modal.find('.adc-picker-card:first').focus();
            });

            $(document).on('click', '.adc-modal-close, .adc-modal-overlay', function(e) {
                if ($(e.target).hasClass('adc-modal-overlay') || $(e.target).hasClass('adc-modal-close') || $(e.target).closest('.adc-modal-close').length) {
                    $modal.fadeOut(150);
                }
            });

            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $modal.is(':visible')) {
                    $modal.fadeOut(150);
                }
            });

            $(document).on('click keydown', '.adc-picker-card', function(e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
                if (e.type === 'keydown') e.preventDefault();
                var slug = $(this).data('slug');
                var url = slug ? baseNewUrl + '&start_from=' + slug : baseNewUrl;
                window.location.href = url;
            });
        }

        // ---- Initialize wp-color-picker ----
        if ($.fn.wpColorPicker) {
            $('.adc-color-picker-input').wpColorPicker({
                change: function(event, ui) {
                    setTimeout(function() {
                        debouncedPreview();
                        adcUpdateContrastCheck();
                        formDirty = true;
                    }, 10);
                },
                clear: function() {
                    debouncedPreview();
                    adcUpdateContrastCheck();
                    formDirty = true;
                }
            });
        }

        // ---- Clear buttons ----
        $(document).on('click', '.adc-tb-clear', function() {
            var target = $(this).data('target');
            var $input = $('#' + target);
            $input.val('');
            try { $input.wpColorPicker('color', ''); } catch(e) {}
            $input.closest('.wp-picker-container').find('.wp-color-result').css('background-color', '');
            debouncedPreview();
            adcUpdateContrastCheck();
            formDirty = true;
        });

        // ---- Text inputs: live preview on change ----
        $(document).on('input change', '.adc-tb-text-input', function() {
            debouncedPreview();
            formDirty = true;
        });

        // ---- Range sliders ----
        $(document).on('input', '.adc-range-slider', function() {
            var unit = $(this).data('unit') || '';
            var val = $(this).val() + unit;
            var target = $(this).data('target');
            $(this).siblings('.adc-range-value').text(val);
            $('#' + target).val(val).trigger('change');
        });

        // ---- Shadow select ----
        $(document).on('change', '.adc-shadow-select', function() {
            var val = $(this).val();
            var $custom = $(this).siblings('.adc-shadow-custom-input');
            var target = $(this).data('target');
            if (val === 'custom') {
                $custom.show().focus();
            } else {
                $custom.hide();
                $('#' + target).val(val).trigger('change');
            }
            debouncedPreview();
            formDirty = true;
        });

        // ---- Border quick-set buttons ----
        $(document).on('click', '.adc-border-quick-btn', function() {
            var val = $(this).data('value');
            var target = $(this).data('target');
            $('#' + target).val(val).trigger('change');
            debouncedPreview();
            formDirty = true;
        });

        // ---- Font family select ----
        $(document).on('change', '.adc-font-select', function() {
            var val = $(this).val();
            var target = $(this).data('target');
            if (val) {
                $('#' + target).val(val).trigger('change');
            }
            debouncedPreview();
            formDirty = true;
        });

        // ---- Dirty-state: form submit clears dirty flag ----
        var $builderForm = $('#adc-template-builder-form');
        if ($builderForm.length) {
            $builderForm.on('input change', function() { formDirty = true; });
            $builderForm.on('submit', function() { formDirty = false; });
        }

        // ---- Accordion toggle ----
        $(document).on('click', '.adc-accordion-header', function() {
            var $section = $(this).closest('.adc-accordion-section');
            var $body = $section.find('.adc-accordion-body');
            var isOpen = $section.attr('data-open') === 'true';

            if (isOpen) {
                $body.slideUp(150);
                $section.attr('data-open', 'false');
                $(this).attr('aria-expanded', 'false');
                $(this).find('.adc-accordion-arrow').text('▸');
            } else {
                $body.slideDown(150);
                $section.attr('data-open', 'true');
                $(this).attr('aria-expanded', 'true');
                $(this).find('.adc-accordion-arrow').text('▾');
            }

            var sectionKey = $section.data('section');
            try {
                var states = JSON.parse(localStorage.getItem('adc_accordion_states') || '{}');
                states[sectionKey] = !isOpen;
                localStorage.setItem('adc_accordion_states', JSON.stringify(states));
            } catch(e) {}
        });

        // ---- Restore saved accordion states ----
        try {
            var states = JSON.parse(localStorage.getItem('adc_accordion_states') || '{}');
            Object.keys(states).forEach(function(key) {
                var $section = $('[data-section="' + key + '"]');
                if ($section.length) {
                    if (states[key]) {
                        $section.find('.adc-accordion-body').show();
                        $section.attr('data-open', 'true');
                        $section.find('.adc-accordion-header').attr('aria-expanded', 'true').find('.adc-accordion-arrow').text('▾');
                    } else {
                        $section.find('.adc-accordion-body').hide();
                        $section.attr('data-open', 'false');
                        $section.find('.adc-accordion-header').attr('aria-expanded', 'false').find('.adc-accordion-arrow').text('▸');
                    }
                }
            });
        } catch(e) {}

        // ---- Preview background toggle ----
        $(document).on('click', '.adc-preview-bg-btn', function() {
            var bg = $(this).data('bg');
            $('.adc-preview-iframe-wrapper').css('background', bg);
            $('.adc-preview-bg-btn').removeClass('active');
            $(this).addClass('active');
        });

                // ---- Preview refresh button ----
        $('#adc-preview-refresh-btn').on('click', function() {
            previewReady = false;
            $previewStatus.text('Reloading…');
            $previewLoading.show();
            if (previewIframe) {
                previewIframe.src = previewIframe.src;
            }
        });

        // ---- Wire up form inputs to trigger preview updates ----
        $(document).on('input change', '.adc-color-picker-input', function() {
            debouncedPreview();
        });
        $(document).on('input change', '.adc-tb-text-input', function() {
            debouncedPreview();
        });

        // ---- Initial contrast check ----
        setTimeout(adcUpdateContrastCheck, 500);

        // ---- Auto-load from start_from URL param ----
        var startFromSlug = builderData.startFromSlug || '';
        var currentAction = builderData.currentAction || '';
        if (startFromSlug && currentAction === 'new') {
            var $startFromSelect = $('#adc_start_from');
            if ($startFromSelect.length) {
                $startFromSelect.val(startFromSlug);
                adcLoadBuiltinTemplate(startFromSlug, true);
            }
        }
    });

})(jQuery);
