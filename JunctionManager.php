<?php

class JunctionManager
{
    private $debugCallback = null;
    private $fileManager = null;

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
    }

    public function setFileManager(FileManager $fileManager): void
    {
        $this->fileManager = $fileManager;
    }

    private function dbg(string $msg): void
    {
        if ($this->debugCallback !== null) {
            ($this->debugCallback)($msg);
        }
    }

    public function restoreMissingJunction(string $appPath, string $base): void
    {
        $linkPath = $appPath . DIRECTORY_SEPARATOR . $base;

        if (is_dir($linkPath)) return;

        if ($this->fileManager === null) {
            return;
        }

        $versions = $this->fileManager->getLocalVersions($appPath);
        if (empty($versions)) return;

        sort($versions);
        $latest = end($versions);
        $target = $appPath . DIRECTORY_SEPARATOR . $latest;

        if (!is_dir($target)) return;

        $this->dbg("Junction chybí → vytvářím $base → $latest");

        exec('cmd /c mklink /J "' . $linkPath . '" "' . $target . '"');
    }

    public function removeJunction(string $path, string $name): void
    {
        if (strtolower(basename($path)) !== strtolower($name)) return;

        if (is_dir($path)) {
            @rmdir($path);
            if (is_dir($path)) {
                exec('cmd /c rmdir "' . $path . '"');
            }
        }
    }

    public function createJunction(string $target, string $link, string $name): void
    {
        if (strtolower(basename($link)) !== strtolower($name)) return;

        $this->removeJunction($link, $name);
        exec('cmd /c mklink /J "' . $link . '" "' . $target . '"');
    }
}

