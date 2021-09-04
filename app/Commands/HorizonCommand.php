<?php

namespace App\Commands;

use Exception;
use LaravelZero\Framework\Commands\Command;

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

        while(true) {
            $engine = app('LagoonDeploymentEngine');
            $engine->setUsedLocation('horizon');
            $engine->setCallingCommand($this);

            try {
                $engine->loadSteps($deploymentFile, $preflightKey);
                $engine->setLogDirectory($logDir);
                $ret = $engine->executeDeploymentSteps();
                
                if ($ret > 0) {
                    $engine->error("Step failed: " . $engine->getFailureClass() ." [" . $engine->getFailureCode() . "] " . $engine->getFailureMessage());
                    $engine->info("Holding off for 3 seconds");
                    sleep(3);
                } else {
                    $engine->info("All steps completed successfully. Horizon can start.");
                    $engine->runPHPCommand(["artisan","horizon"]);
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
