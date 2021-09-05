<?php 

namespace App\Lagoon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NewRelicEngine
{
    protected $USER_API_KEY;
    protected $BASE_URL;
    protected $SYNTHETICS_BASE_URL;
    
    protected $SYNTHETIC_DEFAULT_LOCATIONS = [
        "AWS_US_EAST_1",
        "AWS_US_WEST_1",
        "AWS_EU_CENTRAL_1",
        "AWS_EU_WEST_1",
        "AWS_AF_SOUTH_1",
    ];

    
    protected $SYNTHETIC_DEFAULT_SIMPLE_OPTIONS = [
        "verifySSL" => true,
        "treatRedirectAsFailure" => true,
    ];

    public function __construct(string $USER_API_KEY, string $BASE_URL, string $SYNTHETICS_BASE_URL)
    {
        $this->USER_API_KEY = $USER_API_KEY;
        $this->BASE_URL = $BASE_URL;
        $this->SYNTHETICS_BASE_URL = $SYNTHETICS_BASE_URL;
    }

    public function apiBaseJsonGET($endPoint, $data = [])
    {
        return Http::withHeaders([
                'Api-Key' => $this->USER_API_KEY
            ])->withOptions([
                'base_uri' => $this->BASE_URL
            ])->get($endPoint, $data)->json();
    }

    public function apiBaseJsonPOST($endPoint, $data = [])
    {
        return Http::withHeaders([
                'Api-Key' => $this->USER_API_KEY
            ])->withOptions([
                'base_uri' => $this->BASE_URL
            ])->post($endPoint, $data)->json();
    }

    public function apiBaseJsonPOSTRawJsonBody($endPoint, $body)
    {
        $result = Http::withHeaders([
                'Api-Key' => $this->USER_API_KEY
            ])->withOptions([
                'base_uri' => $this->BASE_URL
            ])->withBody(
                $body, 'application/json'
            )->post($endPoint);
        
            return ["code" => $result->status(), "json" => $result->json(), "message" => $result->body(), "headers" => $result->headers()];
    }

    public function apiSyntheticsJsonGET($endPoint, $data = [])
    {
        return Http::withHeaders([
                'Api-Key' => $this->USER_API_KEY
            ])->withOptions([
                'base_uri' => $this->SYNTHETICS_BASE_URL
            ])->get($endPoint, $data)->json();
    }

    public function apiSyntheticsJsonPOST($endPoint, $data = [])
    {
        $result = Http::withHeaders([
                'Api-Key' => $this->USER_API_KEY,
                'Conten-Type' => 'application/json'
            ])->withOptions([
                'base_uri' => $this->SYNTHETICS_BASE_URL
            ])->post($endPoint, $data);
        
            return ["code" => $result->status(), "message" => $result->body(), "headers" => $result->headers()];
    }

    public function apiSyntheticsJsonPATCH($endPoint, $data = [])
    {
        $result = Http::withHeaders([
                'Api-Key' => $this->USER_API_KEY,
                'Conten-Type' => 'application/json'
            ])->withOptions([
                'base_uri' => $this->SYNTHETICS_BASE_URL
            ])->patch($endPoint, $data);

        return ["code" => $result->status(), "message" => $result->body(), "headers" => $result->headers()];
    }

    public function apiSyntheticsJsonDELETE($endPoint, $data = [])
    {
        $result = Http::withHeaders([
                'Api-Key' => $this->USER_API_KEY,
                'Conten-Type' => 'application/json'
            ])->withOptions([
                'base_uri' => $this->SYNTHETICS_BASE_URL
            ])->delete($endPoint, $data);

        return ["code" => $result->status(), "message" => $result->body(), "headers" => $result->headers()];
    }

    public function canTalkToNewRelic()
    {
        if(empty($this->USER_API_KEY)) {
            return false;
        }

        $data = $this->apiBaseJsonGET('applications.json');
        if(! is_array($data) || ! isset($data['applications']) || ! is_array($data['applications'])) {
            return false;
        }

        $data = $this->apiSyntheticsJsonGET('monitors',['limit' => 1]);
        if(! is_array($data) || ! isset($data['monitors']) || ! is_array($data['monitors'])) {
            return false;
        }

        return true;
    }

    public function getApplicationNameFromLagoonEnvironment(string $lagoonProjectId, string $lagoonEnvironmentName)
    {
        return $lagoonProjectId . '-' . $lagoonEnvironmentName;
    }

    public function getApplicationIDFromLagoonEnvironment(string $lagoonProjectId, string $lagoonEnvironmentName)
    {
        $applicationName = $this->getApplicationNameFromLagoonEnvironment($lagoonProjectId, $lagoonEnvironmentName);
        $data = $this->apiBaseJsonGET('applications.json', ['filter[name]' => $applicationName]);
        if(! is_array($data) || ! isset($data['applications']) || ! is_array($data['applications'])) {
            return null;
        }
        
        foreach($data['applications'] as $application) {
            if($application['name'] == $applicationName && isset($application['id'])) {
                return $application['id'];
            }
        }

        return null;
    }

