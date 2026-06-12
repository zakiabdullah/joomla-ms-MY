#!/usr/bin/env pwsh
[CmdletBinding()]
param(
    [string]$ConfigPath = 'build/build.config.json',
    [switch]$CleanOnly,
    [switch]$SkipValidation
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

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

function Escape-Xml {
    param([string]$Value)

    if ($null -eq $Value) {
        return ''
    }

    return [System.Security.SecurityElement]::Escape($Value)
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

function Get-MetadataFromPackageXml {
    param([Parameter(Mandatory = $true)][string]$PackageXmlPath)

    $xml = Read-Xml -Path $PackageXmlPath

    return [PSCustomObject]@{
        LanguageTag     = 'ms-MY'
        LanguageName    = [string]$xml.extension.name
        Author          = [string]$xml.extension.author
        AuthorEmail     = [string]$xml.extension.authorEmail
        AuthorUrl       = [string]$xml.extension.authorUrl
        Url             = [string]$xml.extension.url
        Packager        = [string]$xml.extension.packager
        PackagerUrl     = [string]$xml.extension.packagerurl
        Copyright       = [string]$xml.extension.copyright
        License         = [string]$xml.extension.license
        PackageBaseName = [string]$xml.extension.packagename
        BaseVersion     = [string]$xml.extension.version
        CreationDate    = (Get-Date -Format 'yyyy-MM-dd')
        MinimumPhp      = '8.1.0'
    }
}

function Get-BuildTargets {
    param(
        [Parameter(Mandatory = $true)][string]$RepoRoot,
        [Parameter(Mandatory = $true)][string]$PackageXmlPath,
        [Parameter(Mandatory = $true)][string]$ConfigPath
    )

    $metadata = Get-MetadataFromPackageXml -PackageXmlPath $PackageXmlPath
    $resolvedConfigPath = Join-Path $RepoRoot $ConfigPath

    if (Test-Path -Path $resolvedConfigPath) {
        Write-Log -Level INFO -Message "Using build config: $ConfigPath"
        $config = Get-Content -Path $resolvedConfigPath -Raw -Encoding UTF8 | ConvertFrom-Json

        if (-not $config.targets -or $config.targets.Count -eq 0) {
            throw "Config file does not define targets: $ConfigPath"
        }

        if ($config.languageTag) { $metadata.LanguageTag = [string]$config.languageTag }
        if ($config.languageName) { $metadata.LanguageName = [string]$config.languageName }
        if ($config.author) { $metadata.Author = [string]$config.author }
        if ($config.authorEmail) { $metadata.AuthorEmail = [string]$config.authorEmail }
        if ($config.authorUrl) { $metadata.AuthorUrl = [string]$config.authorUrl }
        if ($config.url) { $metadata.Url = [string]$config.url }
        if ($config.packager) { $metadata.Packager = [string]$config.packager }
        if ($config.packagerUrl) { $metadata.PackagerUrl = [string]$config.packagerUrl }
        if ($config.copyright) { $metadata.Copyright = [string]$config.copyright }
        if ($config.license) { $metadata.License = [string]$config.license }
        if ($config.packageBaseName) { $metadata.PackageBaseName = [string]$config.packageBaseName }
        if ($config.minimumPhp) { $metadata.MinimumPhp = [string]$config.minimumPhp }

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

    Write-Log -Level WARN -Message "Config file not found ($ConfigPath). Falling back to auto-detection from pkg_ms-MY.xml"

    $baseVersionParts = Get-VersionParts -Version $metadata.BaseVersion
    $languagePack = $baseVersionParts.Pack

    $j5Version = if ($baseVersionParts.Major -eq 5) {
        $metadata.BaseVersion
    }
    else {
        "5.4.0.$languagePack"
    }

    $j6Version = if ($baseVersionParts.Major -eq 6) {
        $metadata.BaseVersion
    }
    else {
        "6.0.0.$languagePack"
    }

    return [PSCustomObject]@{
        Metadata = $metadata
        Targets  = @(
            [PSCustomObject]@{ Key = 'j5'; JoomlaMajor = 5; PackageVersion = $j5Version },
            [PSCustomObject]@{ Key = 'j6'; JoomlaMajor = 6; PackageVersion = $j6Version }
        )
    }
}

function Get-RelativeUnixPath {
    param(
        [Parameter(Mandatory = $true)][string]$BasePath,
        [Parameter(Mandatory = $true)][string]$FullPath
    )

    $relative = [System.IO.Path]::GetRelativePath($BasePath, $FullPath)
    return $relative.Replace('\\', '/')
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
        Copy-Item -Path $file.FullName -Destination $targetFile -Force
    }
}

function New-LanguageInstallManifest {
    param(
        [Parameter(Mandatory = $true)][string]$OutputPath,
        [Parameter(Mandatory = $true)][string]$Client,
        [Parameter(Mandatory = $true)][string]$Tag,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string]$Version,
        [Parameter(Mandatory = $true)][string]$CreationDate,
        [Parameter(Mandatory = $true)][string]$Author,
        [Parameter(Mandatory = $true)][string]$AuthorEmail,
        [Parameter(Mandatory = $true)][string]$AuthorUrl,
        [Parameter(Mandatory = $true)][string]$Copyright,
        [Parameter(Mandatory = $true)][string]$License,
        [Parameter(Mandatory = $true)][string]$Description,
        [Parameter(Mandatory = $true)][string]$ContentFolder,
        [Parameter(Mandatory = $true)]$RelativeFiles
    )

    $manifest = @(
        '<?xml version="1.0" encoding="UTF-8"?>',
        "<extension client=`"$(Escape-Xml $Client)`" type=`"language`" method=`"upgrade`">",
        "`t<name>$(Escape-Xml $Name)</name>",
        "`t<tag>$(Escape-Xml $Tag)</tag>",
        "`t<version>$(Escape-Xml $Version)</version>",
        "`t<creationDate>$(Escape-Xml $CreationDate)</creationDate>",
        "`t<author>$(Escape-Xml $Author)</author>",
        "`t<authorEmail>$(Escape-Xml $AuthorEmail)</authorEmail>",
        "`t<authorUrl>$(Escape-Xml $AuthorUrl)</authorUrl>",
        "`t<copyright>$(Escape-Xml $Copyright)</copyright>",
        "`t<license>$(Escape-Xml $License)</license>",
        "`t<description>$(Escape-Xml $Description)</description>",
        "`t<files>",
        "`t`t<filename file=`"meta`">install.xml</filename>",
        "`t`t<filename file=`"meta`">langmetadata.xml</filename>",
        "`t`t<folder>$(Escape-Xml ($ContentFolder.Split('/')[0]))</folder>"
    )

    $manifest += @(
        "`t</files>",
        "`t<params />",
        '</extension>'
    )

    Set-Content -Path $OutputPath -Value ($manifest -join "`n") -Encoding UTF8
}

function New-LanguageMetadataManifest {
    param(
        [Parameter(Mandatory = $true)][string]$OutputPath,
        [Parameter(Mandatory = $true)][string]$Client,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string]$Version,
        [Parameter(Mandatory = $true)][string]$CreationDate,
        [Parameter(Mandatory = $true)][string]$Author,
        [Parameter(Mandatory = $true)][string]$AuthorEmail,
        [Parameter(Mandatory = $true)][string]$AuthorUrl,
        [Parameter(Mandatory = $true)][string]$Copyright,
        [Parameter(Mandatory = $true)][string]$License,
        [Parameter(Mandatory = $true)][string]$Tag
    )

    $manifest = @(
        '<?xml version="1.0" encoding="UTF-8"?>',
        "<metafile client=`"$(Escape-Xml $Client)`">",
        "`t<name>$(Escape-Xml $Name)</name>",
        "`t<version>$(Escape-Xml $Version)</version>",
        "`t<creationDate>$(Escape-Xml $CreationDate)</creationDate>",
        "`t<author>$(Escape-Xml $Author)</author>",
        "`t<authorEmail>$(Escape-Xml $AuthorEmail)</authorEmail>",
        "`t<authorUrl>$(Escape-Xml $AuthorUrl)</authorUrl>",
        "`t<copyright>$(Escape-Xml $Copyright)</copyright>",
        "`t<license>$(Escape-Xml $License)</license>",
        "`t<description><![CDATA[$Tag $Client language]]></description>",
        "`t<metadata>",
        "`t`t<name>Malay (Malaysia)</name>",
        "`t`t<nativeName>Bahasa Melayu (Malaysia)</nativeName>",
        "`t`t<tag>$(Escape-Xml $Tag)</tag>",
        "`t`t<rtl>0</rtl>",
        "`t`t<locale>ms_MY.utf8, ms_MY.UTF-8, ms_MY, msa_MY, ms, malay, bahasa-melayu, malaysia</locale>",
        "`t`t<firstDay>1</firstDay>",
        "`t`t<weekEnd>0,6</weekEnd>",
        "`t`t<calendar>gregorian</calendar>",
        "`t</metadata>",
        "`t<params />",
        '</metafile>'
    )

    Set-Content -Path $OutputPath -Value ($manifest -join "`n") -Encoding UTF8
}

