<?php

namespace App\Lagoon\DeploymentEngine\Step\Gatsby;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

Class StoreIncrementalBuildAssetsToS3Step extends IncrementalBuildAssetsS3BaseStep implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        if(empty($this->engine->getProject()) || empty($this->engine->getEnvironment())) {
            $this->setFailure(255, "Both the Project and the Environment need to be set in the engine for Gatsby build caching to work");
            return $this->getReturnCode();
        }

        try {
            $disk = $this->getS3Disk();
            if(!$disk) {
                $this->setFailure($this->getReturnCode() ? $this->getReturnCode() : 255, $this->getFailureMessage() ? $this->getFailureMessage() : "S3 Disk not retrieved.");
                return $this->getReturnCode();
            }

            $cacheArchiveFileName = empty($this->config['cachearchivename']) ? "gatsby_bulid_cache.tgz" : $this->config['cachearchivename'];    
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Storing incremental build cache assets to S3 [" . $this->engine->getUsedLocation() . "] " . $this->getBuildAssetsS3Path($cacheArchiveFileName));

            $tempFileCache = tempnam(sys_get_temp_dir(), Str::slug($this->engine->getProject()) . "_" . Str::slug($this->engine->getEnvironment()) . "_" . $cacheArchiveFileName );
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Temporary cache file: [" . $this->engine->getUsedLocation() . "] " . $tempFileCache);

            $ret = $this->engine->runCommand(array_merge([
                '/usr/bin/tar',
                '-zcf',
                $tempFileCache,           
                ], is_array($this->config['cachedirs']) ? $this->config['cachedirs'] : ['/app/.cache', '/app/public']), 
                null,
                isset($this->config['timeout']) ? $this->config['cachedirs'] : 1200
            );

            if($ret > 0) {
                $this->setFailure($ret, "Error creating the cache archive file");
                return $this->getReturnCode();
            } 

            $fhandle = fopen($tempFileCache, "r");

            $uploaded = $disk->put($this->getBuildAssetsS3Path($cacheArchiveFileName),$fhandle);
            $sizeMatches = $disk->size($this->getBuildAssetsS3Path($cacheArchiveFileName)) == File::size($tempFileCache);
            $uploadExists = $disk->exists($this->getBuildAssetsS3Path($cacheArchiveFileName));

            if($uploaded && $sizeMatches && $uploadExists) {
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Build context uploaded to S3 bucket");

                return 0;
            } else {            
                $this->setFailure(255, "Error uploading the build context to S3 bucket");
                $this->error($this->getFailureMessage());
                return $this->getReturnCode();
            }
        } catch (Exception $ex) {
            $this->setFailure(255, $ex->getMessage());
            return $this->getReturnCode();
        }

        return 0;
    }
}