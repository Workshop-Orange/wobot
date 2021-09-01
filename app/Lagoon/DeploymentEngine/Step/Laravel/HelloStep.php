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
        $this->info("Laravel steps starting");
                
        return 0;
    }
}