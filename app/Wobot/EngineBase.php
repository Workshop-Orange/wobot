<?php

namespace App\Wobot;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

abstract class EngineBase implements EngineInterface
{
    protected $callingCommand;
    protected $failureClass = "";
    protected $failureCode = "";
    protected $failureMessage = "";
    protected $runId;
    protected $logFile;
    protected $usedLocation;
    protected $environment;
    protected $service;
    protected $logDirectory;
    protected $milestones;
    protected $milestoneTrackers;

    public function __construct($usedLocation = "deploy", $logDirectory = "./", $environment = "", $service = "")
    {
        $this->usedLocation = $usedLocation;
        $this->runId = Carbon::now()->format("Y-m-d_H-i-s"). "-" . uniqid();
        $this->logDirectory = $logDirectory;
        $this->logFile = $this->getLogDirectory() . DIRECTORY_SEPARATOR . ".wobot-{$this->usedLocation }-log-" . $this->runId . ".log";
        $this->milestones = collect([]);
        $this->environment = $environment;
        $this->service = $service;
        $this->milestoneTrackers = collect([]);
    }

    public function getShippedLogStoragePath(string $file = null) : string
    {
        $dest = DIRECTORY_SEPARATOR . Str::slug($this->usedLocation) 
            . DIRECTORY_SEPARATOR . Carbon::now()->year 
            . DIRECTORY_SEPARATOR . Carbon::now()->month;

        if($file) {
            $dest .= DIRECTORY_SEPARATOR . $file;
        }

        return $dest;
    }

    public function addMilestoneTracker(EngineMilestoneTrackerInterface $milestoneTracker)
    {
        $this->info("Added milestone tracker: " . get_class($milestoneTracker));
        $this->milestoneTrackers->push($milestoneTracker);
        return $this;
    }

    public function getMilestoneTrackers()
    {
        return $this->milestoneTrackers;
    }

    public function setLogDirectory(string $logDirectory)
    {
        if(Str::endsWith($logDirectory, DIRECTORY_SEPARATOR)) {
            $logDirectory = Str::substr($logDirectory, 0, Str::length($logDirectory) -1);
        }

        $this->logDirectory= $logDirectory;
        $this->logFile = $this->getLogDirectory() . DIRECTORY_SEPARATOR . ".wobot-{$this->usedLocation }-log-" . $this->runId . ".log";
    }

    public function getLogDirectory()
    {
        if(Str::endsWith($this->logDirectory, DIRECTORY_SEPARATOR)) {
            return Str::substr($this->logDirectory, 0, Str::length($this->logDirectory) -1);
        }

        return $this->logDirectory;
    }

    public function setUsedLocation(string $usedLocation)
    {
        $this->usedLocation = $usedLocation;
        $this->logFile = $this->getLogDirectory() . DIRECTORY_SEPARATOR . ".wobot-{$this->usedLocation }-log-" . $this->runId . ".log";
    }

    public function getUsedLocation()
    {
        return $this->usedLocation;
    }
    
    public function setCallingCommand(Command $callingCommand) 
    {
        $this->callingCommand = $callingCommand;
        return $this;
    }

    public function getCallingCommand()
    {
        return $this->callingCommand;
    }

    public function setFailure(string $class, int $code, string $message)
    {
        $this->failureClass = $class;
        $this->failureCode = $code;
        $this->failureMessage = $message;

        return $this;
    }

    public function getFailureClass(): string
    {
        return $this->failureClass;
    }

    public function getFailureCode(): string
    {
        return $this->failureCode;
    }

    public function getFailureMessage(): string
    {
        return $this->failureMessage;
    }
    
    public function canSendConsoleOutput()
    {
        return $this->callingCommand && $this->callingCommand->getOutput();
    }

    public function runCommand(array $command, array $env = null, int $timeout = 60): int
    {
        $commandId = uniqid();
        $commandRunLogFile = $this->getLogDirectory() . DIRECTORY_SEPARATOR . ".wobot-{$this->usedLocation }-log-" . $this->runId . "-cmd-".$commandId.".log";
        
        $process = new Process($command, null, $env);
        $process->setTimeout($timeout);

        $ret = $process->run();
        File::append($commandRunLogFile, "Out:\n" . $process->getOutput()."\n");
        File::append($commandRunLogFile, "Err:\n" . $process->getErrorOutput()."\n");

        $this->info("[Ret={$ret}] Run: " . implode(" " , $command));
        if($ret > 0) {
            $this->error("Failed command output: " . $commandRunLogFile);
        } else {
            $this->info("Success command output: " . $commandRunLogFile);
        }
        
        return $ret;
    }

    public function runPHPCommand(array $command, array $env = null, int $timeout = 60): int
    {
        $fullCommandStack = array_merge(["php"], $command);
        return $this->runCommand($fullCommandStack, $env, $timeout);
    }

    public function runLaravelArtisanCommand(array $command, array $env = null, int $timeout = 60) : int
    {
        $fullCommandStack = array_merge(["artisan"], $command);
        return $this->runPHPCommand($fullCommandStack, $env, $timeout);
    }

    public function startTrackMilestone(string $milestoneId, string $message, array $fields = [])
    {
        if(! isset($this->milestoneTrackers) || $this->milestoneTrackers->count() <= 0) {
            $this->warn("There are no milestone trackers to start track: [message: {$message}]");
            return;
        }

        foreach($this->milestoneTrackers as $tracker) {
            $tracker->startTracker($milestoneId, $message, $fields);
        }
    }

    public function executeTrackersCleanUp(): int
    {
        foreach($this->milestoneTrackers as $tracker) {
            if($ret = $tracker->cleanUp() > 0) {
                $this->setFailure($tracker, $ret, "Tracker cleanup failed");
                return $ret;
            }
        }
        
        return 0;
    }

