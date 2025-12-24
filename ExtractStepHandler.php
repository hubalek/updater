<?php

class ExtractStepHandler
{
    private FileManager $fileManager;
    private $debugCallback = null;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
    }

    private function dbg(string $msg): void
    {
        if ($this->debugCallback !== null) {
            ($this->debugCallback)($msg);
        }
    }

    /**
     * Execute extract7z step
     */
    public function executeExtract7z(string $source, array $variables, string $basePath): bool
    {
        $sourcePath = PathResolver::resolvePath($source, $variables, $basePath);

        $this->dbg("Extracting 7z: $sourcePath");

        if (!file_exists($sourcePath)) {
            $this->dbg("Source file does not exist: $sourcePath");
            return false;
        }

        // Extract to finalDir if available, otherwise to same directory as source file
        $extractTo = $variables['finalDir'] ?? dirname($sourcePath);
        
        return $this->fileManager->extract7z($sourcePath, $extractTo);
    }

    /**
     * Execute extractZip step
     */
    public function executeExtractZip(string $source, array $variables, string $basePath): bool
    {
        $sourcePath = PathResolver::resolvePath($source, $variables, $basePath);

        $this->dbg("Extracting ZIP: $sourcePath");

        if (!file_exists($sourcePath)) {
            $this->dbg("Source file does not exist: $sourcePath");
            return false;
        }

        // Extract to finalDir if available, otherwise to same directory as source file
        $extractTo = $variables['finalDir'] ?? dirname($sourcePath);
        
        return $this->fileManager->extractZip($sourcePath, $extractTo);
    }
}

