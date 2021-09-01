<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel\Traits;

use Illuminate\Support\Facades\File;

trait OverrideDatabaseConfigTrait 
{
    public function overrideDatabaseConfig()
    {
        if(isset($this->config['database-config-file']) && File::exists($this->config['database-config-file'])) {
            $this->info('Using provided DB config: ' . $this->config['database-config-file']);
            $dbConfig = include($this->config['database-config-file']);
            config(['database' => $dbConfig]);
        } else {
            $this->info('Using default DB config');
        }
    }
}