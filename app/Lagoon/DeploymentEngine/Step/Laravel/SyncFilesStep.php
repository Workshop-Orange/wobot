<?php

namespace App\Lagoon\DeploymentEngine\Step\Laravel;

use App\Lagoon\DeploymentEngine\Step\StepInterface;
use App\Lagoon\DeploymentEngine\Step\StepBase;

Class SyncFilesStep extends SyncBaseStep implements StepInterface
{
    public function execute(): int
    {
        return $this->executeSync("files");
    }
}