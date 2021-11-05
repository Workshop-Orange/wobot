<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Lagoon\DeploymentEngine\Step\StepBase;
use Exception;
use Illuminate\Support\Str;

Class SyncDatabaseStep extends SyncBaseStep implements StepInterface
{
    public function execute(): int
    {
        $syncRet = $this->executeSync("mariadb");

        if($syncRet > 0) {
            return $syncRet;
        }

        if(empty($this->config['anonymize']) || $this->config['anonymize'] != true) {
            return $syncRet;
        }

        try {
            $comRet = $this->engine->runLaravelArtisanCommand([
             'db:anonymize'
            ]);

            if($comRet > 0) {
                $this->setFailure(255, "Database anonymization failed");
                return $this->getReturnCode();
            }
        } catch(Exception $ex) {
            $this->setFailure(255, "Database anonymization failed: " . $ex->getMessage());
            return $this->getReturnCode();
        }

        $this->engine->trackMilestoneProgress(class_basename($this), "Database anonymized successfully");
        return 0;
    }
}