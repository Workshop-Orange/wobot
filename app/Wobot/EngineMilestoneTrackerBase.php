<?php

namespace App\Wobot;

abstract class EngineMilestoneTrackerBase implements EngineMilestoneTrackerInterface
{
    protected $config;
    protected $engine;

    public function __construct(EngineInterface $engine, array $config = [])
    {
        $this->engine = $engine;
        $this->configure($config);
    }

    public function configure(array $config)
    {
        $this->config = collect($config);
    }
}