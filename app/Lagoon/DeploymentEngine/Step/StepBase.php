<?php

namespace App\Lagoon\DeploymentEngine\Step;

use App\Lagoon\DeploymentEngine\DeploymentEngineInterface;

abstract class StepBase implements StepInterface
{
    protected $engine;
    protected $usedLocation;
    protected $returnCode = 0;
    protected $failureMessage;
    protected $config;

    public function __construct(DeploymentEngineInterface $engine, array $config)
    {
        $this->engine = $engine;
        $this->usedLocation = "global";
        $this->config = $config;
    }

    public function setUsedLocation(string $usedLocation) : StepInterface {
        $this->usedLocation = $usedLocation;
        return $this;
    }

    public function registered(): int
    {
        $this->info("Registered step: " . get_class($this) . " | " . json_encode($this->config));
        return 0;
    }

    public function setFailure(int $code, string $message)
    {
        $this->returnCode = $code;
        $this->failureMessage = $message;

        return $this;
    }

    public function getReturnCode(): int
    {
        return $this->returnCode;   
    }

    public function getFailureMessage(): string
    {
        return $this->failureMessage;
    }

    public function info($log)
    {
        $this->engine->info($log, $this->usedLocation);
    }

    public function warn($log)
    {
        $this->engine->warn($log, $this->usedLocation);
    }

    public function error($log)
    {
        $this->engine->error($log, $this->usedLocation);
    }
}