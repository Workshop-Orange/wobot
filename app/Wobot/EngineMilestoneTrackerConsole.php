<?php

namespace App\Wobot;

use Illuminate\Support\Facades\File;

class EngineMilestoneTrackerConsole extends EngineMilestoneTrackerBase implements EngineMilestoneTrackerInterface
{

    public function configure(array $config)
    {
        parent::configure($config);
    }

    public function trackMilestoneProgress(EngineMilestone $milestone)
    {
        if($milestone->getIsOK()) {
            $this->engine->info("CLI TRACKER: [thread: {$this->threadId}] [category: {$milestone->getMilestoneId()}] [message: {$milestone->getMessage()}]");
        } else {
            $this->engine->error("CLI TRACKER: [thread: {$this->threadId}] [category: {$milestone->getMilestoneId()}] [message: {$milestone->getMessage()}]");
        }
    }

    public function startTracker(string $milestoneId, $message, array $fields = [])
    {
        $this->engine->info("START CLI TRACKER: [{$milestoneId}] " . $message);
        if(!$this->threadId) {
            $this->threadId = uniqid();
            if($this->deploymentthreadFile) {
                $this->engine->info("START CLI TRACKER: Stored thread id for future messages: " . $this->deploymentthreadFile);
                File::put($this->deploymentthreadFile, $this->threadId);
            }
        }
    }

    public function endTracker(string $milestoneId, $message, array $fields = [], bool $isOK = true)
    {
        if($isOK) {
            $this->engine->info("END CLI TRACKER: [{$milestoneId}] " . $message);
        } else {
            $this->engine->error("END CLI TRACKER: [{$milestoneId}] " . $message);
        }
    }
}