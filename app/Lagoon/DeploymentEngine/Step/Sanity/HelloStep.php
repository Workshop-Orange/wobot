<?php

namespace App\Lagoon\DeploymentEngine\Step\Sanity;

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
        $this->info("Steps starting");
        
        return 0;
    }
}