<?php

class Updater
{
    private string $root;
    private bool $debug = false;

    private array $defaultFilter = [
        "mustContain"    => ["portable", "x64"],
        "mustNotContain" => ["arm"],
        "allowedExt"     => ["zip", "7z"]
    ];

    public function __construct(string $root, bool $debug = false)
    {
        $this->root  = rtrim($root, "/\\") . DIRECTORY_SEPARATOR;
        $this->debug = $debug;
    }

    private function dbg(string $msg): void
    {
        if ($this->debug) {
            echo "[DEBUG] $msg\n";
        }
    }

    private function httpGet(string $url): string|false
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $res = curl_exec($ch);
        curl_close($ch);

        return $res !== false ? $res : false;
    }

    private function loadConfig(string $path): array|null
    {
        if (!file_exists($path)) return null;
        $raw = file_get_contents($path);
        $tmp = json_decode($raw, true);
        return is_array($tmp) ? $tmp : null;
    }

    private function getAppFolders(): array
    {
        $dirs = array_diff(scandir($this->root), [".", ".."]);
        $result = [];
        foreach ($dirs as $d) {
            if (is_dir($this->root . $d)) $result[] = $d;
        }
        return $result;
    }

    private function getJsonConfigs(string $appPath): array
    {
        $files = array_diff(scandir($appPath), [".", ".."]);
        $out = [];
        foreach ($files as $f) {
            if (preg_match('/\.json$/i', $f)) $out[] = $f;
        }
        return $out;
    }

    private function mergeFilters(array|null $jsonFilter): array
    {
        $filter = $this->defaultFilter;

        if ($jsonFilter !== null && is_array($jsonFilter)) {
            foreach (["mustContain", "mustNotContain", "allowedExt"] as $key) {
                if (array_key_exists($key, $jsonFilter)) {
                    $filter[$key] = $jsonFilter[$key];
                }
            }
        }

        return $filter;
    }

    private function findAsset(array $assets, array $filter): string|null
    {
        $this->dbg("Kontroluji " . count($assets) . " assetů…");

        foreach ($assets as $asset) {
            $url = $asset["browser_download_url"] ?? "";
            $this->dbg("  Asset: $url");
            $ok = true;

            foreach ($filter["mustContain"] as $word) {
                if (stripos($url, $word) === false) {
                    $this->dbg("   mustContain selhalo: $word");
                    $ok = false;
                    break;
                }
            }

            if ($ok) {
                foreach ($filter["mustNotContain"] as $word) {
                    if (stripos($url, $word) !== false) {
                        $this->dbg("   mustNotContain selhalo: $word");
                        $ok = false;
                        break;
                    }
                }
            }

            if ($ok) {
                $match = false;
                foreach ($filter["allowedExt"] as $ext) {
                    if (preg_match('/\.' . preg_quote($ext) . '$/i', $url)) {
                        $match = true;
                        break;
                    }
                }
                if (!$match) {
                    $this->dbg("   allowedExt selhalo");
                    $ok = false;
                }
            }

            if ($ok) {
                $this->dbg("  ✔ Asset vyhovuje");
                return $url;
            }

            $this->dbg("  ✘ Asset nevyhovuje");
        }

        $this->dbg("Nenalezen vhodný asset");
        return null;
    }

    private function extractVersionName(string $url): string
    {
        $name = basename($url);
        return preg_replace('/\.(zip|7z)$/i', '', $name);
    }

    private function getLocalVersions(string $appPath): array
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

    private function restoreMissingJunction(string $appPath, string $base): void
    {
        $linkPath = $appPath . DIRECTORY_SEPARATOR . $base;

        if (is_dir($linkPath)) return;

        $versions = $this->getLocalVersions($appPath);
        if (empty($versions)) return;

        sort($versions);
        $latest = end($versions);
        $target = $appPath . DIRECTORY_SEPARATOR . $latest;

        if (!is_dir($target)) return;

        $this->dbg("Junction chybí → vytvářím $base → $latest");

        exec('cmd /c mklink /J "' . $linkPath . '" "' . $target . '"');
    }

    private function isVersionDownloaded(string $appPath, string $version): bool
    {
        foreach ($this->getLocalVersions($appPath) as $v) {
            if (strcasecmp($v, $version) === 0) return true;
        }
        return false;
    }

    private function downloadFile(string $url, string $save): bool
    {
        $this->dbg("Stahuji: $url");

        $fp = fopen($save, "w+b");
        if (!$fp) return false;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $res = curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        return $res !== false;
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

    private function extractZip(string $zip, string $to): bool
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

    private function removeJunction(string $path, string $name): void
    {
        if (strtolower(basename($path)) !== strtolower($name)) return;

        if (is_dir($path)) {
            @rmdir($path);
            if (is_dir($path)) {
                exec('cmd /c rmdir "' . $path . '"');
            }
        }
    }

    private function createJunction(string $target, string $link, string $name): void
    {
        if (strtolower(basename($link)) !== strtolower($name)) return;

        $this->removeJunction($link, $name);
        exec('cmd /c mklink /J "' . $link . '" "' . $target . '"');
    }

    // ----------------------------------------------------------
    // UPDATED processJson() — bez předčasných returnů
    // ----------------------------------------------------------
    private function processJson(string $appFolder, string $appPath, string $jsonFile): void
    {
        $this->dbg("=== Zpracovávám $jsonFile v $appFolder ===");

        $baseName   = pathinfo($jsonFile, PATHINFO_FILENAME);
        $configPath = $appPath . DIRECTORY_SEPARATOR . $jsonFile;

        // obnov junction pokud chybí
        $this->restoreMissingJunction($appPath, $baseName);

        $run = true;

        $cfg = $this->loadConfig($configPath);
        if ($cfg === null) {
            $this->dbg("JSON nevalidní");
            $run = false;
        }

        $apiUrl = $cfg["url"] ?? "";
        $json   = null;
        $data   = null;

        if ($run) {
            $json = $this->httpGet($apiUrl);
            if ($json === false) {
                $this->dbg("HTTP selhalo");
                $run = false;
            }
        }

        if ($run) {
            $data = json_decode($json, true);
            if (!is_array($data) || empty($data["assets"])) {
                $this->dbg("API neobsahuje assets");
                $run = false;
            }
        }

        $assetUrl = null;
        $version  = null;
        $zipPath  = null;
        $extPath  = null;
        $lnkPath  = null;

        if ($run) {
            $filter   = $this->mergeFilters($cfg["filter"] ?? null);
            $assetUrl = $this->findAsset($data["assets"], $filter);

            if ($assetUrl === null) {
                $run = false;
            }
        }

        if ($run) {
            $version = $this->extractVersionName($assetUrl);
            if ($this->isVersionDownloaded($appPath, $version)) {
                $run = false;
            }
        }

        if ($run) {
            $zipPath = $appPath . DIRECTORY_SEPARATOR . $version . ".zip";
            $extPath = $appPath . DIRECTORY_SEPARATOR . $version;
            $lnkPath = $appPath . DIRECTORY_SEPARATOR . $baseName;

            if (!$this->downloadFile($assetUrl, $zipPath)) {
                $run = false;
            }
        }

        if ($run) {
            if (!$this->extractZip($zipPath, $extPath)) {
                $run = false;
            }
        }

        if ($run) {
            $this->createJunction($extPath, $lnkPath, $baseName);
            echo "UPDATED - {$appFolder}/{$baseName} - {$version}\n";
        }
    }

    public function run(): void
    {
        foreach ($this->getAppFolders() as $folder) {
            $appPath = $this->root . $folder;
            foreach ($this->getJsonConfigs($appPath) as $jsonFile) {
                $this->processJson($folder, $appPath, $jsonFile);
            }
        }
    }
}
