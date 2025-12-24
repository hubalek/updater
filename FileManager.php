<?php

class FileManager
{
    use DebugTrait;

    private ZipExtractor $zipExtractor;
    private SevenZipExtractor $sevenZipExtractor;

    public function __construct()
    {
        $this->zipExtractor = new ZipExtractor();
        $this->sevenZipExtractor = new SevenZipExtractor();
    }

    public function setDebugCallback(callable $callback): void
    {
        $this->debugCallback = $callback;
        $this->zipExtractor->setDebugCallback($callback);
        $this->sevenZipExtractor->setDebugCallback($callback);
    }

    public function getLocalVersions(string $appPath): array
    {
        $items = array_diff(scandir($appPath), [".", ".."]);
        $out = [];
        foreach ($items as $it) {
            $full = $appPath . DIRECTORY_SEPARATOR . $it;
            if (is_dir($full)) $out[] = $it;
            if (preg_match('/\.(zip|7z)$/i', $it)) {
                $out[] = preg_replace('/\.(zip|7z)$/i', '', $it);
            }
        }
        return array_unique($out);
    }

    public function isVersionDownloaded(string $appPath, string $version): bool
    {
        foreach ($this->getLocalVersions($appPath) as $v) {
            if (strcasecmp($v, $version) === 0) return true;
        }
        return false;
    }

    public function extractZip(string $zip, string $to): bool
    {
        return $this->zipExtractor->extract($zip, $to);
    }

    public function extract7z(string $archive, string $to): bool
    {
        return $this->sevenZipExtractor->extract($archive, $to);
    }

    public function moveFileOrDir(string $from, string $to): bool
    {
        if (!file_exists($from)) {
            return false;
        }

        // If destination exists and is a directory, move into it
        if (is_dir($to)) {
            $to = $to . DIRECTORY_SEPARATOR . basename($from);
        } else {
            // Create destination directory if it doesn't exist
            $toDir = dirname($to);
            if (!is_dir($toDir)) {
                mkdir($toDir, 0777, true);
            }
        }

        // Use retry rename for moving
        return $this->retryRename($from, $to);
    }

    /**
     * Find previous installation directory
     * First tries to find junction (symlink), if not found, finds last directory alphabetically
     * @param string $appPath Application path
     * @param string $baseName Base name (junction name)
     * @param string $excludeDir Directory to exclude (current finalDir)
     * @return string|null Path to previous installation or null if not found
     */
    public function findPreviousInstallation(string $appPath, string $baseName, string $excludeDir = ''): ?string
    {
        // First, try to find junction (symlink)
        $junctionPath = $appPath . DIRECTORY_SEPARATOR . $baseName;
        if (is_dir($junctionPath)) {
            // On Windows, readlink returns the target path for junctions
            $target = @readlink($junctionPath);
            if ($target !== false) {
                // Convert relative path to absolute if needed
                if (!preg_match('/^[A-Za-z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . '|^' . preg_quote(DIRECTORY_SEPARATOR, '/') . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $target)) {
                    // Relative path, resolve relative to junction's directory
                    $target = realpath(dirname($junctionPath) . DIRECTORY_SEPARATOR . $target) ?: $target;
                }
                if (is_dir($target)) {
                    $this->dbg("Found previous installation via junction: $target");
                    return $target;
                }
            }
        }

        // If no junction, find last directory alphabetically (excluding current finalDir and temp dirs)
        $items = array_diff(scandir($appPath), [".", ".."]);
        $directories = [];
        
        foreach ($items as $item) {
            $fullPath = $appPath . DIRECTORY_SEPARATOR . $item;
            
            // Skip if not a directory
            if (!is_dir($fullPath)) {
                continue;
            }
            
            // Skip current finalDir
            if ($excludeDir !== '' && realpath($fullPath) === realpath($excludeDir)) {
                continue;
            }
            
            // Skip temp directories (starting with .temp_)
            if (strpos($item, '.temp_') === 0) {
                continue;
            }
            
            // Skip junction itself (baseName)
            if (strcasecmp($item, $baseName) === 0) {
                continue;
            }
            
            $directories[] = $item;
        }
        
        if (empty($directories)) {
            $this->dbg("No previous installation found");
            return null;
        }
        
        // Sort alphabetically and get last one
        sort($directories);
        $lastDir = end($directories);
        $previousPath = $appPath . DIRECTORY_SEPARATOR . $lastDir;
        
        $this->dbg("Found previous installation (alphabetically last): $previousPath");
        return $previousPath;
    }

    /**
     * Copy file or directory
     * @param string $from Source path
     * @param string $to Destination path
     * @return bool Success status
     */
    public function copyFileOrDir(string $from, string $to): bool
    {
        if (!file_exists($from)) {
            $this->dbg("Source does not exist: $from");
            return false;
        }

        // Create destination directory if needed
        $toDir = is_dir($to) ? $to : dirname($to);
        if (!is_dir($toDir)) {
            mkdir($toDir, 0777, true);
        }

        if (is_dir($from)) {
            return $this->copyDirectory($from, $to);
        } else {
            // If destination is a directory, copy into it
            if (is_dir($to)) {
                $to = $to . DIRECTORY_SEPARATOR . basename($from);
            }
            $this->dbg("Copying file: $from -> $to");
            
            // Get original modification time
            $originalMtime = @filemtime($from);
            
            if (!@copy($from, $to)) {
                return false;
            }
            
            // Preserve original modification time
            if ($originalMtime !== false) {
                @touch($to, $originalMtime);
            }
            
            return true;
        }
    }

    /**
     * Recursively copy directory
     */
    private function copyDirectory(string $from, string $to): bool
    {
        if (!is_dir($from)) {
            return false;
        }

        // If destination exists and is a directory, copy into it
        if (is_dir($to)) {
            $to = $to . DIRECTORY_SEPARATOR . basename($from);
        }

        if (!is_dir($to)) {
            mkdir($to, 0777, true);
        }

        $files = array_diff(scandir($from), ['.', '..']);
        foreach ($files as $file) {
            $fromPath = $from . DIRECTORY_SEPARATOR . $file;
            $toPath = $to . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($fromPath)) {
                if (!$this->copyDirectory($fromPath, $toPath)) {
                    return false;
                }
            } else {
                // Get original modification time
                $originalMtime = @filemtime($fromPath);
                
                if (!@copy($fromPath, $toPath)) {
                    return false;
                }
                
                // Preserve original modification time
                if ($originalMtime !== false) {
                    @touch($toPath, $originalMtime);
                }
            }
        }

        return true;
    }

    /**
     * Retry rename operation with exponential backoff
     */
    private function retryRename(string $src, string $dst): bool
    {
        $delay = 100000;

        for ($i = 1; $i <= 10; $i++) {
            if (@rename($src, $dst)) {
                return true;
            }

            $err = error_get_last();
            $msg = $err["message"] ?? "";

            if (stripos($msg, "code: 32") === false && stripos($msg, "code: 5") === false) {
                return false;
            }

            if ($i === 10) {
                usleep(60000000);
                return @rename($src, $dst);
            }

            usleep($delay);
            $delay *= 2;
        }
        return false;
    }
}

