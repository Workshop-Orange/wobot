<?php

namespace App\Lagoon;

use Monolog\Logger;
use Illuminate\Http\Request;
use Monolog\Handler\BufferHandler;
use NewRelic\Monolog\Enricher\{Handler, Processor};

class NewRelicLogger
{
    protected $request;
    protected $config;

    public function __construct(Request $request = null)
    {
        $this->request = $request;
    }

    public function __invoke(array $config)
    {
	    $this->config = $config;

        // add the new relic logger
        $log = new Logger(isset($this->config['channel']) ? $this->config['channel'] : 'laravel');
        $log->pushProcessor(new Processor);
        $handler = new Handler;
        
        // Optional - if you don't have the new relic php agent installed.
        if(isset($this->config['NEWRELIC_LICENSE'])) {
            $handler->setLicenseKey($this->config['NEWRELIC_LICENSE']);
        }

        // using the BufferHandler improves the performance by batching the log 
        // messages to the end of the request
        // $log->pushHandler(new BufferHandler($handler));
        $log->pushHandler($handler);

        foreach ($log->getHandlers() as $handler) {
            $handler->pushProcessor([$this, 'includeMetaData']);
        }

        return $log;
    }

    // lets add some extra metadata to every request
    public function includeMetaData(array $record): array
    {
        // set the service or app name to the record
        $record['service'] = isset($this->config['service']) ? $this->config['service'] : "unconfigured";

        // set the hostname to record so we know host this was created on
        $record['hostname'] = gethostname();

        $record['env'] = isset($this->config['env']) ? $this->config['env'] : [];

        // check to see if we have a request
        if($this->request){
            $record['extra'] += [
                'ip' => $this->request->getClientIp(),
            ];
        }
        return $record;
    }
}