function New-PackageManifest {
    param(
        [Parameter(Mandatory = $true)][string]$OutputPath,
        [Parameter(Mandatory = $true)]$Metadata,
        [Parameter(Mandatory = $true)][int]$JoomlaMajor,
        [Parameter(Mandatory = $true)][string]$PackageVersion,
        [Parameter(Mandatory = $true)]$SubPackageDescriptors
    )

    $fileEntries = @()

    foreach ($descriptor in $SubPackageDescriptors) {
        $fileEntries += "`t`t<file type=`"language`" client=`"$($descriptor.Client)`" id=`"$($Metadata.LanguageTag)`">$($descriptor.ZipName)</file>"
    }

    $description = @"
<![CDATA[
        <h2>Malay Language Pack ($(Escape-Xml $Metadata.LanguageTag)) for Joomla! $JoomlaMajor</h2>
        <h3>Installation / Update</h3>
        <p>The Malay language pack has been installed successfully. Enable it via <strong>System -> Manage -> Languages</strong>.</p>
        <h3>Installation Error</h3>
        <p>If installation fails, download the latest package from <a href="https://github.com/zakiabdullah/joomla-ms-MY/releases" target="_blank">GitHub Releases</a>.</p>
        <p>Report issues at <a href="https://github.com/zakiabdullah/joomla-ms-MY/issues" target="_blank">GitHub Issues</a>.</p>
]]>
"@

    $manifest = @(
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<extension type="package" method="upgrade">',
        "`t<name>$(Escape-Xml $Metadata.LanguageName)</name>",
        "`t<packagename>$(Escape-Xml $Metadata.PackageBaseName)</packagename>",
        "`t<version>$(Escape-Xml $PackageVersion)</version>",
        "`t<creationDate>$(Escape-Xml $Metadata.CreationDate)</creationDate>",
        "`t<author>$(Escape-Xml $Metadata.Author)</author>",
        "`t<authorEmail>$(Escape-Xml $Metadata.AuthorEmail)</authorEmail>",
        "`t<authorUrl>$(Escape-Xml $Metadata.AuthorUrl)</authorUrl>",
        "`t<url>$(Escape-Xml $Metadata.Url)</url>",
        "`t<packager>$(Escape-Xml $Metadata.Packager)</packager>",
        "`t<packagerurl>$(Escape-Xml $Metadata.PackagerUrl)</packagerurl>",
        "`t<copyright>$(Escape-Xml $Metadata.Copyright)</copyright>",
        "`t<license>$(Escape-Xml $Metadata.License)</license>",
        "`t<description>$description</description>",
        "`t<scriptfile>script.php</scriptfile>",
        "`t<blockChildUninstall>true</blockChildUninstall>",
        "`t<files>"
    )

    $manifest += $fileEntries
    $manifest += @(
        "`t</files>",
        "`t<updateservers>",
        "`t`t<server type=`"collection`" name=`"Joomla! Update Directory`">https://update.joomla.org/language/translationlist_$JoomlaMajor.xml</server>",
        "`t</updateservers>",
        '</extension>'
    )

    Set-Content -Path $OutputPath -Value ($manifest -join "`n") -Encoding UTF8
}

