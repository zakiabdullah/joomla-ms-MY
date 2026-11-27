#!/usr/bin/env pwsh
[CmdletBinding()]
param(
    [string]$ConfigPath = 'build/build.config.json',
    [switch]$CleanOnly,
    [switch]$SkipValidation
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$script:SourceDate = $null

function Write-Log {
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet('INFO', 'WARN', 'ERROR')]
        [string]$Level,

        [Parameter(Mandatory = $true)]
        [string]$Message
    )

    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'

    switch ($Level) {
        'INFO' { Write-Host "[$timestamp] [INFO]  $Message" }
        'WARN' { Write-Warning "[$timestamp] [WARN]  $Message" }
        'ERROR' { Write-Error "[$timestamp] [ERROR] $Message" }
    }
}

function Get-SourceDate {
    <#
    .SYNOPSIS
    Returns the timestamp used for reproducible builds (UTC).

    .DESCRIPTION
    Uses SOURCE_DATE_EPOCH when set (https://reproducible-builds.org/specs/source-date-epoch/),
    otherwise the commit time of HEAD, otherwise the current time. Zip entry times and
    manifest creation dates come from this value, so the same commit always produces
    byte-identical packages locally and in CI.
    #>
    if ($script:SourceDate) {
        return $script:SourceDate
    }

    $epoch = $env:SOURCE_DATE_EPOCH

    if (-not $epoch) {
        try {
            $epoch = (& git -C $PSScriptRoot log -1 --format=%ct 2>$null)
        }
        catch {
            $epoch = $null
        }
    }

    if ($epoch -and "$epoch".Trim() -match '^\d+$') {
        $script:SourceDate = [DateTimeOffset]::FromUnixTimeSeconds([long]"$epoch".Trim())
    }
    else {
        $script:SourceDate = [DateTimeOffset]::UtcNow
    }

    return $script:SourceDate
}

function Get-RepoRoot {
    if ([string]::IsNullOrWhiteSpace($PSScriptRoot)) {
        return (Get-Location).Path
    }

    return $PSScriptRoot
}

function Ensure-Directory {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -Path $Path)) {
        [void](New-Item -Path $Path -ItemType Directory -Force)
    }
}

function Remove-DirectorySafe {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (Test-Path -Path $Path) {
        Remove-Item -Path $Path -Recurse -Force
    }
}

function Get-VersionParts {
    param([Parameter(Mandatory = $true)][string]$Version)

    if ($Version -notmatch '^(\d+)\.(\d+)\.(\d+)(?:\.(\d+))?$') {
        throw "Invalid version format: $Version. Expected x.y.z or x.y.z.n"
    }

    return [PSCustomObject]@{
        Major = [int]$Matches[1]
        Minor = [int]$Matches[2]
        Patch = [int]$Matches[3]
        Pack  = if ($Matches[4]) { [int]$Matches[4] } else { 1 }
    }
}

function Read-Xml {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -Path $Path)) {
        throw "Required XML file is missing: $Path"
    }

    [xml]$xml = Get-Content -Path $Path -Raw -Encoding UTF8
    return $xml
}

