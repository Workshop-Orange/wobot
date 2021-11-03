<?php

namespace App\Wobot;

use Illuminate\Support\Facades\File;

abstract class EngineMilestoneTrackerBase implements EngineMilestoneTrackerInterface
{
    protected $config;
    protected $engine;
    protected $threadId;
    protected $deploymentthreadFile;

    public function __construct(EngineInterface $engine, array $config = [])
    {
        $this->engine = $engine;
        $this->configure($config);
        if($this->config->get('deploymentthread')) {
            $this->deploymentthreadFile = $this->config->get('deploymentthread');
            if(File::exists($this->deploymentthreadFile)) {
                $this->engine->info("Reviving thread for deployment: " . $this->deploymentthreadFile);
                $this->threadId = File::get($this->deploymentthreadFile);
                $this->engine->info("Using thread: " . $this->threadId);
            }
        }
    }

    public function configure(array $config)
    {
        $this->config = collect($config);
    }

    public function cleanUp(): int
    {
        if($this->deploymentthreadFile) {
            $this->engine->info("Removing deployment thread file: " . $this->deploymentthreadFile);
            if(File::exists($this->deploymentthreadFile)) {
                File::delete($this->deploymentthreadFile);
            }
        
            if(File::exists($this->deploymentthreadFile)) {
                $this->engine->setFailure(class_basename($this), 255, "Could not delete file: " . $this->deploymentthreadFile);
                return 255;
            }
        }

        return 0;
    }
}