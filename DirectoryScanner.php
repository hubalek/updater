<?php

class DirectoryScanner
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, "/\\") . DIRECTORY_SEPARATOR;
    }

    public function getAppFolders(): array
    {
        $dirs = array_diff(scandir($this->root), [".", ".."]);
        $result = [];
        foreach ($dirs as $d) {
            if (is_dir($this->root . $d)) $result[] = $d;
        }
        return $result;
    }

    public function getJsonConfigs(string $appPath): array
    {
        $files = array_diff(scandir($appPath), [".", ".."]);
        $out = [];
        foreach ($files as $f) {
            if (preg_match('/\.json$/i', $f)) $out[] = $f;
        }
        return $out;
    }

    public function getAppPath(string $folder): string
    {
        return $this->root . $folder;
    }
}

