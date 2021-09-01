<?php

namespace App\Lagoon\DeploymentEngine;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Console\Command;

interface DeploymentEngineInterface
{
    public function setCallingCommand(Command $callingCommand);
    public function getCallingCommand();

    public function setUsedLocation(string $usedLocation);
    public function getUsedLocation();

    public function info($log, $prefix = null);
    public function error($log, $prefix = null);
    public function warn($log, $prefix = null);

    public function registerDeploymentStep(StepInterface $step);
    
    public function executeDeploymentSteps(): int;

    public function setFailure(string $class, int $code, string $message);
    public function getFailureMessage() : string;
    public function getFailureCode() : string;
    public function getFailureClass() : string;

    public function loadSteps(string $wobotConfigFilePath, string $configKey) : array;

    public function runCommand(array $command) : int;
    public function runPHPCommand(array $command) : int;
    public function runLaravelArtisanCommand(array $command) : int;
}