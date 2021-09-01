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
        if(! env('LAGOON_PR_NUMBER') && env('LAGOON_GIT_BRANCH') != "dev")
        {
            $this->info("We're not on a PR branch or on a dev branch. Assuming no DB seeding at this time.");
            return $this->getReturnCode();
        }

        if(env('LAGOON_ENVIRONMENT_TYPE') == "production") {
            $this->warn("I won't perform seeding in production");
            return $this->getReturnCode();
        }

        $this->info("Checking database seeding");
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
                return $this->getReturnCode();
            } else {
                $this->info("Migrations table found");
            }

            $result = DB::table("migrations")->count();
            if($result <= 0) {
                $this->setFailure(255, "migration table exists but has no contents, suggesting migrations failed.");
                return $this->getReturnCode();            
            } else {
                $this->info("Migrations table has entries");
            }

        } catch (\Exception $ex) {
            $this->setFailure(255, $ex->getMessage());
            return $this->getReturnCode();
        }

        try {
            $runSeeder = false;

            $result = DB::table("users")->count();

            if($result > 0 && empty(env('LAGOON_PR_NUMBER'))) {
                $this->warn("There are users in the database, and we aren't on a PR. Assuming we don't want to reseed.");
                $runSeeder = false;
                return $this->getReturnCode();
            } else if($result > 0 && !empty(env('LAGOON_PR_NUMBER'))) {
                $this->warn("There are users in the database, and we are on a PR. Reseeding.");
                $runSeeder = true;
            } else {
                $runSeeder = true;
            }
            
            if($runSeeder) {
                $migrateFreshRet = $this->engine->runLaravelArtisanCommand(["migrate:fresh"]);
                if($migrateFreshRet > 0) {
                    $this->setFailure($migrateFreshRet, "Error running migrate:fresh");
                    return $this->getReturnCode();
                } else {
                    $this->info("migrate:fresh status returns 0 exit status");
                }

                $migrateSeedRet = $this->engine->runLaravelArtisanCommand(["db:seed"]);
                if($migrateSeedRet > 0) {
                    $this->setFailure($migrateSeedRet, "Error running db:seed");
                    return $this->getReturnCode();
                } else {
                    $this->info("db:seed status returns 0 exit status");
                }
            } else {
                $this->warn("Seems like we will not run the seeder");
            }
        } catch (\Exception $ex) {
            $this->setFailure($ex->getCode(), $ex->getMessage());
            return $this->getReturnCode();
        }

        return $this->getReturnCode();
    }
}