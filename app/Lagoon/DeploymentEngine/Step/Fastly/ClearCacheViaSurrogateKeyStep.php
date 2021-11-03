<?php

namespace App\Lagoon\DeploymentEngine\Step\Fastly;

use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Support\Facades\Http;

Class ClearCacheViaSurrogateKeyStep extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        if(empty($this->config['surrogatekeys']) || !is_array($this->config['surrogatekeys']) || count($this->config['surrogatekeys']) <0) 
        {
            $this->engine->trackMilestoneProgress(class_basename($this::class),
                "No surrogate keys specified");
        }
        
        $fastlyServiceIdVar = empty($this->config['fastlyserviceidvar']) ? 'LAGOON_FASTLY_SERVICE_ID' : $this->config['fastlyserviceidvar'];
        $fastlyServiceTokenVar = empty($this->config['fastlyservicetokenvar']) ? 'FASTLY_SERVICE_TOKEN' : $this->config['fastlyservicetokenvar'];

        $fastlyServiceId = env($fastlyServiceIdVar, '');
        $fastlyServiceToken = env($fastlyServiceTokenVar, '');

        if(empty($fastlyServiceId) || empty($fastlyServiceToken)) 
        {
            $this->setFailure(255, "Both the Fastly service id and token are required to clear surrogate cache keys");
            return $this->getReturnCode();
        }

        if(preg_match("/^(.*):(.*)/", $fastlyServiceId, $matches)) {
            $fastlyServiceId = $matches[1];
        }

        $redactedToken = preg_replace("/\w/", "x", $fastlyServiceToken);

        $this->engine->trackMilestoneProgress(class_basename($this::class),
                    "Clearing caches [Token: {$redactedToken}] [Service: {$fastlyServiceId}]");

        foreach($this->config['surrogatekeys'] as $key) {
            $url = "https://api.fastly.com/service/{$fastlyServiceId}/purge/{$key}";

            $response = Http::withHeaders(["Fastly-Key" => $fastlyServiceToken])->post($url);
            if($response->json('status') != "ok") {
                $this->setFailure(255, "Failed clearing cache for surrogate key: " . $key);

                $this->engine->trackMilestoneProgress(class_basename($this::class),
                    $this->getFailureMessage(), false);
                
                return $this->getReturnCode();
            } else {
                $this->engine->trackMilestoneProgress(class_basename($this::class),
                    "Successfully cleared cache for surrogate key: " . $key);
            }
        }

        $this->engine->trackMilestoneProgress(class_basename($this::class),
                    "Cache successfully cleared for requested surrogate keys");

        return 0;
    }
}