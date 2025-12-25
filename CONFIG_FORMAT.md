# Configuration File Format

## Overview

Configuration files are JSON files (`.json` or `.js`) placed in application folders. All configurations must use the steps-based format.

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

### Download Step

The first step must be a `download` step, which contains:

- **`url`** (string) - GitHub API URL for releases (for GitHub downloads)
  - Format: `https://api.github.com/repos/OWNER/REPO/releases/latest`
  - Or: `https://api.github.com/repos/OWNER/REPO/releases`
- **`pageUrl`** (string) - HTML page URL (for HTML page downloads)
  - Example: `"https://www.example.com/download.htm"`
- **`filter`** (object) - Asset filtering rules (for GitHub downloads)
  - Merges with default filter: `["portable", "x64"]` must contain, `["arm"]` must not contain, `["zip", "7z"]` allowed extensions
  - **`mustContain`** (array of strings) - All strings must be present in asset URL
  - **`mustNotContain`** (array of strings) - None of these strings can be in asset URL
  - **`allowedExt`** (array of strings) - Allowed file extensions (e.g., `["zip", "7z"]`)
- **`findLink`** (object) - Link filtering rules (for HTML page downloads)
  - Same structure as `filter` above
- **`versionFrom`** (string) - How to extract version (optional, for HTML page downloads)
  - Example: `"exe"` for special version extraction from EXE filenames

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

- **`from`** (string) - Source path (supports wildcards `*`)
- **`to`** (string) - Destination path
- If `to` ends with `/`, treats as directory and moves files into it
- Supports wildcards in `from` path

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