    public function endTrackMilestone(string $milestoneId, string $message, array $fields = [], bool $isOK = true)
    {
        if(! isset($this->milestoneTrackers) || $this->milestoneTrackers->count() <= 0) {
            $this->warn("There are no milestone trackers to end track: [message: {$message}]");
            return;
        }

        foreach($this->milestoneTrackers as $tracker) {
            $tracker->endTracker($milestoneId, $message, $fields, $isOK);
        }
    }

    public function trackMilestoneProgress(string $milestoneId, string $message, bool $isOK=true) 
    {
        if(! isset($this->milestoneTrackers) || $this->milestoneTrackers->count() <= 0) {
            $this->warn("There are no milestone trackers to track: [id: {$milestoneId}] [message: {$message}]");
            return;
        }

        $milestone = new EngineMilestone($milestoneId, $message, $isOK);

        foreach($this->milestoneTrackers as $tracker) {
            $tracker->trackMilestoneProgress($milestone);
        }

        return $milestone;
    }

    public function info($log, $prefix = null)
    {     
        if(!$prefix) {
            $prefix = $this->getUsedLocation();
        }

        $now = Carbon::now();
        $logString = $now . ":" .  ( $prefix ? $prefix : 'global' ) . ":" . $log;
        $logStringForFile = $now . ":INFO:" .  ( $prefix ? $prefix : 'global' ) . ":" . $log;

        
        if($this->canSendConsoleOutput()) {
            $this->callingCommand->info($logString);
        }
        
        app('log')->info($logString);

        touch($this->logFile);
        if(File::isWritable($this->logFile)) {
            File::append($this->logFile, $logStringForFile . "\n");
        }
    }

    public function error($log, $prefix = null)
    {     
        if(!$prefix) {
            $prefix = $this->getUsedLocation();
        }
        
        $now = Carbon::now();
        $logString = $now . ":" .  ( $prefix ? $prefix : 'global' ) . ":" . $log;
        $logStringForFile = $now . ":ERROR:" .  ( $prefix ? $prefix : 'global' ) . ":" . $log;
        
        if($this->canSendConsoleOutput()) {
            $this->callingCommand->error($logString);
        }

        app('log')->error($logString);
        
        touch($this->logFile);
        if(File::isWritable($this->logFile)) {
            File::append($this->logFile, $logStringForFile . "\n");
        }
    }

    public function warn($log, $prefix = null)
    {     
        if(!$prefix) {
            $prefix = $this->getUsedLocation();
        }
        
        $now = Carbon::now();
        $logString = $now . ":" .  ( $prefix ? $prefix : 'global' ) . ":" . $log;
        $logStringForFile = $now . ":WARN:" .  ( $prefix ? $prefix : 'global' ) . ":" . $log;
        
        if($this->canSendConsoleOutput()) {
            $this->callingCommand->warn($logString);
        }

        app('log')->warning($logString);
        
        touch($this->logFile);
        if(File::isWritable($this->logFile)) {
            File::append($this->logFile, $logStringForFile . "\n");
        }
    }

    public function loadTrackers(string $wobotConfigFilePath, string $configKey)
    {
        if(!File::exists($wobotConfigFilePath)) {
            $this->setFailure(self::class, 255, "File not found: " . $wobotConfigFilePath);
            throw new Exception($this->getFailureMessage(), $this->getFailureCode());
        }

        try {
            $config = Yaml::parseFile($wobotConfigFilePath);
            $trackers = Arr::get($config, $configKey);
        } catch(Exception $ex) {
            $this->setFailure(self::class, 255, $ex->getMessage());

            throw new Exception($this->getFailureMessage(), $this->getFailureCode());
        }

        if(empty($trackers) || count($trackers) <= 0) {
            $this->setFailure(self::class, 255, "No trackers loaded");
            
            throw new Exception($this->getFailureMessage(), $this->getFailureCode());
        }

        foreach($trackers as $configTracker) {
            if(isset($configTracker['class'])) {
                $className = $configTracker['class'];
                if(!class_exists($className)) {
                    $this->setFailure(self::class, 255, "Class not found: " . $className);
            
                    throw new Exception($this->getFailureMessage(), $this->getFailureCode());                    
                }

                if(isset($configTracker['match'])) {
                    if(isset($configTracker['match']['environment'])) {
                        $matchEnvironment = $configTracker['match']['environment'];
                        if(! preg_match('/'. $matchEnvironment .'/', $this->environment)) {
                            $this->info("[{$className}] Config match is set, but environment does not match: [match: {$matchEnvironment}] [environment: {$this->environment}]");
                            continue;
                        }
                    }

                    if(isset($configTracker['match']['service'])) {
                        $matchService = $configTracker['match']['service'];
                        if(! preg_match('/'. $matchService .'/', $this->service)) {
                            $this->info("Config match is set, but service does not match: [match: {$matchService}] [service: {$this->service}]");
                            continue;
                        }
                    }

                    if(isset($configTracker['match']['prbase'])) {
                        $matchPRBase = $configTracker['match']['prbase'];
                        if(! preg_match('/'. $matchPRBase .'/', $this->prbase)) {
                            $this->info("Config match is set, but prbase does not match: [match: {$matchPRBase}] [prbase: {$this->prbase}]");
                            continue;
                        }
                    }
                }
                
                $newTracker = new $className($this, $configTracker);
                $this->addMilestoneTracker($newTracker); 
            }
        }
        

        return $this->milestoneTrackers;
    }

    
    public function shipLogs(EngineLogShipInterface $logShipper) 
    {
        $this->info("Log shipping not implemented for " . class_basename($this));
        return 0;
    }
}