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
        $this->info("Preparing database migrations");
        $this->overrideDatabaseConfig();

        $callingCommand = $this->engine->getCallingCommand();
        if(!$callingCommand) {
            $this->setFailure(255, "The calling command cannot be resolved.");
            return $this->getReturnCode();
        }

        try {
            if(env('LAGOON_ENVIRONMENT_TYPE') == "production") {
                $this->warn("I won't perform migrations in production");
                return $this->getReturnCode();
            }

            $migrateRet = $this->engine->runLaravelArtisanCommand(["migrate"]);
            if($migrateRet > 0) {
                $this->setFailure($migrateRet, "Error migrating database");
                return $this->getReturnCode();
            }
        } catch (\Exception $ex) {
            $this->setFailure($ex->getCode(), $ex->getMessage());
            return $this->getReturnCode();
        }

        return $this->getReturnCode();
    }
}