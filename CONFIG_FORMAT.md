# Configuration File Format

## Overview

Configuration files are JSON files (`.json` or `.js`) placed in application folders. The system supports two modes:
1. **Legacy mode** - Simple format for basic GitHub releases (backward compatible)
2. **Steps-based mode** - Advanced format with custom processing steps

## Basic Structure

### Legacy Format (Backward Compatible)

```json
{
  "url": "https://api.github.com/repos/OWNER/REPO/releases/latest",
  "filter": {
    "mustContain": ["portable", "x64"],
    "mustNotContain": ["arm"],
    "allowedExt": ["zip", "7z"]
  }
}
```

**Behavior:**
- Downloads ZIP file from GitHub release
- Extracts to `{version}` directory
- Creates junction from `{baseName}` to `{version}`

### Steps-Based Format (New)

```json
{
  "url": "https://api.github.com/repos/OWNER/REPO/releases/latest",
  "filter": {
    "mustContain": ["portable", "x64"],
    "mustNotContain": ["arm"],
    "allowedExt": ["zip", "7z"]
  },
  "finalDirPattern": "AppName-{version}-x64",
  "steps": [
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

- **`url`** (string) - GitHub API URL for releases
  - Format: `https://api.github.com/repos/OWNER/REPO/releases/latest`
  - Or: `https://api.github.com/repos/OWNER/REPO/releases`

### Optional Fields

- **`filter`** (object) - Asset filtering rules
  - Merges with default filter: `["portable", "x64"]` must contain, `["arm"]` must not contain, `["zip", "7z"]` allowed extensions
  - **`mustContain`** (array of strings) - All strings must be present in asset URL
  - **`mustNotContain`** (array of strings) - None of these strings can be in asset URL
  - **`allowedExt`** (array of strings) - Allowed file extensions (e.g., `["zip", "7z"]`)

- **`finalDirPattern`** (string) - Pattern for final directory name (only in steps-based mode)
  - Use `{version}` placeholder for version number
  - Example: `"TotalCommander-{version}-x64"`
  - Default: Uses `{version}` directly if not specified

- **`steps`** (array) - Processing steps (only in steps-based mode)
  - If present and non-empty, uses steps-based processing
  - If absent or empty, uses legacy processing

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

Variables can be used in step configurations. Use `{variable}` syntax (recommended) or `$variable` (legacy):

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
  "url": "https://api.github.com/repos/user/app/releases/latest",
  "filter": {
    "mustContain": ["portable"],
    "mustNotContain": [],
    "allowedExt": ["zip"]
  },
  "steps": [
    { "extractZip": "{downloadedFile}" }
  ]
}
```

### Multiple Steps with Move

```json
{
  "url": "https://api.github.com/repos/user/app/releases/latest",
  "filter": {
    "mustContain": ["x64", ".exe"],
    "mustNotContain": [],
    "allowedExt": ["7z"]
  },
  "finalDirPattern": "App-{version}-x64",
  "steps": [
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

### Legacy Format (Still Supported)

```json
{
  "url": "https://api.github.com/repos/user/app/releases/latest",
  "filter": {
    "mustContain": ["portable", "x64"],
    "mustNotContain": ["arm"],
    "allowedExt": ["zip"]
  }
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
- Junction is created from `{baseName}` to `{finalDir}` (or `{version}` in legacy mode)
- Junction name is the configuration file name without extension

