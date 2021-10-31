<?php

namespace App\Commands;

use App\Lagoon\DeploymentEngine\DeploymentBaseCommand;
use Dotenv\Dotenv;
use Exception;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class LagoonBuildPrepareCommand extends DeploymentBaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'lagoon-build:prepare
        {--wobot-conf=./.wobot.yml : Specify the wobot config file to use}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Prepare the lagoon build environment for wobot';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $wobotConfigFilePath = $this->option("wobot-conf");
        $config = Yaml::parseFile($wobotConfigFilePath);

        $envFiles = !empty($config['lagoon']['includeenvfiles']) && is_array($config['lagoon']['includeenvfiles'])  ? $config['lagoon']['includeenvfiles'] : [];
        $additionalEnv = Dotenv::createArrayBacked(getcwd(), $envFiles)->load();
        $fileContents = "# Autogenerated environment for Workshop Orange Docker Compose approach. \n# Do not change this file.\n";
        $fileContents .= "# This file is a merge of " . implode(", ", $envFiles) . "\n\n";
        foreach($additionalEnv as $key => $value) {
                $fileContents .= $key.'="' . $value . '"' . "\n";
        }

        File::put(".env", $fileContents);
    }
}
