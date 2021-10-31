<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

Class GenericCheckPersistentStorageStep extends StepBase implements StepInterface
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
            "Checking persistent storage");

        $paths[] = $this->storage_path('framework/sessions');
        $paths[] = $this->storage_path('framework/views');
        $paths[] = $this->storage_path('framework/cache');

        foreach($paths as $path) {
            if(! File::isDirectory($path)) {
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Directory Not OK: " . $path, false);

                $this->setFailure(255, "Could not find directory: " . $path);
                return $this->getReturnCode();
            } else {
                $testFile = $path . DIRECTORY_SEPARATOR . ".lagoon-prepare-test-file-" . uniqid();
                touch($testFile);
                if(! File::isWritable($testFile)) {
                    $this->engine->trackMilestoneProgress(class_basename($this::class), "Directory Not OK: " . $path, false);
                    $this->setFailure(255, "Could not create the file: " . $testFile);
                    return $this->getReturnCode();
                }

                File::delete($testFile);
                if(File::exists($testFile)) {
                    $this->engine->trackMilestoneProgress(class_basename($this::class), "Directory Not OK: " . $path, false);
                    $this->setFailure(255, "Could not delete the test file: " . $testFile);
                    return $this->getReturnCode();
                }

                $this->engine->trackMilestoneProgress(class_basename($this::class), "Directory OK: " . $path);
            }

        }

        return $this->getReturnCode();
    }
}