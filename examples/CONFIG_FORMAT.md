# Configuration File Format

## Overview

Configuration files are JSON files (`.json`) placed in application folders. All configurations must use the steps-based format.

## Basic Structure

```json
{
  "finalDirPattern": "AppName-{version}-x64",
  "steps": [
    {
      "download": {
        "url": "https://api.github.com/repos/OWNER/REPO/releases/latest",
        "filter": {
          "mustContain": ["portable", "x64"],
          "mustNotContain": ["arm"],
          "allowedExt": ["zip", "7z"]
        }
      }
    },
    { "extractZip": "{downloadedFile}" },
    { "extract7z": "INSTALL.CAB" },
    {
      "move": {
        "from": "INSTALL/*",
        "to": "{finalDir}/"
      }
    }
  ]
}
```

## Configuration Options

### Required Fields

- **`steps`** (array) - Processing steps (required, must be non-empty)
  - First step must be a `download` step
  - Subsequent steps are executed in order

### Optional Fields

- **`finalDirPattern`** (string) - Pattern for final directory name
  - Use `{version}` placeholder for version number
  - Example: `"TotalCommander-{version}-x64"`
  - Default: Uses `{version}` directly if not specified

### Download Step (Required)

The first step must be a `download` step. It supports three types of downloads:

**1. GitHub downloads:**
- **`url`** (string, required) - GitHub API URL for releases
  - Format: `https://api.github.com/repos/OWNER/REPO/releases/latest`
  - Or: `https://api.github.com/repos/OWNER/REPO/releases`
- **`filter`** (object, optional) - Asset filtering rules
  - Merges with default filter: `["portable", "x64"]` must contain, `["arm"]` must not contain, `["zip", "7z"]` allowed extensions
  - **`mustContain`** (array of strings, optional) - All strings must be present in asset URL
  - **`mustNotContain`** (array of strings, optional) - None of these strings can be in asset URL
  - **`allowedExt`** (array of strings, optional) - Allowed file extensions (e.g., `["zip", "7z"]`)
- **`filenamePattern`** (string, optional) - Pattern for renaming downloaded file
  - Use `{version}` placeholder for version number
  - Example: `"app-{version}-x64"` to rename downloaded file to `app-2.20-x64.zip`
  - File extension is automatically preserved if not included in pattern
  - If not specified, original filename from URL is used

**2. HTML page downloads:**
- **`pageUrl`** (string, required) - HTML page URL
  - Example: `"https://www.example.com/download.htm"`
- **`findLink`** (object, optional) - Link filtering rules
  - Same structure as `filter` above
  - Merges with default filter: `["portable", "x64"]` must contain, `["arm"]` must not contain, `["zip", "7z"]` allowed extensions
  - **`mustContain`** (array of strings, optional) - All strings must be present in link URL
  - **`mustNotContain`** (array of strings, optional) - None of these strings can be in link URL
  - **`allowedExt`** (array of strings, optional) - Allowed file extensions (e.g., `["zip", "7z"]`)
- **`versionFrom`** (string, optional) - How to extract version from filename
  - Example: `"exe"` for special version extraction from EXE filenames
- **`versionPattern`** (string, optional) - Regex pattern to extract version from HTML page text
  - Must contain a capture group `()` for the version number
  - Delimiter is added automatically if not present (default: `/pattern/i`)
  - Example: `"v(\\d+\\.\\d+)"` to match "v2.20" and extract "2.20"
  - Example: `"Version\\s+(\\d+\\.\\d+)"` to match "Version 1.25" and extract "1.25"
  - If pattern matches, extracted version is used; otherwise falls back to filename-based extraction
- **`filenamePattern`** (string, optional) - Pattern for renaming downloaded file
  - Use `{version}` placeholder for version number
  - Example: `"app-{version}-x64"` to rename downloaded file to `app-2.20-x64.zip`
  - File extension is automatically preserved if not included in pattern
  - If not specified, original filename from URL is used

**3. Redirect URL downloads (e.g., VS Code):**
- **`redirectUrl`** (string, required) - URL that returns a redirect to the final download URL
  - Example: `"https://code.visualstudio.com/sha/download?build=stable&os=win32-x64-archive"`
  - The system will follow the redirect and extract the final URL from the `Location` header
