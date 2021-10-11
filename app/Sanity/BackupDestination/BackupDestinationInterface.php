<?php

namespace App\Sanity\BackupDestination;

use App\Sanity\SanityEngineInterface;

interface BackupDestinationInterface {
    public function __construct(SanityEngineInterface $engine, array $config);
    public function registered(): int;
    public function execute(string $fileName, string $backupFileUri, string $md5Hash): int;

    public function info($log);
    public function error($log);
    public function warn($log);

    public function setUsedLocation(string $usedLocation) : BackupDestinationInterface;

    public function setFailure(int $code, string $message);
    public function getFailureMessage() : string;
    public function getReturnCode() : int;
}