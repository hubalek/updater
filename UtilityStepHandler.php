<?php

class UtilityStepHandler
{
    use DebugTrait;

    /**
     * Execute sleep step
     * @param int|array $sleepConfig Sleep duration in seconds, or array with "seconds" key
     * @return bool Always returns true
     */
    public function executeSleep(int|array $sleepConfig): bool
    {
        $seconds = is_int($sleepConfig) ? $sleepConfig : ($sleepConfig['seconds'] ?? 0);
        
        if ($seconds > 0) {
            $this->dbg("Sleeping for $seconds second(s)");
            sleep($seconds);
        }
        
        return true;
    }
}

