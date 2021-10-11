<?php

namespace App\Sanity;

use App\Sanity\BackupDestination\BackupDestinationInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Arr;

trait SanityEngineBackupTrait 
{
    protected $backupDestinations;

    public function initializeBackupTrait()
    {
        $this->backupDestinations = collect([]);
    }

    public function registerBackupDestination(BackupDestinationInterface $step)
    {
        $step->setUsedLocation($this->getUsedLocation());
        $ret = $step->registered();
        if($ret <= 0) {
            $this->backupDestinations->push($step);
        } 

        return $ret;
    }

    public function loadSanityBackupConfig(string $wobotConfigFilePath, string $configKey): array
    {
        if(!File::exists($wobotConfigFilePath)) {
            $this->setFailure(self::class, 255, "File not found: " . $wobotConfigFilePath);
            throw new Exception($this->getFailureMessage(), $this->getFailureCode());
        }

        $backups = [];
        try {
            $config = Yaml::parseFile($wobotConfigFilePath);
            if(empty($this->woProject)) 
            {
                $this->warn("Missing the project identifier from the environment, wobot checking configuration");
                $lagoonProject = Arr::get($config, "lagoon.project");
                if(!empty($lagoonProject)) {
                    $this->info("Found a laoon project id: " . $lagoonProject);
                    $this->setWoProject($lagoonProject);
                } else {
                    $this->setFailure(self::class, 255, "Project code is needed to name the backups");
                    throw new Exception($this->getFailureMessage(), $this->getFailureCode());
                }
            }

            if(empty($this->sanityProject)) 
            {
                $this->setFailure(self::class, 255, "Sanity project is needed to name the backups");
                throw new Exception($this->getFailureMessage(), $this->getFailureCode());
            }

            if(empty($this->sanityDataset)) 
            {
                $this->setFailure(self::class, 255, "Sanity dataset is needed to name the backups");
                throw new Exception($this->getFailureMessage(), $this->getFailureCode());
            }

            $backups = Arr::get($config, $configKey);
        } catch(Exception $ex) {
            $this->setFailure(self::class, 255, $ex->getMessage());

            throw new Exception($this->getFailureMessage(), $this->getFailureCode());
        }

        if(empty($backups) || count($backups) <= 0) {
            $this->setFailure(self::class, 255, "No destinations loaded");
            throw new Exception($this->getFailureMessage(), $this->getFailureCode());
        }

        foreach($backups as $configStep) {
            if(isset($configStep['class'])) {
                $className = $configStep['class'];
                if(!class_exists($className)) {
                    $this->setFailure(self::class, 255, "Class not found: " . $className);
            
                    throw new Exception($this->getFailureMessage(), $this->getFailureCode());                    
                }

                $newBackupDestination = new $className($this, $configStep);
                $this->registerBackupDestination($newBackupDestination); 
            } else {
                $this->error("Destination is missing the class step.");
            }
        }

        return $backups;
    }

    public function executeBackupSanityDataset() 
    {
        $backupHash = null;
        $backupFileName = $this->woProject . "-" . 
            $this->sanityProject . "-" . 
            $this->sanityDataset . "-" . 
            Carbon::now()->format("Y-m-d-H_i_s") . 
            ".tar.gz";

        $tmpBackupFileUri = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $backupFileName;
        
        touch($tmpBackupFileUri);
        if(! File::isWritable($tmpBackupFileUri)) {
            $this->setFailure(self::class, 255, "File is not writeable: " . $tmpBackupFileUri);
            throw new Exception($this->getFailureMessage(), $this->getFailureCode());  
        }

        $this->info("Running sanity backup to: " . $tmpBackupFileUri);
        $env = getenv();
        if(! isset($env['SANITY_AUTH_TOKEN'])) {
            $this->warn('Using the SANITY_TOKEN for the SANITY_AUTH TOKEN');
            $env['SANITY_AUTH_TOKEN'] = env('SANITY_TOKEN');
        }

        $ret = $this->runCommand([
            "sanity",
            "dataset",
            "export",
            $this->sanityDataset,
            $tmpBackupFileUri,
            "--overwrite"
        ]);

        if($ret > 0) {
            $this->setFailure(self::class, $ret, "Sanity export returned non-zero exit code");
            throw new Exception($this->getFailureMessage(), $this->getFailureCode());
        }
        
        $backupHash = md5_file($tmpBackupFileUri);

        foreach($this->backupDestinations as $destination)
        {
            $dRet = $destination->execute($backupFileName, $tmpBackupFileUri, $backupHash);
            if($dRet > 0) {
                $this->setFailure(get_class($destination), $destination->getReturnCode(), $destination->getFailureMessage());
                return $dRet;
            }
        }

        // All steps returned 0, we're good
        return 0;
    }
}