<?php

namespace App\Wobot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class EngineMilestoneTrackerSlack extends EngineMilestoneTrackerBase implements EngineMilestoneTrackerInterface
{
    protected $slackToken;
    protected $slackChannel;
    protected $maxBatch = 10;
    protected $milestoneBatcher;
    protected $startFields;

    public function configure(array $config)
    {
        parent::configure($config);
        
        $this->maxBatch = $this->config->get('maxbatch') ? $this->config->get('maxbatch') : 10;
        $this->slackToken = $this->config->get('tokenvar') ? env($this->config->get('tokenvar'),'') : '' ;
        $this->slackChannel = $this->config->get('channel');
        $this->engine->info("Slack token: " . $this->slackToken);
        $this->engine->info("Slack channel: " . $this->slackChannel);
        $this->milestoneBatcher = collect([]);
    }

    public function trackMilestoneProgress(EngineMilestone $milestone)
    {
        $this->milestoneBatcher->push($milestone);

        if($this->milestoneBatcher->count() >= $this->maxBatch) {
            $this->sendBatch();
        }
    }

    public function sendBatch()
    {
        $attachments = [];

        while($milestone = $this->milestoneBatcher->shift()) {
            $attachments[] = [
                "fallback" => $milestone->getMessage(),
                "color"=>  $milestone->getIsOK() ? "#00FF00" : "#FF0000",
                "text" => $milestone->getMessage(),
                "footer"=>  $this->engine->getUsedLocation() . " | Category: " . $milestone->getMilestoneId(),
            ];
        }

        if(count($attachments) > 0) {
            $this->engine->info("Sending ". count($attachments)." attachments to slack");
            $this->sendAttachments("", $attachments);
        }
    }

    public function startTracker(string $milestoneId, $message, array $fields = [])
    {
        $this->startFields = $fields;
        if(!$this->threadId) {
            $return = $this->send("Deployment: " . $this->engine->getUsedLocation(), $fields);
            if(isset($return['ts'])) {
                $this->threadId = $return['ts'];
                if($this->deploymentthreadFile) {
                    $this->engine->info("Stored thread id for future messages: " . $this->deploymentthreadFile);
                    File::put($this->deploymentthreadFile, $this->threadId);
                }
            }    
        } else {
            $this->updateMainThread("Deployment: " . $this->engine->getUsedLocation(), true);
        }

        $return = $this->send($message, $fields);
    }

    public function updateMainThread($message, bool $isOK)
    {
        $payload = [
            'channel'    => $this->slackChannel,
            'text'       => $message,
            'username'   => "Wobot",
            'icon_emoji' => ":robot_face:",
            'ts' => $this->threadId
        ];
        
        $attachments[] = [
            "fallback" => $message,
            "color"=>  $isOK ? "#00FF00" : "#FF0000",
            "fields" =>  $this->startFields,
            "footer"=>  $this->engine->getUsedLocation(),
        ];

        $payload['attachments'] = $attachments;

        try {
            $response = Http::withToken($this->slackToken)->post("https://slack.com/api/chat.update", $payload);
        
            if(!$response->successful() || ! $response->json('ok')) {
                if($response->json('error')) {
                    $this->engine->error("Slack: HTTP-". $response->status() . "] " . $response->json('error'));    
                }

                if($response->json('warning')) {
                    $this->engine->warn("Slack: HTTP-". $response->status() . "] " . $response->json('warning'));    
                }
            }
        } catch (\Exception $e) {
            $this->engine->error($e->getMessage());
        }
    }

    public function endTracker(string $milestoneId, $message, array $fields = [], bool $isOK = true)
    {
        $this->sendBatch();
        $this->send($message, $fields, $isOK);

        if($this->threadId) {
            $this->updateMainThread("Deployment: " . $this->engine->getUsedLocation() . " " . ($isOK ? "passed" : "failed"), $isOK);
        }
    }
    
    public function send($message, $fields = [], bool $isOK = true)
    {    
        $attachments = [];

        if(count($fields) > 0) {
            $attachments[] = [
                    "fallback" => $message,
                    "color"=>  $isOK ? "#00FF00" : "#FF0000",
                    "fields" =>  $fields,
                    "footer"=>  $this->engine->getUsedLocation(),
            ];
        }
                   
        return $this->sendAttachments($message, $attachments);     
    }

    public function sendAttachments($message, $attachments = [])
    {    
        $payload = [
                'channel'    => $this->slackChannel,
                'text'       => $message,
                'username'   => "Wobot",
                'icon_emoji' => ":robot_face:",
            ];

            if(count($attachments) > 0) {
                $payload['attachments'] = $attachments;
            }
                   
            if($this->threadId) {
                $payload['thread_ts'] = $this->threadId;
            }
                
            try {
                $response = Http::withToken($this->slackToken)->post("https://slack.com/api/chat.postMessage", $payload);
            
                if(!$response->successful() || ! $response->json('ok')) {
                    if($response->json('error')) {
                        $this->engine->error("Slack: HTTP-". $response->status() . "] " . $response->json('error'));    
                    }

                    if($response->json('warning')) {
                        $this->engine->warn("Slack: HTTP-". $response->status() . "] " . $response->json('warning'));    
                    }
                }
            } catch (\Exception $e) {
                $this->engine->error($e->getMessage());
            }
     
        return $response->json();
    }
}