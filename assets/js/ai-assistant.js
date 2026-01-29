/**
 * AI Preprocessing Assistant - JavaScript
 * Zabbix 7.0 Module
 */

(function($) {
    'use strict';
    
    /**
     * Main AI Assistant Class
     */
    class AiPreprocessingAssistant {
        constructor() {
            this.modal = null;
            this.csrfToken = null;
            this.steps = [];
            this.init();
        }
        
        init() {
            // Wait for DOM to be ready
            $(document).ready(() => {
                this.csrfToken = $('meta[name="csrf-token"]').attr('content');
                this.addAiButton();
                this.bindEvents();
            });
        }
        
        /**
         * Add AI Assistant button to preprocessing section
         */
        addAiButton() {
            // Wait for preprocessing section to load
            const checkInterval = setInterval(() => {
                const addButton = $('button:contains("Add")').filter(function() {
                    return $(this).closest('[id*="preprocessing"]').length > 0;
                });
                
                if (addButton.length > 0 && !$('#ai-assistant-btn').length) {
                    clearInterval(checkInterval);
                    
                    const aiButton = $(`
                        <button type="button" class="btn-alt" id="ai-assistant-btn">
                            <span class="btn-text">AI Assistant</span>
                            <span class="icon-assistant" style="margin-left: 5px; font-size: 14px;">🤖</span>
                        </button>
                    `);
                    
                    aiButton.insertBefore(addButton);
                    aiButton.on('click', () => this.openModal());
                }
            }, 300);
        }
        
        /**
         * Open AI Assistant modal
         */
        openModal() {
            this.createModal();
            this.modal.modal('show');
        }
        
        /**
         * Create modal HTML
         */
        createModal() {
            const modalHtml = `
                <div id="ai-assistant-modal" class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <span class="icon-assistant" style="margin-right: 8px;">🤖</span>
                                    AI Preprocessing Assistant
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="ai-assistant-container">
                                    <!-- Step 1: Input Section -->
                                    <div class="input-section">
                                        <div class="form-group">
                                            <label for="ai-prompt" class="form-label">
                                                <strong>Describe what you want to achieve:</strong>
                                                <small class="text-muted">(Be as specific as possible)</small>
                                            </label>
                                            <textarea id="ai-prompt" class="form-control ai-textarea" 
                                                      rows="4" 
                                                      placeholder="Example: 
1. Extract numeric value from JSON response
2. Convert bytes to megabytes
3. Discard unchanged values with 1-hour heartbeat
4. Handle errors by setting value to 0"></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="item-value" class="form-label">
                                                <strong>Sample Item Value (Optional):</strong>
                                                <small class="text-muted">(Paste a sample value for better results)</small>
                                            </label>
                                            <textarea id="item-value" class="form-control ai-textarea" 
                                                      rows="3" 
                                                      placeholder='Example JSON: {"status": "ok", "data": {"value": 1024, "unit": "bytes"}}
Example Text: Response time: 125ms
Example Number: 99.9'></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="value-type" class="form-label">
                                                <strong>Value Type:</strong>
                                            </label>
                                            <select id="value-type" class="form-control">
                                                <option value="numeric">Numeric (float)</option>
                                                <option value="unsigned">Numeric (unsigned)</option>
                                                <option value="text">Text</option>
                                                <option value="log">Log</option>
                                                <option value="json">JSON</option>
                                                <option value="xml">XML</option>
                                            </select>
                                        </div>
                                        
                                        <div class="text-center">
                                            <button id="generate-btn" class="btn btn-primary btn-lg">
                                                <span class="icon-spinner d-none" id="loading-spinner"></span>
                                                Generate Preprocessing Steps
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Step 2: Results Section -->
                                    <div id="results-section" class="d-none mt-4">
                                        <h5>Suggested Preprocessing Steps:</h5>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover" id="preprocessing-steps-table">
                                                <thead class="thead-light">
                                                    <tr>
                                                        <th width="50">#</th>
                                                        <th>Type</th>
                                                        <th>Parameters</th>
                                                        <th>Custom on fail</th>
                                                        <th>Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="steps-tbody">
                                                    <!-- Steps will be inserted here -->
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="alert alert-info">
                                            <small>
                                                <strong>Note:</strong> These are AI-generated suggestions. 
                                                Please review and test before applying to production items.
                                            </small>
                                        </div>
                                        
                                        <div class="text-right">
                                            <button id="test-steps" class="btn btn-outline-secondary mr-2">
                                                Test Steps
                                            </button>
                                            <button id="apply-steps" class="btn btn-success">
                                                <span class="icon-check"></span>
                                                Add to Item
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Error Section -->
                                    <div id="error-section" class="alert alert-danger d-none mt-3"></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <small class="text-muted mr-auto">
                                    Powered by AI • Review suggestions before applying
                                </small>
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if present
            $('#ai-assistant-modal').remove();
            
            // Add new modal to body
            $('body').append(modalHtml);
            this.modal = $('#ai-assistant-modal');
            
            // Initialize modal events
            this.initModalEvents();
        }
        
        /**
         * Initialize modal event handlers
         */
        initModalEvents() {
            const self = this;
            
            // Generate button click
            $('#generate-btn').off('click').on('click', function() {
                self.generatePreprocessing();
            });
            
            // Apply steps button
            $('#apply-steps').off('click').on('click', function() {
                self.applyPreprocessingSteps();
                self.modal.modal('hide');
            });
            
            // Test steps button
            $('#test-steps').off('click').on('click', function() {
                self.testPreprocessingSteps();
            });
            
            // Modal hidden event
            this.modal.on('hidden.bs.modal', function() {
                $(this).remove();
            });
            
            // Enter key in textarea
            $('#ai-prompt').on('keydown', function(e) {
                if (e.ctrlKey && e.keyCode === 13) {
                    self.generatePreprocessing();
                }
            });
        }
        
        /**
         * Generate preprocessing steps using AI
         */
        async generatePreprocessing() {
            const prompt = $('#ai-prompt').val().trim();
            const itemValue = $('#item-value').val().trim();
            const valueType = $('#value-type').val();
            
            if (!prompt) {
                this.showError('Please describe what preprocessing you need.');
                return;
            }
            
            // Show loading state
            this.setLoadingState(true);
            this.hideError();
            $('#results-section').addClass('d-none');
            
            try {
                // Prepare request data
                const requestData = {
                    prompt: prompt,
                    item_value: itemValue,
                    value_type: valueType,
                    csrf_token: this.csrfToken
                };
                
                // Make API call to backend
                const response = await $.ajax({
                    url: 'zabbix.php?action=ai.preprocessing.assistant.generate',
                    method: 'POST',
                    data: JSON.stringify(requestData),
                    contentType: 'application/json',
                    dataType: 'json'
                });
                
                if (response.success) {
                    this.steps = response.steps;
                    this.displayPreprocessingSteps();
                } else {
                    throw new Error(response.error || 'Failed to generate preprocessing steps');
                }
            } catch (error) {
                console.error('AI Assistant Error:', error);
                this.showError(`Error: ${error.statusText || error.message}`);
            } finally {
                this.setLoadingState(false);
            }
        }
        
        /**
         * Display generated preprocessing steps
         */
        displayPreprocessingSteps() {
            const tbody = $('#steps-tbody');
            tbody.empty();
            
            this.steps.forEach((step, index) => {
                const row = $(`
                    <tr>
                        <td class="text-center">${index + 1}</td>
                        <td><strong>${step.type}</strong></td>
                        <td><code class="params-code">${this.escapeHtml(step.params)}</code></td>
                        <td class="text-center">${step.on_fail ? '✓' : ''}</td>
                        <td><small>${step.description || ''}</small></td>
                    </tr>
                `);
                
                tbody.append(row);
            });
            
            $('#results-section').removeClass('d-none');
            $('html, body').animate({
                scrollTop: $('#results-section').offset().top - 20
            }, 500);
        }
        
        /**
         * Apply preprocessing steps to the item form
         */
        applyPreprocessingSteps() {
            // Find the preprocessing add button
            const addButton = $('button:contains("Add")').filter(function() {
                return $(this).closest('[id*="preprocessing"]').length > 0;
            });
            
            if (addButton.length === 0) {
                alert('Could not find preprocessing section. Please refresh the page.');
                return;
            }
            
            // Add each step
            this.steps.forEach((step, index) => {
                // Click the add button to create new row
                addButton.click();
                
                // Get all preprocessing rows
                const rows = $('tr[id*="preprocessing_"]');
                const newRow = rows.last();
                
                // Fill in the step details
                setTimeout(() => {
                    // Set type
                    const typeSelect = newRow.find('select[name$="[type]"]');
                    typeSelect.val(step.type_value);
                    typeSelect.trigger('change');
                    
                    // Set parameters
                    const paramsTextarea = newRow.find('textarea[name$="[params]"]');
                    if (paramsTextarea.length) {
                        paramsTextarea.val(step.params);
                    }
                    
                    // Set custom on fail
                    if (step.on_fail) {
                        const errorHandlerCheckbox = newRow.find('input[name$="[error_handler]"]');
                        if (errorHandlerCheckbox.length) {
                            errorHandlerCheckbox.prop('checked', true);
                        }
                    }
                    
                    // Trigger any necessary events
                    newRow.find('input, select, textarea').first().trigger('change');
                }, 100 * index);
            });
            
            // Show success message
            this.showToast('Preprocessing steps added successfully!');
        }
        
        /**
         * Test preprocessing steps (simulated)
         */
        testPreprocessingSteps() {
            const testValue = $('#item-value').val().trim() || 'Sample value for testing';
            
            // Show testing dialog
            const testModal = $(`
                <div class="modal fade" id="test-modal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Test Results</h5>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label>Test Input:</label>
                                    <pre class="bg-light p-2">${this.escapeHtml(testValue)}</pre>
                                </div>
                                <div class="form-group">
                                    <label>Processing Steps:</label>
                                    <ol class="pl-3">
                                        ${this.steps.map((step, i) => 
                                            `<li><strong>${step.type}</strong>: ${step.params}</li>`
                                        ).join('')}
                                    </ol>
                                </div>
                                <div class="alert alert-info">
                                    <small>
                                        <strong>Note:</strong> Actual testing requires Zabbix server execution. 
                                        This is a simulation. Use "Execute now" on the item for real testing.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(testModal);
            $('#test-modal').modal('show');
            $('#test-modal').on('hidden.bs.modal', function() {
                $(this).remove();
            });
        }
        
        /**
         * Utility Methods
         */
        setLoadingState(isLoading) {
            const button = $('#generate-btn');
            const spinner = $('#loading-spinner');
            
            if (isLoading) {
                button.prop('disabled', true);
                spinner.removeClass('d-none').addClass('spinner');
                button.html(`<span class="spinner"></span> Generating...`);
            } else {
                button.prop('disabled', false);
                spinner.addClass('d-none').removeClass('spinner');
                button.html('Generate Preprocessing Steps');
            }
        }
        
        showError(message) {
            const errorSection = $('#error-section');
            errorSection.html(message).removeClass('d-none');
        }
        
        hideError() {
            $('#error-section').addClass('d-none').empty();
        }
        
        showToast(message) {
            // Create toast notification
            const toast = $(`
                <div class="toast" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
                    <div class="toast-header bg-success text-white">
                        <strong class="mr-auto">Success</strong>
                        <button type="button" class="ml-2 mb-1 close text-white" data-dismiss="toast">&times;</button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `);
            
            $('body').append(toast);
            toast.toast({ delay: 3000 });
            toast.toast('show');
            
            toast.on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        /**
         * Bind global events
         */
        bindEvents() {
            // Listen for preprocessing section updates
            $(document).on('change', '[id*="preprocessing"]', () => {
                // Ensure AI button is still present
                if (!$('#ai-assistant-btn').length) {
                    this.addAiButton();
                }
            });
        }
    }
    
    // Initialize AI Assistant when DOM is ready
    $(document).ready(() => {
        // Check if we're on an item edit page
        if (window.location.href.includes('action=item.edit')) {
            window.aiAssistant = new AiPreprocessingAssistant();
        }
    });
    
})(jQuery);
