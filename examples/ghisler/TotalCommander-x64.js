{
  "special": {
    "pageUrl": "https://www.ghisler.com/download.htm",

    "findLink": {
      "mustContain": ["x64", ".exe"],
      "mustNotContain": []
    },

    "versionFrom": "exe",    // extrakce verze z názvu EXE (tcmd1156x64 → 11.56)

    "finalDirPattern": "TotalCommander-{version}-x64",

    "steps": [
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
}
