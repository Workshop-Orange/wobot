<?php

namespace App\Lagoon\DeploymentEngine;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Wobot\EngineInterface;

interface DeploymentEngineInterface extends EngineInterface
{
    public function registerDeploymentStep(StepInterface $step);
    public function executeDeploymentSteps(): int;
    public function loadSteps(string $wobotConfigFilePath, string $configKey) : array;
    public function setProject(string $project);
    public function getProject();
    public function getEnvironment();
    public function setEnvironment(string $environment);
    public function getService();
    public function setService(string $service);
    public function getPRBase();
    public function setPRBase(string $prbase);
}