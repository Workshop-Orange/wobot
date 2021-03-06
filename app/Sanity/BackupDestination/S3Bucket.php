<?php

namespace App\Sanity\BackupDestination;

class S3Bucket extends BackupDestinationBase implements BackupDestinationInterface
{
    public function execute(string $fileName, string $backupFileUri, string $md5Hash): int
    {
        $this->warn("Not yet implemented: Sending backup to S3 bucket");
        return 0;
    }
}