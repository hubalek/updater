<?php

class StepExecutor
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
     * Replace variables in string with actual values
     */
    private function replaceVariables(string $str, array $vars): string
    {
        $result = $str;
        foreach ($vars as $key => $value) {
            $result = str_replace('$' . $key, $value, $result);
            $result = str_replace('{' . $key . '}', $value, $result);
        }
        return $result;
    }

    /**
     * Execute a single step
     */
    public function executeStep(array $step, array $variables, string $basePath): bool
    {
        if (isset($step['extract7z'])) {
            return $this->executeExtract7z($step['extract7z'], $variables, $basePath);
        }

        if (isset($step['extractZip'])) {
            return $this->executeExtractZip($step['extractZip'], $variables, $basePath);
        }

        if (isset($step['move'])) {
            return $this->executeMove($step['move'], $variables, $basePath);
        }

        $this->dbg("Unknown step type: " . json_encode($step));
        return false;
    }

    /**
     * Execute extract7z step
     */
    private function executeExtract7z(string $source, array $variables, string $basePath): bool
    {
        $sourcePath = $this->replaceVariables($source, $variables);
        
        // If path is relative, make it relative to basePath
        if (!preg_match('/^[A-Za-z]:\\\\|^\\\\/', $sourcePath)) {
            $sourcePath = $basePath . DIRECTORY_SEPARATOR . $sourcePath;
        }

        $sourcePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourcePath);
        $sourcePath = realpath($sourcePath) ?: $sourcePath;

        $this->dbg("Extracting 7z: $sourcePath");

        if (!file_exists($sourcePath)) {
            $this->dbg("Source file does not exist: $sourcePath");
            return false;
        }

        // Extract to same directory as source file
        $extractTo = dirname($sourcePath);
        
        return $this->fileManager->extract7z($sourcePath, $extractTo);
    }

    /**
     * Execute extractZip step
     */
    private function executeExtractZip(string $source, array $variables, string $basePath): bool
    {
        $sourcePath = $this->replaceVariables($source, $variables);
        
        // If path is relative, make it relative to basePath
        if (!preg_match('/^[A-Za-z]:\\\\|^\\\\/', $sourcePath)) {
            $sourcePath = $basePath . DIRECTORY_SEPARATOR . $sourcePath;
        }

        $sourcePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourcePath);
        $sourcePath = realpath($sourcePath) ?: $sourcePath;

        $this->dbg("Extracting ZIP: $sourcePath");

        if (!file_exists($sourcePath)) {
            $this->dbg("Source file does not exist: $sourcePath");
            return false;
        }

        // Extract to same directory as source file
        $extractTo = dirname($sourcePath);
        
        return $this->fileManager->extractZip($sourcePath, $extractTo);
    }

    /**
     * Execute move step
     */
    private function executeMove(array $moveConfig, array $variables, string $basePath): bool
    {
        $from = $moveConfig['from'] ?? '';
        $to = $moveConfig['to'] ?? '';

        if (empty($from) || empty($to)) {
            $this->dbg("Move step missing 'from' or 'to'");
            return false;
        }

        $fromPath = $this->replaceVariables($from, $variables);
        $toPath = $this->replaceVariables($to, $variables);

        // If paths are relative, make them relative to basePath
        if (!preg_match('/^[A-Za-z]:\\\\|^\\\\/', $fromPath)) {
            $fromPath = $basePath . DIRECTORY_SEPARATOR . $fromPath;
        }
        if (!preg_match('/^[A-Za-z]:\\\\|^\\\\/', $toPath)) {
            $toPath = $basePath . DIRECTORY_SEPARATOR . $toPath;
        }

        $fromPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fromPath);
        $toPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $toPath);

        // Handle wildcards in from path
        if (strpos($fromPath, '*') !== false) {
            return $this->moveWithWildcard($fromPath, $toPath);
        }

        $this->dbg("Moving: $fromPath -> $toPath");

        if (!file_exists($fromPath)) {
            $this->dbg("Source does not exist: $fromPath");
            return false;
        }

        // Create destination directory if it doesn't exist
        $toDir = is_dir($toPath) ? $toPath : dirname($toPath);
        if (!is_dir($toDir)) {
            mkdir($toDir, 0777, true);
        }

        // If destination is a directory, move into it
        if (is_dir($toPath)) {
            $toPath = $toPath . DIRECTORY_SEPARATOR . basename($fromPath);
        }

        return $this->fileManager->moveFileOrDir($fromPath, $toPath);
    }

    /**
     * Move files matching wildcard pattern
     */
    private function moveWithWildcard(string $fromPattern, string $toDir): bool
    {
        $baseDir = dirname($fromPattern);
        $pattern = basename($fromPattern);

        if (!is_dir($baseDir)) {
            $this->dbg("Base directory does not exist: $baseDir");
            return false;
        }

        if (!is_dir($toDir)) {
            mkdir($toDir, 0777, true);
        }

        $files = glob($baseDir . DIRECTORY_SEPARATOR . $pattern);
        if (empty($files)) {
            $this->dbg("No files match pattern: $fromPattern");
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            $dest = $toDir . DIRECTORY_SEPARATOR . basename($file);
            $this->dbg("Moving: $file -> $dest");
            if (!$this->fileManager->moveFileOrDir($file, $dest)) {
                $success = false;
            }
        }

        return $success;
    }
}