function Set-ScriptMinimumJoomla {
    param(
        [Parameter(Mandatory = $true)][string]$SourcePath,
        [Parameter(Mandatory = $true)][string]$DestinationPath,
        [Parameter(Mandatory = $true)][int]$JoomlaMajor
    )

    $content = Get-Content -Path $SourcePath -Raw -Encoding UTF8

    $minimumPattern = '\$this->minimumJoomla\s*=\s*''[^'']+'';'

    if ($content -match $minimumPattern) {
        $replacement = '$this->minimumJoomla = ''' + $JoomlaMajor + '.0'';'
        $content = [regex]::Replace($content, $minimumPattern, $replacement)
    }

    Set-Content -Path $DestinationPath -Value $content -Encoding UTF8
}

function Compress-FolderContent {
    param(
        [Parameter(Mandatory = $true)][string]$SourceFolder,
        [Parameter(Mandatory = $true)][string]$ZipPath
    )

    if (Test-Path -Path $ZipPath) {
        Remove-Item -Path $ZipPath -Force
    }

    $paths = Get-ChildItem -Path $SourceFolder -Force | Select-Object -ExpandProperty FullName

    if (-not $paths -or $paths.Count -eq 0) {
        throw "Cannot create zip from empty folder: $SourceFolder"
    }

    Compress-Archive -Path $paths -DestinationPath $ZipPath -Force
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
            Description = 'Malay (ms-MY) - Site'
        },
        [PSCustomObject]@{
            Name        = 'admin'
            Client      = 'administrator'
            SourceRoot  = Join-Path $RepoRoot 'administrator/language/ms-MY'
            ContentRoot = 'administrator/language/ms-MY'
            ZipName     = 'admin_ms-MY.zip'
            Description = 'Malay (ms-MY) - Administrator'
        },
        [PSCustomObject]@{
            Name        = 'api'
            Client      = 'api'
            SourceRoot  = Join-Path $RepoRoot 'api/language/ms-MY'
            ContentRoot = 'api/language/ms-MY'
            ZipName     = 'api_ms-MY.zip'
            Description = 'Malay (ms-MY) - API'
        },
        [PSCustomObject]@{
            Name        = 'installation'
            Client      = 'installation'
            SourceRoot  = Join-Path $RepoRoot 'installation/language/ms-MY'
            ContentRoot = 'installation/language/ms-MY'
            ZipName     = 'installation_ms-MY.zip'
            Description = 'Malay (ms-MY) - Installation'
        }
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
        $subContentRoot = Join-Path $subBuildRoot ($sub.ContentRoot -replace '/', [System.IO.Path]::DirectorySeparatorChar)

        Ensure-Directory -Path $subBuildRoot
        Ensure-Directory -Path $subContentRoot

        Copy-FilesToContentRoot -Files $sourceFiles -DestinationContentRoot $subContentRoot

        $installXmlPath = Join-Path $subBuildRoot 'install.xml'
        $metaXmlPath = Join-Path $subBuildRoot 'langmetadata.xml'

        New-LanguageInstallManifest `
            -OutputPath $installXmlPath `
            -Client $sub.Client `
            -Tag $Metadata.LanguageTag `
            -Name "Malay ($($Metadata.LanguageTag))" `
            -Version $Target.PackageVersion `
            -CreationDate $Metadata.CreationDate `
            -Author $Metadata.Author `
            -AuthorEmail $Metadata.AuthorEmail `
            -AuthorUrl $Metadata.AuthorUrl `
            -Copyright $Metadata.Copyright `
            -License $Metadata.License `
            -Description $sub.Description `
            -ContentFolder $sub.ContentRoot `
            -RelativeFiles ($sourceFiles.Relative)

        New-LanguageMetadataManifest `
            -OutputPath $metaXmlPath `
            -Client $sub.Client `
            -Name "Malay ($($Metadata.LanguageTag))" `
            -Version $Target.PackageVersion `
            -CreationDate $Metadata.CreationDate `
            -Author $Metadata.Author `
            -AuthorEmail $Metadata.AuthorEmail `
            -AuthorUrl $Metadata.AuthorUrl `
            -Copyright $Metadata.Copyright `
            -License $Metadata.License `
            -Tag $Metadata.LanguageTag

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

    New-PackageManifest `
        -OutputPath $packageManifestPath `
        -Metadata $Metadata `
        -JoomlaMajor $Target.JoomlaMajor `
        -PackageVersion $Target.PackageVersion `
        -SubPackageDescriptors $builtSubPackages

    Set-ScriptMinimumJoomla `
        -SourcePath (Join-Path $RepoRoot 'script.php') `
        -DestinationPath (Join-Path $pkgRoot 'script.php') `
        -JoomlaMajor $Target.JoomlaMajor

    if ($DoValidation) {
        Validate-XmlFile -Path $packageManifestPath
    }

    $finalZipName = "pkg_ms-MY_$($Target.Key).zip"
    $finalZipPath = Join-Path $DistRoot $finalZipName

    Compress-FolderContent -SourceFolder $pkgRoot -ZipPath $finalZipPath

    if ($DoValidation -and (Get-Item -Path $finalZipPath).Length -le 0) {
        throw "Invalid final package zip (empty): $finalZipPath"
    }

    Write-Log -Level INFO -Message "Built final package: dist/$finalZipName"
}

$repoRoot = Get-RepoRoot
$pkgXmlPath = Join-Path $repoRoot 'pkg_ms-MY.xml'
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

    $plan = Get-BuildTargets -RepoRoot $repoRoot -PackageXmlPath $pkgXmlPath -ConfigPath $ConfigPath
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

    Write-Log -Level INFO -Message 'SHA256 Checksums:'
    
    $notesPath = Join-Path $repoRoot 'RELEASE_NOTES_DRAFT.md'
    $updateNotes = Test-Path -Path $notesPath
    if ($updateNotes) {
        $notesContent = Get-Content -Path $notesPath -Raw -Encoding UTF8
    }

    foreach ($file in $zipFiles) {
        $hash = (Get-FileHash -Path $file.FullName -Algorithm SHA256).Hash
        Write-Host ("{0,-20} {1}" -f $file.Name, $hash)

        if ($updateNotes) {
            # Gunakan Regex untuk menggantikan nilai di baris SHA256 bagi setiap fail
            $pattern = "(?i)(###\s+$([regex]::Escape($file.Name))\s*\r?\n-\s*SHA256:\s*)[^\r\n]*"
            $notesContent = [regex]::Replace($notesContent, $pattern, "`${1}$hash")
        }
    }

    if ($updateNotes) {
        Set-Content -Path $notesPath -Value $notesContent -Encoding UTF8
        Write-Log -Level INFO -Message 'Updated checksums automatically in RELEASE_NOTES_DRAFT.md'
    }
}
finally {
    Pop-Location
}