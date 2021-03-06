<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

Class GenericCheckMeilisearchStep extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function getScoutConfig()
    {
        if(isset($this->config['scout-config-file']) && File::exists($this->config['scout-config-file'])) {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                'Using provided Scout config: ' . $this->config['scout-config-file']);

            $scoutConfig = include($this->config['scout-config-file']);
            return $scoutConfig;
        } else {
            $this->engine->trackMilestoneProgress(class_basename($this::class), 
                'Using default Scout config');

            return config('scout');
        }
    }

    public function execute(): int
    {
        $this->engine->trackMilestoneProgress(class_basename($this::class), "Performing meilisearch check");

        $scoutConfig= $this->getScoutConfig();
        $driver = Arr::get($scoutConfig, "driver");
        $host = Arr::get($scoutConfig, "meilisearch.host");
        $key = Arr::get($scoutConfig, "meilisearch.key");
        $stats = $host . "/stats";

        if($driver != "meilisearch" || !$host) {
            $this->engine->trackMilestoneProgress(class_basename($this::class), "Skipping: Scout is not configured, or meilisearch is not used. [Driver={$driver}] [Host={$host}]");
            return $this->getReturnCode();
        }

        $this->engine->trackMilestoneProgress(class_basename($this::class), "Scout is configured and meilisearch is used. [Driver={$driver}] [Host={$host}]");

        try {

            $headers = [];
            if($key) {
                $this->engine->trackMilestoneProgress(class_basename($this::class), 
                    "Found an API key");
                $headers['X-Meili-API-Key'] = $key;
                $headers['Authorization'] = "Bearer " . $key;
            }

            $response = Http::timeout(3)->withHeaders($headers)->get($stats);
            $responsDataJson = $response->json();
            if(! $responsDataJson || ! is_array($responsDataJson) || ! isset($responsDataJson['indexes'])) {
                $this->setFailure(255, "Scout is used, meili is used, but the host url doesn't return expected data. |".$response->body()."|");
                
                return $this->getReturnCode();
            } else {
                $this->engine->trackMilestoneProgress(class_basename($this::class), "Success: Meilisearch returned expected data.");
            }
        } catch(\Exception $ex) {
            $this->setFailure(255, $ex->getMessage());
            return $this->getReturnCode();
        }

        return $this->getReturnCode();
    }
}