    public function getMonitorsWithNameStartingWith(string $startsWith, $limit = 80, $depth = 0)
    {
        $offset = 0;

        if($depth > 0) {
            $offset = ($depth * $limit) + 1;
        }

        $monitors = [];

        $data = $this->apiSyntheticsJsonGET('monitors',['limit' => $limit, 'offset' => $offset]);
        if(! is_array($data) || ! isset($data['monitors']) || ! is_array($data['monitors'])) {
            return [];
        } 

        foreach($data['monitors'] as $monitor) {
            if(Str::startsWith($monitor['name'], $startsWith)) {
                $monitors[$monitor['name']] = $monitor;
            }
        }

        if(isset($data['count']) && $data['count'] >= $limit) {
            $moreMonitors = $this->getMonitorsWithNameStartingWith($startsWith, $limit, $depth + 1);
            $monitors = array_merge($monitors, $moreMonitors);
        } 

        return $monitors;
    }

    public function getMonitorIDForLagoonEnvironmentMonitor(string $lagoonProjectId, string $lagoonEnvironmentName, string $name, string $url) 
    {
        $applicationName = $this->getApplicationNameFromLagoonEnvironment($lagoonProjectId, $lagoonEnvironmentName);
        $monitorName = $applicationName . '-' . $name;
    }

    public function createSimpleMonitorForLagoonEnvironmentMonitor(string $lagoonProjectId, string $lagoonEnvironmentName, string $name, string $url, string $status = "enabled", int $frequency =  15, int $slaThreshold = 7, array $locations = [], array $options = [])
    {
        $applicationName = $this->getApplicationNameFromLagoonEnvironment($lagoonProjectId, $lagoonEnvironmentName);
        $monitorName = $applicationName . '-' . $name;
        if(count($locations) <= 0) {
            $locations = $this->SYNTHETIC_DEFAULT_LOCATIONS;
        }

        if(count($options) <= 0) {
            $options = $this->SYNTHETIC_DEFAULT_SIMPLE_OPTIONS;
        }

        $result = $this->apiSyntheticsJsonPOST("monitors", [
            "name" => $monitorName,
            "frequency" => $frequency,
            "uri" => $url,
            "status" => $status,
            "locations" => $locations,
            "type" => "simple",
            "slaThreshold" => $slaThreshold,
            "options" => $options
        ]);
        
        if(! empty($result['code']) && $result['code'] == 201) {
            $monitorURL = !empty($result['headers']['Location'][0]) ? $result['headers']['Location'][0] : '';
            if(preg_match('/^http.*\/api\/v3\/monitors\/(.*)$/', $monitorURL, $match)) {
                $monitorId = $match[1];
                $monitorData = $this->apiSyntheticsJsonGET("monitors/" . $monitorId);
                $result['monitor'] = $monitorData;
            }
        }

        return $result;
    }

    public function updateSimpleMonitorURL($monitorId, $newURL)
    {
        $result = $this->apiSyntheticsJsonPATCH("monitors/" . $monitorId,['uri' => $newURL]);
        return $result;
    }

    public function deleteSyntheticMonitor($monitorId)
    {
        $result = $this->apiSyntheticsJsonDELETE("monitors/" . $monitorId);
        print_r($result);
        return $result;
    }

    public function getPolicyForPolicyName($policyName)
    {
        $result = $this->apiBaseJsonGET("alerts_policies.json", ["filter[name]" => $policyName]);

        if(! empty($result['policies']) && is_array($result['policies'])) {
            foreach($result['policies'] as $policy) {
                if(isset($policy['name']) && $policy['name'] == $policyName) {
                    return $policy;
                }
            }
        }

        return null;
    }

    public function getSyntheticConditionsForPolicyId($policyId)
    {
        $result = $this->apiBaseJsonGET("alerts_synthetics_conditions.json",["policy_id" => $policyId]);
        $return = [];

        if(!empty($result['synthetics_conditions']) && is_array($result['synthetics_conditions'])) {
            foreach($result['synthetics_conditions'] as $condition) {
                $return[$condition['name']] = $condition;
            }
        }
        print_r($return);
        return $result;
    }

    public function createSyntheticPolicyConditionForMontior($syntheticConditionName, $monitorId, $policyId)
    {
        $jsonRequest = '{
            "synthetics_condition": {
              "name": "'.$syntheticConditionName.'",
              "monitor_id": "'.$monitorId.'",
              "enabled": true
            }
          }';

        $result = $this->apiBaseJsonPOSTRawJsonBody("alerts_synthetics_conditions/policies/{$policyId}.json", $jsonRequest);
        print_r($result);
        return $result;
    }
}