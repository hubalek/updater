{
  "finalDirPattern": "TotalCommander-{version}-x64",
  "steps": [
    {
      "download": {
        "pageUrl": "https://www.ghisler.com/download.htm",
        "findLink": {
          "mustContain": ["x64", ".exe"],
          "mustNotContain": []
        },
        "versionFrom": "exe"
      }
    },
    { "extract7z": "$downloadedFile" },
    { "extract7z": "INSTALL.CAB" },
    {
      "move": {
        "from": "INSTALL/*",
        "to": "$finalDir/"
      }
    }
  ]
}
