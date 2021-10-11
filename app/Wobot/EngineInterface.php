<?php

namespace App\Wobot;

use Illuminate\Console\Command;

interface EngineInterface
{
    public function setCallingCommand(Command $callingCommand);
    public function getCallingCommand();

    public function setUsedLocation(string $usedLocation);
    public function getUsedLocation();

    public function info($log, $prefix = null);
    public function error($log, $prefix = null);
    public function warn($log, $prefix = null);

    public function setFailure(string $class, int $code, string $message);
    public function getFailureMessage() : string;
    public function getFailureCode() : string;
    public function getFailureClass() : string;

    public function runCommand(array $command, array $env = null) : int;
    public function runPHPCommand(array $command,  array $env = null) : int;
    public function runLaravelArtisanCommand(array $command,  array $env = null) : int;
}