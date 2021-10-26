<?php

namespace App\Lagoon\DeploymentEngine\Step\Gatsby;

use App\Lagoon\DeploymentEngine\Step\StepInterface;

Class StoreIncrementalBuildAssetsToS3Step extends IncrementalBuildAssetsS3BaseStep implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->info("Storing incremental build assetes to S3: " . $this->engine->getUsedLocation());
        print_r($this->config);

        $disk = $this->getS3Disk();

        $disk->put("/bryan.txt","hello world");

        if($disk->exists("/bryan.txt")) {
            $this->info("Build context uploaded to S3 bucket");
            return 0;
        } else {            
            $this->setFailure(255, "Error uploading the build context to S3 bucket");
            $this->error($this->getFailureMessage());
            return $this->getReturnCode();
        }

        return 0;
    }
}