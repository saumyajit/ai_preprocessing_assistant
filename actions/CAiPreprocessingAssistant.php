<?php

namespace Modules\AiPreprocessingAssistant\Actions;

use APP;
use CController as CAction;

class CAiPreprocessingAssistant extends CAction
{
    public function init()
    {
        $this->disableCsrfValidation();
    }

    public function checkInput()
    {
        return true;
    }

    public function checkPermissions()
    {
        return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL);
    }

    public function doAction()
    {
        $prompt = $this->getInput('prompt', '');
        $item_value = $this->getInput('item_value', '');
        
        try {
            // Call AI service (you'll need to implement this)
            $steps = $this->callAiService($prompt, $item_value);
            
            echo json_encode([
                'success' => true,
                'steps' => $steps
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function callAiService($prompt, $item_value)
    {
        // TODO: Implement actual AI integration
        // For now, return mock data based on common preprocessing patterns
        
        $common_steps = [
            [
                'type' => 'JSONPath',
                'type_value' => 29, // JSONPath preprocessing type
                'params' => '$.data.value',
                'on_fail' => true
            ],
            [
                'type' => 'Regular expression',
                'type_value' => 11,
                'params' => '([0-9]+\\.[0-9]+)',
                'on_fail' => true
            ],
            [
                'type' => 'Custom multiplier',
                'type_value' => 13,
                'params' => '0.001',
                'on_fail' => false
            ],
            [
                'type' => 'Discard unchanged with heartbeat',
                'type_value' => 20,
                'params' => '3600',
                'on_fail' => true
            ]
        ];
        
        return $common_steps;
    }
}
