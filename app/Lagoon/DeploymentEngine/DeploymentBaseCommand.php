<?php

namespace App\Lagoon\DeploymentEngine;

use Dotenv\Dotenv;
use Exception;
use LaravelZero\Framework\Commands\Command;

abstract class DeploymentBaseCommand extends Command
{
    protected $usedLocation = "";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function laggonBaseHandle(string $usedLocation)
    {
        $deploymentFile = $this->option("wobot-conf");
        $stepsKey = $this->option("steps-key");
        $trackersKey = $this->option("trackers-key");
        $logDir = $this->option("log-dir");
        $project = $this->option("this-project") ? $this->option("this-project") : env('LAGOON_PROJECT');
        $environment = $this->option("this-environment") ? $this->option("this-environment") : env('LAGOON_ENVIRONMENT');
        $service = $this->option("this-service") ? $this->option("this-service") : env('SERVICE_NAME');
        $prbase = $this->option("this-prbase") ? $this->option("this-prbase") : env('LAGOON_PR_BASE_BRANCH');

        $engine = app('LagoonDeploymentEngine');
        $engine->setUsedLocation($usedLocation);
        $engine->setCallingCommand($this);
        $engine->setProject($project);
        $engine->setEnvironment($environment);
        $engine->setService($service);
        $engine->setPRBase($prbase);

        try{
            $trackerFields = [
                [
                    "title" => "Environment",
                    "value"=> $engine->getEnvironment(),
                    "short"=> true
                ], 
                [
                    "title" => "Service",
                    "value"=> $engine->getService(),
                    "short"=> true
                ],
            ];

            if($engine->getPRBase()) {
                $trackerFields[] = [
                    "title" => "PR Base",
                    "value"=> $engine->getPRBase(),
                    "short"=> true
                ];
            }
        } catch (Exception $ex) {
            $this->error("Engine initialization error: " . $ex->getMessage());
            return 255;
        }

        try {
            $engine->setLogDirectory($logDir);
            $engine->loadTrackers($deploymentFile, $trackersKey);
            $engine->startTrackMilestone($engine->getUsedLocation(), "Starting {$engine->getUsedLocation()}", $trackerFields);

            $engine->loadSteps($deploymentFile, $stepsKey);
            $engine->info("Wobot version: " . config('app.version'));
            $ret = $engine->executeDeploymentSteps();
            
            if ($ret > 0) {
                $engine->error("Step failed: " . $engine->getFailureClass() ." [" . $engine->getFailureCode() . "] " . $engine->getFailureMessage());
                $engine->endTrackMilestone($engine->getUsedLocation(), 
                    "Step failed: " . $engine->getFailureClass() ." [" . $engine->getFailureCode() . "] " . $engine->getFailureMessage(),  
                    $trackerFields, false);
            } else {
                $engine->info("All steps completed successfully");
                $engine->endTrackMilestone($engine->getUsedLocation(), "All steps completed successfully", $trackerFields);
            }
    
            if ($ret > 255) {
                $ret = 255;
            }

            return $ret;
        } catch(Exception $ex) {
            $engine->error(empty($engine->getFailureMessage()) ? $ex->getMessage() : $engine->getFailureMessage());

            $engine->endTrackMilestone($engine->getUsedLocation(), 
                "Failed: " . empty($engine->getFailureMessage()) ? $ex->getMessage() : $engine->getFailureMessage(), $trackerFields, false);
            
                return $engine->getFailureCode() > 0 ? $engine->getFailureCode() : 255;
        }
    }
}
