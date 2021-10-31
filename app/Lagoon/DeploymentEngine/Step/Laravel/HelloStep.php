<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Lagoon\DeploymentEngine\Step\StepBase;

Class HelloStep extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->engine->trackMilestoneProgress(class_basename($this::class), 
            "Laravel steps starting");
        
        if(! empty($this->config['syntheticresult'])) {
            if(is_numeric($this->config['syntheticresult'])) {
                if($this->config['syntheticresult'] > 0) {
                    $this->setFailure($this->config['syntheticresult'], "Synthetic result requested: ". $this->config['syntheticresult']); 
                    return $this->getReturnCode();
                }
            } else if($this->config['syntheticresult'] === true) {
                return 0;
            } else {
                $this->setFailure(255, "Synthetic result requested: ". $this->config['syntheticresult']); 
                return $this->getReturnCode();
            }
        }

        return 0;
    }
}