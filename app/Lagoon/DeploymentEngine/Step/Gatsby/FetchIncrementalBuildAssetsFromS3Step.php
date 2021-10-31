<?php

namespace App\Lagoon\DeploymentEngine\Step\Gatsby;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

Class FetchIncrementalBuildAssetsFromS3Step extends IncrementalBuildAssetsS3BaseStep implements StepInterface
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

        $this->info("Fetching incremental build assetes from S3: " . $this->engine->getUsedLocation());

        try {
            $disk = $this->getS3Disk();
            if(!$disk) {
                $this->setFailure($this->getReturnCode() ? $this->getReturnCode() : 255, $this->getFailureMessage() ? $this->getFailureMessage() : "S3 Disk not retrieved.");
                return $this->getReturnCode();
            }

            $cacheArchiveFileName = empty($this->config['cachearchivename']) ? "gatsby_bulid_cache.tgz" : $this->config['cachearchivename'];    
            $this->info("Fetching incremental build cache assets from S3 [" . $this->engine->getUsedLocation() . "] " . $this->getBuildAssetsS3Path($cacheArchiveFileName));

            if(!$disk->exists($this->getBuildAssetsS3Path($cacheArchiveFileName))) {
                $this->warn("The cache file does not yet exist");
                return 0;
            }

            $tempFileCache = tempnam(sys_get_temp_dir(), Str::slug($this->engine->getProject()) . "_" . Str::slug($this->engine->getEnvironment()) . "_" . $cacheArchiveFileName );
            $this->info("Temporary cache file: [" . $this->engine->getUsedLocation() . "] " . $tempFileCache);

            $downloaded = File::put($tempFileCache, $disk->readStream($this->getBuildAssetsS3Path($cacheArchiveFileName)));
            $downloadExists = File::exists($tempFileCache);
            $sizeMatches = $disk->size($this->getBuildAssetsS3Path($cacheArchiveFileName)) == File::size($tempFileCache);

            if($downloaded && $downloadExists && $sizeMatches) {
                $this->info("Build context downloaded from S3 bucket");
                $tempFileCacheDir = sys_get_temp_dir() 
                    . DIRECTORY_SEPARATOR 
                    . Str::slug($this->engine->getProject()) 
                    . "_" . Str::slug($this->engine->getEnvironment())
                    . "_" . uniqid();

                File::makeDirectory($tempFileCacheDir, 0755, true, true);
                $this->info("Decompressing cache to: " . $tempFileCacheDir);

                if(! File::isDirectory($tempFileCacheDir)) {
                    $this->setFailure(255, "Error extracting the cache archive file: temporary directory could not be created");
                    return $this->getReturnCode();
                }
    
                $ret = $this->engine->runCommand(array_merge([
                    '/usr/bin/tar',
                    '-zxf',
                    $tempFileCache,
                    '--directory',
                    $tempFileCacheDir
                    ]), 
                    null,
                    isset($this->config['timeout']) ? $this->config['cachedirs'] : 1200
                );
                
                if($ret > 0) {
                    $this->setFailure($ret, "Error extracting the cache archive file");
                    return $this->getReturnCode();
                }

                $cacheDirs = is_array($this->config['cachedirs']) ? $this->config['cachedirs'] : ['/app/.cache', '/app/public'];
                foreach($cacheDirs as $cacheDir) {

                    if(Str::endsWith($cacheDir, DIRECTORY_SEPARATOR)) {
                        $cacheDir = Str::substr($cacheDir, 0, Str::length($cacheDir) - 1);
                    }

                    if(Str::startsWith($cacheDir, "/")) {
                        $sourceDir = $tempFileCacheDir . $cacheDir;
                    } else {
                        $sourceDir = $tempFileCacheDir . DIRECTORY_SEPARATOR . $cacheDir;
                    }

                    $destDir = $cacheDir;
                    
                    $rsyncCmd = [
                        "/usr/bin/rsync",
                        "-va",
                        "--delete",
                        '--out-format="[%t]:%o:%f:Last Modified %M"',
                        $sourceDir . DIRECTORY_SEPARATOR,
                        $destDir
                    ];
                    
                    $ret = $this->engine->runCommand(
                        $rsyncCmd, 
                        null, 
                        isset($this->config['timeout']) ? $this->config['cachedirs'] : 1200
                    );

                    if($ret > 0) {
                        $this->setFailure($ret, "Error extracting the cache archive file directory: " . $cacheDir);
                        return $this->getReturnCode();
                    }
                }

                return 0;
            } else {            
                $this->setFailure(255, "Error uploading the build context from S3 bucket");
                $this->error($this->getFailureMessage());
                return $this->getReturnCode();
            }

        } catch (\Exception $ex) {
            $this->setFailure(255, $ex->getMessage());
            return $this->getReturnCode();
        }
        return 0;
    }
}