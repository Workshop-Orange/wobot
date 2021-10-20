<?php

namespace App\Commands;

use App\Wobot\EngineMilestoneTrackerSlack;
use Exception;
use LaravelZero\Framework\Commands\Command;

class DeploymentPostCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'deployment:post 
        {--wobot-conf=./.wobot.yml : Specify the wobot config file to use}
        {--steps-key=deployment.post : Specify the dot notation key of deployment steps to use}
        {--trackers-key=deployment.trackers : Specify the dot notation key of tracker configuration to use}
        {--log-dir=./ : Specify the directory to store the logfile}
        {--this-environment= : Specify what environment this is}
        {--this-service= : Specify what service this is}
        {--this-prbase= : Specify what prbase this is}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Run post-deployment tasks';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $deploymentFile = $this->option("wobot-conf");
        $stepsKey = $this->option("steps-key");
        $trackersKey = $this->option("trackers-key");
        $logDir = $this->option("log-dir");
        $environment = $this->option("this-environment") ? $this->option("this-environment") : env('LAGOON_ENVIRONMENT');
        $service = $this->option("this-service") ? $this->option("this-service") : env('SERVICE_NAME');
        $prbase = $this->option("this-prbase") ? $this->option("this-prbase") : env('LAGOON_PR_BASE_BRANCH');

        $engine = app('LagoonDeploymentEngine');
        $engine->setUsedLocation('post-deploy');
        $engine->setCallingCommand($this);
        $engine->setService($service);
        $engine->setEnvironment($environment);
        $engine->setPRBase($prbase);

        try {
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

            $engine->setLogDirectory($logDir);
            $engine->loadTrackers($deploymentFile, $trackersKey);
            $engine->startTrackMilestones("Starting post deployment");

            $engine->loadSteps($deploymentFile, $stepsKey);
            
            $engine->info("Wobot version: " . config('app.version'));
            $ret = $engine->executeDeploymentSteps();
            
            if ($ret > 0) {
                $engine->error("Step failed: " . $engine->getFailureClass() ." [" . $engine->getFailureCode() . "] " . $engine->getFailureMessage());
                $engine->endTrackMilestones("Step failed: " . $engine->getFailureClass() ." [" . $engine->getFailureCode() . "] " . $engine->getFailureMessage(), $trackerFields);
            } else {
                $engine->info("All steps completed successfully");
                $engine->endTrackMilestones("All steps completed successfully", $trackerFields);
            }
    
            if ($ret > 255) {
                $ret = 255;
            }
            
            return $ret;
        } catch(Exception $ex) {
            $engine->error(empty($engine->getFailureMessage()) ? $ex->getMessage() : $engine->getFailureMessage());
            return $engine->getFailureCode() > 0 ? $engine->getFailureCode() : 255;
        }
    }
}
