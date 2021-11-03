<?php

namespace App\Wobot;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EngineLogShipS3 extends EngineLogShipBase 
{
    protected $s3Key;
    protected $s3Secret;
    protected $s3Region;
    protected $s3Bucket;

    protected $s3Disk;

    public function __construct($key, $secret, $region, $bucket)
    {
        parent::__construct();

        $this->s3Key = $key;
        $this->s3Secret = $secret;
        $this->s3Region = $region;
        $this->s3Bucket = $bucket;

        $this->s3Disk = $this->getS3Disk();
    }   

    public function shipLogs(EngineInterface $engine, array $logFiles): int
    {
        foreach($logFiles as $logFile) 
        {
            $this->shipLog($engine, $logFile);
        }

        return 0;
    }

    public function shipLog(EngineInterface $engine, string $uri): int
    {
        $destination = $engine->getShippedLogStoragePath(File::name($uri). "." . File::extension($uri));
        $this->info("Shipping {$uri} to {$destination}");
        $this->s3Disk->put($destination, File::get($uri));
        
        if($this->s3Disk->exists($destination) && File::size($uri) == $this->s3Disk->size($destination)) {
            File::delete($uri);
        }

        return 0;
    }

    public function getS3Disk() {
        $disk = Storage::build([
            'driver' => 's3',
            'key' => $this->s3Key,
            'secret' => $this->s3Secret,
            'region' => $this->s3Region,
            'bucket' => $this->s3Bucket,
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ]);

        return $disk;
    }
}