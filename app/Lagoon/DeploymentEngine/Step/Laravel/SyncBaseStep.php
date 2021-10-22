<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Lagoon\DeploymentEngine\Step\StepBase;
use Illuminate\Support\Str;

abstract Class SyncBaseStep extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function executeSync($syncStyle = ""): int
    {
        $this->info("Laravel sync {$syncStyle} steps starting");
        if(!isset($this->config['sourceenv'])) {
            $this->setFailure(255, "sourceenv is required for environment sync");
            $this->error($this->getFailureMessage());
            return $this->getReturnCode();   
        }

        $lagoonSyncSource = isset($this->config['sourceenv']) ? $this->config['sourceenv'] : '';
        if(Str::startsWith($lagoonSyncSource, "/") && Str::endsWith($lagoonSyncSource, "/")) {
            $lagoonSyncSourceVar = isset($this->config['sourceenvvar']) ? $this->config['sourceenvvar'] : '';
            if(! $lagoonSyncSourceVar) {
                $this->setFailure(255,"sourceenvvar is required for environment sync where sourceenv is a regex");
                $this->error($this->getFailureMessage());
                return $this->getReturnCode();   
            }

            if(preg_match($lagoonSyncSource, env($lagoonSyncSourceVar,''), $matches)) {
                if(empty($matches[1])) {
                    $this->warn("Match is blank trying to extract the source environment from the supplied regex and variable [regex: {$lagoonSyncSource}] [var: {$lagoonSyncSourceVar}]. This is not necessarily an error, so bailing gracefully.");
                    return 0;    
                }

                $lagoonSyncSource = $matches[1];
            } else {
                $this->warn("Cannot extract the source environment from the supplied regex and variable [regex: {$lagoonSyncSource}] [var: {$lagoonSyncSourceVar}]. This is not necessarily an error, so bailing gracefully.");
                return 0;
            }
        }

        $lagoonSyncCmd = isset($this->config['lagoonsync']) ? $this->config['lagoonsync'] : '/usr/bin/lagoon-sync';
        $lagoonSyncConfig = isset($this->config['lagoonsyncconfig']) ? $this->config['lagoonsyncconfig'] : '/app/.lagoon.yml';
        $lagoonSyncServiceName = $this->engine->getService();
        $lagoonSyncTimeout = isset($this->config['timeout']) ? $this->config['timeout'] : 60 * 5;

        $this->info("[Sync {$syncStyle}] Source environment is [{$lagoonSyncSource}] and service is [{$lagoonSyncServiceName}]");

        $cmd = [
            $lagoonSyncCmd,
            "--config",
            $lagoonSyncConfig,
            "sync",
            $syncStyle,
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
            $this->setFailure($ex->getCode() ? $ex->getCode() : 255, $ex->getMessage());
            $this->error($this->getFailureMessage());
            return $this->getReturnCode();
        }

        return $this->getReturnCode();
    }
}