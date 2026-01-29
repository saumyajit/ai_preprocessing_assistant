<?php
/**
 * AI Assistant View Template
 * This file handles the AI Assistant interface rendering
 */

namespace Modules\AiPreprocessingAssistant;

use APP;
use CPartial;

// Prevent direct access
if (!defined('ZBX_PAGE_NO_AUTHERIZATION')) {
    exit;
}

/**
 * Render AI Assistant modal content
 * 
 * @param array $data  Data passed from controller
 * @return string      HTML content
 */
function renderAiAssistant(array $data = []): string {
    $html = '';
    
    // Get translations
    $translations = APP::Component()->get('translations');
    
    // Start output buffering
    ob_start();
    ?>
    
    <!-- AI Assistant Modal Container -->
    <div id="ai-assistant-container" style="display: none;">
        <!-- Modal will be injected here by JavaScript -->
    </div>
    
    <!-- AI Processing Status Indicator -->
    <div id="ai-processing-status" class="d-none" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm mr-2" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
                <span id="ai-status-message">Processing...</span>
            </div>
        </div>
    </div>
    
    <!-- AI Suggestions Template (for JavaScript) -->
    <script type="text/template" id="ai-suggestion-template">
        <div class="ai-suggestion-card">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0">AI Suggestion</h6>
                </div>
                <div class="card-body">
                    <div class="suggestion-content"></div>
                    <div class="suggestion-steps mt-3"></div>
                </div>
                <div class="card-footer text-right">
                    <button type="button" class="btn btn-sm btn-outline-secondary mr-2 btn-reject">
                        <i class="icon-close"></i> Discard
                    </button>
                    <button type="button" class="btn btn-sm btn-success btn-apply">
                        <i class="icon-check"></i> Apply
                    </button>
                </div>
            </div>
        </div>
    </script>
    
    <!-- Step Details Template -->
    <script type="text/template" id="step-details-template">
        <div class="step-details mt-2 p-2 border rounded">
            <div class="d-flex justify-content-between align-items-center">
                <strong class="step-type"></strong>
                <span class="badge badge-info step-number"></span>
            </div>
            <div class="mt-2">
                <small class="text-muted">Parameters:</small>
                <pre class="step-params bg-light p-2 mb-2"></pre>
            </div>
            <div class="step-actions">
                <button type="button" class="btn btn-sm btn-outline-primary btn-test-step">
                    <i class="icon-test"></i> Test Step
                </button>
                <button type="button" class="btn btn-sm btn-outline-success btn-apply-step ml-2">
                    <i class="icon-add"></i> Add Step
                </button>
            </div>
        </div>
    </script>
    
    <!-- Error Display Template -->
    <script type="text/template" id="error-template">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center">
                <i class="icon-error mr-2" style="font-size: 1.2em;"></i>
                <div class="error-content flex-grow-1"></div>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="error-details mt-2" style="display: none;">
                <pre class="bg-dark text-light p-2"></pre>
            </div>
        </div>
    </script>
    
    <!-- Preprocessing Step Preview -->
    <script type="text/template" id="preprocessing-preview-template">
        <div class="preview-container border rounded p-3">
            <h6 class="border-bottom pb-2">Preview of Steps to be Added</h6>
            <table class="table table-sm table-borderless">
                <thead>
                    <tr>
                        <th width="40">#</th>
                        <th>Type</th>
                        <th>Parameters</th>
                        <th width="100">On Fail</th>
                    </tr>
                </thead>
                <tbody class="preview-steps"></tbody>
            </table>
        </div>
    </script>
    
    <!-- Step Row Template -->
    <script type="text/template" id="step-row-template">
        <tr>
            <td class="text-center step-index"></td>
            <td class="step-type"></td>
            <td><code class="step-params"></code></td>
            <td class="text-center">
                <span class="step-on-fail"></span>
            </td>
        </tr>
    </script>
    
    <!-- Configuration Options (for module settings) -->
    <?php if (APP::getUserType() >= USER_TYPE_SUPER_ADMIN): ?>
    <div id="ai-assistant-config" class="d-none">
        <div class="form-group">
            <label for="ai-provider">AI Provider</label>
            <select id="ai-provider" class="form-control">
                <option value="openai">OpenAI GPT</option>
                <option value="anthropic">Anthropic Claude</option>
                <option value="local">Local LLM</option>
                <option value="mock">Mock (Testing)</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="api-key">API Key</label>
            <input type="password" id="api-key" class="form-control" placeholder="Enter your API key">
        </div>
        
        <div class="form-group">
            <label for="model">Model</label>
            <input type="text" id="model" class="form-control" placeholder="gpt-4, claude-3, etc.">
        </div>
        
        <div class="form-group">
            <label for="temperature">Temperature</label>
            <input type="range" id="temperature" class="form-control-range" min="0" max="1" step="0.1" value="0.3">
            <small class="form-text text-muted">Lower = more deterministic, Higher = more creative</small>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
    // Global AI Assistant configuration
    window.AI_ASSISTANT_CONFIG = {
        endpoints: {
            generate: 'zabbix.php?action=ai.preprocessing.assistant.generate',
            test: 'zabbix.php?action=ai.preprocessing.assistant.test',
            validate: 'zabbix.php?action=ai.preprocessing.assistant.validate'
        },
        default_prompts: {
            json_extract: "Extract numeric value from JSON response",
            regex_match: "Extract number using regular expression",
            unit_conversion: "Convert bytes to megabytes",
            error_handling: "Set custom value on error",
            deduplication: "Discard unchanged values with heartbeat"
        },
        preprocessing_types: <?= json_encode(getPreprocessingTypes()) ?>,
        max_steps: 10,
        debug: <?= defined('ZBX_DEBUG') && ZBX_DEBUG ? 'true' : 'false' ?>
    };
    
    // Helper function to get preprocessing types
    function getPreprocessingTypes() {
        return {
            0: 'None',
            11: 'Regular expression',
            12: 'Trim',
            13: 'Custom multiplier',
            14: 'Right trim',
            15: 'Left trim',
            16: 'XML XPath',
            17: 'JSONPath',
            18: 'In range',
            19: 'Matches regular expression',
            20: 'Discard unchanged with heartbeat',
            21: 'Discard unchanged',
            22: 'JavaScript',
            23: 'Prometheus pattern',
            24: 'Prometheus to JSON',
            25: 'CSV to JSON',
            26: 'Replace',
            27: 'Check for error in JSON',
            28: 'Check for error in XML',
            29: 'Throttle timestamps',
            30: 'Script',
            31: 'SNMP walk value'
        };
    }
    </script>
    
    <style>
    /* Additional styles for AI Assistant */
    .ai-suggestion-card {
        animation: slideIn 0.3s ease-out;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .step-details {
        transition: all 0.2s ease;
    }
    
    .step-details:hover {
        background-color: #f8f9fa;
        border-color: #007bff;
    }
    
    .ai-textarea {
        font-family: 'Consolas', 'Monaco', monospace;
        font-size: 0.9em;
    }
    
    .params-code {
        background: #f8f9fa;
        padding: 2px 6px;
        border-radius: 3px;
        border: 1px solid #dee2e6;
        font-family: monospace;
        font-size: 0.9em;
        word-break: break-all;
    }
    
    .icon-assistant {
        font-size: 1.2em;
        vertical-align: middle;
    }
    
    .icon-spinner {
        display: inline-block;
        width: 1em;
        height: 1em;
        border: 2px solid rgba(0,0,0,.1);
        border-left-color: #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 0.5em;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    </style>
    
    <?php
    $html = ob_get_clean();
    return $html;
}

/**
 * Render the AI button in preprocessing section
 * This is called from Module.php
 */
function renderAiButton(): string {
    return <<<HTML
    <button type="button" class="btn-alt" id="ai-assistant-btn-main" style="margin-right: 10px;">
        <span class="btn-text">AI Assistant</span>
        <span class="icon-assistant" style="margin-left: 5px;">🤖</span>
    </button>
    <script>
    jQuery(function($) {
        $('#ai-assistant-btn-main').on('click', function() {
            // Trigger AI assistant modal
            if (window.aiAssistant) {
                window.aiAssistant.openModal();
            } else {
                alert('AI Assistant is not initialized. Please refresh the page.');
            }
        });
    });
    </script>
    HTML;
}

/**
 * Get available preprocessing types for AI to use
 */
function getAvailablePreprocessingTypes(): array {
    return [
        'regex' => [
            'id' => 11,
            'name' => 'Regular expression',
            'description' => 'Extract value using regular expression pattern'
        ],
        'jsonpath' => [
            'id' => 17,
            'name' => 'JSONPath',
            'description' => 'Extract value from JSON using JSONPath expression'
        ],
        'multiplier' => [
            'id' => 13,
            'name' => 'Custom multiplier',
            'description' => 'Multiply value by specified factor'
        ],
        'discard_heartbeat' => [
            'id' => 20,
            'name' => 'Discard unchanged with heartbeat',
            'description' => 'Discard value if unchanged for specified period'
        ],
        'javascript' => [
            'id' => 22,
            'name' => 'JavaScript',
            'description' => 'Custom JavaScript transformation'
        ],
        'replace' => [
            'id' => 26,
            'name' => 'Replace',
            'description' => 'Replace substring with another value'
        ],
        'trim' => [
            'id' => 12,
            'name' => 'Trim',
            'description' => 'Remove whitespace from both ends'
        ]
    ];
}

/**
 * Format preprocessing step for display
 */
function formatStepForDisplay(array $step): string {
    $types = getAvailablePreprocessingTypes();
    $typeName = array_column($types, 'name', 'id')[$step['type_value']] ?? 'Unknown';
    
    return sprintf(
        '<div class="step-display">
            <strong>%s</strong>: %s
            %s
        </div>',
        htmlspecialchars($typeName),
        htmlspecialchars($step['params']),
        $step['on_fail'] ? '<span class="badge badge-warning">On Fail</span>' : ''
    );
}

return [
    'renderAiAssistant' => 'renderAiAssistant',
    'renderAiButton' => 'renderAiButton',
    'getAvailablePreprocessingTypes' => 'getAvailablePreprocessingTypes',
    'formatStepForDisplay' => 'formatStepForDisplay'
];
?>
