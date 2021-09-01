<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use App\Lagoon\DeploymentEngine\Step\Laravel\Traits\OverrideDatabaseConfigTrait;

Class GenericCheckDatabaseStep extends StepBase implements StepInterface
{
    use OverrideDatabaseConfigTrait;

    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->info("Checking database");

        $this->overrideDatabaseConfig();
        DB::reconnect();

        $callingCommand = $this->engine->getCallingCommand();
        if(!$callingCommand) {
            $this->setFailure(255, "The calling command cannot be resolved.");
            return $this->getReturnCode();
        }

        try {
            
            if(env('LAGOON_ENVIRONMENT_TYPE') == "production") {
                $this->warn("I won't perform deep database checks in production");
                return $this->getReturnCode();
            }

            if(!Schema::hasTable("migrations")) {
                $this->setFailure(254, "migration table is missing, suggesting migrations did not run.");
                return $this->getReturnCode();
            } else {
                $this->info("Migrations table found");
            }

            $result = DB::table("migrations")->count();
            if($result <= 0) {
                $this->setFailure(253, "migration table exists but has no contents, suggesting migrations failed.");
                return $this->getReturnCode();            
            } else {
                $this->info("Migrations table has entries");
            }

        } catch (\Exception $ex) {
            $this->setFailure(252, "ERR: " . $ex->getMessage());
            return $this->getReturnCode();
        }

        try {
            $migrateRet = $this->engine->runLaravelArtisanCommand(["migrate:status"]);
            if($migrateRet > 0) {
                $this->setFailure($migrateRet, "Error checking migration status");
                return $this->getReturnCode();
            } else {
                $this->info("Migrations status returns 0 exit status");
            } 
        } catch (\Exception $ex) {
            $this->setFailure($ex->getCode(), "ERR:" . $ex->getMessage());
            return $this->getReturnCode();
        }

        return $this->getReturnCode();
    }
}