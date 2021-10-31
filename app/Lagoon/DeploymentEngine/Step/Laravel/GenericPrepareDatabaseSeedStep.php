<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Lagoon\DeploymentEngine\Step\Laravel\Traits\OverrideDatabaseConfigTrait;

Class GenericPrepareDatabaseSeedStep extends StepBase implements StepInterface
{
    use OverrideDatabaseConfigTrait;

    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->setFailure(255, "The seeder logic needs to be rewritten. Don't use this.");
        $this->engine->trackMilestoneProgress(class_basename($this::class), 
            "ERROR: The seeder logic needs to be rewritten.", false);

        return $this->getReturnCode();

        if(env('LAGOON_ENVIRONMENT_TYPE') == "production") {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Not performing seeding in a production environment");

            return $this->getReturnCode();
        }

        $this->overrideDatabaseConfig();
        DB::reconnect();

        $callingCommand = $this->engine->getCallingCommand();
        if(!$callingCommand) {
            $this->setFailure(255, "The calling command cannot be resolved.");
            return $this->getReturnCode();
        }

        try {
            if(!Schema::hasTable("migrations")) {
                $this->setFailure(255, "migration table is missing, suggesting migrations did not run.");
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "migration table is missing, suggesting migrations did not run", false);
                return $this->getReturnCode();
            } else {
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "migration table found");
            }

            $result = DB::table("migrations")->count();
            if($result <= 0) {
                $this->setFailure(255, "migration table exists but has no contents, suggesting migrations failed.");
                $this->engine->trackMilestoneProgress(class_basename($this::class), $this->getFailureMessage(), false);
                return $this->getReturnCode();            
            } else {
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Migrations table has entries");
            }

        } catch (\Exception $ex) {
            $this->setFailure(255, $ex->getMessage());
            return $this->getReturnCode();
        }

        try {
            $migrateFreshRet = $this->engine->runLaravelArtisanCommand(["migrate:fresh"]);
            if($migrateFreshRet > 0) {
                $this->setFailure($migrateFreshRet, "Error running migrate:fresh");
                $this->engine->trackMilestoneProgress(class_basename($this::class), $this->getFailureMessage(), false);
                return $this->getReturnCode();
            } else {
                $this->engine->trackMilestoneProgress(class_basename($this::class), "migrate:fresh success");
            }

            $migrateSeedRet = $this->engine->runLaravelArtisanCommand(["db:seed"]);
            if($migrateSeedRet > 0) {
                $this->setFailure($migrateSeedRet, "Error running db:seed");
                return $this->getReturnCode();
            } else {
                $this->engine->trackMilestoneProgress(class_basename($this::class), "db:seed success");
            }
            
        } catch (\Exception $ex) {
            $this->setFailure($ex->getCode(), $ex->getMessage());
            return $this->getReturnCode();
        }

        return $this->getReturnCode();
    }
}