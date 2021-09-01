<?php

namespace App\Lagoon\DeploymentEngine\Step;

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