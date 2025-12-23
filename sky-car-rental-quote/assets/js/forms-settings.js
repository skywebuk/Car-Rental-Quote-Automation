/**
 * Car Rental Quotes - Forms Settings JavaScript
 * Modern ES6+ implementation with enhanced error handling and UX
 * File: /wp-content/plugins/car-rental-quote-automation/assets/js/forms-settings.js
 * 
 * @package CarRentalQuoteAutomation
 * @since 2.2.0
 */

(function($) {
    'use strict';

    /**
     * Forms Settings Manager
     */
    class FormsSettingsManager {
        constructor() {
            this.formConfigIndex = window.crqaFormsCount || 0;
            this.handlers = window.crqaHandlers || { wpforms: 'WPForms' };
            this.ajaxUrl = window.ajaxurl || '/wp-admin/admin-ajax.php';
            this.nonces = {
                getForms: window.crqaSettings?.nonce_get_forms || '',
                getFields: window.crqaSettings?.nonce_get_fields || ''
            };
            
            this.cache = {
                forms: new Map(),
                fields: new Map()
            };
            
            this.templates = {
                loading: '<div class="crqa-loading"><span class="spinner is-active"></span> Loading...</div>',
                error: '<div class="crqa-error">%message%</div>'
            };
            
            this.init();
        }

        /**
         * Initialize the manager
         */
        init() {
            console.log('CRQA Forms Settings Manager initialized');
            this.bindEvents();
            this.initializeSortable();
            this.loadExistingForms();
        }

        /**
         * Bind all events
         */
        bindEvents() {
            // Add new form configuration
            $(document).on('click', '#add-form-config', this.handleAddForm.bind(this));
            
            // Remove form configuration
            $(document).on('click', '.remove-form-config', this.handleRemoveForm.bind(this));
            
            // Handler selection change
            $(document).on('change', '.handler-selector', this.handleHandlerChange.bind(this));
            
            // Form selection change
            $(document).on('change', '.form-selector', this.handleFormChange.bind(this));
            
            // Vehicle source radio change
            $(document).on('change', 'input[name*="[vehicle_source]"]', this.handleVehicleSourceChange.bind(this));
            
            // Product ID source radio change
            $(document).on('change', 'input[name*="[product_id_source]"]', this.handleProductIdSourceChange.bind(this));
            
            // Form submission validation
            $('form').on('submit', this.validateFormSubmission.bind(this));
            
            // Auto-save draft
            this.initializeAutoSave();
        }

        /**
         * Initialize sortable functionality
         */
        initializeSortable() {
            if ($.fn.sortable) {
                $('#forms-container').sortable({
                    handle: '.form-config-header',
                    placeholder: 'form-config-placeholder',
                    start: (e, ui) => {
                        ui.placeholder.height(ui.item.height());
                    },
                    update: () => {
                        this.reindexForms();
                        this.showNotification('Form order updated', 'info');
                    }
                });
            }
        }

        /**
         * Load existing form configurations
         */
        loadExistingForms() {
            $('.handler-selector').each((index, element) => {
                const $selector = $(element);
                if ($selector.val()) {
                    // Force WPForms and trigger change
                    $selector.val('wpforms').trigger('change');
                }
            });
        }

        /**
         * Handle adding new form configuration
         */
        handleAddForm(e) {
            e.preventDefault();
            
            console.log('Adding new form configuration');
            
            const newFormHtml = this.createFormConfigHtml(this.formConfigIndex);
            const $newForm = $(newFormHtml).hide();
            
            $('#forms-container').append($newForm);
            $newForm.fadeIn(300);
            
            // Auto-select WPForms and load forms
            $newForm.find('.handler-selector').val('wpforms').trigger('change');
            
            this.formConfigIndex++;
            
            // Scroll to new form
            this.scrollToElement($newForm);
            
            // Show success notification
            this.showNotification('New form configuration added', 'success');
        }

        /**
         * Handle removing form configuration
         */
        handleRemoveForm(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const $formItem = $button.closest('.form-config-item');
            
            // Show confirmation dialog
            this.showConfirmDialog(
                'Remove Form Configuration',
                'Are you sure you want to remove this form configuration? This action cannot be undone.',
                () => {
                    $formItem.fadeOut(300, () => {
                        $formItem.remove();
                        this.reindexForms();
                        this.showNotification('Form configuration removed', 'info');
                    });
                }
            );
        }

        /**
         * Handle form handler change
         */
        async handleHandlerChange(e) {
            const $select = $(e.currentTarget);
            const $container = $select.closest('.form-config-item');
            const handlerId = $select.val();
            const $formSelector = $container.find('.form-selector');
            const savedFormId = $formSelector.attr('data-saved-value');
            
            console.log(`Handler changed to: ${handlerId}`);
            
            // Clear and disable form selector
            $formSelector.html('<option value="">— Loading forms... —</option>').prop('disabled', true);
            $container.find('.field-mappings').empty();
            
            if (!handlerId) {
                $formSelector.html('<option value="">— Select Form Plugin First —</option>').prop('disabled', false);
                return;
            }
            
            try {
                const forms = await this.loadHandlerForms(handlerId);
                this.populateFormSelector($formSelector, forms, savedFormId);
                
                // Auto-select saved form if exists
                if (savedFormId && $formSelector.find(`option[value="${savedFormId}"]`).length) {
                    $formSelector.val(savedFormId).trigger('change');
                }
            } catch (error) {
                console.error('Error loading forms:', error);
                $formSelector.html('<option value="">— Error Loading Forms —</option>').prop('disabled', false);
                this.showNotification('Failed to load forms. Please try again.', 'error');
            }
        }

        /**
         * Handle form selection change
         */
        async handleFormChange(e) {
            const $select = $(e.currentTarget);
            const $container = $select.closest('.form-config-item');
            const formId = $select.val();
            const handlerId = $container.find('.handler-selector').val();
            const index = $container.data('index');
            
            console.log(`Form selected: ${formId} (Handler: ${handlerId})`);
            
            const $mappings = $container.find('.field-mappings');
            
            if (!formId || !handlerId) {
                $mappings.empty();
                return;
            }
            
            // Show loading state
            $mappings.html(this.templates.loading);
            
            try {
                const fields = await this.loadFormFields(handlerId, formId);
                const savedMappings = this.getSavedMappings($mappings);
                this.displayFieldMappings($mappings, fields, index, savedMappings);
            } catch (error) {
                console.error('Error loading fields:', error);
                $mappings.html(this.templates.error.replace('%message%', 'Failed to load form fields. Please try again.'));
                this.showNotification('Failed to load form fields', 'error');
            }
        }

        /**
         * Load forms for a handler
         */
        async loadHandlerForms(handlerId) {
            // Check cache first
            const cacheKey = `forms_${handlerId}`;
            if (this.cache.forms.has(cacheKey)) {
                console.log('Returning cached forms');
                return this.cache.forms.get(cacheKey);
            }
            
            const response = await $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'crqa_get_handler_forms',
                    handler_id: handlerId,
                    nonce: this.nonces.getForms
                }
            });
            
            if (response.success && response.data.forms) {
                // Cache the result
                this.cache.forms.set(cacheKey, response.data.forms);
                return response.data.forms;
            }
            
            throw new Error('Invalid response from server');
        }

        /**
         * Load form fields
         */
        async loadFormFields(handlerId, formId) {
            // Check cache first
            const cacheKey = `fields_${handlerId}_${formId}`;
            if (this.cache.fields.has(cacheKey)) {
                console.log('Returning cached fields');
                return this.cache.fields.get(cacheKey);
            }
            
            const response = await $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'crqa_get_form_fields',
                    handler_id: handlerId,
                    form_id: formId,
                    nonce: this.nonces.getFields
                }
            });
            
            if (response.success && response.data.fields) {
                // Cache the result
                this.cache.fields.set(cacheKey, response.data.fields);
                return response.data.fields;
            }
            
            throw new Error('Invalid response from server');
        }

        /**
         * Populate form selector with options
         */
        populateFormSelector($selector, forms, savedValue) {
            let html = '<option value="">— Select Form —</option>';
            
            if (forms && forms.length > 0) {
                forms.forEach(form => {
                    const selected = savedValue && form.id == savedValue ? ' selected' : '';
                    html += `<option value="${form.id}"${selected}>${this.escapeHtml(form.title)} (ID: ${form.id})</option>`;
                });
            } else {
                html = '<option value="">— No Forms Found —</option>';
            }
            
            $selector.html(html).prop('disabled', false);
        }

        /**
         * Display field mappings
         */
        displayFieldMappings($container, fields, index, savedMappings = {}) {
            const mappingFields = {
                'customer_name': { label: 'Customer Name', required: true },
                'customer_email': { label: 'Customer Email', required: true },
                'customer_phone': { label: 'Customer Phone', required: false },
                'vehicle_name': { label: 'Vehicle Name/Type', required: false, special: 'vehicle' },
                'rental_dates': { label: 'Rental Dates/Period', required: false },
                'product_id': { label: 'Product ID', required: false, special: 'product' }
            };
            
            let html = `
                <div class="crqa-mappings-header">
                    <h4>Field Mappings</h4>
                    <span class="description">Map form fields to quote fields</span>
                </div>
                <table class="form-table crqa-mappings-table">
            `;
            
            Object.entries(mappingFields).forEach(([fieldKey, fieldConfig]) => {
                const requiredMark = fieldConfig.required ? ' <span class="required">*</span>' : '';
                
                html += `<tr class="mapping-row ${fieldConfig.required ? 'required-field' : ''}">`;
                html += `<th>${fieldConfig.label}${requiredMark}</th>`;
                html += '<td>';
                
                if (fieldConfig.special === 'vehicle') {
                    html += this.createVehicleFieldMapping(index, fieldKey, fields, savedMappings);
                } else if (fieldConfig.special === 'product') {
                    html += this.createProductFieldMapping(index, fieldKey, fields, savedMappings);
                } else {
                    html += this.createRegularFieldMapping(index, fieldKey, fields, savedMappings);
                }
                
                html += '</td></tr>';
            });
            
            html += '</table>';
            
            // Add help text
            html += `
                <div class="crqa-mappings-help">
                    <p class="description">
                        <strong>Tips:</strong><br>
                        • Required fields are marked with <span class="required">*</span><br>
                        • Use Smart Tags for dynamic values (e.g., {field_id="5"}, {page_id})<br>
                        • Vehicle matching works automatically if no Product ID is provided
                    </p>
                </div>
            `;
            
            $container.html(html);
            
            // Initialize radio button states
            this.initializeFieldStates($container, index);
        }

        /**
         * Create vehicle field mapping HTML
         */
        createVehicleFieldMapping(index, fieldKey, fields, savedMappings) {
            const isSmartTag = savedMappings.vehicle_smart_tag ? true : false;
            
            let html = `
                <div class="field-mapping-option">
                    <label>
                        <input type="radio" name="form_config[${index}][vehicle_source]" value="field" ${!isSmartTag ? 'checked' : ''}>
                        Select from form field
                    </label>
                    <select name="form_config[${index}][mappings][${fieldKey}]" class="regular-text vehicle-field-select field-selector">
                        <option value="">— Not Mapped —</option>
            `;
            
            fields.forEach(field => {
                const selected = !isSmartTag && savedMappings[fieldKey] == field.id ? ' selected' : '';
                html += `<option value="${field.id}"${selected}>${this.escapeHtml(field.label)} (${field.type})</option>`;
            });
            
            html += `
                    </select>
                </div>
                <div class="field-mapping-option">
                    <label>
                        <input type="radio" name="form_config[${index}][vehicle_source]" value="smart_tag" ${isSmartTag ? 'checked' : ''}>
                        Use Smart Tag or Fixed Value
                    </label>
                    <input type="text" 
                           name="form_config[${index}][vehicle_smart_tag]" 
                           class="regular-text vehicle-smart-tag smart-tag-input" 
                           placeholder="e.g., Luxury Car or {field_id=&quot;5&quot;}" 
                           value="${this.escapeHtml(savedMappings.vehicle_smart_tag || '')}">
                    <p class="description">Enter a fixed value or a WPForms Smart Tag</p>
                </div>
            `;
            
            return html;
        }

        /**
         * Create product field mapping HTML
         */
        createProductFieldMapping(index, fieldKey, fields, savedMappings) {
            const isSmartTag = savedMappings.product_id_smart_tag ? true : false;
            
            let html = `
                <div class="field-mapping-option">
                    <label>
                        <input type="radio" name="form_config[${index}][product_id_source]" value="field" ${!isSmartTag ? 'checked' : ''}>
                        Select from form field
                    </label>
                    <select name="form_config[${index}][mappings][${fieldKey}]" class="regular-text product-id-field-select field-selector">
                        <option value="">— Not Mapped —</option>
            `;
            
            fields.forEach(field => {
                const selected = !isSmartTag && savedMappings[fieldKey] == field.id ? ' selected' : '';
                html += `<option value="${field.id}"${selected}>${this.escapeHtml(field.label)} (${field.type})</option>`;
            });
            
            html += `
                    </select>
                </div>
                <div class="field-mapping-option">
                    <label>
                        <input type="radio" name="form_config[${index}][product_id_source]" value="smart_tag" ${isSmartTag ? 'checked' : ''}>
                        Use Smart Tag
                    </label>
                    <input type="text" 
                           name="form_config[${index}][product_id_smart_tag]" 
                           class="regular-text product-id-smart-tag smart-tag-input" 
                           placeholder="e.g., {page_id} or {field_id=&quot;3&quot;}" 
                           value="${this.escapeHtml(savedMappings.product_id_smart_tag || '')}">
                    <p class="description">Enter a Smart Tag containing the product ID</p>
                </div>
            `;
            
            return html;
        }

        /**
         * Create regular field mapping HTML
         */
        createRegularFieldMapping(index, fieldKey, fields, savedMappings) {
            let html = `
                <select name="form_config[${index}][mappings][${fieldKey}]" class="regular-text field-selector">
                    <option value="">— Not Mapped —</option>
            `;
            
            fields.forEach(field => {
                const selected = savedMappings[fieldKey] == field.id ? ' selected' : '';
                html += `<option value="${field.id}"${selected}>${this.escapeHtml(field.label)} (${field.type})</option>`;
            });
            
            html += '</select>';
            
            return html;
        }

        /**
         * Initialize field states for radio buttons
         */
        initializeFieldStates($container, index) {
            // Vehicle source
            const $vehicleInputs = $container.find(`input[name="form_config[${index}][vehicle_source]"]`);
            $vehicleInputs.on('change', this.handleVehicleSourceChange.bind(this));
            $vehicleInputs.filter(':checked').trigger('change');
            
            // Product ID source
            const $productInputs = $container.find(`input[name="form_config[${index}][product_id_source]"]`);
            $productInputs.on('change', this.handleProductIdSourceChange.bind(this));
            $productInputs.filter(':checked').trigger('change');
        }

        /**
         * Handle vehicle source change
         */
        handleVehicleSourceChange(e) {
            const $radio = $(e.currentTarget);
            const $container = $radio.closest('td');
            const isSmartTag = $radio.val() === 'smart_tag';
            
            $container.find('.vehicle-field-select').prop('disabled', isSmartTag);
            $container.find('.vehicle-smart-tag').prop('disabled', !isSmartTag);
            
            // Add visual indication
            $container.find('.field-mapping-option').removeClass('active');
            $radio.closest('.field-mapping-option').addClass('active');
        }

        /**
         * Handle product ID source change
         */
        handleProductIdSourceChange(e) {
            const $radio = $(e.currentTarget);
            const $container = $radio.closest('td');
            const isSmartTag = $radio.val() === 'smart_tag';
            
            $container.find('.product-id-field-select').prop('disabled', isSmartTag);
            $container.find('.product-id-smart-tag').prop('disabled', !isSmartTag);
            
            // Add visual indication
            $container.find('.field-mapping-option').removeClass('active');
            $radio.closest('.field-mapping-option').addClass('active');
        }

        /**
         * Create form configuration HTML
         */
        createFormConfigHtml(index) {
            return `
                <div class="form-config-item" data-index="${index}">
                    <div class="form-config-header">
                        <span class="dashicons dashicons-menu handle"></span>
                        <h4>WPForm Configuration #${index + 1}</h4>
                        <button type="button" class="button button-link-delete remove-form-config">
                            <span class="dashicons dashicons-trash"></span> Remove
                        </button>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Form Plugin</th>
                            <td>
                                <select name="form_config[${index}][form_handler]" class="handler-selector regular-text">
                                    <option value="wpforms" selected>WPForms</option>
                                </select>
                                <p class="description">Currently only WPForms is supported</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Select Form</th>
                            <td>
                                <select name="form_config[${index}][form_id]" class="form-selector regular-text" data-saved-value="">
                                    <option value="">— Select Form —</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Form Type</th>
                            <td>
                                <select name="form_config[${index}][form_type]" class="regular-text">
                                    <option value="standard">Standard</option>
                                    <option value="premium">Premium</option>
                                    <option value="long-term">Long-term</option>
                                    <option value="corporate">Corporate</option>
                                    <option value="event">Event</option>
                                </select>
                                <p class="description">Categorize this form for reporting</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Status</th>
                            <td>
                                <label class="crqa-toggle">
                                    <input type="checkbox" name="form_config[${index}][enabled]" value="1" checked>
                                    <span class="slider"></span>
                                    <span class="label-text">Enable this form</span>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="field-mappings"></div>
                </div>
            `;
        }

        /**
         * Reindex all forms after changes
         */
        reindexForms() {
            $('.form-config-item').each((index, element) => {
                const $item = $(element);
                $item.data('index', index);
                $item.find('.form-config-header h4').text(`WPForm Configuration #${index + 1}`);
                
                // Update all field names
                $item.find('input, select, textarea').each((i, field) => {
                    const name = $(field).attr('name');
                    if (name) {
                        const newName = name.replace(/\[(\d+)\]/, `[${index}]`);
                        $(field).attr('name', newName);
                    }
                });
            });
            
            this.formConfigIndex = $('.form-config-item').length;
        }

        /**
         * Validate form submission
         */
        validateFormSubmission(e) {
            const $forms = $('.form-config-item');
            let hasValidForm = false;
            let errors = [];
            
            $forms.each((index, element) => {
                const $item = $(element);
                const handler = $item.find('.handler-selector').val();
                const formId = $item.find('.form-selector').val();
                const enabled = $item.find('input[name*="[enabled]"]').is(':checked');
                
                if (enabled) {
                    if (!handler || !formId) {
                        errors.push(`Form configuration #${index + 1} is incomplete`);
                    } else {
                        // Check required field mappings
                        const requiredFields = ['customer_name', 'customer_email'];
                        requiredFields.forEach(field => {
                            const $field = $item.find(`select[name*="[mappings][${field}]"]`);
                            if ($field.length && !$field.val()) {
                                errors.push(`Form #${index + 1}: ${field.replace('_', ' ')} mapping is required`);
                            }
                        });
                        
                        hasValidForm = true;
                    }
                }
            });
            
            if ($forms.length > 0 && !hasValidForm && errors.length === 0) {
                errors.push('Please complete at least one form configuration');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                this.showValidationErrors(errors);
                return false;
            }
            
            // Show loading state
            this.showLoadingState();
            return true;
        }

        /**
         * Initialize auto-save functionality
         */
        initializeAutoSave() {
            let autoSaveTimer;
            const autoSaveDelay = 30000; // 30 seconds
            
            $(document).on('change', '.form-config-item input, .form-config-item select, .form-config-item textarea', () => {
                clearTimeout(autoSaveTimer);
                
                autoSaveTimer = setTimeout(() => {
                    this.autoSaveDraft();
                }, autoSaveDelay);
            });
        }

        /**
         * Auto-save draft configuration
         */
        async autoSaveDraft() {
            // Collect form data
            const formData = this.collectFormData();
            
            try {
                await $.ajax({
                    url: this.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'crqa_autosave_forms_config',
                        config: formData,
                        nonce: this.nonces.getForms
                    }
                });
                
                this.showNotification('Draft saved', 'info', 2000);
            } catch (error) {
                console.error('Auto-save failed:', error);
            }
        }

        /**
         * Collect current form data
         */
        collectFormData() {
            const forms = [];
            
            $('.form-config-item').each((index, element) => {
                const $item = $(element);
                const config = {
                    form_handler: $item.find('.handler-selector').val(),
                    form_id: $item.find('.form-selector').val(),
                    form_type: $item.find('select[name*="[form_type]"]').val(),
                    enabled: $item.find('input[name*="[enabled]"]').is(':checked'),
                    mappings: {}
                };
                
                // Collect mappings
                $item.find('.field-mappings select').each((i, select) => {
                    const name = $(select).attr('name');
                    if (name) {
                        const match = name.match(/\[mappings\]\[(\w+)\]/);
                        if (match) {
                            config.mappings[match[1]] = $(select).val();
                        }
                    }
                });
                
                forms.push(config);
            });
            
            return forms;
        }

        /**
         * Get saved mappings from data attribute
         */
        getSavedMappings($element) {
            try {
                const data = $element.attr('data-saved-mappings');
                return data ? JSON.parse(data) : {};
            } catch (e) {
                console.error('Error parsing saved mappings:', e);
                return {};
            }
        }

        /**
         * Show notification
         */
        showNotification(message, type = 'success', duration = 3000) {
            const $notification = $(`
                <div class="notice notice-${type} is-dismissible crqa-notification">
                    <p>${message}</p>
                </div>
            `);
            
            $('.wrap h1').after($notification);
            
            setTimeout(() => {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, duration);
        }

        /**
         * Show confirmation dialog
         */
        showConfirmDialog(title, message, onConfirm, onCancel = () => {}) {
            const dialog = `
                <div class="crqa-dialog-overlay">
                    <div class="crqa-dialog">
                        <h3>${title}</h3>
                        <p>${message}</p>
                        <div class="dialog-buttons">
                            <button class="button button-primary confirm-btn">Confirm</button>
                            <button class="button cancel-btn">Cancel</button>
                        </div>
                    </div>
                </div>
            `;
            
            const $dialog = $(dialog);
            $('body').append($dialog);
            
            $dialog.find('.confirm-btn').on('click', () => {
                onConfirm();
                $dialog.remove();
            });
            
            $dialog.find('.cancel-btn, .crqa-dialog-overlay').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    onCancel();
                    $dialog.remove();
                }
            });
        }

        /**
         * Show validation errors
         */
        showValidationErrors(errors) {
            const errorHtml = errors.map(error => `<li>${error}</li>`).join('');
            const $errorBox = $(`
                <div class="notice notice-error">
                    <p><strong>Please fix the following errors:</strong></p>
                    <ul>${errorHtml}</ul>
                </div>
            `);
            
            $('.wrap h1').after($errorBox);
            
            // Scroll to errors
            $('html, body').animate({
                scrollTop: $errorBox.offset().top - 50
            }, 300);
        }

        /**
         * Show loading state during form submission
         */
        showLoadingState() {
            const $submitButton = $('input[type="submit"]');
            $submitButton.prop('disabled', true).val('Saving...');
            
            // Add loading overlay
            const $overlay = $('<div class="crqa-loading-overlay"><span class="spinner is-active"></span></div>');
            $('body').append($overlay);
        }

        /**
         * Scroll to element
         */
        scrollToElement($element) {
            $('html, body').animate({
                scrollTop: $element.offset().top - 100
            }, 500);
        }

        /**
         * Escape HTML for security
         */
        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    }

    // Initialize when DOM is ready
    $(document).ready(() => {
        // Only initialize on the forms settings page
        if ($('#forms-container').length) {
            window.crqaFormsManager = new FormsSettingsManager();
        }
    });

})(jQuery);

