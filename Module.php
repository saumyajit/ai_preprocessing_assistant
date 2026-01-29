<?php

namespace Modules\AiPreprocessingAssistant;

use APP;
use CController as CAction;
use CControllerResponseData;
use CControllerResponseFatal;
use CControllerResponseRedirect;
use CProfile;
use CRoleHelper;
use CWebUser;
use Exception;

class Module extends \Core\CModule
{
    private const MODULE_ID = 'ai.preprocessing.assistant';
    private const API_ENDPOINT = 'zabbix.php?action=ai.preprocessing.assistant';
    
    /**
     * @var array Module configuration
     */
    private $config = [
        'ai_provider' => 'openai',
        'api_key' => '',
        'model' => 'gpt-4',
        'temperature' => 0.3,
        'max_tokens' => 1000,
        'timeout' => 30,
        'enabled' => true,
        'debug_mode' => false
    ];
    
    /**
     * Initialize module
     */
    public function init(): void
    {
        // Register autoloader for module classes
        spl_autoload_register([$this, 'autoloader']);
        
        // Load module configuration
        $this->loadConfig();
        
        // Register event handlers
        $this->registerEventHandlers();
        
        // Register assets if module is enabled
        if ($this->config['enabled']) {
            $this->registerAssets();
        }
    }
    
