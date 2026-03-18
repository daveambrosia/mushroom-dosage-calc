<?php
/**
 * QR Code Generator - Version 2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ADC_QR_Generator {
    
    /**
     * Render the QR generator page
     */
    public static function render_page() {
        $strains = ADC_Strains::get_all(array('active_only' => true, 'orderby' => 'name'));
        $edibles = ADC_Edibles::get_all(array('active_only' => true, 'orderby' => 'name'));
        
        ?>
        <div class="wrap adc-admin adc-qr-generator">
            <h1>QR Code Generator</h1>
            
            <div class="adc-qr-layout">
                <div class="adc-qr-controls">
                    <h2>Select Items</h2>
                    
                    <div class="adc-qr-tabs">
                        <button type="button" class="adc-qr-tab active" data-tab="strains">Strains</button>
                        <button type="button" class="adc-qr-tab" data-tab="edibles">Edibles</button>
                    </div>
                    
                    <div class="adc-qr-tab-content active" id="adc-qr-strains">
                        <div class="adc-qr-search">
                            <input type="text" id="adc-strain-search" placeholder="Search strains...">
                        </div>
                        <div class="adc-qr-list" id="adc-strain-list">
                            <?php foreach ($strains as $strain): ?>
                                <label class="adc-qr-item" data-name="<?php echo esc_attr(strtolower($strain['name'])); ?>">
                                    <input type="checkbox" name="strains[]" value="<?php echo esc_attr($strain['short_code']); ?>" 
                                           data-name="<?php echo esc_attr($strain['name']); ?>"
                                           data-code="<?php echo esc_attr($strain['short_code']); ?>">
                                    <span class="adc-qr-item-name"><?php echo esc_html($strain['name']); ?></span>
                                    <span class="adc-qr-item-code"><?php echo esc_html($strain['short_code']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="adc-qr-tab-content" id="adc-qr-edibles" style="display: none;">
                        <div class="adc-qr-search">
                            <input type="text" id="adc-edible-search" placeholder="Search edibles...">
                        </div>
                        <div class="adc-qr-list" id="adc-edible-list">
                            <?php foreach ($edibles as $edible): ?>
                                <label class="adc-qr-item" data-name="<?php echo esc_attr(strtolower($edible['name'])); ?>">
                                    <input type="checkbox" name="edibles[]" value="<?php echo esc_attr($edible['short_code']); ?>"
                                           data-name="<?php echo esc_attr($edible['name']); ?>"
                                           data-code="<?php echo esc_attr($edible['short_code']); ?>">
                                    <span class="adc-qr-item-name"><?php echo esc_html($edible['name']); ?></span>
                                    <span class="adc-qr-item-code"><?php echo esc_html($edible['short_code']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="adc-qr-options">
                        <h3>QR Code Options</h3>
                        <table class="form-table">
                            <tr>
                                <th><label for="qr-size">Size</label></th>
                                <td>
                                    <select id="qr-size">
                                        <option value="128">Small (128px)</option>
                                        <option value="256" selected>Medium (256px)</option>
                                        <option value="512">Large (512px)</option>
                                        <option value="1024">Extra Large (1024px)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="qr-format">URL Format</label></th>
                                <td>
                                    <select id="qr-format">
                                        <option value="short" selected>Short URL (recommended)</option>
                                        <option value="legacy">Legacy URL (for external producers)</option>
                                    </select>
                                    <p class="description">Short URLs create smaller QR codes.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="qr-include-label">Include Label</label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="qr-include-label" checked>
                                        Add name and code below QR
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="adc-qr-actions">
                        <button type="button" class="button button-primary" id="adc-generate-qr">Generate QR Codes</button>
                        <button type="button" class="button" id="adc-download-all" style="display: none;">Download All (ZIP)</button>
                    </div>
                </div>
                
                <div class="adc-qr-preview">
                    <h2>Preview</h2>
                    <div class="adc-qr-preview-area" id="adc-qr-preview-area">
                        <p class="adc-qr-placeholder">Select items and click "Generate QR Codes" to preview.</p>
                    </div>
                </div>
            </div>
            
            <div class="adc-qr-legacy-section">
                <h2>Generate Legacy URL for External Producers</h2>
                <p>Use this to generate QR codes for producers who aren't in your database yet. The QR will contain all the compound data directly in the URL.</p>
                
                <div class="adc-legacy-form">
                    <table class="form-table">
                        <tr>
                            <th><label for="legacy-type">Type</label></th>
                            <td>
                                <select id="legacy-type">
                                    <option value="strain">Strain</option>
                                    <option value="edible">Edible</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="legacy-name">Name *</label></th>
                            <td><input type="text" id="legacy-name" class="regular-text" placeholder="e.g., Local Golden Teacher"></td>
                        </tr>
                        <tr class="legacy-strain-field">
                            <th><label for="legacy-psilocybin">Psilocybin (mcg/g) *</label></th>
                            <td><input type="number" id="legacy-psilocybin" min="0" placeholder="e.g., 7000"></td>
                        </tr>
                        <tr class="legacy-strain-field">
                            <th><label for="legacy-psilocin">Psilocin (mcg/g)</label></th>
                            <td><input type="number" id="legacy-psilocin" min="0" placeholder="e.g., 800"></td>
                        </tr>
                        <tr class="legacy-edible-field" style="display: none;">
                            <th><label for="legacy-total-mg">Total mg *</label></th>
                            <td><input type="number" id="legacy-total-mg" min="0" placeholder="e.g., 2000"></td>
                        </tr>
                        <tr class="legacy-edible-field" style="display: none;">
                            <th><label for="legacy-pieces">Pieces per Package *</label></th>
                            <td><input type="number" id="legacy-pieces" min="1" placeholder="e.g., 4"></td>
                        </tr>
                    </table>
                    
                    <button type="button" class="button button-primary" id="adc-generate-legacy">Generate Legacy QR</button>
                    
                    <div class="adc-legacy-result" id="adc-legacy-result" style="display: none;">
                        <div class="adc-legacy-qr" id="adc-legacy-qr"></div>
                        <div class="adc-legacy-url">
                            <label>URL:</label>
                            <input type="text" id="adc-legacy-url" readonly class="large-text">
                            <button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('adc-legacy-url').value)">Copy</button>
                        </div>
                        <button type="button" class="button" id="adc-download-legacy">Download QR</button>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            const baseUrl = '<?php echo esc_js(home_url('/' . ADC_DB::get_setting('short_url_path', 'c') . '/')); ?>';
            const calcUrl = '<?php echo esc_js(ADC_QR_Handler::get_calculator_page_url()); ?>';
            
            // Tab switching
            $('.adc-qr-tab').on('click', function() {
                const tab = $(this).data('tab');
                $('.adc-qr-tab').removeClass('active');
                $(this).addClass('active');
                $('.adc-qr-tab-content').hide();
                $('#adc-qr-' + tab).show();
            });
            
            // Search filtering
            $('#adc-strain-search').on('input', function() {
                const search = $(this).val().toLowerCase();
                $('#adc-strain-list .adc-qr-item').each(function() {
                    const name = $(this).data('name');
                    $(this).toggle(name.indexOf(search) !== -1);
                });
            });
            
            $('#adc-edible-search').on('input', function() {
                const search = $(this).val().toLowerCase();
                $('#adc-edible-list .adc-qr-item').each(function() {
                    const name = $(this).data('name');
                    $(this).toggle(name.indexOf(search) !== -1);
                });
            });
            
            // Generate QR codes
            $('#adc-generate-qr').on('click', function() {
                const selected = [];
                $('input[name="strains[]"]:checked, input[name="edibles[]"]:checked').each(function() {
                    selected.push({
                        code: $(this).data('code'),
                        name: $(this).data('name'),
                        type: $(this).attr('name').replace('[]', '')
                    });
                });
                
                if (selected.length === 0) {
                    adcError('Please select at least one item.');
                    return;
                }
                
                const size = parseInt($('#qr-size').val());
                const format = $('#qr-format').val();
                const includeLabel = $('#qr-include-label').is(':checked');
                
                const $preview = $('#adc-qr-preview-area');
                $preview.html('');
                
                selected.forEach(function(item) {
                    const url = format === 'short' ? baseUrl + item.code : calcUrl + '?code=' + item.code + '&type=' + item.type.replace('s', '');
                    
                    const sizeLabel = $('#qr-size option:selected').text();
                    const $container = $('<div class="adc-qr-item-preview"></div>');
                    const $canvas = $('<canvas></canvas>');
                    $container.append($canvas);
                    
                    $container.append('<div class="adc-qr-size-label">' + sizeLabel + '</div>');
                    
                    if (includeLabel) {
                        $container.append('<div class="adc-qr-label"><strong>' + item.name + '</strong><br>' + item.code + '</div>');
                    }
                    
                    $preview.append($container);
                    
                    if (typeof QRCode !== 'undefined') {
                        QRCode.toCanvas($canvas[0], url, { width: size }, function(error) {
                            if (error) console.error(error);
                        });
                    }
                    
                    // Add download button
                    const $download = $('<button class="button">Download</button>');
                    $download.on('click', function() {
                        const link = document.createElement('a');
                        link.download = item.code + '.png';
                        link.href = $canvas[0].toDataURL();
                        link.click();
                    });
                    $container.append($download);
                });
                
                if (selected.length > 1) {
                    $('#adc-download-all').show();
                }
            });
            
            // Legacy type toggle
            $('#legacy-type').on('change', function() {
                const isEdible = $(this).val() === 'edible';
                $('.legacy-strain-field').toggle(!isEdible);
                $('.legacy-edible-field').toggle(isEdible);
            });
            
            // Generate legacy QR
            $('#adc-generate-legacy').on('click', function() {
                const type = $('#legacy-type').val();
                const name = $('#legacy-name').val().trim();
                
                if (!name) {
                    adcError('Please enter a name.');
                    return;
                }
                
                let url;
                if (type === 'strain') {
                    const psilocybin = $('#legacy-psilocybin').val();
                    if (!psilocybin) {
                        adcError('Please enter psilocybin value.');
                        return;
                    }
                    const psilocin = $('#legacy-psilocin').val() || 0;
                    url = calcUrl + '?data=' + encodeURIComponent('name:' + name + ',psilocybin:' + psilocybin + ',psilocin:' + psilocin);
                } else {
                    const totalMg = $('#legacy-total-mg').val();
                    const pieces = $('#legacy-pieces').val();
                    if (!totalMg || !pieces) {
                        adcError('Please enter total mg and pieces.');
                        return;
                    }
                    url = calcUrl + '?type=edible&name=' + encodeURIComponent(name) + '&total_mg=' + totalMg + '&pieces=' + pieces;
                }
                
                $('#adc-legacy-url').val(url);
                $('#adc-legacy-result').show();
                
                const $qrContainer = $('#adc-legacy-qr');
                $qrContainer.html('<canvas id="legacy-canvas"></canvas>');
                
                if (typeof QRCode !== 'undefined') {
                    QRCode.toCanvas(document.getElementById('legacy-canvas'), url, { width: 256 }, function(error) {
                        if (error) console.error(error);
                    });
                }
            });
            
            // Download legacy QR
            $('#adc-download-legacy').on('click', function() {
                const canvas = document.getElementById('legacy-canvas');
                const link = document.createElement('a');
                link.download = 'qr-' + $('#legacy-name').val().replace(/\s+/g, '-').toLowerCase() + '.png';
                link.href = canvas.toDataURL();
                link.click();
            });
        });
        </script>
        
        <?php
    }
}
