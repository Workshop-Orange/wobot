<?php

namespace App\Commands;

use App\Lagoon\DeploymentEngine\DeploymentBaseCommand;
use Exception;

class BuildGatsbyPreCommand extends DeploymentBaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'gatsby-build:pre 
        {--clean-up : Should the command clean up after itself - typically the post-build step}
        {--wobot-conf=./.wobot.yml : Specify the wobot config file to use}
        {--steps-key=build.gatsby.pre : Specify the dot notation key of build steps to use}
        {--trackers-key=deployment.trackers : Specify the dot notation key of tracker configuration to use}
        {--log-dir=./ : Specify the directory to store the logfile}
        {--this-project= : Specify what project this is}
        {--this-environment= : Specify what environment this is}
        {--this-service= : Specify what service this is}
        {--this-prbase= : Specify what prbase this is}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Run gatsby pre-build tasks';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        return $this->laggonBaseHandle("gatsby-pre-build");
    }
}
