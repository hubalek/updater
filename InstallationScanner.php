<?php

class InstallationScanner
{
    use DebugTrait;

    /**
     * Get all local versions from application path
     * @param string $appPath Application path
     * @return array Array of version names (directories and archive names without extension)
     */
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

    /**
     * Check if version is already downloaded
     * @param string $appPath Application path
     * @param string $version Version to check
     * @return bool True if version exists
     */
    public function isVersionDownloaded(string $appPath, string $version): bool
    {
        foreach ($this->getLocalVersions($appPath) as $v) {
            if (strcasecmp($v, $version) === 0) return true;
        }
        return false;
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
}

