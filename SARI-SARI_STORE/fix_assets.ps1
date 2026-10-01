$ErrorActionPreference = "Stop"
$path = "c:\xampp\htdocs\SARISARI\SARISARI\SARI-SARI_STORE"

# Rename Assets to assets
if (Test-Path "$path\Assets") {
    Rename-Item -Path "$path\Assets" -NewName "temp_assets"
    Rename-Item -Path "$path\temp_assets" -NewName "assets"
    Write-Host "Renamed Assets to assets"
}

# Replace Assets/ with assets/ in all PHP files
$files = Get-ChildItem -Path $path -Recurse -Filter "*.php"
$count = 0
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    if ($content -match 'Assets/') {
        # Using -creplace for case-sensitive replace
        $newContent = $content -creplace 'Assets/', 'assets/'
        Set-Content -Path $file.FullName -Value $newContent
        $count++
    }
}
Write-Host "Updated $count files."
