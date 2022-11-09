<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\Laravel\Traits\OverrideDatabaseConfigTrait;
use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;

Class GenericPrepareDatabaseMigrateStep extends StepBase implements StepInterface
{
    use OverrideDatabaseConfigTrait;

    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Preparing database migrations");
        $this->overrideDatabaseConfig();

        $callingCommand = $this->engine->getCallingCommand();
        if(!$callingCommand) {
            $this->setFailure(255, "The calling command cannot be resolved.");
            return $this->getReturnCode();
        }

        try {
            if(env('LAGOON_ENVIRONMENT_TYPE') == "production") {
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Not performing migrations in a production environment");
                return $this->getReturnCode();
            }

            $migrateRet = $this->engine->runLaravelArtisanCommand(["migrate","--force"]);
            if($migrateRet > 0) {
                $this->setFailure($migrateRet, "Error migrating database");
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Error migrating database", false);
                return $this->getReturnCode();
            }

            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Migration step completed");
        } catch (\Exception $ex) {
            $this->setFailure($ex->getCode(), $ex->getMessage());
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                $this->getFailureMessage(), false);
            return $this->getReturnCode();
        }

        return $this->getReturnCode();
    }
}
