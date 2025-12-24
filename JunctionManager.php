<?php

class JunctionManager
{
    use DebugTrait;

    private $fileManager = null;

    public function setFileManager(FileManager $fileManager): void
    {
        $this->fileManager = $fileManager;
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

        $this->dbg("Junction missing → creating $base → $latest");

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

