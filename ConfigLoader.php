<?php

class ConfigLoader
{
    private array $defaultFilter = [
        "mustContain"    => ["portable", "x64"],
        "mustNotContain" => ["arm"],
        "allowedExt"     => ["zip", "7z"]
    ];

    public function loadConfig(string $path): array|null
    {
        if (!file_exists($path)) return null;
        $raw = file_get_contents($path);
        $tmp = json_decode($raw, true);
        return is_array($tmp) ? $tmp : null;
    }

    public function mergeFilters(array|null $jsonFilter): array
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
}

