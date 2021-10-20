<?php

namespace App\Wobot;

interface EngineMilestoneTrackerInterface
{
    public function __construct(EngineInterface $engine, array $config = []);
    public function configure(array $config);
    public function startTracker($message, array $fields = []);
    public function trackMilestone(EngineMilestone $milestone);
    public function endTracker($message, array $fields = []);

}