<?php

namespace App\Lagoon\DeploymentEngine;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Process\Process;

abstract class DeploymentEngineBase implements DeploymentEngineInterface
{
    protected $callingCommand;
    protected $deploymentSteps;
    protected $failureClass = "";
    protected $failureCode = "";
    protected $failureMessage = "";
    protected $runId;
    protected $logFile;
    protected $usedLocation;
    protected $logDirectory;

    public function __construct($usedLocation = "deploy", $logDirectory = "./")
    {
        $this->usedLocation = $usedLocation;
        $this->deploymentSteps = collect([]);
        $this->runId = Carbon::now()->format("Y-m-d_H-i-s"). "-" . uniqid();
        $this->logDirectory = $logDirectory;
        $this->logFile = $this->getLogDirectory() . DIRECTORY_SEPARATOR . ".wobot-{$this->usedLocation }-log-" . $this->runId . ".log";
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

    public function runCommand(array $command): int
    {
        $commandId = uniqid();
        $commandRunLogFile = $this->getLogDirectory() . DIRECTORY_SEPARATOR . ".wobot-{$this->usedLocation }-log-" . $this->runId . "-cmd-".$commandId.".log";
        
        $process = new Process($command);

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

    public function runPHPCommand(array $command): int
    {
        $fullCommandStack = array_merge(["php"], $command);
        return $this->runCommand($fullCommandStack);
    }

    public function runLaravelArtisanCommand(array $command) : int
    {
        $fullCommandStack = array_merge(["artisan"], $command);
        return $this->runPHPCommand($fullCommandStack);
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

    public function registerDeploymentStep(StepInterface $step)
    {
        $step->setUsedLocation($this->getUsedLocation());
        $ret = $step->registered();
        if($ret <= 0) {
            $this->deploymentSteps->push($step);
        } 

        return $ret;
    }

    public function executeDeploymentSteps(): int
    {
        foreach($this->deploymentSteps as $step)
        {
            $stepRet = $step->execute();
            if($stepRet > 0) {
                $this->setFailure(get_class($step), $step->getReturnCode(), $step->getFailureMessage());
                return $stepRet;
            }
        }

        // All steps returned 0, we're good
        return 0;
    }

    public function loadSteps(string $wobotConfigFilePath, string $configKey): array
    {
        if(!File::exists($wobotConfigFilePath)) {
            $this->setFailure(self::class, 255, "File not found: " . $wobotConfigFilePath);
            throw new Exception($this->getFailureMessage(), $this->getFailureCode());
        }

        $steps = [];
        try {
            $config = Yaml::parseFile($wobotConfigFilePath);
            $steps = Arr::get($config, $configKey);
        } catch(Exception $ex) {
            $this->setFailure(self::class, 255, $ex->getMessage());

            throw new Exception($this->getFailureMessage(), $this->getFailureCode());
        }

        if(empty($steps) || count($steps) <= 0) {
            $this->setFailure(self::class, 255, "No steps loaded");
            
            throw new Exception($this->getFailureMessage(), $this->getFailureCode());
        }

        foreach($steps as $configStep) {
            if(isset($configStep['class'])) {
                $className = $configStep['class'];
                if(!class_exists($className)) {
                    $this->setFailure(self::class, 255, "Class not found: " . $className);
            
                    throw new Exception($this->getFailureMessage(), $this->getFailureCode());                    
                }

                $newStep = new $className($this, $configStep);
                $this->registerDeploymentStep($newStep); 
            }
        }

        return $steps;
    }
}