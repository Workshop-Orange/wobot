<?php

namespace App\Wobot;

class EngineMilestone
{
    protected $isOK;
    protected $milestoneId;
    protected $message;

    public function __construct($milestoneId, $message, $isOK)
    {
        $this->setIsOK($isOK);
        $this->setMessage($message);
        $this->setMilestoneId($milestoneId);
    }

    public function setIsOK(bool $isOK = true)
    {
        $this->isOK = $isOK;
        return $this;
    }

    public function getIsOK()
    {
        return $this->isOK;
    }

    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setMilestoneId($milestoneId)
    {
        $this->milestoneId = $milestoneId;
        return $this;
    }

    public function getMilestoneId()
    {
        return $this->milestoneId;
    }
}