function Get-BuildTargets {
    <#
    .SYNOPSIS
    Reads the enabled targets from build/build.config.json.

    .DESCRIPTION
    Package metadata (names, authors, descriptions) is not configured here: it
    comes from the repository manifests, which build.ps1 copies as they are.
    #>
    param(
        [Parameter(Mandatory = $true)][string]$RepoRoot,
        [Parameter(Mandatory = $true)][string]$ConfigPath
    )

    $resolvedConfigPath = Join-Path $RepoRoot $ConfigPath

    if (-not (Test-Path -Path $resolvedConfigPath)) {
        throw "Build config not found: $ConfigPath"
    }

    Write-Log -Level INFO -Message "Using build config: $ConfigPath"
    $config = Get-Content -Path $resolvedConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json

    if (-not $config.targets -or $config.targets.Count -eq 0) {
        throw "Config file does not define targets: $ConfigPath"
    }

    $metadata = [PSCustomObject]@{
        LanguageTag = if ($config.languageTag) { [string]$config.languageTag } else { 'ms-MY' }
    }

    $targets = @()

    foreach ($target in $config.targets) {
        if ($null -ne $target.enabled -and -not [bool]$target.enabled) {
            continue
        }

        if (-not $target.key -or -not $target.joomlaMajor -or -not $target.packageVersion) {
            throw "Invalid target in config. Expected key, joomlaMajor, packageVersion."
        }

        [void](Get-VersionParts -Version ([string]$target.packageVersion))

        $targets += [PSCustomObject]@{
            Key            = [string]$target.key
            JoomlaMajor    = [int]$target.joomlaMajor
            PackageVersion = [string]$target.packageVersion
        }
    }

    if ($targets.Count -eq 0) {
        throw "No enabled targets found in config: $ConfigPath"
    }

    return [PSCustomObject]@{
        Metadata = $metadata
        Targets  = $targets
    }
}

