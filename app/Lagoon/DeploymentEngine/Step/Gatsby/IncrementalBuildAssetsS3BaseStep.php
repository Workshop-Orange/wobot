<?php


namespace App\Lagoon\DeploymentEngine\Step\Gatsby;


use App\Lagoon\DeploymentEngine\Step\StepBase;
use App\Lagoon\DeploymentEngine\Step\StepInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

abstract class IncrementalBuildAssetsS3BaseStep extends StepBase implements StepInterface {

    public function getBuildAssetsS3PathParts($fileName = "")
    {
        $ret = [
            Str::slug($this->engine->getProject()),
            Str::slug($this->engine->getEnvironment()),
        ];

        if($fileName) {
            $ret[] = $fileName;
        } else {
            $ret[] = "gatsby_build.tgz";
        }

        return $ret;
    }

    public function getBuildAssetsS3Path($uniquePart = "")
    {
        return DIRECTORY_SEPARATOR .  implode(DIRECTORY_SEPARATOR, $this->getBuildAssetsS3PathParts($uniquePart));
    }

    public function getS3Disk() {
        $accessKeyVar = empty($this->config['s3accesskeyvar']) ? 'AWS_ACCESS_KEY_ID' : $this->config['s3accesskeyvar'];
        $secretAccessKeyVar = empty($this->config['s3secretaccesskeyvar']) ? 'AWS_SECRET_ACCESS_KEY' : $this->config['s3secretaccesskeyvar'];
        $s3bucketVar = empty($this->config['s3bucketvar']) ? 'AWS_BUCKET' : $this->config['s3bucketvar'];
        $s3regionVar = empty($this->config['s3regionvar']) ? 'AWS_BUCKET' : $this->config['s3regionvar'];
        
        if(empty(env($s3regionVar))) {
            $this->setFailure(255, "An S3 region is required but couldn't be found");
            return null;
        }

        if(empty(env($s3bucketVar)) && empty($this->config['s3bucket'])) {
            $this->setFailure(255, "An S3 bucket is required but couldn't be found");
            return null;
        }

        if(empty(env($accessKeyVar))) {
            $this->setFailure(255, "An S3 access token is required but couldn't be found");
            return null;
        }

        if(empty(env($secretAccessKeyVar))) {
            $this->setFailure(255, "An S3 secret access token is required but couldn't be found");
            return null;
        }

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