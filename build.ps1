# ============================================================
# build.ps1 - Skrip Pembinaan Pakej Bahasa ms-MY Joomla 5
# ============================================================
# Penggunaan: .\build.ps1
# Output: dist\ms-MY_joomla_lang_full_5.4.4v1.zip
# ============================================================

$ErrorActionPreference = "Stop"

# --- Konfigurasi ---
$version      = "5.4.4"
$packVersion  = "5.4.4.1"
$creationDate = "2026-03"
$author       = "Joomla! Malaysia"
$authorEmail  = "zaki@joomla.my"
$authorUrl    = "www.joomla.my"
$copyright    = "(C) 2026 Open Source Matters, Inc."
$license      = "GNU General Public License version 2 or later; see LICENSE.txt"

$rootDir  = $PSScriptRoot
$distDir  = Join-Path $rootDir "dist"
$tempDir  = Join-Path $rootDir "_build_temp"

# --- Fungsi: Jana install.xml ---
function New-InstallXml {
    param(
        [string]$Client,
        [string]$Description,
        [string]$CopyrightYear,
        [string]$SourceDir
    )

    $files = Get-ChildItem -Path $SourceDir -File |
        Where-Object { $_.Name -ne "install.xml" -and $_.Name -ne "langmetadata.xml" } |
        Sort-Object Name

    $fileEntries = foreach ($f in $files) {
        "`t`t<filename>$($f.Name)</filename>"
    }

    $xml = @"
<?xml version="1.0" encoding="UTF-8"?>
<extension client="$Client" type="language" method="upgrade">
	<name>Malay (ms-MY)</name>
	<tag>ms-MY</tag>
	<version>$version</version>
	<creationDate>$creationDate</creationDate>
	<author>$author</author>
	<authorEmail>$authorEmail</authorEmail>
	<authorUrl>$authorUrl</authorUrl>
	<copyright>$copyright</copyright>
	<license>$license</license>
	<description>$Description</description>
	<files>
		<filename file="meta">install.xml</filename>
		<filename file="meta">langmetadata.xml</filename>
$($fileEntries -join "`n")
	</files>
	<params />
</extension>
"@

    return $xml
}

# --- Bersihkan ---
Write-Host "=== Pembinaan Pakej Bahasa ms-MY ===" -ForegroundColor Cyan
Write-Host ""

if (Test-Path $tempDir) { Remove-Item $tempDir -Recurse -Force }
if (Test-Path $distDir) { Remove-Item $distDir -Recurse -Force }
New-Item -ItemType Directory -Path $tempDir -Force | Out-Null
New-Item -ItemType Directory -Path $distDir -Force | Out-Null

# --- Definisi seksyen ---
$sections = @(
    @{
        Name        = "site"
        Client      = "site"
        SourceDir   = Join-Path $rootDir "language\ms-MY"
        ZipName     = "site_ms-MY.zip"
        Description = "Pakej Bahasa Melayu (ms-MY) - Laman"
        CopyYear    = "2013"
    },
    @{
        Name        = "admin"
        Client      = "administrator"
        SourceDir   = Join-Path $rootDir "administrator\language\ms-MY"
        ZipName     = "admin_ms-MY.zip"
        Description = "Pakej Bahasa Melayu (ms-MY) - Pentadbir"
        CopyYear    = "2013"
    },
    @{
        Name        = "api"
        Client      = "api"
        SourceDir   = Join-Path $rootDir "api\language\ms-MY"
        ZipName     = "api_ms-MY.zip"
        Description = "Pakej Bahasa Melayu (ms-MY) - API"
        CopyYear    = "2020"
    }
)

foreach ($section in $sections) {
    $name      = $section.Name
    $sourceDir = $section.SourceDir
    $zipName   = $section.ZipName
    $zipPath   = Join-Path $tempDir $zipName

    Write-Host "[$name] Menjana install.xml..." -ForegroundColor Yellow

    # Jana install.xml baharu
    $installXml = New-InstallXml `
        -Client $section.Client `
        -Description $section.Description `
        -CopyrightYear $section.CopyYear `
        -SourceDir $sourceDir

    # Tulis install.xml ke folder sumber
    $installXml | Set-Content -Path (Join-Path $sourceDir "install.xml") -Encoding UTF8

    # Kira fail
    $fileCount = (Get-ChildItem -Path $sourceDir -File).Count
    Write-Host "[$name] $fileCount fail dijumpai" -ForegroundColor Gray

    # Cipta ZIP
    Write-Host "[$name] Mencipta $zipName..." -ForegroundColor Yellow
    Compress-Archive -Path (Join-Path $sourceDir "*") -DestinationPath $zipPath -Force

    $zipSize = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
    Write-Host "[$name] $zipName dicipta ($zipSize KB)" -ForegroundColor Green
}

# --- Cipta pakej ZIP utama ---
Write-Host ""
Write-Host "Mencipta pakej utama..." -ForegroundColor Yellow

# Salin pkg_ms-MY.xml dan script.php ke temp
Copy-Item (Join-Path $rootDir "pkg_ms-MY.xml") -Destination $tempDir
Copy-Item (Join-Path $rootDir "script.php") -Destination $tempDir

$packageName = "ms-MY_joomla_lang_full_${packVersion}.zip"
$packagePath = Join-Path $distDir $packageName

Compress-Archive -Path (Join-Path $tempDir "*") -DestinationPath $packagePath -Force

# --- Bersihkan temp ---
Remove-Item $tempDir -Recurse -Force

# --- Ringkasan ---
$packageSize = [math]::Round((Get-Item $packagePath).Length / 1KB, 1)
Write-Host ""
Write-Host "=== SELESAI ===" -ForegroundColor Green
Write-Host "Pakej: dist\$packageName ($packageSize KB)" -ForegroundColor Cyan
Write-Host ""

# Papar kandungan ZIP untuk pengesahan
Write-Host "Kandungan pakej:" -ForegroundColor Yellow
$shell = New-Object -ComObject Shell.Application
$zip = $shell.NameSpace((Resolve-Path $packagePath).Path)
foreach ($item in $zip.Items()) {
    $size = [math]::Round($item.Size / 1KB, 1)
    Write-Host "  $($item.Name) ($size KB)"
}