- **`versionPattern`** (string, optional) - Regex pattern to extract version from filename
  - Must contain a capture group `()` for the version number
  - Delimiter is added automatically if not present (default: `/pattern/i`)
  - Example: `"VSCode-win32-x64-(\\d+\\.\\d+\\.\\d+)"` to extract version from `VSCode-win32-x64-1.107.1.zip`
  - Default: Automatically extracts version pattern like `1.107.1` or `1.85.0` from filename
  - If pattern doesn't match, uses filename without extension as version
- **`filenamePattern`** (string, optional) - Pattern for renaming downloaded file
  - Use `{version}` placeholder for version number
  - If not specified, original filename from redirect URL is used (recommended for VS Code)

## Steps

Steps are executed in order. Each step is an object with one of these keys:

### extractZip

Extracts a ZIP archive.

```json
{ "extractZip": "{downloadedFile}" }
{ "extractZip": "path/to/file.zip" }
```

- Extracts to `{finalDir}` if available, otherwise to same directory as source
- Automatically flattens single-directory archives

### extract7z

Extracts a 7z archive (requires 7z command available).

```json
{ "extract7z": "{downloadedFile}" }
{ "extract7z": "INSTALL.CAB" }
```

- Extracts to `{finalDir}` if available, otherwise to same directory as source
- Automatically flattens single-directory archives

### move

Moves files or directories.

```json
{
  "move": {
    "from": "INSTALL/*",
    "to": "{finalDir}/"
  }
}
```

- **`from`** (string, required) - Source path (supports wildcards `*`)
- **`to`** (string, required) - Destination path
- If `to` ends with `/`, treats as directory and moves files into it
- Supports wildcards in `from` path

### sleep

Pauses execution for specified number of seconds.

```json
{ "sleep": 5 }
{ "sleep": { "seconds": 10 } }
```

- **`sleep`** (integer or object, required) - Sleep duration in seconds
  - Can be a number: `5` for 5 seconds
  - Or an object: `{ "seconds": 10 }` for 10 seconds

### remove

Removes files or directories.

```json
{ "remove": "temp/*.tmp" }
{ "remove": { "path": "old-dir" } }
```

- **`remove`** (string or object, required) - Path to remove (supports wildcards `*`)
  - Can be a string: `"temp/*.tmp"`
  - Or an object: `{ "path": "old-dir" }`
- Supports wildcards in path
- Returns success even if target doesn't exist

### copy

Copies files from previous installation directory.

```json
{
  "copy": {
    "from": "config.ini",
    "to": "{tempDir}/"
  }
}
```

- **`from`** (string, required) - Source file path in previous installation
  - Supports variables like `{oldDir}/config.ini`
- **`to`** (string, optional) - Destination path
  - Defaults to `{tempDir}/` if not specified
- Automatically finds previous installation directory
- Returns success if no previous installation exists

## Variables

Variables can be used in step configurations. Use `{variable}` syntax (recommended) or `$variable` (both are supported):

- **`{downloadedFile}`** - Full path to downloaded file
- **`{finalDir}`** - Full path to final directory (based on `finalDirPattern` or `version`)
- **`{tempDir}`** - Temporary directory for intermediate extraction steps
- **`{oldDir}`** - Path to previous installation directory (for copy step)
- **`{version}`** - Extracted version name
- **`{appPath}`** - Application folder path
- **`{appFolder}`** - Application folder name
- **`{baseName}`** - Configuration file base name (without extension)

## Examples

### Simple ZIP Extraction

```json
{
  "steps": [
    {
      "download": {
        "url": "https://api.github.com/repos/user/app/releases/latest",
        "filter": {
          "mustContain": ["portable"],
          "mustNotContain": [],
          "allowedExt": ["zip"]
        }
      }
    },
    { "extractZip": "{downloadedFile}" }
  ]
}
```

### Multiple Steps with Move

