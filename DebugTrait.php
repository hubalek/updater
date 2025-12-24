<?php

trait DebugTrait
{
    private $debugCallback = null;

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
    }

    protected function dbg(string $msg): void
    {
        if ($this->debugCallback !== null) {
            ($this->debugCallback)($msg);
        }
    }
}

