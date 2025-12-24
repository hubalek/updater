<?php

class FileManager
{
    private $debugCallback = null;

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

    private function retryRename(string $src, string $dst): bool
    {
        $delay = 100000;

        for ($i = 1; $i <= 10; $i++) {

            if (@rename($src, $dst)) return true;

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

    public function extractZip(string $zip, string $to): bool
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

        // obsah cílové složky po rozbalení
        $items = array_diff(scandir($to), [".", ".."]);
        $dirs  = [];

        foreach ($items as $i) {
            if (is_dir($to . DIRECTORY_SEPARATOR . $i)) {
                $dirs[] = $i;
            } else {
                // jsou tu soubory na top-level → neflattenujeme
                return true;
            }
        }

        // flatten pouze pokud je na top-level přesně jeden adresář
        if (count($dirs) === 1) {
            $inner      = $to . DIRECTORY_SEPARATOR . $dirs[0];
            $innerItems = array_diff(scandir($inner), [".", ".."]);

            // zvedneme obsah vnitřního adresáře o úroveň výš
            foreach ($innerItems as $it) {
                $src = $inner . DIRECTORY_SEPARATOR . $it;
                $dst = $to   . DIRECTORY_SEPARATOR . $it;

                if (!$this->retryRename($src, $dst)) {
                    // když se něco nepodaří přesunout ani po retry, necháme to být
                    return true;
                }
            }

            // pokus o smazání prázdné mezisložky (dvoufázově, jako u junction)
            if (is_dir($inner)) {
                @rmdir($inner);

                if (is_dir($inner)) {
                    exec('cmd /c rmdir "' . $inner . '"');
                }
            }
        }

        return true;
    }
}

