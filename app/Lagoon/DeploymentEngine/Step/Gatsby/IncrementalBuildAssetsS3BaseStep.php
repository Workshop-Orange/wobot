<?php


namespace App\Lagoon\DeploymentEngine\Step\Gatsby;


use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Support\Facades\Storage;

abstract class IncrementalBuildAssetsS3BaseStep extends StepBase implements StepInterface {
    public function getS3Disk() {
        $accessKeyVar = empty($this->config['s3accesskeyvar']) ? 'AWS_ACCESS_KEY_ID' : $this->config['s3accesskeyvar'];
        $secretAccessKeyVar = empty($this->config['s3secretaccesskeyvar']) ? 'AWS_SECRET_ACCESS_KEY' : $this->config['s3secretaccesskeyvar'];
        $s3bucketVar = empty($this->config['s3bucketvar']) ? 'AWS_BUCKET' : $this->config['s3bucketvar'];
        $s3regionVar = empty($this->config['s3regionvar']) ? 'AWS_BUCKET' : $this->config['s3regionvar'];
        
        $disk = Storage::build([
            'driver' => 's3',
            'key' => env($accessKeyVar),
            'secret' => env($secretAccessKeyVar),
            'region' => env($s3regionVar),
            'bucket' => empty($this->config['s3bucket']) ? env($s3bucketVar, '') : $this->config['s3bucket'],
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ]);

        return $disk;
    }
}