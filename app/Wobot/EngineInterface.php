<?php

namespace App\Wobot;

use Illuminate\Console\Command;

interface EngineInterface
{
    public function setCallingCommand(Command $callingCommand);
    public function getCallingCommand();

    public function addMilestoneTracker(EngineMilestoneTrackerInterface $milestoneTracker);
    public function getMilestoneTrackers();

    public function loadTrackers(string $wobotConfigFilePath, string $configKey);
    public function executeTrackersCleanUp() : int;

    public function setUsedLocation(string $usedLocation);
    public function getUsedLocation();

    public function info($log, $prefix = null);
    public function error($log, $prefix = null);
    public function warn($log, $prefix = null);
    public function shipLogs(EngineLogShipInterface $logShipper);

    public function trackMilestoneProgress(string $milestoneId, string $message, bool $isOK = true);
    public function startTrackMilestone(string $milestoneId, string $message, array $fields = []);
    public function endTrackMilestone(string $milestoneId, string $message, array $fields = [], bool $isOK = true);

    public function setFailure(string $class, int $code, string $message);
    public function getFailureMessage() : string;
    public function getFailureCode() : string;
    public function getFailureClass() : string;

    public function runCommand(array $command, array $env = null, int $timeout = 60) : int;
    public function runPHPCommand(array $command,  array $env = null, int $timeout = 60) : int;
    public function runLaravelArtisanCommand(array $command,  array $env = null, int $timeout = 60) : int;

    public function getShippedLogStoragePath(string $file = null) : string;
}