    /**
     * Autoloader for module classes
     */
    public function autoloader(string $class_name): void
    {
        $namespace = __NAMESPACE__ . '\\';
        
        if (strpos($class_name, $namespace) === 0) {
            $class_name = substr($class_name, strlen($namespace));
            $file_path = dirname(__DIR__) . '/' . str_replace('\\', '/', $class_name) . '.php';
            
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }
    
    /**
     * Load module configuration from database
     */
    private function loadConfig(): void
    {
        try {
            // Try to get configuration from database
            $db_config = $this->getDbConfig();
            
            if ($db_config) {
                $this->config = array_merge($this->config, $db_config);
            }
            
            // Apply environment overrides if in debug mode
            if (defined('ZBX_DEBUG') && ZBX_DEBUG) {
                $this->config['debug_mode'] = true;
            }
        } catch (Exception $e) {
            // Log error but don't break the module
            error_log('AI Preprocessing Assistant: Failed to load configuration - ' . $e->getMessage());
        }
    }
    
    /**
     * Get configuration from database
     */
    private function getDbConfig(): array
    {
        $config = [];
        
        // This would typically read from a module-specific configuration table
        // For now, we'll use Zabbix profiles as a temporary solution
        
        $profile_keys = [
            'ai_provider' => self::MODULE_ID . '.ai_provider',
            'api_key' => self::MODULE_ID . '.api_key',
            'model' => self::MODULE_ID . '.model',
            'temperature' => self::MODULE_ID . '.temperature',
            'enabled' => self::MODULE_ID . '.enabled'
        ];
        
        foreach ($profile_keys as $key => $profile_key) {
            $value = CProfile::get($profile_key, null, CWebUser::$data['userid']);
            
            if ($value !== null) {
                // Handle special types
                if ($key === 'temperature') {
                    $value = (float) $value;
                } elseif ($key === 'enabled') {
                    $value = (bool) $value;
                }
                
                $config[$key] = $value;
            }
        }
        
        return $config;
    }
    
    /**
     * Save configuration to database
     */
    private function saveConfig(array $config): void
    {
        foreach ($config as $key => $value) {
            $profile_key = self::MODULE_ID . '.' . $key;
            CProfile::update($profile_key, $value, PROFILE_TYPE_STR, CWebUser::$data['userid']);
        }
        
        CProfile::flush();
    }
    
    /**
     * Register event handlers
     */
    private function registerEventHandlers(): void
    {
        // Hook into item.edit action
        APP::getEventManager()->registerHandler('item.edit.before', [$this, 'onBeforeItemEdit']);
        APP::getEventManager()->registerHandler('item.edit.after', [$this, 'onAfterItemEdit']);
        
        // Hook into module actions
        APP::getEventManager()->registerHandler('module.action', [$this, 'onModuleAction']);
        
        // Register custom action for AI processing
        APP::getComponent('asset-manager')->registerAction(self::MODULE_ID . '.generate', [
            'class_name' => 'CAiPreprocessingAssistant',
            'controller' => 'ai.preprocessing.assistant'
        ]);
    }
    
    /**
     * Register module assets
     */
    private function registerAssets(): void
    {
        $assets = APP::getComponent('assets');
        
        // Register JavaScript
        $assets->registerScriptFile('modules/' . self::MODULE_ID . '/assets/js/ai-assistant.js');
        
        // Register CSS
        $assets->registerCssFile('modules/' . self::MODULE_ID . '/assets/css/ai-assistant.css');
        
        // Add inline script for configuration
        $this->addConfigScript();
    }
    
    /**
     * Add configuration script to page
     */
    private function addConfigScript(): void
    {
        $script = "
            <script>
            window.AI_ASSISTANT_CONFIG = {
                module_id: '" . self::MODULE_ID . "',
                endpoints: {
                    generate: '" . self::API_ENDPOINT . ".generate',
                    test: '" . self::API_ENDPOINT . ".test',
                    validate: '" . self::API_ENDPOINT . ".validate',
                    config: '" . self::API_ENDPOINT . ".config'
                },
                csrf_token: '" . CWebUser::getCsrfToken() . "',
                enabled: " . ($this->config['enabled'] ? 'true' : 'false') . ",
                debug: " . ($this->config['debug_mode'] ? 'true' : 'false') . ",
                default_prompts: {
                    json_extract: '" . _('Extract numeric value from JSON response') . "',
                    regex_match: '" . _('Extract number using regular expression') . "',
                    unit_conversion: '" . _('Convert bytes to megabytes') . "',
                    error_handling: '" . _('Set custom value on error') . "',
                    deduplication: '" . _('Discard unchanged values with heartbeat') . "'
                },
                translations: {
                    ai_assistant: '" . _('AI Assistant') . "',
                    generating: '" . _('Generating...') . "',
                    error_generating: '" . _('Error generating preprocessing steps') . "',
                    success_added: '" . _('Preprocessing steps added successfully!') . "',
                    please_describe: '" . _('Please describe what preprocessing you need.') . "'
                }
            };
            </script>
        ";
        
        APP::getComponent('assets')->addJs($script);
    }
    
    /**
     * Event handler: before item.edit action
     */
    public function onBeforeItemEdit(CAction $action): void
    {
        // Only inject AI assistant on item edit pages
        if ($action->getAction() === 'item.edit') {
            $this->injectAiAssistant($action);
        }
    }
    
    /**
     * Event handler: after item.edit action
     */
    public function onAfterItemEdit(CAction $action): void
    {
        // Optional: Track usage statistics
        if ($action->getAction() === 'item.edit' && $this->config['debug_mode']) {
            $this->logUsage('item_edit_visited');
        }
    }
    
    /**
     * Event handler: module actions
     */
    public function onModuleAction(string $action_name, array $params = []): ?array
    {
        if (strpos($action_name, self::MODULE_ID) === 0) {
            return $this->handleModuleAction($action_name, $params);
        }
        
        return null;
    }
    
    /**
     * Handle module-specific actions
     */
    private function handleModuleAction(string $action_name, array $params): array
    {
        $action = str_replace(self::MODULE_ID . '.', '', $action_name);
        
        switch ($action) {
            case 'generate':
                return $this->generatePreprocessingSteps($params);
                
            case 'test':
                return $this->testPreprocessingSteps($params);
                
            case 'validate':
                return $this->validatePreprocessingSteps($params);
                
            case 'config.get':
                return [
                    'success' => true,
                    'config' => $this->config
                ];
                
            case 'config.set':
                if ($this->validateConfig($params)) {
                    $this->saveConfig($params);
                    $this->config = array_merge($this->config, $params);
                    
                    return [
                        'success' => true,
                        'message' => 'Configuration updated successfully'
                    ];
                }
                break;
                
            case 'status':
                return [
                    'success' => true,
                    'status' => [
                        'enabled' => $this->config['enabled'],
                        'provider' => $this->config['ai_provider'],
                        'model' => $this->config['model'],
                        'version' => $this->getVersion()
                    ]
                ];
        }
        
        return [
            'success' => false,
            'error' => 'Unknown action: ' . $action
        ];
    }
    
    /**
     * Inject AI Assistant into item preprocessing section
     */
    private function injectAiAssistant(CAction $action): void
    {
        if (!$this->config['enabled']) {
            return;
        }
        
        // Get the AI button HTML
        $ai_button = $this->getAiButton();
        
        // Inject JavaScript to add the button
        $script = "
            jQuery(document).ready(function($) {
                // Function to add AI Assistant button
                function addAiAssistantButton() {
                    // Find preprocessing section
                    var preprocessingSection = $('#preprocessing');
                    
                    if (preprocessingSection.length) {
                        // Find the 'Add' button in preprocessing section
                        var addButton = preprocessingSection.find('button:contains(\"" . _('Add') . "\")');
                        
                        if (addButton.length && !$('#ai-assistant-btn').length) {
                            // Create AI Assistant button
                            var aiButton = $('<div style=\"display: inline-block; margin-right: 10px;\">' +
                                '" . addslashes($ai_button) . "' +
                                '</div>');
                            
                            // Insert before the Add button
                            addButton.before(aiButton);
                            
                            // Initialize AI Assistant
                            if (typeof window.AiPreprocessingAssistant !== 'undefined') {
                                window.aiAssistant = new window.AiPreprocessingAssistant();
                            }
                            
                            // Add click handler
                            $('#ai-assistant-btn-main').on('click', function(e) {
                                e.preventDefault();
                                
                                if (window.aiAssistant && typeof window.aiAssistant.openModal === 'function') {
                                    window.aiAssistant.openModal();
                                } else {
                                    // Fallback: open simple modal
                                    openAiAssistantModal();
                                }
                            });
                        }
                    }
                }
                
                // Fallback modal function
                function openAiAssistantModal() {
                    var modal = $('<div id=\"ai-assistant-fallback\" style=\"display:none;\">' +
                        '<div style=\"padding:20px;\">' +
                        '<h3>AI Preprocessing Assistant</h3>' +
                        '<p>This feature requires JavaScript to be fully initialized.</p>' +
                        '<p>Please refresh the page or check the browser console for errors.</p>' +
                        '</div>' +
                        '</div>');
                    
                    $('body').append(modal);
                    modal.show();
                    
                    setTimeout(function() {
                        modal.remove();
                    }, 5000);
                }
                
                // Try to add button immediately
                addAiAssistantButton();
                
                // Also try on preprocessing section changes
                $(document).on('change', '#preprocessing', function() {
                    setTimeout(addAiAssistantButton, 100);
                });
                
                // Retry a few times in case the page loads slowly
                var retries = 0;
                var maxRetries = 10;
                
                var checkInterval = setInterval(function() {
                    if ($('#ai-assistant-btn-main').length || retries >= maxRetries) {
                        clearInterval(checkInterval);
                    } else {
                        addAiAssistantButton();
                        retries++;
                    }
                }, 500);
            });
        ";
        
        // Add the script to the page
        APP::getComponent('assets')->addJs($script);
        
        // Also add the main AI Assistant view
        $this->addAiAssistantView();
    }
    
    /**
     * Get AI button HTML
     */
    private function getAiButton(): string
    {
        try {
            $viewFile = dirname(__DIR__) . '/views/ai.assistant.php';
            
            if (file_exists($viewFile)) {
                $functions = include $viewFile;
                
                if (is_array($functions) && isset($functions['renderAiButton'])) {
                    return $functions['renderAiButton']();
                }
            }
        } catch (Exception $e) {
            error_log('AI Preprocessing Assistant: Failed to load AI button view - ' . $e->getMessage());
        }
        
        // Fallback button
        return '
            <button type="button" class="btn-alt" id="ai-assistant-btn-main">
                <span class="btn-text">' . _('AI Assistant') . '</span>
                <span class="icon-assistant" style="margin-left: 5px;">🤖</span>
            </button>
        ';
    }
    
    /**
     * Add AI Assistant view to page
     */
    private function addAiAssistantView(): void
    {
        try {
            $viewFile = dirname(__DIR__) . '/views/ai.assistant.php';
            
            if (file_exists($viewFile)) {
                $functions = include $viewFile;
                
                if (is_array($functions) && isset($functions['renderAiAssistant'])) {
                    $html = $functions['renderAiAssistant']();
                    APP::getComponent('assets')->addJs($html);
                }
            }
        } catch (Exception $e) {
            error_log('AI Preprocessing Assistant: Failed to load AI assistant view - ' . $e->getMessage());
        }
    }
    
    /**
     * Generate preprocessing steps using AI
     */
    private function generatePreprocessingSteps(array $params): array
    {
        // Validate input
        if (empty($params['prompt'])) {
            return [
                'success' => false,
                'error' => _('Prompt is required')
            ];
        }
        
        // Check if AI service is configured
        if (empty($this->config['api_key']) && $this->config['ai_provider'] !== 'mock') {
            return [
                'success' => false,
                'error' => _('AI service is not configured. Please contact administrator.')
            ];
        }
        
        try {
            // Generate preprocessing steps based on provider
            $steps = $this->callAiService($params);
            
            return [
                'success' => true,
                'steps' => $steps,
                'provider' => $this->config['ai_provider'],
                'model' => $this->config['model']
            ];
            
        } catch (Exception $e) {
            error_log('AI Preprocessing Assistant: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => _('Failed to generate preprocessing steps: ') . $e->getMessage(),
                'debug' => $this->config['debug_mode'] ? [
                    'trace' => $e->getTraceAsString()
                ] : null
            ];
        }
    }
    
    /**
     * Call AI service based on provider
     */
    private function callAiService(array $params): array
    {
        $provider = $this->config['ai_provider'];
        $prompt = $params['prompt'];
        $item_value = $params['item_value'] ?? '';
        $value_type = $params['value_type'] ?? 'numeric';
        
        switch ($provider) {
            case 'openai':
                return $this->callOpenAI($prompt, $item_value, $value_type);
                
            case 'anthropic':
                return $this->callAnthropic($prompt, $item_value, $value_type);
                
            case 'mock':
                return $this->generateMockSteps($prompt, $item_value, $value_type);
                
            default:
                throw new Exception('Unsupported AI provider: ' . $provider);
        }
    }
    
    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $prompt, string $item_value, string $value_type): array
    {
        // Prepare the API request
        $api_url = 'https://api.openai.com/v1/chat/completions';
        
        $messages = [
            [
                'role' => 'system',
                'content' => $this->getSystemPrompt($value_type)
            ],
            [
                'role' => 'user',
                'content' => $this->getUserPrompt($prompt, $item_value)
            ]
        ];
        
        $request_data = [
            'model' => $this->config['model'],
            'messages' => $messages,
            'temperature' => (float) $this->config['temperature'],
            'max_tokens' => (int) $this->config['max_tokens']
        ];
        
        // Make API call
        $response = $this->makeApiRequest($api_url, $request_data);
        
        // Parse response
        return $this->parseAiResponse($response, 'openai');
    }
    
