<?php

class MoveStepHandler
{
    use DebugTrait;

    private FileManager $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    /**
     * Execute move step
     */
    public function execute(array $moveConfig, array $variables, string $basePath): bool
    {
        $from = $moveConfig['from'] ?? '';
        $to = $moveConfig['to'] ?? '';

        if (empty($from) || empty($to)) {
            $this->dbg("Move step missing 'from' or 'to'");
            return false;
        }

        $fromPath = PathResolver::resolvePath($from, $variables, $basePath);
        $toPath = PathResolver::resolvePath($to, $variables, $basePath);

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
        // If toPath ends with / or \, treat it as directory
        if (str_ends_with($toPath, DIRECTORY_SEPARATOR) || str_ends_with($toPath, '/') || str_ends_with($toPath, '\\')) {
            $toDir = rtrim($toPath, DIRECTORY_SEPARATOR . '/\\');
            if (!is_dir($toDir)) {
                mkdir($toDir, 0777, true);
            }
        } else {
            $toDir = is_dir($toPath) ? $toPath : dirname($toPath);
            if (!is_dir($toDir)) {
                mkdir($toDir, 0777, true);
            }
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

        $this->dbg("Wildcard move: baseDir=$baseDir, pattern=$pattern");

        if (!is_dir($baseDir)) {
            $this->dbg("Base directory does not exist: $baseDir");
            // Try to list what's in parent directory for debugging
            $parentDir = dirname($baseDir);
            if (is_dir($parentDir)) {
                $items = array_diff(scandir($parentDir), [".", ".."]);
                $this->dbg("Parent directory contents: " . implode(", ", $items));
            }
            return false;
        }

        if (!is_dir($toDir)) {
            mkdir($toDir, 0777, true);
        }

        $files = glob($baseDir . DIRECTORY_SEPARATOR . $pattern);
        if (empty($files)) {
            $this->dbg("No files match pattern: $fromPattern");
            // List what's actually in baseDir for debugging
            $items = array_diff(scandir($baseDir), [".", ".."]);
            $this->dbg("Base directory contents: " . implode(", ", $items));
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

