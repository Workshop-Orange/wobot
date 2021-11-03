<?php

namespace App\Wobot;

interface EngineMilestoneTrackerInterface
{
    public function __construct(EngineInterface $engine, array $config = []);
    public function configure(array $config);
    public function startTracker(string $milestoneId, $message, array $fields = []);
    public function trackMilestoneProgress(EngineMilestone $milestone);
    public function endTracker(string $milestoneId, $message, array $fields = [], bool $isOK = true);
    public function cleanUp() : int;
}