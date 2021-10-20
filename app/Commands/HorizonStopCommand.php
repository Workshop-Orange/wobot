<?php

namespace App\Commands;

use Exception;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\Process;

class HorizonStopCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'horizon:stop 
        {--wobot-conf=./.wobot.yml : Specify the wobot config file to use}
        {--prekill-key=horizon.prekill : Specify the dot notation key of prekill steps to check}
        {--log-dir=./ : Specify the directory to store the logfile}
        {--max-run-secs=1800 : How many seconds can horizon run for until its restarted}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Stop horizon after checking prekill steps';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $deploymentFile = $this->option("wobot-conf");
        $prekillKey = $this->option("prekill-key");
        $logDir = $this->option("log-dir");
        $maxRunSecs = $this->option("max-run-secs");

        $engine = app('LagoonDeploymentEngine');
        $engine->setUsedLocation('horizon');
        $engine->setCallingCommand($this);

        try {
            $engine->loadSteps($deploymentFile, $prekillKey);
            $engine->setLogDirectory($logDir);
            $engine->info("Wobot version: " . config('app.version'));
            $ret = $engine->executeDeploymentSteps();
            
            if ($ret > 0) {
                $engine->error("Step failed: " . $engine->getFailureClass() ." [" . $engine->getFailureCode() . "] " . $engine->getFailureMessage());
                $engine->info("Holding off for 3 seconds");
                sleep(3);
            } else {
                $engine->info("All steps completed successfully. Horizon can die.");
                
                $process = new Process(['php', 'artisan', 'horizon:terminate']);
                $process->setTimeout($maxRunSecs + 15);
                $process->setIdleTimeout($maxRunSecs + 15);
                return $process->run();
            }
    
            if ($ret > 255) {
                $ret = 255;
            }
        } catch(Exception $ex) {
            $engine->error(empty($engine->getFailureMessage()) ? $ex->getMessage() : $engine->getFailureMessage());
        }
    }
}
