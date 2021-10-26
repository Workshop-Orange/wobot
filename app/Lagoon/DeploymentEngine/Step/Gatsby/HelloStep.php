<?php

namespace App\Lagoon\DeploymentEngine\Step\Gatsby;

use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;

Class HelloStep extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->info("Gatsby steps starting: " . $this->engine->getUsedLocation());
        
        return 0;
    }
}