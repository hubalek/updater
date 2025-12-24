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
        // Start with default filter values
        $filter = $this->defaultFilter;

        // If filter is provided, merge with defaults
        // Empty arrays will override default values (empty array = no restrictions for that rule)
        if ($jsonFilter !== null && is_array($jsonFilter)) {
            foreach (["mustContain", "mustNotContain", "allowedExt"] as $key) {
                // If key exists in JSON filter (even if empty array), it overrides default
                if (array_key_exists($key, $jsonFilter)) {
                    $filter[$key] = $jsonFilter[$key];
                }
                // If key doesn't exist, default value is kept
            }
        }

        return $filter;
    }
}

