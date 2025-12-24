<?php

class ExtractStepHandler
{
    use DebugTrait;

    private FileManager $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    /**
     * Execute extract step (common logic for both ZIP and 7z)
     */
    private function executeExtract(string|array $sourceConfig, array $variables, string $basePath, string $type, callable $extractFn): bool
    {
        // Support both string (source) and array (source + to)
        $source = is_string($sourceConfig) ? $sourceConfig : ($sourceConfig['source'] ?? '');
        $extractTo = null;
        
        if (is_array($sourceConfig)) {
            $extractTo = $sourceConfig['to'] ?? null;
            if ($extractTo !== null) {
                // Use PathResolver to resolve the "to" path
                $extractTo = PathResolver::resolvePath($extractTo, $variables, $basePath);
            }
        }

        // Resolve source path
        $sourcePath = PathResolver::resolvePath($source, $variables, $basePath);

        // If source file doesn't exist and we have a tempDir, try looking there
        if (!file_exists($sourcePath) && isset($variables['tempDir']) && is_dir($variables['tempDir'])) {
            $tempSourcePath = PathResolver::resolvePath($source, $variables, $variables['tempDir']);
            if (file_exists($tempSourcePath)) {
                $sourcePath = $tempSourcePath;
                $this->dbg("Found source in tempDir: $sourcePath");
            }
        }

        $this->dbg("Extracting $type: $sourcePath");

        if (!file_exists($sourcePath)) {
            $this->dbg("Source file does not exist: $sourcePath");
            return false;
        }

        // Extract to specified directory, or finalDir if available, otherwise to same directory as source file
        if ($extractTo === null) {
            $extractTo = $variables['finalDir'] ?? dirname($sourcePath);
        }
        
        $this->dbg("Extracting to: $extractTo");
        return $extractFn($sourcePath, $extractTo);
    }

    /**
     * Execute extract7z step
     * Supports: "source" or {"source": "...", "to": "..."}
     */
    public function executeExtract7z(string|array $sourceConfig, array $variables, string $basePath): bool
    {
        return $this->executeExtract($sourceConfig, $variables, $basePath, '7z', [$this->fileManager, 'extract7z']);
    }

    /**
     * Execute extractZip step
     * Supports: "source" or {"source": "...", "to": "..."}
     */
    public function executeExtractZip(string|array $sourceConfig, array $variables, string $basePath): bool
    {
        return $this->executeExtract($sourceConfig, $variables, $basePath, 'ZIP', [$this->fileManager, 'extractZip']);
    }
}

