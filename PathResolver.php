<?php

class PathResolver
{
    /**
     * Replace variables in string with actual values
     */
    public static function replaceVariables(string $str, array $vars): string
    {
        $result = $str;
        foreach ($vars as $key => $value) {
            $result = str_replace('$' . $key, $value, $result);
            $result = str_replace('{' . $key . '}', $value, $result);
        }
        return $result;
    }

    /**
     * Resolve path: replace variables, normalize separators, resolve relative paths
     */
    public static function resolvePath(string $path, array $variables, string $basePath): string
    {
        // Replace variables first
        $resolved = self::replaceVariables($path, $variables);
        
        // Normalize path separators
        $resolved = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved);
        
        // If path is not absolute (doesn't start with drive letter or UNC), make it relative to basePath
        if (!preg_match('/^[A-Za-z]:' . preg_quote(DIRECTORY_SEPARATOR, '/') . '|^' . preg_quote(DIRECTORY_SEPARATOR, '/') . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $resolved)) {
            $resolved = $basePath . DIRECTORY_SEPARATOR . $resolved;
        }

        return realpath($resolved) ?: $resolved;
    }
}

