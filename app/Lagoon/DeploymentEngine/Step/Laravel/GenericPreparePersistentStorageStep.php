<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

Class GenericPreparePersistentStorageStep extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function storage_path($path)
    {
        if(isset($this->config['persistent-storage'])) {
            $storageDir = $this->config['persistent-storage'];
            if(Str::endsWith($storageDir, DIRECTORY_SEPARATOR)) {
                $storageDir = Str::substr($storageDir, 0, Str::length($storageDir) -1);
            }

            if(Str::startsWith($path, DIRECTORY_SEPARATOR)) {
                $path = Str::substr($path, 1, Str::length($storageDir));
            }

            return $storageDir . DIRECTORY_SEPARATOR . $path;
        } else {

        }
    }

    public function execute(): int
    {
        $this->engine->trackMilestoneProgress(class_basename($this::class), 
            "Preparing persistent storage");


        $paths[] = $this->storage_path('framework/sessions');
        $paths[] = $this->storage_path('framework/views');
        $paths[] = $this->storage_path('framework/cache');

        foreach($paths as $path) {
            File::ensureDirectoryExists($path, 0755, true);
            if(! File::isDirectory($path)) {
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Directory not prepared: " . $path, false);
                $this->setFailure(255, "Could not create directory: " . $path);
                return $this->getReturnCode();
            } else {
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Directory prepared: " . $path);
            }
        }

        $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "All directories prepared");
                    
        return $this->getReturnCode();
    }
}