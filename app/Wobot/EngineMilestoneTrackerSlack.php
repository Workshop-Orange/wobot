<?php

namespace App\Wobot;

use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\File;

class EngineMilestoneTrackerSlack extends EngineMilestoneTrackerBase implements EngineMilestoneTrackerInterface
{
    protected $slackToken;
    protected $slackChannel;
    protected $threadId;
    protected $maxBatch = 15;
    protected $milestoneBatcher;
    protected $deploymentthreadFile;

    public function configure(array $config)
    {
        parent::configure($config);
        $this->slackToken = $this->config->get('tokenvar') ? env($this->config->get('tokenvar'),'') : '' ;
        $this->slackChannel = $this->config->get('channel');
        $this->engine->info("Slack token: " . $this->slackToken);
        $this->engine->info("Slack channel: " . $this->slackChannel);
        $this->milestoneBatcher = collect([]);


        if($this->config->get('deploymentthread')) {
            $this->deploymentthreadFile = $this->config->get('deploymentthread');
            if(File::exists($this->deploymentthreadFile)) {
                $this->engine->info("Reviving thread for deployment: " . $this->deploymentthreadFile);
                $this->threadId = File::get($this->deploymentthreadFile);
                $this->engine->info("Using thread: " . $this->threadId);
            }
        }
    }

    public function trackMilestone(EngineMilestone $milestone)
    {
        $this->engine->warn("SLACK TRACKER: [thread: {$this->threadId}] [category: {$milestone->getCategory()}] [message: {$milestone->getMessage()}]");
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
                "color"=>  "#36a64f",
                "text" => $milestone->getMessage(),
                "footer"=>  $this->engine->getUsedLocation() . " | Catebory: " . $milestone->getCategory(),
            ];
        }

        if(count($attachments) > 0) {
            $this->engine->info("Sending ". count($attachments)." attachments to slack");
            $this->sendAttachments("", $attachments);
        }
    }

    public function startTracker($message, array $fields = [])
    {
        $this->engine->warn("START SLACK TRACKER: " . $message);
        $return = $this->send($message, $fields);
        if(!$this->threadId && isset($return['ts'])) {
            $this->threadId = $return['ts'];
            if($this->deploymentthreadFile) {
                $this->engine->info("Stored thread id for future messages: " . $this->deploymentthreadFile);
                File::put($this->deploymentthreadFile, $this->threadId);
            }
        }
    }

    public function endTracker($message, array $fields = [])
    {
        $this->engine->warn("END SLACK TRACKER: " . $message);
        $this->sendBatch();
        $this->send($message, $fields);
    }
    
    public function send($message, $fields = [])
    {    
        $attachments = [];

        if(count($fields) > 0) {
            $attachments[] = [
                    "fallback" => $message,
                    "color"=>  "#36a64f",
                    "fields" =>  $fields,
                    "footer"=>  "Wobot",
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
            } catch (\Exception $e) {
                $this->engine->error($e->getMessage());
            }
     
        return $response->json();
    }
}