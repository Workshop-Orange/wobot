<?php

namespace App\Wobot;

use Illuminate\Console\Command;

interface EngineLogShipInterface
{
    public function shipLogs(EngineInterface $engine, array $logFiles) :int;
    public function shipLog(EngineInterface $engine, string $uri) : int;
}