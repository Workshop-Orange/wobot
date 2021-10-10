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

    public function __construct($usedLocation = "deploy", $logDirectory = "./")
    {
        parent::__construct($usedLocation, $logDirectory);
        $this->deploymentSteps = collect([]);
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