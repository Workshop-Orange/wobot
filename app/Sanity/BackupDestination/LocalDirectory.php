<?php

namespace App\Sanity\BackupDestination;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;

class LocalDirectory extends BackupDestinationBase implements BackupDestinationInterface
{
    public function execute(string $fileName, string $backupFileUri, string $md5Hash): int
    {
        $backupDir = $this->config['dir'] . DIRECTORY_SEPARATOR . 
            Carbon::now()->year . DIRECTORY_SEPARATOR .
            Carbon::now()->month . DIRECTORY_SEPARATOR;
        
        $backupFile = $backupDir . $fileName;

        $this->info("Sending backup to local directory [tmp: ".$backupFileUri."] [stored: ".$backupFile."]");
        if(! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true, true);

            if(! File::isDirectory($backupDir)) {
                $this->setFailure(255, "Could not create the backup directory: " . $backupDir);
                throw new Exception($this->getFailureMessage(), $this->getReturnCode());
            }
        }

        File::copy($backupFileUri, $backupFile);

        $newMd5Hash = md5_file($backupFile);

        if($md5Hash != $newMd5Hash) {
            $this->setFailure(255, "MD5 of backup and destination copies do not match: [".$md5Hash."] [".$newMd5Hash."]");
            throw new Exception($this->getFailureMessage(), $this->getReturnCode());
        }

        return 0;
    }
}