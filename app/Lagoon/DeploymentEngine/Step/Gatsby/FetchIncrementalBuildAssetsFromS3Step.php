<?php

namespace App\Lagoon\DeploymentEngine\Step\Gatsby;

use App\Lagoon\DeploymentEngine\Step\StepInterface;

Class FetchIncrementalBuildAssetsFromS3Step extends IncrementalBuildAssetsS3BaseStep implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->info("Fetching incremental build assetes from S3: " . $this->engine->getUsedLocation());
        print_r($this->config);

        $disk = $this->getS3Disk();

        if($disk->exists("/bryan.txt")) {
            $contents = $disk->get("/bryan.txt");
            $this->info("Build context retrieved from S3: " . $contents);
            return 0;
        } else {            
            $this->setFailure(255, "Error retrieveing the build context from S3 bucket");
            $this->error($this->getFailureMessage());
            return $this->getReturnCode();
        }
        
        return 0;
    }   
}