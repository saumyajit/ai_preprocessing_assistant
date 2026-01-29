<?php

namespace Modules\AiPreprocessingAssistant;

use APP;
use CController as CAction;

class Module extends \Core\CModule
{
    public function init(): void
    {
        // Register module assets
        APP::getComponent('assets')->registerScriptFile('modules/ai.preprocessing.assistant/assets/js/ai-assistant.js');
        APP::getComponent('assets')->registerCssFile('modules/ai.preprocessing.assistant/assets/css/ai-assistant.css');
    }

    public function onBeforeAction(CAction $action): void
    {
        // Hook into item configuration page
        if ($action->getAction() === 'item.edit') {
            $this->injectAiAssistant();
        }
    }

    private function injectAiAssistant(): void
    {
        // Add AI assistant button to preprocessing section
        $script = "
            jQuery(document).ready(function($) {
                // Wait for preprocessing section to load
                setTimeout(function() {
                    addAiAssistantButton();
                }, 500);
                
                function addAiAssistantButton() {
                    var addButton = $('button:contains(\"Add\")').filter(function() {
                        return $(this).closest('[id*=\"preprocessing\"]').length > 0;
                    });
                    
                    if (addButton.length > 0) {
                        var aiButton = $('<button type=\"button\" class=\"btn-alt\" id=\"ai-assistant-btn\">' +
                            '<span class=\"btn-text\">AI Assistant</span>' +
                            '</button>');
                        
                        aiButton.insertBefore(addButton);
                        
                        // Add click handler
                        aiButton.on('click', function() {
                            openAiAssistantModal();
                        });
                    }
                }
                
                function openAiAssistantModal() {
                    // Create modal for AI assistant
                    var modal = $('<div id=\"ai-assistant-modal\" class=\"modal fade\" style=\"display:none\">' +
                        '<div class=\"modal-dialog modal-lg\">' +
                        '<div class=\"modal-content\">' +
                        '<div class=\"modal-header\">' +
                        '<h4 class=\"modal-title\">AI Preprocessing Assistant</h4>' +
                        '<button type=\"button\" class=\"close\" data-dismiss=\"modal\">&times;</button>' +
                        '</div>' +
                        '<div class=\"modal-body\">' +
                        '<div id=\"ai-assistant-content\">' +
                        '<div class=\"form-grid\">' +
                        '<div class=\"form-field\">' +
                        '<label for=\"ai-prompt\">Describe what you want to achieve:</label>' +
                        '<textarea id=\"ai-prompt\" rows=\"4\" class=\"textarea-flexible\" ' +
                        'placeholder=\"Example: Extract numeric value from JSON response, convert to decimal, and discard unchanged values...\"></textarea>' +
                        '</div>' +
                        '<div class=\"form-field\">' +
                        '<label for=\"item-value\">Current item value (optional):</label>' +
                        '<textarea id=\"item-value\" rows=\"2\" class=\"textarea-flexible\" ' +
                        'placeholder=\"Paste sample item value here...\"></textarea>' +
                        '</div>' +
                        '<div class=\"form-field\">' +
                        '<button id=\"generate-btn\" class=\"btn-action\">Generate Preprocessing</button>' +
                        '<div id=\"loading\" style=\"display:none;margin-left:10px;\">' +
                        '<span class=\"icon-loading-small\"></span> Generating...</div>' +
                        '</div>' +
                        '</div>' +
                        '<div id=\"ai-result\" style=\"display:none;margin-top:20px;\">' +
                        '<h5>Suggested Preprocessing Steps:</h5>' +
                        '<div id=\"preprocessing-steps\" class=\"list-table\"></div>' +
                        '<div class=\"form-buttons\">' +
                        '<button id=\"apply-steps\" class=\"btn-action\">Add to Item</button>' +
                        '</div>' +
                        '</div>' +
                        '</div>' +
                        '</div>' +
                        '</div>' +
                        '</div>');
                    
                    $('body').append(modal);
                    modal.modal('show');
                    
                    // Handle modal removal
                    modal.on('hidden.bs.modal', function() {
                        $(this).remove();
                    });
                    
                    // Handle generate button click
                    $('#generate-btn').on('click', generatePreprocessing);
                }
                
                function generatePreprocessing() {
                    var prompt = $('#ai-prompt').val();
                    var itemValue = $('#item-value').val();
                    
                    if (!prompt.trim()) {
                        alert('Please describe what preprocessing you need.');
                        return;
                    }
                    
                    $('#loading').show();
                    $('#generate-btn').prop('disabled', true);
                    
                    // Call AI backend
                    $.ajax({
                        url: 'zabbix.php?action=ai.preprocessing.assistant.view',
                        method: 'POST',
                        data: {
                            prompt: prompt,
                            item_value: itemValue,
                            csrf_token: CSRF_TOKEN
                        },
                        dataType: 'json',
                        success: function(response) {
                            $('#loading').hide();
                            $('#generate-btn').prop('disabled', false);
                            
                            if (response.success) {
                                displayPreprocessingSteps(response.steps);
                            } else {
                                alert('Error: ' + response.error);
                            }
                        },
                        error: function() {
                            $('#loading').hide();
                            $('#generate-btn').prop('disabled', false);
                            alert('Failed to connect to AI service.');
                        }
                    });
                }
                
                function displayPreprocessingSteps(steps) {
                    var html = '<table class=\"list-table\">' +
                        '<thead>' +
                        '<tr>' +
                        '<th>Step</th>' +
                        '<th>Type</th>' +
                        '<th>Parameters</th>' +
                        '<th>Custom on fail</th>' +
                        '</tr>' +
                        '</thead>' +
                        '<tbody>';
                    
                    steps.forEach(function(step, index) {
                        html += '<tr>' +
                            '<td>' + (index + 1) + '</td>' +
                            '<td>' + step.type + '</td>' +
                            '<td>' + step.params + '</td>' +
                            '<td>' + (step.on_fail ? 'Yes' : 'No') + '</td>' +
                            '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    
                    $('#preprocessing-steps').html(html);
                    $('#ai-result').show();
                    
                    // Handle apply button
                    $('#apply-steps').off('click').on('click', function() {
                        applyPreprocessingSteps(steps);
                        $('#ai-assistant-modal').modal('hide');
                    });
                }
                
                function applyPreprocessingSteps(steps) {
                    // Add steps to the item preprocessing form
                    steps.forEach(function(step) {
                        // Simulate adding a preprocessing step
                        var addButton = $('button:contains(\"Add\")').filter(function() {
                            return $(this).closest('[id*=\"preprocessing\"]').length > 0;
                        });
                        
                        // Click add button
                        addButton.click();
                        
                        // Get the newly added row
                        var rows = $('tr[id*=\"preprocessing_\"]');
                        var newRow = rows.last();
                        
                        // Fill in the details
                        newRow.find('select[name$=\"[type]\"]').val(step.type_value).trigger('change');
                        newRow.find('textarea[name$=\"[params]\"]').val(step.params);
                        
                        if (step.on_fail) {
                            newRow.find('input[name$=\"[error_handler]\"]').prop('checked', true);
                        }
                    });
                }
            });
        ";
        
        APP::getComponent('assets')->addJs($script);
    }
}
