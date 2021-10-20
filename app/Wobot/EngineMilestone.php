<?php

namespace App\Wobot;

class EngineMilestone
{
    protected $category;
    protected $message;

    public function __construct($category, $message)
    {
        $this->setMessage($message);
        $this->setCategory($category);
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

    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    public function getCategory()
    {
        return $this->category;
    }
}