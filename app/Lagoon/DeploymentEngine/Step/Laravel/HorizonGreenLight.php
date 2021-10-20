<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Lagoon\DeploymentEngine\Step\StepBase;
use Illuminate\Support\Facades\File;

Class HorizonGreenLight extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }


    
    public function execute(): int
    {
        $this->info("Setting laravel horizon green light");
        $semaphore = isset($this->config['semaphore']) ? $this->config['semaphore'] : null;
        
        touch($semaphore);

        if(! File::exists($semaphore)) {
            $this->warn("Semaphore file was not created");
            $this->setFailure(254, "Semaphore file was not created");
            return $this->getReturnCode();
        }

        if(! File::isWritable($semaphore)) {
            $this->warn("Semaphore file is not writeable");
            $this->setFailure(254, "Semaphore file is not writeable");
            return $this->getReturnCode();
        }

        File::put($semaphore, "proceed");
        return 0;
    }
}