    /**
     * Call Anthropic API
     */
    private function callAnthropic(string $prompt, string $item_value, string $value_type): array
    {
        $api_url = 'https://api.anthropic.com/v1/messages';
        
        $request_data = [
            'model' => $this->config['model'],
            'max_tokens' => (int) $this->config['max_tokens'],
            'temperature' => (float) $this->config['temperature'],
            'system' => $this->getSystemPrompt($value_type),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $this->getUserPrompt($prompt, $item_value)
                ]
            ]
        ];
        
        $response = $this->makeApiRequest($api_url, $request_data);
        
        return $this->parseAiResponse($response, 'anthropic');
    }
    
    /**
     * Generate mock steps for testing
     */
    private function generateMockSteps(string $prompt, string $item_value, string $value_type): array
    {
        // This generates example preprocessing steps based on common patterns
        $steps = [];
        
        // Check for common patterns in the prompt
        $prompt_lower = strtolower($prompt);
        
        // JSON extraction
        if (strpos($prompt_lower, 'json') !== false || strpos($item_value, '{') !== false) {
            $steps[] = [
                'type' => 'JSONPath',
                'type_value' => 17,
                'params' => '$.data.value',
                'on_fail' => true,
                'description' => 'Extract value from JSON using JSONPath'
            ];
        }
        
        // Regular expression extraction
        if (strpos($prompt_lower, 'regex') !== false || strpos($prompt_lower, 'pattern') !== false) {
            $steps[] = [
                'type' => 'Regular expression',
                'type_value' => 11,
                'params' => '([0-9]+\.[0-9]+)',
                'on_fail' => true,
                'description' => 'Extract numeric value using regular expression'
            ];
        }
        
        // Unit conversion
        if (strpos($prompt_lower, 'convert') !== false || strpos($prompt_lower, 'bytes') !== false) {
            $steps[] = [
                'type' => 'Custom multiplier',
                'type_value' => 13,
                'params' => '0.000001',
                'on_fail' => false,
                'description' => 'Convert bytes to megabytes'
            ];
        }
        
        // Error handling
        if (strpos($prompt_lower, 'error') !== false || strpos($prompt_lower, 'fail') !== false) {
            $steps[] = [
                'type' => 'Custom on fail',
                'type_value' => 0,
                'params' => '0',
                'on_fail' => true,
                'description' => 'Set value to 0 on error'
            ];
        }
        
        // Deduplication
        if (strpos($prompt_lower, 'discard') !== false || strpos($prompt_lower, 'unchanged') !== false) {
            $steps[] = [
                'type' => 'Discard unchanged with heartbeat',
                'type_value' => 20,
                'params' => '3600',
                'on_fail' => true,
                'description' => 'Discard unchanged values with 1-hour heartbeat'
            ];
        }
        
        // If no specific patterns detected, add some default steps
        if (empty($steps)) {
            $steps = [
                [
                    'type' => 'Trim',
                    'type_value' => 12,
                    'params' => '',
                    'on_fail' => false,
                    'description' => 'Remove whitespace from both ends'
                ],
                [
                    'type' => 'Regular expression',
                    'type_value' => 11,
                    'params' => '([0-9]+)',
                    'on_fail' => true,
                    'description' => 'Extract numeric value'
                ]
            ];
        }
        
        return $steps;
    }
    
    /**
     * Get system prompt for AI
     */
    private function getSystemPrompt(string $value_type): string
    {
        $preprocessing_types = $this->getPreprocessingTypes();
        
        return "You are a Zabbix preprocessing assistant. Help users create preprocessing steps for Zabbix monitoring items.
        
        Available preprocessing types:
        " . json_encode($preprocessing_types, JSON_PRETTY_PRINT) . "
        
        Rules:
        1. Return preprocessing steps in JSON format
        2. Each step must have: type, type_value, params, on_fail (boolean), description
        3. Keep steps simple and focused
        4. Maximum 5 steps
        5. Value type is: " . $value_type . "
        
        Example response format:
        [
            {
                \"type\": \"JSONPath\",
                \"type_value\": 17,
                \"params\": \"$.data.value\",
                \"on_fail\": true,
                \"description\": \"Extract value from JSON\"
            }
        ]";
    }
    
    /**
     * Get user prompt for AI
     */
    private function getUserPrompt(string $prompt, string $item_value): string
    {
        $user_prompt = "User request: " . $prompt;
        
        if (!empty($item_value)) {
            $user_prompt .= "\n\nSample item value:\n" . $item_value;
        }
        
        $user_prompt .= "\n\nGenerate preprocessing steps for this request.";
        
        return $user_prompt;
    }
    
    /**
     * Make API request to AI service
     */
    private function makeApiRequest(string $url, array $data): array
    {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['api_key']
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception('CURL error: ' . $error);
        }
        
        if ($http_code !== 200) {
            throw new Exception('API error: HTTP ' . $http_code . ' - ' . $response);
        }
        
        return json_decode($response, true) ?: [];
    }
    
    /**
     * Parse AI response
     */
    private function parseAiResponse(array $response, string $provider): array
    {
        switch ($provider) {
            case 'openai':
                $content = $response['choices'][0]['message']['content'] ?? '';
                break;
                
            case 'anthropic':
                $content = $response['content'][0]['text'] ?? '';
                break;
                
            default:
                $content = '';
        }
        
        // Try to extract JSON from the response
        $json_start = strpos($content, '[');
        $json_end = strrpos($content, ']');
        
        if ($json_start !== false && $json_end !== false) {
            $json_str = substr($content, $json_start, $json_end - $json_start + 1);
            $steps = json_decode($json_str, true);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($steps)) {
                return $steps;
            }
        }
        
        // If no valid JSON found, return mock steps
        return $this->generateMockSteps('', '', 'numeric');
    }
    
    /**
     * Test preprocessing steps
     */
    private function testPreprocessingSteps(array $params): array
    {
        // This would normally execute the steps against a test value
        // For now, return a simulation
        
        return [
            'success' => true,
            'results' => [
                'input' => $params['test_value'] ?? 'Sample value',
                'steps' => $params['steps'] ?? [],
                'output' => '123.45',
                'warnings' => [
                    'This is a simulation. Actual testing requires Zabbix server execution.'
                ]
            ]
        ];
    }
    
    /**
     * Validate preprocessing steps
     */
    private function validatePreprocessingSteps(array $params): array
    {
        $steps = $params['steps'] ?? [];
        $errors = [];
        
        foreach ($steps as $index => $step) {
            // Validate step structure
            if (!isset($step['type_value']) || !is_numeric($step['type_value'])) {
                $errors[] = "Step " . ($index + 1) . ": Missing or invalid type_value";
            }
            
            if (!isset($step['params'])) {
                $errors[] = "Step " . ($index + 1) . ": Missing params";
            }
        }
        
        return [
            'success' => empty($errors),
            'valid' => empty($errors),
            'errors' => $errors,
            'step_count' => count($steps)
        ];
    }
    
    /**
     * Validate configuration
     */
    private function validateConfig(array $config): bool
    {
        // Only super admins can change configuration
        if (CWebUser::getType() < USER_TYPE_SUPER_ADMIN) {
            return false;
        }
        
        // Validate AI provider
        if (isset($config['ai_provider']) && !in_array($config['ai_provider'], ['openai', 'anthropic', 'mock', 'local'])) {
            return false;
        }
        
        // Validate temperature
        if (isset($config['temperature']) && ($config['temperature'] < 0 || $config['temperature'] > 1)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get preprocessing types
     */
    private function getPreprocessingTypes(): array
    {
        return [
            0 => 'None',
            11 => 'Regular expression',
            12 => 'Trim',
            13 => 'Custom multiplier',
            14 => 'Right trim',
            15 => 'Left trim',
            16 => 'XML XPath',
            17 => 'JSONPath',
            18 => 'In range',
            19 => 'Matches regular expression',
            20 => 'Discard unchanged with heartbeat',
            21 => 'Discard unchanged',
            22 => 'JavaScript',
            23 => 'Prometheus pattern',
            24 => 'Prometheus to JSON',
            25 => 'CSV to JSON',
            26 => 'Replace',
            27 => 'Check for error in JSON',
            28 => 'Check for error in XML',
            29 => 'Throttle timestamps',
            30 => 'Script',
            31 => 'SNMP walk value'
        ];
    }
    
    /**
     * Get module version
     */
    private function getVersion(): string
    {
        $manifest_file = dirname(__DIR__) . '/manifest.json';
        
        if (file_exists($manifest_file)) {
            $manifest = json_decode(file_get_contents($manifest_file), true);
            return $manifest['version'] ?? '1.0.0';
        }
        
        return '1.0.0';
    }
    
    /**
     * Log usage statistics
     */
    private function logUsage(string $event): void
    {
        // This would log to a database table
        // For now, just log to error log in debug mode
        if ($this->config['debug_mode']) {
            error_log('AI Preprocessing Assistant Usage: ' . $event);
        }
    }
    
    /**
     * Install module
     */
    public function install(): void
    {
        // Create necessary database tables
        $this->createDatabaseTables();
        
        // Set default configuration
        $this->saveConfig($this->config);
        
        // Log installation
        error_log('AI Preprocessing Assistant module installed successfully.');
    }
    
    /**
     * Uninstall module
     */
    public function uninstall(): void
    {
        // Clean up database tables
        $this->dropDatabaseTables();
        
        // Remove configuration
        $this->cleanupConfiguration();
        
        // Log uninstallation
        error_log('AI Preprocessing Assistant module uninstalled.');
    }
    
    /**
     * Create database tables for module
     */
    private function createDatabaseTables(): void
    {
        // TODO: Implement database table creation
        // This would create tables for storing:
        // - AI request history
        // - User preferences
        // - Usage statistics
    }
    
    /**
     * Drop module database tables
     */
    private function dropDatabaseTables(): void
    {
        // TODO: Implement database table cleanup
    }
    
    /**
     * Cleanup module configuration
     */
    private function cleanupConfiguration(): void
    {
        // Remove all module-related profiles
        $db = DB::getSchema();
        $prefix = self::MODULE_ID . '.%';
        
        DB::delete('profiles', ['key_glob' => [$prefix]]);
    }
    
    /**
     * Check requirements
     */
    public function checkRequirements(): array
    {
        $errors = [];
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $errors[] = 'PHP 7.4 or higher is required';
        }
        
        // Check for cURL
        if (!function_exists('curl_init')) {
            $errors[] = 'cURL extension is required';
        }
        
        // Check for JSON
        if (!function_exists('json_decode')) {
            $errors[] = 'JSON extension is required';
        }
        
        // Check Zabbix version
        if (version_compare(ZABBIX_VERSION, '7.0.0', '<')) {
            $errors[] = 'Zabbix 7.0 or higher is required';
        }
        
        return $errors;
    }
}
