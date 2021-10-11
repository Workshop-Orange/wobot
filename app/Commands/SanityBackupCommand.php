<?php

namespace App\Commands;

use Exception;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class SanityBackupCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'sanity:backup
        {--wobot-conf=./.wobot.yml : Specify the wobot config file to use}
        {--backup-key=sanity.backup.destinations : Specify the dot notation key of sanity backup config in wobot-confg}
        {--log-dir=./ : Specify the directory to store the logfile}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'WOBot opinionate backup of a sanity.io dataset';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $configFile = $this->option("wobot-conf");
        $backupKey = $this->option("backup-key");
        $logDir = $this->option("log-dir");

        $engine = app('SanityEngine');
        $engine->setUsedLocation('sanity-backup');
        $engine->setCallingCommand($this);

        try {
            $engine->setLogDirectory($logDir);
            $engine->loadSanityBackupConfig($configFile, $backupKey);
            $engine->info("Wobot version: " . config('app.version'));
            $ret = $engine->executeBackupSanityDataset();
            
            if ($ret > 0) {
                $engine->error("Step failed: " . $engine->getFailureClass() ." [" . $engine->getFailureCode() . "] " . $engine->getFailureMessage());
            } else {
                $engine->info("Backup completed successfully");
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
