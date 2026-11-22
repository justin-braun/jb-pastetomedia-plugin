<#
.SYNOPSIS
  Release JB Paste to Media: bump version, finalize changelog, commit, tag,
  build the WordPress-installable zip, push and create a GitHub Release.

.EXAMPLE
  ./bin/release.ps1 -Version 0.1.0           # full release
  ./bin/release.ps1 -Version 0.1.0 -DryRun   # only build dist/ zip from HEAD, change nothing
#>
param(
    [Parameter(Mandatory = $true)][string]$Version,
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'
$Slug = 'jb-paste-to-media'
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

if ($Version -notmatch '^\d+\.\d+\.\d+$') { throw "Version must be MAJOR.MINOR.PATCH (got '$Version')." }
$Tag = "v$Version"
$Zip = "dist/$Slug-$Version.zip"
New-Item -ItemType Directory -Force dist | Out-Null

function Build-Zip([string]$Ref) {
    if (Test-Path $Zip) { Remove-Item $Zip -Force }
    # git archive writes forward-slash paths under one top-level folder, as WordPress expects.
    git archive --format=zip --prefix="$Slug/" -o $Zip $Ref
    if ($LASTEXITCODE -ne 0) { throw 'git archive failed.' }
    Write-Host "Built $Zip"
}

if ($DryRun) {
    Build-Zip 'HEAD'
    Write-Host 'Dry run: no files changed, nothing committed, pushed or released.'
    exit 0
}

if (git status --porcelain) { throw 'Working tree is not clean. Commit or stash changes first.' }
if (git tag --list $Tag) { throw "Tag $Tag already exists." }

$Utf8 = New-Object System.Text.UTF8Encoding($false)
function Read-Text($p) { [IO.File]::ReadAllText((Join-Path $Root $p)) }
function Write-Text($p, $t) { [IO.File]::WriteAllText((Join-Path $Root $p), $t, $Utf8) }

# Changelog: the Unreleased section becomes this version.
$changelog = Read-Text 'CHANGELOG.md'
$m = [regex]::Match($changelog, '(?s)## \[Unreleased\]\s*(.*?)(?=\n## \[|\z)')
$notes = $m.Groups[1].Value.Trim()
if (-not $m.Success -or -not $notes) { throw 'CHANGELOG.md has no entries under ## [Unreleased].' }
$date = Get-Date -Format 'yyyy-MM-dd'
$changelog = $changelog.Remove($m.Index, $m.Length).Insert($m.Index, "## [Unreleased]`n`n## [$Version] - $date`n`n$notes`n")
Write-Text 'CHANGELOG.md' $changelog

# Version strings.
$main = Read-Text "$Slug.php"
$main = $main -replace '(\* Version:\s+)\S+', "`${1}$Version"
$main = $main -replace "(define\( 'JBPTM_VERSION', ')[^']+", "`${1}$Version"
Write-Text "$Slug.php" $main

$readme = Read-Text 'readme.txt'
$readme = $readme -replace '(Stable tag:\s+)\S+', "`${1}$Version"
$readme = $readme -replace '= Unreleased =', "= $Version ="
Write-Text 'readme.txt' $readme

git add -A
git commit -m "Release $Version"
git tag -a $Tag -m "Release $Version"
if ($LASTEXITCODE -ne 0) { throw 'Tagging failed.' }

Build-Zip $Tag

git push origin HEAD --follow-tags
if ($LASTEXITCODE -ne 0) { throw 'Push failed.' }

$notesFile = Join-Path ([IO.Path]::GetTempPath()) "$Slug-$Version-notes.md"
[IO.File]::WriteAllText($notesFile, $notes, $Utf8)
gh release create $Tag $Zip --title "$Tag" --notes-file $notesFile
if ($LASTEXITCODE -ne 0) { throw 'gh release create failed.' }
Remove-Item $notesFile -Force
Write-Host "Released $Tag"
