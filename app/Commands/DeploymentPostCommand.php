<?php

namespace App\Commands;

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
        {--log-dir=./ : Specify the directory to store the logfile}
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
        $logDir = $this->option("log-dir");

        $engine = app('LagoonDeploymentEngine');
        $engine->setUsedLocation('post-deploy');
        $engine->setCallingCommand($this);

        try {
            $engine->loadSteps($deploymentFile, $stepsKey);
            $engine->setLogDirectory($logDir);
            $ret = $engine->executeDeploymentSteps();
            
            if ($ret > 0) {
                $engine->error("Step failed: " . $engine->getFailureClass() ." [" . $engine->getFailureCode() . "] " . $engine->getFailureMessage());
            } else {
                $engine->info("All steps completed successfully");
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
