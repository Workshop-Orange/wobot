<?php

namespace App\Lagoon\DeploymentEngine;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Wobot\EngineInterface;

interface DeploymentEngineInterface extends EngineInterface
{
    public function registerDeploymentStep(StepInterface $step);
    public function executeDeploymentSteps(): int;
    public function loadSteps(string $wobotConfigFilePath, string $configKey) : array;
}