<?php

namespace App\Lagoon\DeploymentEngine;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Wobot\EngineBase;
use App\Wobot\EngineLogShipInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;


abstract class DeploymentEngineBase extends EngineBase implements DeploymentEngineInterface
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
    protected $project;
    protected $environment;
    protected $service;
    protected $prbase;

    public function __construct($usedLocation = "deploy", $logDirectory = "./", $project = "", $environment="", $service="", $prbase = "")
    {
        parent::__construct($usedLocation, $logDirectory);
        $this->deploymentSteps = collect([]);
        $this->project = $project;
        $this->environment = $environment;
        $this->service = $service;
        $this->prbase = $prbase;
    }

    public function getShippedLogStoragePath(string $file = null) : string
    {
        $dest =  DIRECTORY_SEPARATOR . Str::slug(empty($this->project) ? "no-project" : $this->project)
               . DIRECTORY_SEPARATOR . Str::slug(empty($this->environment) ? "no-environment" : $this->environment)
               . DIRECTORY_SEPARATOR . Carbon::now()->year 
               . DIRECTORY_SEPARATOR . Carbon::now()->month;

        if($this->service) {
            $dest .= DIRECTORY_SEPARATOR . Str::slug($this->service);
        }

        if($file) {
            $dest .= DIRECTORY_SEPARATOR . $file;
        }

        return $dest;
    }


    public function getDeploymentSteps() : array
    {
        return $this->deploymentSteps->toArray();
    }

    public function setProject($project) 
    {
        $this->project = $project;
        return $this;
    }

    public function getProject()
    {
        return $this->project;
    }

    public function setPRBase($prbase) 
    {
        $this->prbase = $prbase;
        return $this;
    }

    public function getPRBase()
    {
        return $this->prbase;
    }

    public function setEnvironment($environment) 
    {
        $this->environment = $environment;
        return $this;
    }

    public function getEnvironment()
    {
        return $this->environment;
    }

    public function setService($service) 
    {
        $this->service = $service;
        return $this;
    }

    public function getService()
    {
        return $this->service;
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

    public function executeDeploymentStepsCleanUp(): int
    {
        foreach($this->deploymentSteps as $step)
        {
            $stepRet = $step->cleanUp();
            
            if($stepRet > 0) {
                $this->setFailure(get_class($step), $step->getReturnCode(), $step->getFailureMessage());
                return $stepRet;
            }
        }
        
        // All steps returned 0, we're good
        return 0;
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

                if(isset($configStep['match'])) {
                    if(isset($configStep['match']['environment'])) {
                        $matchEnvironment = $configStep['match']['environment'];
                        if(! preg_match('/'. $matchEnvironment .'/', $this->environment)) {
                            $this->info("[{$className}] Config match is set, but environment does not match: [match: {$matchEnvironment}] [environment: {$this->environment}]");
                            continue;
                        }
                    }

                    if(isset($configStep['match']['service'])) {
                        $matchService = $configStep['match']['service'];
                        if(! preg_match('/'. $matchService .'/', $this->service)) {
                            $this->info("Config match is set, but service does not match: [match: {$matchService}] [service: {$this->service}]");
                            continue;
                        }
                    }

                    if(isset($configStep['match']['prbase'])) {
                        $matchPRBase = $configStep['match']['prbase'];
                        if(! preg_match('/'. $matchPRBase .'/', $this->prbase)) {
                            $this->info("Config match is set, but prbase does not match: [match: {$matchPRBase}] [prbase: {$this->prbase}]");
                            continue;
                        }
                    }
                }
                
                $newStep = new $className($this, $configStep);
                $this->registerDeploymentStep($newStep); 
            }
        }

        $this->trackMilestoneProgress($this->getUsedLocation(), "Steps loaded: " . $this->deploymentSteps->count());

        return $steps;
    }

    public function shipLogs(EngineLogShipInterface $logShipper) 
    {
        $this->info("Looking for logs in " . $this->logDirectory);
        $files = collect(File::files($this->logDirectory, true))->filter(function ($value, $key) {
            return Str::startsWith($value, $this->logDirectory . ".wobot-") && Str::endsWith($value, ".log");
        });
        $this->info("Found " . $files->count() . " files to ship");
        return $logShipper->shipLogs($this, $files->toArray());
    }
}