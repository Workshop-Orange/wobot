<?php

namespace App\Lagoon\DeploymentEngine;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Wobot\EngineBase;
use Exception;
use Illuminate\Support\Arr;
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
    protected $environment;
    protected $service;
    protected $prbase;

    public function __construct($usedLocation = "deploy", $logDirectory = "./", $environment="", $service="", $prbase = "")
    {
        parent::__construct($usedLocation, $logDirectory);
        $this->deploymentSteps = collect([]);
        $this->environment = $environment;
        $this->service = $service;
        $this->prbase = $prbase;
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

    public function executeDeploymentSteps(): int
    {
        foreach($this->deploymentSteps as $step)
        {
            //$this->trackMilestone("step-start", "Starting execution: " . get_class($step));
            $stepRet = $step->execute();
            if($stepRet > 0) {
                $this->setFailure(get_class($step), $step->getReturnCode(), $step->getFailureMessage());
                return $stepRet;
            }

            //$this->trackMilestone("step-end", "Finished execution: " . get_class($step));
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

        $this->trackMilestone("init", "Steps loaded: " . $this->deploymentSteps->count());

        return $steps;
    }
}