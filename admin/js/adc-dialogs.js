/**
 * Ambrosia Dosage Calculator - Custom Dialog System
 * Replaces native alert(), confirm(), prompt() with styled modals
 * Version 1.0.0
 */
(function() {
    'use strict';

    // Create modal container on load
    var modalContainer = null;
    
    function ensureContainer() {
        if (modalContainer) return modalContainer;
        
        modalContainer = document.createElement('div');
        modalContainer.id = 'adc-dialog-container';
        modalContainer.innerHTML = `
            <div class="adc-dialog-overlay" id="adc-dialog-overlay"></div>
            <div class="adc-dialog" id="adc-dialog" role="dialog" aria-modal="true" aria-labelledby="adc-dialog-title">
                <div class="adc-dialog-header">
                    <span class="adc-dialog-icon" id="adc-dialog-icon"></span>
                    <h3 class="adc-dialog-title" id="adc-dialog-title"></h3>
                </div>
                <div class="adc-dialog-body">
                    <p class="adc-dialog-message" id="adc-dialog-message"></p>
                    <input type="text" class="adc-dialog-input" id="adc-dialog-input" style="display:none;">
                </div>
                <div class="adc-dialog-footer" id="adc-dialog-footer"></div>
            </div>
        `;
        document.body.appendChild(modalContainer);
        
        // Close on overlay click (for alerts only)
        document.getElementById('adc-dialog-overlay').addEventListener('click', function() {
            if (modalContainer.dataset.type === 'alert') {
                closeDialog();
            }
        });
        
        // Handle escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modalContainer.classList.contains('show')) {
                closeDialog(false);
            }
        });
        
        return modalContainer;
    }
    
    function showDialog(options) {
        ensureContainer();
        
        var dialog = document.getElementById('adc-dialog');
        var overlay = document.getElementById('adc-dialog-overlay');
        var icon = document.getElementById('adc-dialog-icon');
        var title = document.getElementById('adc-dialog-title');
        var message = document.getElementById('adc-dialog-message');
        var input = document.getElementById('adc-dialog-input');
        var footer = document.getElementById('adc-dialog-footer');
        
        // Set content
        modalContainer.dataset.type = options.type;
        dialog.className = 'adc-dialog adc-dialog-' + options.type;
        icon.textContent = options.icon || '';
        title.textContent = options.title || '';
        message.textContent = options.message || '';
        
        // Handle input for prompt
        if (options.type === 'prompt') {
            input.style.display = 'block';
            input.value = options.defaultValue || '';
            input.placeholder = options.placeholder || '';
        } else {
            input.style.display = 'none';
        }
        
        // Build buttons
        footer.innerHTML = '';
        options.buttons.forEach(function(btn, index) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'adc-dialog-btn ' + (btn.class || '');
            button.textContent = btn.text;
            button.onclick = function() {
                if (options.type === 'prompt' && btn.value) {
                    closeDialog(input.value);
                } else {
                    closeDialog(btn.value);
                }
            };
            if (btn.primary) {
                button.classList.add('adc-dialog-btn-primary');
            }
            footer.appendChild(button);
        });
        
        // Show modal
        modalContainer.classList.add('show');
        
        // Focus appropriate element
        setTimeout(function() {
            if (options.type === 'prompt') {
                input.focus();
                input.select();
            } else {
                footer.querySelector('.adc-dialog-btn-primary, .adc-dialog-btn').focus();
            }
        }, 50);
        
        // Handle enter key for prompt
        if (options.type === 'prompt') {
            input.onkeydown = function(e) {
                if (e.key === 'Enter') {
                    closeDialog(input.value);
                }
            };
        }
    }
    
    var resolveCallback = null;
    
    function closeDialog(value) {
        if (modalContainer) {
            modalContainer.classList.remove('show');
        }
        if (resolveCallback) {
            resolveCallback(value);
            resolveCallback = null;
        }
    }
    
    // Public API
    window.adcAlert = function(message, options) {
        options = options || {};
        return new Promise(function(resolve) {
            resolveCallback = resolve;
            showDialog({
                type: 'alert',
                icon: options.icon || (options.type === 'error' ? '⚠️' : options.type === 'success' ? '✓' : 'ℹ️'),
                title: options.title || (options.type === 'error' ? 'Error' : options.type === 'success' ? 'Success' : 'Notice'),
                message: message,
                buttons: [
                    { text: options.buttonText || 'OK', value: true, primary: true }
                ]
            });
        });
    };
    
    window.adcConfirm = function(message, options) {
        options = options || {};
        return new Promise(function(resolve) {
            resolveCallback = resolve;
            showDialog({
                type: 'confirm',
                icon: options.icon || '❓',
                title: options.title || 'Confirm',
                message: message,
                buttons: [
                    { text: options.cancelText || 'Cancel', value: false, class: 'adc-dialog-btn-secondary' },
                    { text: options.confirmText || 'OK', value: true, primary: true, class: options.danger ? 'adc-dialog-btn-danger' : '' }
                ]
            });
        });
    };
    
    window.adcPrompt = function(message, options) {
        options = options || {};
        return new Promise(function(resolve) {
            resolveCallback = resolve;
            showDialog({
                type: 'prompt',
                icon: options.icon || '✏️',
                title: options.title || 'Input',
                message: message,
                defaultValue: options.defaultValue || '',
                placeholder: options.placeholder || '',
                buttons: [
                    { text: options.cancelText || 'Cancel', value: null, class: 'adc-dialog-btn-secondary' },
                    { text: options.confirmText || 'OK', value: true, primary: true }
                ]
            });
        });
    };
    
    // Convenience methods
    window.adcSuccess = function(message, options) {
        return adcAlert(message, Object.assign({ type: 'success', icon: '✓', title: 'Success' }, options || {}));
    };
    
    window.adcError = function(message, options) {
        return adcAlert(message, Object.assign({ type: 'error', icon: '⚠️', title: 'Error' }, options || {}));
    };
    
    window.adcWarning = function(message, options) {
        return adcAlert(message, Object.assign({ type: 'warning', icon: '⚠️', title: 'Warning' }, options || {}));
    };
    
    // Sync versions for onclick handlers (shows dialog, returns true to allow navigation if confirmed)
    window.adcConfirmSync = function(message, options) {
        // For simple onclick="return adcConfirmSync('message')" usage
        // This is a workaround - shows confirm and prevents default, then navigates if confirmed
        event.preventDefault();
        var href = event.currentTarget.href;
        adcConfirm(message, options).then(function(confirmed) {
            if (confirmed && href) {
                window.location.href = href;
            }
        });
        return false;
    };
})();
