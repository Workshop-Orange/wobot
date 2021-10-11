<?php

namespace App\Sanity;

use App\Wobot\EngineBase;


class SanityEngine extends EngineBase implements SanityEngineInterface
{
    use SanityEngineBackupTrait;

    protected $woProject;
    protected $sanityProject;
    protected $sanityDataset;

    public function __construct($usedLocation = "sanity", $logDirectory = "./", $woProject = "", $sanityProject = "", $sanityDataset = "")
    {
        parent::__construct($usedLocation, $logDirectory);
        
        $this->setWoProject($woProject);
        $this->setSanityProject($sanityProject);
        $this->setSanityDataset($sanityDataset);
        $this->initializeBackupTrait();
    }

    public function setWoProject(string $woProject) {
        $this->woProject = $woProject;
    }

    public function setSanityProject(string $sanityProject) {
        $this->sanityProject = $sanityProject;
    }
    
    public function setSanityDataset(string $sanityDataset) {
        $this->sanityDataset = $sanityDataset;
    }
}