function Get-RelativeUnixPath {
    param(
        [Parameter(Mandatory = $true)][string]$BasePath,
        [Parameter(Mandatory = $true)][string]$FullPath
    )

    $relative = [System.IO.Path]::GetRelativePath($BasePath, $FullPath)
    return $relative.Replace('\', '/')
}

function Test-SafeRelativePath {
    param([Parameter(Mandatory = $true)][string]$Path)

    if ($Path.Contains('..')) {
        return $false
    }

    if ([System.IO.Path]::IsPathRooted($Path)) {
        return $false
    }

    return $true
}

function Get-FilteredSourceFiles {
    param([Parameter(Mandatory = $true)][string]$SourceRoot)

    if (-not (Test-Path -Path $SourceRoot)) {
        return @()
    }

    $excludedFiles = @('install.xml', 'langmetadata.xml')
    $items = Get-ChildItem -Path $SourceRoot -Recurse -File

    $files = foreach ($item in $items) {
        if ($excludedFiles -contains $item.Name) {
            continue
        }

        $relative = Get-RelativeUnixPath -BasePath $SourceRoot -FullPath $item.FullName

        if (-not (Test-SafeRelativePath -Path $relative)) {
            throw "Unsafe path detected in source files: $relative"
        }

        [PSCustomObject]@{
            FullName = $item.FullName
            Relative = $relative
        }
    }

    return @($files)
}

function Copy-FilesToContentRoot {
    param(
        [Parameter(Mandatory = $true)]$Files,
        [Parameter(Mandatory = $true)][string]$DestinationContentRoot
    )

    foreach ($file in $Files) {
        $targetFile = Join-Path $DestinationContentRoot ($file.Relative -replace '/', [System.IO.Path]::DirectorySeparatorChar)
        $targetDir = Split-Path -Parent $targetFile
        Ensure-Directory -Path $targetDir
        Copy-NormalizedFile -Source $file.FullName -Destination $targetFile
    }
}

function Copy-NormalizedFile {
    <#
    .SYNOPSIS
    Copies a file, converting CRLF to LF for text files so packages built from a
    Windows working copy are identical to packages built in CI.
    #>
    param(
        [Parameter(Mandatory = $true)][string]$Source,
        [Parameter(Mandatory = $true)][string]$Destination
    )

    $textExtensions = @('.ini', '.xml', '.php', '.txt', '.html', '.css', '.js', '.json')

    if ($textExtensions -notcontains ([System.IO.Path]::GetExtension($Source)).ToLowerInvariant()) {
        Copy-Item -Path $Source -Destination $Destination -Force
        return
    }

    $bytes = [System.IO.File]::ReadAllBytes($Source)
    $text = [System.Text.Encoding]::UTF8.GetString($bytes) -replace "`r`n", "`n"
    [System.IO.File]::WriteAllBytes($Destination, (New-Object System.Text.UTF8Encoding($false)).GetBytes($text))
}

function Apply-TargetOverrides {
    <#
    .SYNOPSIS
    Overlays per-target string overrides onto the staged language files.

    .DESCRIPTION
    The repository keeps one translation tree for every Joomla major version.
    When a string differs between majors (for example a sprintf placeholder
    that only exists in Joomla 6), the version-specific value lives in
    overrides/<target key>/<content root>/<file>.ini. Each KEY="value" line
    there replaces the same key in the staged file, or is appended when the
    key does not exist yet.

    Step 1 of build/make-target-tree.php applies the same rules when it
    generates the j6 branch; keep the two implementations in sync.
    #>
    param(
        [Parameter(Mandatory = $true)][string]$RepoRoot,
        [Parameter(Mandatory = $true)][string]$TargetKey,
        [Parameter(Mandatory = $true)][string]$ContentRoot,
        [Parameter(Mandatory = $true)][string]$StagedContentRoot
    )

    $overrideRoot = Join-Path $RepoRoot (Join-Path "overrides/$TargetKey" $ContentRoot)

    if (-not (Test-Path -Path $overrideRoot)) {
        return
    }

    $utf8NoBom = New-Object System.Text.UTF8Encoding($false)

    foreach ($override in Get-ChildItem -Path $overrideRoot -Filter '*.ini' -File) {
        $stagedFile = Join-Path $StagedContentRoot $override.Name

        if (-not (Test-Path -Path $stagedFile)) {
            throw "Override $($override.FullName) has no matching language file ($($override.Name))"
        }

        $overrides = [ordered]@{}

        foreach ($line in [System.IO.File]::ReadAllLines($override.FullName, $utf8NoBom)) {
            if ($line -match '^\s*([A-Z0-9_.:\-]+)\s*=\s*".*"\s*$') {
                $overrides[$Matches[1]] = $line.Trim()
            }
        }

        $lines = [System.Collections.Generic.List[string]]::new()
        $applied = @{}

        foreach ($line in [System.IO.File]::ReadAllLines($stagedFile, $utf8NoBom)) {
            if ($line -match '^\s*([A-Z0-9_.:\-]+)\s*=' -and $overrides.Contains($Matches[1])) {
                $lines.Add($overrides[$Matches[1]])
                $applied[$Matches[1]] = $true
                continue
            }

            $lines.Add($line)
        }

        foreach ($key in $overrides.Keys) {
            if (-not $applied.ContainsKey($key)) {
                $lines.Add($overrides[$key])
            }
        }

        [System.IO.File]::WriteAllText($stagedFile, (($lines -join "`n") + "`n"), $utf8NoBom)
        Write-Log -Level INFO -Message "Applied $($overrides.Count) override(s) for $TargetKey to $ContentRoot/$($override.Name)"    }
}

function Copy-ManifestForTarget {
    <#
    .SYNOPSIS
    Copies a repository manifest into the build, adjusted for the target.

    .DESCRIPTION
    Joomla's core-translations project packages the j5 and j6 branches with the
    manifests from this repository as they are. Building from the same files
    keeps the GitHub Release packages identical to the official ones. Only the
    values that differ per target are changed, exactly like
    build/make-target-tree.php does for the j6 branch: <version> and the
    translationlist_<major>.xml update server.
    #>
    param(
        [Parameter(Mandatory = $true)][string]$Source,
        [Parameter(Mandatory = $true)][string]$Destination,
        [Parameter(Mandatory = $true)][string]$Version,
        [Parameter(Mandatory = $true)][int]$JoomlaMajor
    )

    if (-not (Test-Path -Path $Source)) {
        throw "Required manifest is missing: $Source"
    }

    $content = [System.IO.File]::ReadAllText($Source, [System.Text.Encoding]::UTF8) -replace "`r`n", "`n"
    $versionPattern = '<version>[^<]*</version>'

    if ([regex]::Matches($content, $versionPattern).Count -ne 1) {
        throw "Expected exactly one <version> in $Source"
    }

    $content = [regex]::Replace($content, $versionPattern, "<version>$Version</version>")
    $content = [regex]::Replace($content, 'translationlist_\d+\.xml', "translationlist_$JoomlaMajor.xml")

    [System.IO.File]::WriteAllText($Destination, $content, (New-Object System.Text.UTF8Encoding($false)))
}

function Set-ScriptMinimumJoomla {
    param(
        [Parameter(Mandatory = $true)][string]$SourcePath,
        [Parameter(Mandatory = $true)][string]$DestinationPath,
        [Parameter(Mandatory = $true)][int]$JoomlaMajor
    )

    $content = (Get-Content -Path $SourcePath -Raw -Encoding UTF8) -replace "`r`n", "`n"

    $minimumPattern = '\$this->minimumJoomla\s*=\s*''[^'']+'';'

    if ($content -match $minimumPattern) {
        $replacement = '$this->minimumJoomla = ''' + $JoomlaMajor + '.0'';'
        $content = [regex]::Replace($content, $minimumPattern, $replacement)
    }

    [System.IO.File]::WriteAllText($DestinationPath, $content, (New-Object System.Text.UTF8Encoding($false)))
}

function Compress-FolderContent {
    param(
        [Parameter(Mandatory = $true)][string]$SourceFolder,
        [Parameter(Mandatory = $true)][string]$ZipPath
    )

    if (Test-Path -Path $ZipPath) {
        Remove-Item -Path $ZipPath -Force
    }

    $files = @(Get-ChildItem -Path $SourceFolder -Recurse -File -Force |
        ForEach-Object {
            [PSCustomObject]@{
                FullName = $_.FullName
                Relative = Get-RelativeUnixPath -BasePath $SourceFolder -FullPath $_.FullName
            }
        } |
        Sort-Object -Property Relative -CaseSensitive)

    if ($files.Count -eq 0) {
        throw "Cannot create zip from empty folder: $SourceFolder"
    }

    # Reproducible zip: fixed entry order, fixed timestamps, no directory entries
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $timestamp = Get-SourceDate
    $archive = [System.IO.Compression.ZipFile]::Open($ZipPath, [System.IO.Compression.ZipArchiveMode]::Create)

    try {
        foreach ($file in $files) {
            $entry = $archive.CreateEntry($file.Relative, [System.IO.Compression.CompressionLevel]::Optimal)
            $entry.LastWriteTime = $timestamp
            $target = $entry.Open()

            try {
                $bytes = [System.IO.File]::ReadAllBytes($file.FullName)
                $target.Write($bytes, 0, $bytes.Length)
            }
            finally {
                $target.Dispose()
            }
        }
    }
    finally {
        $archive.Dispose()
    }
}

function Validate-XmlFile {
    param([Parameter(Mandatory = $true)][string]$Path)

    try {
        [void](Read-Xml -Path $Path)
    }
    catch {
        throw "Invalid XML in $Path. $($_.Exception.Message)"
    }
}

function Build-Target {
    param(
        [Parameter(Mandatory = $true)][string]$RepoRoot,
        [Parameter(Mandatory = $true)][string]$DistRoot,
        [Parameter(Mandatory = $true)]$Metadata,
        [Parameter(Mandatory = $true)]$Target,
        [Parameter(Mandatory = $true)][bool]$DoValidation
    )

    $targetBuildRoot = Join-Path $RepoRoot ("build/joomla{0}" -f $Target.JoomlaMajor)
    Ensure-Directory -Path $targetBuildRoot

    Write-Log -Level INFO -Message "Preparing build area: build/joomla$($Target.JoomlaMajor)"

    Remove-DirectorySafe -Path $targetBuildRoot
    Ensure-Directory -Path $targetBuildRoot

    $subPackages = @(
        [PSCustomObject]@{
            Name        = 'site'
            Client      = 'site'
            SourceRoot  = Join-Path $RepoRoot 'language/ms-MY'
            ContentRoot = 'language/ms-MY'
            ZipName     = 'site_ms-MY.zip'
        },
        [PSCustomObject]@{
            Name        = 'admin'
            Client      = 'administrator'
            SourceRoot  = Join-Path $RepoRoot 'administrator/language/ms-MY'
            ContentRoot = 'administrator/language/ms-MY'
            ZipName     = 'admin_ms-MY.zip'
        },
        [PSCustomObject]@{
            Name        = 'api'
            Client      = 'api'
            SourceRoot  = Join-Path $RepoRoot 'api/language/ms-MY'
            ContentRoot = 'api/language/ms-MY'
            ZipName     = 'api_ms-MY.zip'
        }
        # installation/language/ms-MY is deliberately not packaged: client
        # "installation" maps to JPATH_INSTALLATION, so installing it on a live
        # site would recreate the installation/ folder. Those files reach users
        # through the Joomla core installer (core-translations), like en-GB.
    )

    $builtSubPackages = @()

    foreach ($sub in $subPackages) {
        if (-not (Test-Path -Path $sub.SourceRoot)) {
            Write-Log -Level WARN -Message "Skipping $($sub.Name): source folder missing ($($sub.SourceRoot))"
            continue
        }

        $sourceFiles = Get-FilteredSourceFiles -SourceRoot $sub.SourceRoot

        if ($sourceFiles.Count -eq 0) {
            Write-Log -Level WARN -Message "Skipping $($sub.Name): no translatable files found"
            continue
        }

        $subBuildRoot = Join-Path $targetBuildRoot $sub.Name
        # A language sub-package contains the language files at its archive
        # root; the installer maps that root to language/<tag>,
        # administrator/language/<tag>, or api/language/<tag> from `client`.
        $subContentRoot = $subBuildRoot

        Ensure-Directory -Path $subBuildRoot
        Ensure-Directory -Path $subContentRoot

        Copy-FilesToContentRoot -Files $sourceFiles -DestinationContentRoot $subContentRoot

        Apply-TargetOverrides `
            -RepoRoot $RepoRoot `
            -TargetKey $Target.Key `
            -ContentRoot $sub.ContentRoot `
            -StagedContentRoot $subContentRoot

        $installXmlPath = Join-Path $subBuildRoot 'install.xml'
        $metaXmlPath = Join-Path $subBuildRoot 'langmetadata.xml'

        foreach ($manifest in @(@($installXmlPath, 'install.xml'), @($metaXmlPath, 'langmetadata.xml'))) {
            Copy-ManifestForTarget `
                -Source (Join-Path $sub.SourceRoot $manifest[1]) `
                -Destination $manifest[0] `
                -Version $Target.PackageVersion `
                -JoomlaMajor $Target.JoomlaMajor
        }

        if ($DoValidation) {
            Validate-XmlFile -Path $installXmlPath
            Validate-XmlFile -Path $metaXmlPath
        }

        $zipPath = Join-Path $targetBuildRoot $sub.ZipName
        Compress-FolderContent -SourceFolder $subBuildRoot -ZipPath $zipPath

        if ($DoValidation -and (Get-Item -Path $zipPath).Length -le 0) {
            throw "Invalid zip output (empty): $zipPath"
        }

        Write-Log -Level INFO -Message ("Built {0}: {1} files -> {2}" -f $sub.Name, $sourceFiles.Count, $sub.ZipName)

        $builtSubPackages += [PSCustomObject]@{
            Name    = $sub.Name
            Client  = $sub.Client
            ZipName = $sub.ZipName
            ZipPath = $zipPath
        }
    }

    if ($builtSubPackages.Count -lt 3) {
        throw "Build target j$($Target.JoomlaMajor) does not contain required sub-packages (site/admin/api)."
    }

    $pkgRoot = Join-Path $targetBuildRoot 'pkg'
    Ensure-Directory -Path $pkgRoot

    foreach ($item in $builtSubPackages) {
        Copy-Item -Path $item.ZipPath -Destination (Join-Path $pkgRoot $item.ZipName) -Force
    }

    $packageManifestPath = Join-Path $pkgRoot 'pkg_ms-MY.xml'

    Copy-ManifestForTarget `
        -Source (Join-Path $RepoRoot 'pkg_ms-MY.xml') `
        -Destination $packageManifestPath `
        -Version $Target.PackageVersion `
        -JoomlaMajor $Target.JoomlaMajor

    # The repository manifest must list exactly the sub-packages that were built
    $listed = @(([xml](Get-Content -Path $packageManifestPath -Raw -Encoding UTF8)).extension.files.file | ForEach-Object { [string]$_.'#text' } | Sort-Object)
    $built = @($builtSubPackages.ZipName | Sort-Object)

    if (($listed -join ',') -ne ($built -join ',')) {
        throw "pkg_ms-MY.xml lists [$($listed -join ', ')] but the build produced [$($built -join ', ')]"
    }

    Set-ScriptMinimumJoomla `
        -SourcePath (Join-Path $RepoRoot 'script.php') `
        -DestinationPath (Join-Path $pkgRoot 'script.php') `
        -JoomlaMajor $Target.JoomlaMajor

    if ($DoValidation) {
        Validate-XmlFile -Path $packageManifestPath
    }

    # Same naming as the official language packs from core-translations,
    # e.g. ms-MY_joomla_lang_full_6.2.1v1.zip for package version 6.2.1.1
    $versionParts = Get-VersionParts -Version $Target.PackageVersion
    $finalZipName = '{0}_joomla_lang_full_{1}.{2}.{3}v{4}.zip' -f $Metadata.LanguageTag, $versionParts.Major, $versionParts.Minor, $versionParts.Patch, $versionParts.Pack
    $finalZipPath = Join-Path $DistRoot $finalZipName

    Compress-FolderContent -SourceFolder $pkgRoot -ZipPath $finalZipPath

    if ($DoValidation -and (Get-Item -Path $finalZipPath).Length -le 0) {
        throw "Invalid final package zip (empty): $finalZipPath"
    }

    Write-Log -Level INFO -Message "Built final package: dist/$finalZipName"
}

$repoRoot = Get-RepoRoot
$distRoot = Join-Path $repoRoot 'dist'

Push-Location $repoRoot

try {
    Ensure-Directory -Path (Join-Path $repoRoot 'build')

    Remove-DirectorySafe -Path (Join-Path $repoRoot 'build/joomla5')
    Remove-DirectorySafe -Path (Join-Path $repoRoot 'build/joomla6')
    Remove-DirectorySafe -Path $distRoot

    Ensure-Directory -Path $distRoot

    Write-Log -Level INFO -Message 'Cleaned previous build outputs.'

    if ($CleanOnly) {
        Write-Log -Level INFO -Message 'Clean-only mode completed.'
        exit 0
    }

    $plan = Get-BuildTargets -RepoRoot $repoRoot -ConfigPath $ConfigPath
    $doValidation = -not $SkipValidation

    foreach ($target in $plan.Targets) {
        Write-Log -Level INFO -Message "Starting build for target $($target.Key) (Joomla $($target.JoomlaMajor), package $($target.PackageVersion))"
        Build-Target -RepoRoot $repoRoot -DistRoot $distRoot -Metadata $plan.Metadata -Target $target -DoValidation $doValidation
    }

    Write-Log -Level INFO -Message 'Build complete for all targets.'
    Write-Log -Level INFO -Message 'Output files:'

    $zipFiles = Get-ChildItem -Path $distRoot -Filter '*.zip' | Sort-Object Name

    foreach ($file in $zipFiles) {
        Write-Host (" - {0} ({1:N1} KB)" -f $file.Name, ($file.Length / 1KB))
    }

    # Local checksums are informational only: zip entries carry build timestamps,
    # so the published checksums come from SHA256SUMS.txt written by the release CI.
    Write-Log -Level INFO -Message 'SHA256 Checksums (local build):'

    foreach ($file in $zipFiles) {
        $hash = (Get-FileHash -Path $file.FullName -Algorithm SHA256).Hash
        Write-Host ("{0,-20} {1}" -f $file.Name, $hash)
    }
}
finally {
    Pop-Location
}
