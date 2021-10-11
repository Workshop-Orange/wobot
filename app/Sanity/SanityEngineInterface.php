<?php

namespace App\Sanity;

use App\Sanity\BackupDestination\BackupDestinationInterface;
use App\Wobot\EngineInterface;

interface SanityEngineInterface extends EngineInterface
{
    public function initializeBackupTrait();
    public function registerBackupDestination(BackupDestinationInterface $step);
    public function loadSanityBackupConfig(string $wobotConfigFilePath, string $configKey): array;
    public function executeBackupSanityDataset();
}