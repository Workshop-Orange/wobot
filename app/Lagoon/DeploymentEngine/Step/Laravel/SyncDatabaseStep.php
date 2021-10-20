<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Lagoon\DeploymentEngine\Step\StepBase;

Class SyncDatabaseStep extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->info("Laravel sync database steps starting");
        if(!isset($this->config['sourceenv'])) {
            $this->error("sourceenv is required for environment sync");
            return 255;   
        }

        $lagoonSyncCmd = isset($this->config['lagoonsync']) ? $this->config['lagoonsync'] : '/usr/bin/lagoon-sync';
        $lagoonSyncConfig = isset($this->config['lagoonsyncconfig']) ? $this->config['lagoonsyncconfig'] : '/app/.lagoon.yml';
        $lagoonSyncServiceName = $this->engine->getService();
        $lagoonSyncSource = isset($this->config['sourceenv']) ? $this->config['sourceenv'] : '';
        $lagoonSyncTimeout = isset($this->config['timeout']) ? $this->config['timeout'] : 60 * 5;

        $cmd = [
            $lagoonSyncCmd,
            "--config",
            $lagoonSyncConfig,
            "sync",
            "mariadb",
            "--service-name",
            $lagoonSyncServiceName,
            "--source-environment-name",
            $lagoonSyncSource,
            "--no-interaction"
        ];

        try {
            $ret = $this->engine->runCommand($cmd, null, $lagoonSyncTimeout);
            if($ret > 0) {
                $this->setFailure($ret, "Error syncing database");
                return $this->getReturnCode();
            }
        } catch (\Exception $ex) {
            $this->error("Error running the sync database command: " . $ex->getMessage());
            $this->setFailure($ex->getCode() ? $ex->getCode() : 255, $ex->getMessage());
            return $this->getReturnCode();
        }

        return $this->getReturnCode();
    }
}