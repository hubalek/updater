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

