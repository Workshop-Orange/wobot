<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Lagoon\DeploymentEngine\Step\StepBase;

Class GenericArtisanCommandStep extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        if(empty($this->config['artisancommand'])) {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Generic artisan command: missing command", false);
            $this->setFailure(255, "Generic artisan command: missing command");
            return $this->getReturnCode();
        }

        $command = $this->config['artisancommand'];

        $this->engine->trackMilestoneProgress(class_basename($this::class), 
            "Generic artisan command: " . $command);
        
        $artisanRet = $this->engine->runLaravelArtisanCommand([
            $command
        ]);
        
        if($artisanRet > 0) {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Generic artisan command failed: returned " . $artisanRet , false);
            $this->setFailure(255, "Generic artisan command failed");
            return $this->getReturnCode();
        }

        return 0;
    }
}