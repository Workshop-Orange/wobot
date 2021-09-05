<?php

namespace App\Lagoon\DeploymentEngine\Step;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\NewRelicEngine;
use Illuminate\Support\Arr;

Class GenericPrepareNewRelicSyntheticStep extends StepBase implements StepInterface
{
    public function registered(): int
    {
        return parent::registered();
    }
    
    public function execute(): int
    {
        $this->info("Generic NewRelic Syntentic Check");

        $lagoonProjectName = 'wo-webapp-proworkers-us';
        $lagoonEnvironmentName = 'main';
        if(!isset($this->config['monitors']) || count($this->config['monitors']) < 0) {
            $this->warn("There are no synthetic monitors configured. Skipping.");
            return 0;
        }

        if(!isset($this->config['monitors'][$lagoonEnvironmentName]) || count($this->config['monitors'][$lagoonEnvironmentName]) < 0) {
            $this->warn("There are no synthetic monitors configured for the {$lagoonEnvironmentName} environment. Skipping.");
            return 0;
        }

        $desiredMonitors = $this->config['monitors'][$lagoonEnvironmentName];
        $this->info("There are ". count($desiredMonitors) ." synthetic monitors configured for the {$lagoonEnvironmentName} environment.");

        $newRelicEngine = app(NewRelicEngine::class);

        if(! $newRelicEngine->canTalkToNewRelic()) {
            $this->warn("Cannot communicate with the NewRelic API. Skipping this time.");
            return 0;
        } else {
            $this->info("API communication is possible.");
        }

        $applicationName = $newRelicEngine->getApplicationNameFromLagoonEnvironment($lagoonProjectName, $lagoonEnvironmentName);
        $applicationID = $newRelicEngine->getApplicationIDFromLagoonEnvironment($lagoonProjectName, $lagoonEnvironmentName);
        if(empty($applicationID)) {
            $this->warn("There is no application matching the application name [{$applicationID}]. Skipping synthetic monitoring this time.");    
            
            return 0;
        }

        $this->info("Found the NewRelic Application [Name={$applicationName} ID={$applicationID}]");
        $existingMonitors = $newRelicEngine->getMonitorsWithNameStartingWith($applicationName);

        /* BEWARE: DELETE ALL MONITORS :D 
        foreach($existingMonitors as $monitor) {
            $this->info("Deleteing monitor " .$monitor['id']);
            $newRelicEngine->deleteSyntheticMonitor($monitor['id']);
        }
        
        return 0;
        */

        $this->info("There are " . count($existingMonitors) . " existing monitors for application " . $applicationName);

        $policyIds = [];

        foreach($desiredMonitors as $desiredMonitor) {
            $name = isset($desiredMonitor['name']) ? $desiredMonitor['name'] : uniqid();
            $url = isset($desiredMonitor['url']) ? $desiredMonitor['url'] : null;
            if(empty($url) || empty($name)) {
                $this->warn("No URL or name set for syntetic monitor with [name={$name}] [url={$url}]. Skipping");
                continue;
            }

            $desiredMonitorName = $applicationName . '-' . $name;
            $this->info("Checking synthetic monitoring for {$name}: {$url}");
            $connectToPolicy = false;
            $monitorId = null;
            if(Arr::has($existingMonitors, $desiredMonitorName)) {
                $this->info("Found an existing monitor for " . $desiredMonitorName);
                $existingMonitor = $existingMonitors[$desiredMonitorName];
                if(empty($existingMonitor['uri']) || $existingMonitor['uri'] != $url) {
                    $this->warn("The monitor URL has changed. Updating it.");
                    
                    $updateResult = $newRelicEngine->updateSimpleMonitorURL($existingMonitor['id'], $url);
                    $updateCode = isset($updateResult['code']) ? $updateResult['code'] : '';
                    $updateMessage = isset($updateResult['message']) ? $updateResult['message'] : '';
                    
                    if($updateCode == 204) {
                        $this->info("Montior [{$existingMonitor['id']}] URL updated to [{$url}]");
                        $connectToPolicy = true;
                        $monitorId = $existingMonitor['id'];
                    } else {
                        $this->warn("There was a problem updating the URL for Montior [{$existingMonitor['id']}]: [" . $updateCode ."] "  . $updateMessage);
                    }
                } else {
                    $this->info("The monitor URL for " . $desiredMonitorName . " is correct.");
                    $connectToPolicy = true;
                    $monitorId = $existingMonitor['id'];
                }
            } else {
                $this->info("Did not find an existing monitor for " . $desiredMonitorName);
                
                $createResult = $newRelicEngine->createSimpleMonitorForLagoonEnvironmentMonitor($lagoonProjectName, $lagoonEnvironmentName, $name, $url);
                $createCode = isset($createResult['code']) ? $createResult['code'] : '';
                $createMessage = isset($createResult['message']) ? $createResult['message'] : '';
                $createMonitor = isset($createResult['monitor']) ? $createResult['monitor'] : [];

                if($createCode == 201) {
                    $this->info("Montior created for URL [{$url}]");
                    $connectToPolicy = true;
                    if(isset($createMonitor) && is_array($createMonitor)) {
                        $monitorId = $createMonitor['id'];
                    }
                } else {
                    $this->warn("There was a problem creating the monitor {$desiredMonitorName} for URL {$url}: [" . $createCode ."] "  . $createMessage);
                }
            }

            $policyName = isset($desiredMonitor['policy']) ? $desiredMonitor['policy'] : '';
            $policyId = isset($policyIds[$policyName]) ? $policyIds[$policyName] : '';
            $conditionName = $desiredMonitorName . "-condition"; 
            if($policyName && empty($policyId)) {
                $policy = $newRelicEngine->getPolicyForPolicyName($policyName);
                if(isset($policy['id'])) {
                    $policyIds[$policyName] = $policy['id'];
                    $policyId = $policy['id'];
                }
            } 

            if($connectToPolicy && $policyId) {
                if($monitorId) {
                    $this->info("Checking monitor [{$monitorId}] for policy [{$policyName}] [Policy ID={$policyId}]");
                    $existingPolicyConditions = $newRelicEngine->getSyntheticConditionsForPolicyId($policyId);
                    if(isset($existingPolicyConditions[$conditionName])) {
                        $this->info("Synthetic Condition exists for " . $conditionName);
                    } else {
                        $this->info("Synthetic Condition does not exists for " . $conditionName . ". Creating");

                        // TODO: BG Left Off Here
                        $createConditionResult = $newRelicEngine->createSyntheticPolicyConditionForMontior($conditionName, $monitorId, $policyId);
                    }
                } else {
                    $this->warn("Connect to policy desired, but missing monitor ID means it is not possible for the monitor {$desiredMonitorName} for URL {$url}");
                }
            } else {
                $this->warn("Connect to policy not required or not possible for the monitor {$desiredMonitorName} for URL {$url}. [Policy={$policyName}] [Policy ID={$policyId}]");
            }
        }

        

        $existingMonitors = $newRelicEngine->getMonitorsWithNameStartingWith($applicationName);

        /* BEWARE: DELETE ALL MONITORS :D 
        foreach($existingMonitors as $monitor) {
            $this->info("Deleteing monitor " .$monitor['id']);
            $newRelicEngine->deleteSyntheticMonitor($monitor['id']);
        }
        */         

        return 0;
    }
}