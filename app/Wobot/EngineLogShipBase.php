<?php

namespace App\Wobot;

use Carbon\Carbon;
use Illuminate\Console\Command;


abstract class EngineLogShipBase implements EngineLogShipInterface {
    protected $callingCommand;

    public function __construct()
    {
        
    }

    public function setCallingCommand(Command $callingCommand) 
    {
        $this->callingCommand = $callingCommand;
        return $this;
    }

    public function getCallingCommand()
    {
        return $this->callingCommand;
    }

    public function canSendConsoleOutput()
    {
        return $this->callingCommand && $this->callingCommand->getOutput();
    }

    public function info($log, $prefix = null)
    {     

        $now = Carbon::now();
        $logString = $now . ":" .  ( $prefix ? $prefix : 'global' ) . ":" . $log;
        
        if($this->canSendConsoleOutput()) {
            $this->callingCommand->info($logString);
        }
        
        app('log')->info($logString);
    }

    public function error($log, $prefix = null)
    {     
        $now = Carbon::now();
        $logString = $now . ":" .  ( $prefix ? $prefix : 'global' ) . ":" . $log;
        
        if($this->canSendConsoleOutput()) {
            $this->callingCommand->error($logString);
        }
        
        app('log')->error($logString);
    }

    public function warn($log, $prefix = null)
    {     
        $now = Carbon::now();
        $logString = $now . ":" .  ( $prefix ? $prefix : 'global' ) . ":" . $log;
        
        if($this->canSendConsoleOutput()) {
            $this->callingCommand->warn($logString);
        }
        
        app('log')->warn($logString);
    }
}