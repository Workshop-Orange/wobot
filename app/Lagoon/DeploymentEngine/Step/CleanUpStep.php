<?php

namespace App\Lagoon\DeploymentEngine\Step;

Class CleanUpStep extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->info("Cleaning up deployment steps");
        if($ret = $this->engine->executeDeploymentStepsCleanUp() > 0) {
            return $ret;
        }

        $this->info("Cleaning up trackers steps");
        if($ret = $this->engine->executeTrackersCleanUp() > 0) {
            return $ret;
        }
        
        return 0;
    }
}