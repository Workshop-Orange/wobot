<?php

namespace App\Commands;

use App\Lagoon\DeploymentEngine\DeploymentBaseCommand;
use Exception;

class BuildGatsbyPostCommand extends DeploymentBaseCommand
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'gatsby-build:post 
        {--wobot-conf=./.wobot.yml : Specify the wobot config file to use}
        {--steps-key=build.gatsby.post : Specify the dot notation key of build steps to use}
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
    protected $description = 'Run gatsby post-build tasks';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        return $this->laggonBaseHandle("gatsby-post-build");
    }
}
