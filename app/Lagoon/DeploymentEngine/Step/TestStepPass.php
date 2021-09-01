<?php

namespace App\Lagoon\DeploymentEngine\Step;

Class TestStepPass extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->info("This step passes");
        $this->warn("This step passes");
        $this->error("This step passes");
        
        return $this->getReturnCode();
    }
}