```json
{
  "finalDirPattern": "App-{version}-x64",
  "steps": [
    {
      "download": {
        "url": "https://api.github.com/repos/user/app/releases/latest",
        "filter": {
          "mustContain": ["x64", ".exe"],
          "mustNotContain": [],
          "allowedExt": ["7z"]
        }
      }
    },
    { "extract7z": "{downloadedFile}" },
    { "extract7z": "INSTALL.CAB" },
    {
      "move": {
        "from": "INSTALL/*",
        "to": "{finalDir}/"
      }
    }
  ]
}
```

### HTML Page Download

```json
{
  "finalDirPattern": "App-{version}-x64",
  "steps": [
    {
      "download": {
        "pageUrl": "https://www.example.com/download.htm",
        "findLink": {
          "mustContain": ["x64", ".exe"],
          "mustNotContain": [],
          "allowedExt": ["exe"]
        },
        "versionFrom": "exe"
      }
    },
    { "extract7z": "{downloadedFile}" }
  ]
}
```

### HTML Page Download with Version Pattern

When version is not in the filename, extract it from the HTML page text:

```json
{
  "finalDirPattern": "App-{version}",
  "steps": [
    {
      "download": {
        "pageUrl": "https://www.example.com/download.htm",
        "findLink": {
          "mustContain": ["app", "x64"],
          "mustNotContain": [],
          "allowedExt": ["zip"]
        },
        "versionPattern": "Version\\s+(\\d+\\.\\d+)"
      }
    },
    { "extractZip": "{downloadedFile}" }
  ]
}
```

### HTML Page Download with Filename Pattern

Rename downloaded file to include version:

```json
{
  "finalDirPattern": "App-{version}-x64",
  "steps": [
    {
      "download": {
        "pageUrl": "https://www.example.com/download.htm",
        "findLink": {
          "mustContain": ["app", "x64"],
          "mustNotContain": [],
          "allowedExt": ["zip"]
        },
        "versionPattern": "v(\\d+\\.\\d+)",
        "filenamePattern": "app-{version}-x64"
      }
    },
    { "extractZip": "{downloadedFile}" }
  ]
}
```

This will download `app-x64.zip` and rename it to `app-2.20-x64.zip` (if version is 2.20).

### Redirect URL Download

Download from a URL that redirects to the final download URL (e.g., VS Code):

```json
{
  "finalDirPattern": "VSCode-win32-x64-{version}",
  "steps": [
    {
      "download": {
        "redirectUrl": "https://code.visualstudio.com/sha/download?build=stable&os=win32-x64-archive"
      }
    },
    { "extractZip": "{downloadedFile}" }
  ]
}
```

This will:
1. Request the redirect URL and extract the final URL from the `Location` header
2. Automatically extract version from filename (e.g., `1.107.1` from `VSCode-win32-x64-1.107.1.zip`)
3. Download the file with its original filename (`VSCode-win32-x64-1.107.1.zip`)
4. Create directory `VSCode-win32-x64-1.107.1` (if `finalDirPattern` is set)

The `versionPattern` uses regex with a capture group. Delimiter is added automatically, so you don't need to include it. For example:
- `"v(\\d+\\.\\d+)"` matches "v2.20" and extracts "2.20"
- `"Version\\s+(\\d+\\.\\d+)"` matches "Version 1.25" and extracts "1.25"
- `"v(\\d+\\.\\d+\\.\\d+)"` matches "v2.1.3" and extracts "2.1.3"
- `"Current Version:\\s*([\\d\\.]+)"` matches "Current Version: 3.14" and extracts "3.14"
- `"VSCode-win32-x64-(\\d+\\.\\d+\\.\\d+)"` matches "VSCode-win32-x64-1.107.1.zip" and extracts "1.107.1"

## Default Filter Values

If `filter` is not specified, these defaults are used:
- **mustContain**: `["portable", "x64"]`
- **mustNotContain**: `["arm"]`
- **allowedExt**: `["zip", "7z"]`

## Path Handling

- Absolute paths (starting with drive letter or `\\`) are used as-is
- Relative paths are resolved relative to application folder
- Path separators (`/` and `\`) are automatically normalized
- Variables in paths are replaced before path resolution

## Junction Creation

After all steps complete successfully:
- Junction is created from `{baseName}` to `{finalDir}`
- Junction name is the configuration file name without extension

