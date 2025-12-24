<?php

class UtilityStepHandler
{
    use DebugTrait;

    private FileManager $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

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

    /**
     * Execute remove step
     * @param string|array $removeConfig Path to remove, or array with "path" key (supports wildcards)
     * @param array $variables Variables for path resolution
     * @param string $basePath Base path for resolving relative paths
     * @return bool Success status
     */
    public function executeRemove(string|array $removeConfig, array $variables, string $basePath): bool
    {
        $path = is_string($removeConfig) ? $removeConfig : ($removeConfig['path'] ?? '');
        
        if (empty($path)) {
            $this->dbg("Remove step missing 'path'");
            return false;
        }

        $targetPath = PathResolver::resolvePath($path, $variables, $basePath);

        // Handle wildcards
        if (strpos($targetPath, '*') !== false) {
            return $this->removeWithWildcard($targetPath);
        }

        $this->dbg("Removing: $targetPath");

        if (!file_exists($targetPath)) {
            $this->dbg("Target does not exist: $targetPath");
            return true; // Not an error if it doesn't exist
        }

        return $this->removePath($targetPath);
    }

    /**
     * Remove files/directories matching wildcard pattern
     */
    private function removeWithWildcard(string $pattern): bool
    {
        $baseDir = dirname($pattern);
        $filePattern = basename($pattern);

        if (!is_dir($baseDir)) {
            $this->dbg("Base directory does not exist: $baseDir");
            return false;
        }

        $files = glob($baseDir . DIRECTORY_SEPARATOR . $filePattern);
        if (empty($files)) {
            $this->dbg("No files match pattern: $pattern");
            return true; // Not an error if nothing matches
        }

        $success = true;
        foreach ($files as $file) {
            $this->dbg("Removing: $file");
            if (!$this->removePath($file)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Remove file or directory recursively
     */
    private function removePath(string $path): bool
    {
        if (is_dir($path)) {
            return $this->removeDirectory($path);
        } else {
            return @unlink($path);
        }
    }

    /**
     * Recursively remove directory
     */
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }

    /**
     * Execute copy step - copy files from previous installation
     * @param array $copyConfig Configuration with "from" (file path in previous installation) and optional "to" (destination, defaults to tempDir)
     * @param array $variables Variables for path resolution
     * @param string $basePath Base path for resolving relative paths
     * @return bool Success status
     */
    public function executeCopy(array $copyConfig, array $variables, string $basePath): bool
    {
        $fromFile = $copyConfig['from'] ?? '';
        $toPath = $copyConfig['to'] ?? null;

        if (empty($fromFile)) {
            $this->dbg("Copy step missing 'from'");
            return false;
        }

        // Find previous installation
        $appPath = $variables['appPath'] ?? $basePath;
        $baseName = $variables['baseName'] ?? '';
        $excludeDir = $variables['finalDir'] ?? '';

        $previousInstallation = $this->fileManager->findPreviousInstallation($appPath, $baseName, $excludeDir);
        if ($previousInstallation === null) {
            $this->dbg("No previous installation found, skipping copy");
            return true; // Not an error if no previous installation exists
        }

        // Resolve source path in previous installation
        $sourcePath = $previousInstallation . DIRECTORY_SEPARATOR . $fromFile;
        if (!file_exists($sourcePath)) {
            $this->dbg("Source file does not exist in previous installation: $sourcePath");
            return true; // Not an error if file doesn't exist in previous installation
        }

        // Resolve destination path (default to tempDir)
        if ($toPath === null) {
            $toPath = ($variables['tempDir'] ?? $basePath) . DIRECTORY_SEPARATOR . basename($fromFile);
        } else {
            $toPath = PathResolver::resolvePath($toPath, $variables, $basePath);
        }

        $this->dbg("Copying from previous installation: $sourcePath -> $toPath");
        return $this->fileManager->copyFileOrDir($sourcePath, $toPath);
    }
}

