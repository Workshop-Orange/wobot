<?php

namespace App\Lagoon\DeploymentEngine\Step;

use App\Lagoon\DeploymentEngine\DeploymentEngineInterface;

interface StepInterface
{
    public function __construct(DeploymentEngineInterface $engine, array $config);
    public function registered(): int;
    public function execute(): int;

    public function info($log);
    public function error($log);
    public function warn($log);

    public function setUsedLocation(string $usedLocation) : StepInterface;

    public function setFailure(int $code, string $message);
    public function getFailureMessage() : string;
    public function getReturnCode() : int;

    public function cleanUp() : int;
}