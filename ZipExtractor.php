<?php

class ZipExtractor
{
    use DebugTrait;

    /**
     * Extract ZIP archive
     */
    public function extract(string $zip, string $to): bool
    {
        $zipObj = new ZipArchive();
        if ($zipObj->open($zip) !== true) {
            return false;
        }

        if (!is_dir($to)) {
            mkdir($to, 0777, true);
        }

        $ok = $zipObj->extractTo($to);
        $zipObj->close();

        if (!$ok) {
            return false;
        }

        // Flatten directory structure if needed
        $this->flattenDirectory($to);

        return true;
    }

    /**
     * Flatten directory structure if archive contains single top-level directory
     */
    private function flattenDirectory(string $to): void
    {
        $items = array_diff(scandir($to), [".", ".."]);
        $dirs = [];

        foreach ($items as $i) {
            if (is_dir($to . DIRECTORY_SEPARATOR . $i)) {
                $dirs[] = $i;
            } else {
                // files are at top-level → don't flatten
                return;
            }
        }

        // flatten only if there is exactly one directory at top-level
        if (count($dirs) === 1) {
            $inner = $to . DIRECTORY_SEPARATOR . $dirs[0];
            $innerItems = array_diff(scandir($inner), [".", ".."]);

            // move inner directory contents one level up
            foreach ($innerItems as $it) {
                $src = $inner . DIRECTORY_SEPARATOR . $it;
                $dst = $to . DIRECTORY_SEPARATOR . $it;

                if (!$this->retryRename($src, $dst)) {
                    // if something fails to move even after retry, leave it as is
                    return;
                }
            }

            // attempt to remove empty intermediate directory
            if (is_dir($inner)) {
                @rmdir($inner);
                if (is_dir($inner)) {
                    exec('cmd /c rmdir "' . $inner . '"');
                }
            }
        }
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

