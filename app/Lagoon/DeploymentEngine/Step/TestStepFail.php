<?php

namespace App\Lagoon\DeploymentEngine\Step;

Class TestStepFail extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->info("This step fails");
        $this->warn("This step fails");
        $this->error("This step fails");
        $this->setFailure(255, "Testing a failed step");

        return $this->getReturnCode();
    }
}