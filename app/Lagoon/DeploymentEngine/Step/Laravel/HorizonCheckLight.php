<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Lagoon\DeploymentEngine\Step\StepBase;
use Illuminate\Support\Facades\File;

Class HorizonCheckLight extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->engine->trackMilestoneProgress(class_basename($this::class), "Laravel horizon check light starting");
        $semaphore = isset($this->config['semaphore']) ? $this->config['semaphore'] : null;

        if(! $semaphore) {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "No semaphore specified for HorizonCheckLight");
            $this->setFailure(254, "No semaphore specified for HorizonCheckLight");
            return $this->getReturnCode();
        }

        if(! File::exists($semaphore)) {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Semaphore file does not yet exist.");
            $this->setFailure(254, "Semaphore file does not yet exist.");
            return $this->getReturnCode();
        }

        $light = File::get($semaphore);
        switch($light) {
            case "block":
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Horizon is in block-no-go state");
                $this->setFailure(255, "Horizon is in block-no-go state");
                return $this->getReturnCode();
            case "proceed":
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Horizon is in proceed state");
                return 0;
            default:
                $this->setFailure(254, "Horizon is in an unknown state.");
                return $this->getReturnCode();       
        }
    }
}