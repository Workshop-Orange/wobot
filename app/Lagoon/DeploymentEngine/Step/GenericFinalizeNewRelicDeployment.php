<?php

namespace App\Lagoon\DeploymentEngine\Step;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Lagoon\DeploymentEngine\Step\StepBase;

Class GenericFinalizeNewRelicDeployment extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->info("Generic new relic deployment step");
                
        return 0;
    }
}