/**
 * Additional CSS for enhanced UI (add to your CSS file)
 */
const additionalStyles = `
<style>
/* Form config styling */
.form-config-item {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
    transition: box-shadow 0.2s;
}

.form-config-item:hover {
    box-shadow: 0 2px 5px rgba(0,0,0,.1);
}

.form-config-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e2e4e7;
    display: flex;
    align-items: center;
    cursor: move;
}

.form-config-header .handle {
    margin-right: 10px;
    color: #8c8f94;
}

.form-config-header h4 {
    margin: 0;
    flex: 1;
}

.form-config-placeholder {
    background: #f0f0f1;
    border: 2px dashed #c3c4c7;
    margin-bottom: 20px;
}

/* Field mapping styles */
.crqa-mappings-header {
    padding: 15px 20px;
    border-top: 1px solid #e2e4e7;
    background: #f8f9fa;
}

.crqa-mappings-header h4 {
    margin: 0 0 5px 0;
}

.crqa-mappings-table {
    margin: 0;
}

.mapping-row.required-field th {
    font-weight: 600;
}

.required {
    color: #d63638;
}

.field-mapping-option {
    margin-bottom: 15px;
    padding: 10px;
    border-radius: 4px;
    transition: background 0.2s;
}

.field-mapping-option.active {
    background: #f0f8ff;
}

.field-mapping-option label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.field-mapping-option select,
.field-mapping-option input[type="text"] {
    margin-left: 20px;
}

/* Toggle switch */
.crqa-toggle {
    position: relative;
    display: inline-flex;
    align-items: center;
}

.crqa-toggle input {
    display: none;
}

.crqa-toggle .slider {
    width: 40px;
    height: 20px;
    background-color: #ccc;
    border-radius: 20px;
    position: relative;
    transition: .3s;
    cursor: pointer;
    margin-right: 10px;
}

.crqa-toggle .slider:before {
    content: "";
    position: absolute;
    width: 16px;
    height: 16px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    border-radius: 50%;
    transition: .3s;
}

.crqa-toggle input:checked + .slider {
    background-color: #2271b1;
}

.crqa-toggle input:checked + .slider:before {
    transform: translateX(20px);
}

/* Loading states */
.crqa-loading {
    text-align: center;
    padding: 20px;
}

.crqa-error {
    color: #d63638;
    padding: 20px;
    text-align: center;
}

/* Notification */
.crqa-notification {
    position: fixed;
    top: 32px;
    right: 20px;
    z-index: 100000;
    max-width: 300px;
    box-shadow: 0 3px 6px rgba(0,0,0,.1);
}

/* Dialog */
.crqa-dialog-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.crqa-dialog {
    background: #fff;
    padding: 30px;
    border-radius: 4px;
    max-width: 500px;
    box-shadow: 0 5px 15px rgba(0,0,0,.3);
}

.crqa-dialog h3 {
    margin-top: 0;
}

.dialog-buttons {
    margin-top: 20px;
    text-align: right;
}

.dialog-buttons .button {
    margin-left: 10px;
}

/* Loading overlay */
.crqa-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255,255,255,.8);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.crqa-loading-overlay .spinner {
    float: none;
    margin: 0;
}
</style>
`;

// Inject styles if not already present
if (!document.getElementById('crqa-forms-settings-styles')) {
    document.head.insertAdjacentHTML('beforeend', additionalStyles.replace('<style>', '<style id="crqa-forms-settings-styles">'));
}