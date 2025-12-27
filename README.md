# Updater

PHP-based application updater for managing portable software installations. Automatically downloads, extracts, and organizes software updates from GitHub releases or HTML pages.

## Features

- **GitHub Integration** - Download releases directly from GitHub API
- **HTML Page Parsing** - Extract download links from HTML pages
- **Flexible Configuration** - JSON-based configuration files per application
- **Version Extraction** - Automatic version detection from filenames or HTML content
- **Archive Extraction** - Supports ZIP and 7z archives
- **Junction Management** - Creates Windows junction points for easy access
- **File Operations** - Move, copy, remove files with wildcard support
- **Previous Installation Support** - Copy files from previous installations

## Requirements

- PHP 8.0 or higher
- PHP extensions: `curl`, `zip`, `json`
- 7z command-line tool (for 7z extraction, optional)
- Windows (for junction support)

## Installation

1. Clone the repository:
```bash
git clone https://github.com/hubalek/updater.git
cd updater
```

2. Create `.env` file in the project root:
```env
SOFTWARE_ROOT=C:/path/to/your/software/directory
DEBUG=false
```

3. Run the updater:
```bash
php Updater.php
```

## Configuration

### Environment Variables

Create a `.env` file in the project root:

- **`SOFTWARE_ROOT`** (required) - Root directory where applications are stored
- **`DEBUG`** (optional) - Enable debug output (`true`/`false`, default: `false`)

### Application Configuration

Each application needs a JSON configuration file in its folder. The configuration file should be named after the application (e.g., `volumouse.json`).

**Directory Structure:**
```
SOFTWARE_ROOT/
├── app1/
│   └── app1.json
├── app2/
│   └── app2.json
└── ...
```

See [examples/CONFIG_FORMAT.md](examples/CONFIG_FORMAT.md) for detailed configuration documentation.

### Quick Start Example

Create a configuration file `myapp.json` in your application folder:

```json
{
  "finalDirPattern": "myapp-{version}-x64",
  "steps": [
    {
      "download": {
        "url": "https://api.github.com/repos/owner/repo/releases/latest",
        "filter": {
          "mustContain": ["portable", "x64"],
          "mustNotContain": ["arm"],
          "allowedExt": ["zip"]
        },
        "filenamePattern": "myapp-{version}-x64"
      }
    },
    {
      "extractZip": "{downloadedFile}"
    }
  ]
}
```

## How It Works

1. **Scan** - Scans `SOFTWARE_ROOT` for application folders
2. **Load Config** - Loads JSON configuration files from each folder
3. **Download** - Downloads the latest version from GitHub or HTML page
4. **Extract** - Extracts archives if needed
5. **Process** - Executes additional steps (move, copy, etc.)
6. **Junction** - Creates a junction point from config filename to version directory

## Configuration Format

The updater uses a step-based configuration format. Each configuration file must contain:

- **`steps`** (required) - Array of processing steps
  - First step must be a `download` step
  - Subsequent steps are executed in order

- **`finalDirPattern`** (optional) - Pattern for final directory name
  - Use `{version}` placeholder for version number
  - Example: `"App-{version}-x64"`

### Supported Steps

- **`download`** - Download from GitHub API or HTML page
- **`extractZip`** - Extract ZIP archive
- **`extract7z`** - Extract 7z archive
- **`move`** - Move files or directories
- **`sleep`** - Pause execution
- **`remove`** - Remove files or directories
- **`copy`** - Copy files from previous installation

### Variables

Variables can be used in step configurations using `{variable}` syntax:

- `{downloadedFile}` - Full path to downloaded file
- `{finalDir}` - Full path to final directory
- `{tempDir}` - Temporary directory for intermediate steps
- `{oldDir}` - Path to previous installation directory
- `{version}` - Extracted version name
- `{appPath}` - Application folder path
- `{appFolder}` - Application folder name
- `{baseName}` - Configuration file base name

## Examples

See the [examples](examples/) directory for real-world configuration examples:

- **GitHub Releases** - `audacity/audacity-win-64bit.json`
- **HTML Page Downloads** - `nirsoft/volumouse.json`
- **Complex Workflows** - `ghisler/TotalCommander-x64.json`

## Documentation

For detailed configuration documentation, see [examples/CONFIG_FORMAT.md](examples/CONFIG_FORMAT.md).

## License

[Add your license here]

## Contributing

[Add contribution guidelines here]

