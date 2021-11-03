<?php

namespace App\Commands;

use Exception;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class ShipLogsCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'logs:ship
        {--log-dir=./ : Specify the directory to store the logfile}
        {--wobot-conf=./.wobot.yml : Specify the wobot config file to use}
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
    protected $description = 'Ship logs to longer term storage';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $usedLocation = "ship-logs";
        $confFile = $this->option("wobot-conf");
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

        try {
            $this->info("Shipping logs");

            if($ret = $engine->shipLogs(app('EngineLogShipper')) > 0) {
                $this->error("Error shipping logs: " . $engine->getFailureMessage());
                return $ret;
            } 

            $this->info("Logs shipped");
        } catch(Exception $ex) {
            $engine->error(empty($engine->getFailureMessage()) ? $ex->getMessage() : $engine->getFailureMessage());
            return $engine->getFailureCode() > 0 ? $engine->getFailureCode() : 255;
        }
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
