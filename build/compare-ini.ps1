<#
.SYNOPSIS
Skrip untuk membandingkan kunci (keys) dalam fail .ini en-GB dan ms-MY.
Ia akan memaparkan sebarang kunci yang hilang dalam terjemahan ms-MY.
#>
param (
    [Parameter(Mandatory=$true)]
    [string]$EnGbFilePath,
    
    [Parameter(Mandatory=$true)]
    [string]$MsMyFilePath
)

if (-not (Test-Path $EnGbFilePath) -or -not (Test-Path $MsMyFilePath)) {
    Write-Error "Sila pastikan kedua-dua fail wujud."
    exit
}

# Fungsi pembantu untuk mendapatkan senarai kunci (keys) dari fail .ini
function Get-IniKeys ($filePath) {
    $content = Get-Content $filePath
    $keys = @()
    foreach ($line in $content) {
        # Abaikan komen dan baris kosong
        if ($line -match '^\s*;' -or [string]::IsNullOrWhiteSpace($line)) { continue }
        # Cari baris yang mengandungi KEY="VALUE"
        if ($line -match '^([A-Z0-9_\-]+)\s*=') {
            $keys += $Matches[1]
        }
    }
    return $keys
}

$enKeys = Get-IniKeys $EnGbFilePath
$myKeys = Get-IniKeys $MsMyFilePath

# Cari perbezaan: Kunci yang ada dalam en-GB tetapi tiada dalam ms-MY
$missingKeys = Compare-Object -ReferenceObject $enKeys -DifferenceObject $myKeys | Where-Object SideIndicator -eq '<=' | Select-Object -ExpandProperty InputObject

if ($missingKeys) {
    Write-Warning "Terdapat $($missingKeys.Count) kunci yang hilang dalam $MsMyFilePath :"
    $missingKeys | ForEach-Object { Write-Host "- $_" -ForegroundColor Yellow }
} else {
    Write-Host "Hebat! Semua kunci dalam fail ini sejajar." -ForegroundColor Green
}