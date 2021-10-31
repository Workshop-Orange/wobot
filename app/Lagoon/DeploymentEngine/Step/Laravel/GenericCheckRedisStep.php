<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use App\Lagoon\DeploymentEngine\Step\Laravel\Traits\OverrideDatabaseConfigTrait;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

Class GenericCheckRedisStep extends StepBase implements StepInterface
{
    use OverrideDatabaseConfigTrait;

    public function registered(): int
    {
        return parent::registered();
    }

    public function getSessionConfig()
    {
        if(isset($this->config['session-config-file']) && File::exists($this->config['session-config-file'])) {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 'Using provided Session config: ' . $this->config['session-config-file']);
            $sessionConfig = include($this->config['session-config-file']);
            return $sessionConfig;
        } else {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 'Using default Session config');
            return config('session');
        }
    }

    public function getCacheConfig()
    {
        if(isset($this->config['cache-config-file']) && File::exists($this->config['cache-config-file'])) {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 'Using provided Cache config: ' . $this->config['cache-config-file']);
            $cacheConfig = include($this->config['cache-config-file']);
            return $cacheConfig;
        } else {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 'Using default Cache config');
            return config('cache');
        }
    }
    
    public function execute(): int
    {
        $this->engine->trackMilestoneProgress(class_basename($this::class), "Performing redis check");
        $this->overrideDatabaseConfig();

        $redisDb = config('database.redis');
        $sessionDriver = Arr::get($this->getSessionConfig(),"driver");
        $cacheDefault = Arr::get($this->getCacheConfig(),"default");
        
        if(! $redisDb) {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Redis is not configured as a database. [sessionDriver={$sessionDriver}] [cacheDefault={$cacheDefault}]", false);
            $this->setFailure(255,"Redis is not configured as a database. [sessionDriver={$sessionDriver}] [cacheDefault={$cacheDefault}]");
            return $this->getReturnCode();
        }

        if($sessionDriver != "redis" && $cacheDefault != "redis") {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Redis is configured as a database, but the session and the cache dont use it. [sessionDriver={$sessionDriver}] [cacheDefault={$cacheDefault}]", false);
                $this->setFailure(255,"Redis is configured as a database, but session and cache don't use it [sessionDriver={$sessionDriver}] [cacheDefault={$cacheDefault}]");
                return $this->getReturnCode();
        }

        $this->engine->trackMilestoneProgress(class_basename($this::class), 
            "Redis is configured as a database, the session or the cache use it. [sessionDriver={$sessionDriver}] [cacheDefault={$cacheDefault}]");
        
        try {
            $testKey = "lagoon-redis-test-".uniqid();
            $testValue = "lagoon-redis-test-".uniqid();

            Redis::set($testKey, $testValue);
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Set [{$testKey}] = [{$testValue}]");

            $fetchTestValue = Redis::get($testKey);
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Get [{$testKey}] = [{$fetchTestValue}]");

            if($fetchTestValue != $testValue) {
                $this->setFailure(255, "Redis get/set values do not match");
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Redis get/set values do not match", false);
                return $this->getReturnCode();        
            }
        } catch (\Exception $ex) {
            $this->setFailure(255, "Error testing Redis get/set values: " . $ex->getMessage());
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                "Error testing Redis get/set values: " . $ex->getMessage(), false);
                
            return $this->getReturnCode();
        }

        return $this->getReturnCode();
    }
}