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
     * Execute extract step (common logic for both ZIP and 7z)
     */
    private function executeExtract(string $source, array $variables, string $basePath, string $type, callable $extractFn): bool
    {
        $sourcePath = PathResolver::resolvePath($source, $variables, $basePath);

        $this->dbg("Extracting $type: $sourcePath");

        if (!file_exists($sourcePath)) {
            $this->dbg("Source file does not exist: $sourcePath");
            return false;
        }

        // Extract to finalDir if available, otherwise to same directory as source file
        $extractTo = $variables['finalDir'] ?? dirname($sourcePath);
        
        return $extractFn($sourcePath, $extractTo);
    }

    /**
     * Execute extract7z step
     */
    public function executeExtract7z(string $source, array $variables, string $basePath): bool
    {
        return $this->executeExtract($source, $variables, $basePath, '7z', [$this->fileManager, 'extract7z']);
    }

    /**
     * Execute extractZip step
     */
    public function executeExtractZip(string $source, array $variables, string $basePath): bool
    {
        return $this->executeExtract($source, $variables, $basePath, 'ZIP', [$this->fileManager, 'extractZip']);
    }
}

