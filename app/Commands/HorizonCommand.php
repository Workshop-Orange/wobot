<?php

namespace App\Commands;

use Exception;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class HorizonCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'horizon 
        {--wobot-conf=./.wobot.yml : Specify the wobot config file to use}
        {--preflight-key=horizon.preflight : Specify the dot notation key of preflight steps to check}
        {--log-dir=./ : Specify the directory to store the logfile}
        {--max-run-secs=1800 : How many seconds can horizon run for until its restarted}
        {--trackers-key=deployment.trackers : Specify the dot notation key of tracker configuration to use}
        {--this-project= : Specify what project this is}
        {--this-environment= : Specify what environment this is}
        {--this-service= : Specify what service this is}
        {--this-prbase= : Specify what prbase this is}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Run horizon after checking preflight steps';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $deploymentFile = $this->option("wobot-conf");
        $preflightKey = $this->option("preflight-key");
        $logDir = $this->option("log-dir");
        $trackersKey = $this->option("trackers-key");
        $maxRunSecs = $this->option("max-run-secs");
        $project = $this->option("this-project") ? $this->option("this-project") : env('LAGOON_PROJECT');
        $environment = $this->option("this-environment") ? $this->option("this-environment") : env('LAGOON_ENVIRONMENT');
        $service = $this->option("this-service") ? $this->option("this-service") : env('SERVICE_NAME');
        $prbase = $this->option("this-prbase") ? $this->option("this-prbase") : env('LAGOON_PR_BASE_BRANCH');

        while(true) {
            $engine = app('LagoonDeploymentEngine');
            $engine->setUsedLocation('horizon');
            $engine->setCallingCommand($this);
            $engine->setProject($project);
            $engine->setEnvironment($environment);
            $engine->setService($service);
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

                $engine->loadTrackers($deploymentFile, $trackersKey);
                $engine->startTrackMilestone($engine->getUsedLocation(), "Starting {$engine->getUsedLocation()}", $trackerFields);

                $engine->loadSteps($deploymentFile, $preflightKey);
                $engine->setLogDirectory($logDir);
                $engine->info("Wobot version: " . config('app.version'));
                $ret = $engine->executeDeploymentSteps();
                
                if ($ret > 0) {
                    $engine->error("Step failed: " . $engine->getFailureClass() ." [" . $engine->getFailureCode() . "] " . $engine->getFailureMessage());
                    $engine->info("Holding off for 10 seconds");
                    sleep(10);
                } else {
                    $engine->info("All steps completed successfully. Horizon can start.");
                    
                    $process = new Process(['php', 'artisan', 'horizon']);
                    $process->setTimeout($maxRunSecs + 15);
                    $process->setIdleTimeout($maxRunSecs + 15);
                    $process->start();
                    
                    while($process->isRunning()) {
                        $msecrun = microtime(true) - $process->getStartTime();

                        $engine->info("The horizon process has run for " . $msecrun . " seconds of {$maxRunSecs}.");
                        if($msecrun >= $maxRunSecs) {
                            $engine->warn("It is time for Horizon to die and restart.");
                            $process->signal(SIGSTOP);
                            $process->stop(10, SIGKILL);
                        } else {
                            $engine->info("Horizon will continue to run");
                        }

                        sleep(60);
                    }
                }
        
                if ($ret > 255) {
                    $ret = 255;
                }
            } catch(Exception $ex) {
                $engine->error(empty($engine->getFailureMessage()) ? $ex->getMessage() : $engine->getFailureMessage());
            }
        }
    }
}
