<#
.SYNOPSIS
  Blue-green deployment for UDOM Student Materials (XAMPP / Apache on Windows).

.DESCRIPTION
  Two complete copies of the site live side by side:
      <DeployRoot>\releases\blue    <DeployRoot>\releases\green
  Apache serves /blog from ONE of them (the "active" colour). A deploy:
      1. copies the new code into the IDLE colour (the live site is not touched),
      2. tests it: PHP syntax of every file, then real HTTP checks through a private preview URL
         (/blog-blue or /blog-green, this computer only),
      3. backs up the database,
      4. switches Apache to the new colour (graceful restart: requests in progress finish),
      5. watches the live site; if it fails, it switches back automatically.
  The previous colour stays untouched, so "rollback" is just switching back: a few seconds.

  Shared between both colours (never copied, never lost on a switch):
      <DeployRoot>\shared\uploads, <DeployRoot>\shared\logs, <DeployRoot>\shared\config\secrets.php

.EXAMPLE
  # One time (as Administrator), then restart Apache once:
  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 install
  # Every release (as Administrator):
  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 deploy
  # Something wrong after a release? Instant switch back:
  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 rollback
  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 status
#>
[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('install', 'deploy', 'switch', 'rollback', 'status')]
    [string]$Command = 'status',

    # Code to deploy (default: the project folder this script lives in)
    [string]$Source = '',
    [string]$DeployRoot = 'C:\xampp\deploy\blog',
    [string]$UrlPath = '/blog',
    [string]$BaseUrl = 'http://localhost',

    [string]$ApacheConf = 'C:\xampp\apache\conf\httpd.conf',
    [string]$ApacheBin = 'C:\xampp\apache\bin\httpd.exe',
    [string]$ServiceName = 'Apache2.4',
    [string]$PhpBin = 'C:\xampp\php\php.exe',
    [string]$MysqlDump = 'C:\xampp\mysql\bin\mysqldump.exe',
    [string]$DbName = 'udom_blog',
    [string]$DbUser = 'root',
    [string]$DbPassword = '',
    [int]$KeepDbBackups = 10,

    # Seconds the live site is watched after a switch (automatic rollback if it fails)
    [int]$WatchSeconds = 30,
    # Prepare and test the idle colour but do not switch to it
    [switch]$NoSwitch,
    [switch]$SkipDbBackup,
    # Only for testing the automatic rollback: skip the checks that run BEFORE the switch
    [switch]$SkipPreflight,
    # Custom command that makes Apache reload its configuration (tests / non-service setups).
    # Default: "httpd -k restart -n <ServiceName>" (needs Administrator).
    [string]$RestartCommand = ''
)

Set-StrictMode -Version 2
$ErrorActionPreference = 'Stop'
# $PSScriptRoot is not available inside param() defaults on Windows PowerShell 5.1
if (-not $Source) { $Source = Split-Path -Parent $PSScriptRoot }
$Source = (Resolve-Path -LiteralPath $Source).Path.TrimEnd('\')
$DeployRoot = $DeployRoot.TrimEnd('\')

$Colors = @('blue', 'green')
$ReleasesDir = Join-Path $DeployRoot 'releases'
$SharedDir = Join-Path $DeployRoot 'shared'
$BackupDir = Join-Path $DeployRoot 'backups'
$StateFile = Join-Path $DeployRoot 'state.json'
$LockFile = Join-Path $DeployRoot 'deploy.lock'
$SiteConf = Join-Path $DeployRoot 'apache-blog.conf'
$LogFile = Join-Path $DeployRoot 'deploy.log'
$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

# Pages that must work before (preview) and after (live) a switch
$SmokePages = @('health.php', 'index.php', 'materials.php', 'shared.php', 'faq.php', 'privacy.php',
    'account/login.php', 'account/register.php', 'account/forgot_password.php', 'sitemap.xml', 'robots.txt')

# ------------------------------------------------------------------ helpers

function Write-Step([string]$Text) { Write-Host ''; Write-Host "==> $Text" -ForegroundColor Cyan; Write-Log $Text }
function Write-Ok([string]$Text) { Write-Host "    OK   $Text" -ForegroundColor Green }
function Write-Bad([string]$Text) { Write-Host "    FAIL $Text" -ForegroundColor Red; Write-Log "FAIL $Text" }
function Write-Info([string]$Text) { Write-Host "    $Text" }
function Write-Log([string]$Text) {
    if (Test-Path $DeployRoot) {
        $line = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + "  [$Command] $Text"
        [IO.File]::AppendAllText($LogFile, $line + [Environment]::NewLine, $Utf8NoBom)
    }
}
function Fail([string]$Text) { Write-Bad $Text; throw "deploy.ps1: $Text" }

function Write-TextFile([string]$Path, [string]$Text) {
    # Write to a temp file, then rename over the target (readers never see a half-written file)
    $tmp = "$Path.tmp"
    [IO.File]::WriteAllText($tmp, $Text, $Utf8NoBom)
    Move-Item -LiteralPath $tmp -Destination $Path -Force
}

function Get-State {
    if (Test-Path $StateFile) { return (Get-Content -Raw -LiteralPath $StateFile | ConvertFrom-Json) }
    return [pscustomobject]@{ active = $null; previous = $null; history = @() }
}
function Save-State($State) { Write-TextFile $StateFile ($State | ConvertTo-Json -Depth 6) }
function Add-History($State, [string]$Action, [string]$From, [string]$To, [string]$Result) {
    $entry = [pscustomobject]@{ time = (Get-Date -Format 's'); action = $Action; from = $From; to = $To; result = $Result }
    $State.history = @(@($entry) + @($State.history) | Select-Object -First 50)
}

# Run a program and capture its output + exit code. Windows PowerShell 5.1 turns anything a program
# writes to stderr (httpd prints "Syntax OK" there) into an error, so stderr is captured as text.
function Invoke-Native([string]$Exe, [string[]]$Arguments) {
    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $out = & $Exe @Arguments 2>&1 | ForEach-Object { "$_" }
        return @{ Code = $LASTEXITCODE; Output = (($out | Out-String).Trim()) }
    } finally { $ErrorActionPreference = $old }
}

# Safe property read (StrictMode throws on missing properties)
function Get-Prop($Object, [string]$Name) {
    if ($null -eq $Object) { return $null }
    $p = $Object.PSObject.Properties[$Name]
    if ($p) { return $p.Value }
    return $null
}

function Get-OtherColor([string]$Color) { if ($Color -eq 'blue') { return 'green' } return 'blue' }
function Get-ReleaseDir([string]$Color) {
    if ($Colors -notcontains $Color) { Fail "Unknown colour '$Color'" }
    return (Join-Path $ReleasesDir $Color)
}
function Get-ReleaseInfo([string]$Color) {
    $file = Join-Path (Get-ReleaseDir $Color) 'RELEASE.json'
    if (Test-Path $file) { return (Get-Content -Raw -LiteralPath $file | ConvertFrom-Json) }
    return $null
}
function Convert-ToApachePath([string]$Path) { return ($Path -replace '\\', '/') }

function Test-IsAdmin {
    $principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

# GET a URL without following redirects. Returns @{ Status; Body } (Status 0 = no answer).
function Invoke-Http([string]$Url, [int]$TimeoutSec = 15) {
    try {
        $r = Invoke-WebRequest -Uri $Url -UseBasicParsing -MaximumRedirection 0 -TimeoutSec $TimeoutSec -ErrorAction Stop
        return @{ Status = [int]$r.StatusCode; Body = [string]$r.Content }
    } catch {
        $resp = $null
        if ($_.Exception.PSObject.Properties['Response']) { $resp = $_.Exception.Response }
        if ($null -ne $resp) {
            $body = ''
            try { $reader = New-Object IO.StreamReader($resp.GetResponseStream()); $body = $reader.ReadToEnd(); $reader.Close() } catch { }
            return @{ Status = [int]$resp.StatusCode; Body = $body }
        }
        return @{ Status = 0; Body = $_.Exception.Message }
    }
}

# Remove a junction (link only - NEVER the shared folder it points to)
function Remove-Junction([string]$Path) {
    if (Test-Path -LiteralPath $Path) {
        $item = Get-Item -LiteralPath $Path -Force
        if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
            [IO.Directory]::Delete($Path, $false)
        } else {
            Fail "$Path should be a junction to the shared folder but is a real folder. Remove it by hand after checking its contents."
        }
    }
}

function New-Junction([string]$Path, [string]$Target) {
    Remove-Junction $Path
    New-Item -ItemType Junction -Path $Path -Target $Target | Out-Null
}

# ------------------------------------------------------------------ settings (secrets.php)

# A release does not get a COPY of the settings: its config\secrets.php just loads the ONE shared
# file, so editing <DeployRoot>\shared\config\secrets.php takes effect at once in both colours.
function Write-SecretsLoader([string]$ReleaseDir) {
    $shared = Convert-ToApachePath (Join-Path $SharedDir 'config\secrets.php')
    $php = "<?php`r`n// Generated by deploy\deploy.ps1. The LIVE settings are in ONE file - edit that file, not this one:`r`n" +
        "//   $shared`r`nreturn require '$($shared -replace "'", "\'")';`r`n"
    Write-TextFile (Join-Path $ReleaseDir 'config\secrets.php') $php
}

# Warn when the development copy has settings the live site does not (names only, never values)
function Compare-Secrets {
    $devFile = Join-Path $Source 'config\secrets.php'
    $liveFile = Join-Path $SharedDir 'config\secrets.php'
    if (-not (Test-Path $devFile) -or -not (Test-Path $liveFile)) { return }
    $code = '$d = require $argv[1]; $l = require $argv[2]; echo implode(",", array_keys(array_diff_key($d, $l)));'
    $r = Invoke-Native $PhpBin @('-r', $code, $devFile, $liveFile)
    if ($r.Code -eq 0 -and $r.Output) {
        Write-Host "    NOTE settings in htdocs only, NOT used by the live site: $($r.Output)" -ForegroundColor Yellow
        Write-Host "         The live site reads: $liveFile" -ForegroundColor Yellow
    }
}

# ------------------------------------------------------------------ Apache configuration

# The generated Apache include. $Active = colour served at /blog (or $null: /blog stays as before)
function Get-SiteConfText([string]$Active) {
    $lines = @(
        '# Generated by deploy\deploy.ps1 - DO NOT EDIT BY HAND (it is rewritten on every switch).',
        "# Active colour: $(if ($Active) { $Active } else { 'none yet' })   Written: $(Get-Date -Format 's')",
        '',
        '# Private previews of both colours and the development copy (this computer only).',
        '# AuthMerging And keeps every rule of the site''s own .htaccess files (e.g. private uploads stay denied).'
    )
    foreach ($c in $Colors) {
        $lines += "Alias `"$UrlPath-$c`" `"$(Convert-ToApachePath (Get-ReleaseDir $c))`""
        $lines += "<Location `"$UrlPath-$c`">"
        $lines += '    AuthMerging And'
        $lines += '    Require local'
        $lines += '</Location>'
    }
    $lines += "Alias `"$UrlPath-dev`" `"$(Convert-ToApachePath $Source)`""
    $lines += "<Location `"$UrlPath-dev`">"
    $lines += '    AuthMerging And'
    $lines += '    Require local'
    $lines += '</Location>'
    if ($Active) {
        $lines += ''
        $lines += "# THE LIVE SITE: $UrlPath is served by the $Active release"
        $lines += "Alias `"$UrlPath`" `"$(Convert-ToApachePath (Get-ReleaseDir $Active))`""
    }
    $lines += ''
    $lines += "<Directory `"$(Convert-ToApachePath $ReleasesDir)`">"
    $lines += '    Options -Indexes +FollowSymLinks'
    $lines += '    AllowOverride All'
    $lines += '    Require all granted'
    $lines += '</Directory>'
    return (($lines -join "`r`n") + "`r`n")
}

function Test-ApacheConfig {
    $r = Invoke-Native $ApacheBin @('-t', '-f', $ApacheConf)
    return @{ Ok = ($r.Output -match 'Syntax OK'); Output = $r.Output }
}

function Restart-Apache {
    if ($RestartCommand) {
        & ([scriptblock]::Create($RestartCommand)) | Out-Null
    } else {
        $r = Invoke-Native $ApacheBin @('-k', 'restart', '-n', $ServiceName)
        if ($r.Output -match 'Access is denied|Failed to open') { Fail "Apache could not be restarted (run PowerShell as Administrator): $($r.Output)" }
    }
}

# Release colour reported by a health.php answer ('unmanaged' = a plain development copy)
function Get-HealthColor([string]$Body) {
    try { $rel = Get-Prop ($Body | ConvertFrom-Json) 'release' } catch { return $null }
    if ($rel) { return [string](Get-Prop $rel 'color') }
    return 'unmanaged'
}

# Which colour does $UrlPath answer with right now? ($null when the site is down)
function Get-LiveColor {
    $r = Invoke-Http "$BaseUrl$UrlPath/health.php" 5
    if ($r.Status -eq 200) { return (Get-HealthColor $r.Body) }
    return $null
}

# ------------------------------------------------------------------ checks

# Real HTTP checks of one colour through its preview URL (or the live URL)
function Test-Release([string]$Prefix, [string]$ExpectColor, [string]$ExpectVersion) {
    $problems = @()
    $h = Invoke-Http "$BaseUrl$Prefix/health.php"
    if ($h.Status -ne 200) {
        $problems += "health.php answered HTTP $($h.Status): $($h.Body -replace '\s+', ' ')"
    } else {
        $j = $null
        try { $j = $h.Body | ConvertFrom-Json } catch { $problems += 'health.php did not return JSON' }
        if ($j) {
            $rel = Get-Prop $j 'release'
            $color = [string](Get-Prop $rel 'color')
            $ver = [string](Get-Prop $rel 'version')
            if ((Get-Prop $j 'status') -ne 'ok') { $problems += "health.php status: $(Get-Prop $j 'status')" }
            if ($ExpectColor -and $color -ne $ExpectColor) { $problems += "health.php is served by '$color', expected '$ExpectColor'" }
            if ($ExpectVersion -and $ver -ne $ExpectVersion) { $problems += "release version '$ver', expected '$ExpectVersion'" }
        }
    }
    foreach ($page in $SmokePages) {
        if ($page -eq 'health.php') { continue }
        $r = Invoke-Http "$BaseUrl$Prefix/$page"
        if ($r.Status -ne 200) { $problems += "$page -> HTTP $($r.Status)"; continue }
        # Case-sensitive: PHP's own messages ("Warning:"), not words such as "notice boards"
        if ($r.Body -cmatch '(Fatal error|Parse error|Warning: |Notice: |Deprecated: |Uncaught [A-Z])') { $problems += "$page shows a PHP error: $($Matches[1])" }
        if ($page -like '*.php' -and $r.Body -notmatch '</html>') { $problems += "$page is cut off (no </html>)" }
    }
    $nf = Invoke-Http "$BaseUrl$Prefix/this-page-does-not-exist-$(Get-Random)"
    if ($nf.Status -ne 404) { $problems += "missing page answered HTTP $($nf.Status) instead of 404" }
    $secret = Invoke-Http "$BaseUrl$Prefix/config/secrets.php"
    if ($secret.Status -eq 200) { $problems += 'config/secrets.php is reachable from the web!' }
    return , $problems
}

function Test-PhpSyntax([string]$Dir) {
    $errors = @()
    $files = Get-ChildItem -LiteralPath $Dir -Recurse -Filter *.php -File |
        Where-Object { $_.FullName -notmatch '\\(uploads|logs)\\' }
    foreach ($f in $files) {
        $r = Invoke-Native $PhpBin @('-l', $f.FullName)
        if ($r.Code -ne 0) { $errors += (($r.Output -split "`r?`n" | Where-Object { $_ -match 'error' } | Select-Object -First 1)) }
    }
    return @{ Count = @($files).Count; Errors = $errors }
}

# ------------------------------------------------------------------ switching

function Backup-Database([string]$Label) {
    if ($SkipDbBackup) { Write-Info 'Database backup skipped (-SkipDbBackup)'; return }
    New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
    $file = Join-Path $BackupDir ("db_{0}_{1}.sql" -f (Get-Date -Format 'yyyyMMdd_HHmmss'), $Label)
    $dumpArgs = @('--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4', "--user=$DbUser")
    if ($DbPassword) { $dumpArgs += "--password=$DbPassword" }
    $dumpArgs += "--result-file=$file"
    $dumpArgs += $DbName
    $r = Invoke-Native $MysqlDump $dumpArgs
    if ($r.Code -ne 0 -or -not (Test-Path $file) -or (Get-Item $file).Length -lt 100) { Fail "Database backup failed (mysqldump exit $($r.Code))" }
    Write-Ok ("Database backup: {0} ({1:N0} KB)" -f $file, ((Get-Item $file).Length / 1KB))
    Get-ChildItem -LiteralPath $BackupDir -Filter 'db_*.sql' | Sort-Object LastWriteTime -Descending |
        Select-Object -Skip $KeepDbBackups | Remove-Item -Force
}

# Point $UrlPath at $Target and make Apache use it. Returns $true when the live site answers from $Target.
function Switch-To([string]$Target, [string]$Previous) {
    $oldText = $null
    if (Test-Path $SiteConf) { $oldText = [IO.File]::ReadAllText($SiteConf) }
    Write-TextFile $SiteConf (Get-SiteConfText $Target)
    $t = Test-ApacheConfig
    if (-not $t.Ok) {
        if ($null -ne $oldText) { Write-TextFile $SiteConf $oldText }
        Fail "Apache rejected the new configuration, nothing was switched:`n$($t.Output)"
    }
    $sw = [Diagnostics.Stopwatch]::StartNew()
    Restart-Apache
    for ($i = 0; $i -lt 30; $i++) {
        if ((Get-LiveColor) -eq $Target) {
            Write-Ok ("$UrlPath now served by $Target (switch took {0:N1} s)" -f $sw.Elapsed.TotalSeconds)
            return $true
        }
        Start-Sleep -Milliseconds 500
    }
    Write-Bad "$UrlPath did not start answering from $Target"
    return $false
}

# Watch the live site after a switch. Returns $true if it stayed healthy.
function Watch-Live([string]$Color, [string]$Version) {
    if ($WatchSeconds -le 0) { return $true }
    Write-Info "Watching the live site for $WatchSeconds s (automatic rollback on failure)..."
    $deadline = (Get-Date).AddSeconds($WatchSeconds)
    $failures = 0
    while ((Get-Date) -lt $deadline) {
        $problems = Test-Release $UrlPath $Color $Version
        if ($problems.Count -eq 0) { $failures = 0 } else {
            $failures++
            Write-Bad ("live check failed ({0}/2): {1}" -f $failures, ($problems -join '; '))
            if ($failures -ge 2) { return $false }
        }
        Start-Sleep -Seconds 3
    }
    Write-Ok 'Live site stayed healthy'
    return $true
}

function Invoke-Switch([string]$Target, [string]$Reason, [bool]$CheckFirst) {
    $state = Get-State
    $from = [string]$state.active
    if ($from -eq $Target) { Write-Ok "$Target is already live"; return }
    $info = Get-ReleaseInfo $Target
    if (-not $info) { Fail "There is no $Target release to switch to. Run: deploy.ps1 deploy" }

    if ($CheckFirst) {
        Write-Step "Checking $Target through its preview URL ($UrlPath-$Target)"
        $problems = Test-Release "$UrlPath-$Target" $Target $info.version
        if ($problems.Count -gt 0) { $problems | ForEach-Object { Write-Bad $_ }; Fail "$Target is not healthy, staying on $from" }
        Write-Ok "$Target ($($info.version)) passed all checks"
    }

    Write-Step "Switching $UrlPath from $(if ($from) { $from } else { 'the development copy' }) to $Target ($Reason)"
    if (-not (Switch-To $Target $from)) {
        if ($from) { Write-Info "Returning to $from..."; [void](Switch-To $from $Target) }
        else { Write-TextFile $SiteConf (Get-SiteConfText $null); Restart-Apache }
        Add-History $state $Reason $from $Target 'failed: Apache did not switch'
        Save-State $state
        Fail 'Switch failed; the previous version is still live'
    }

    if (-not (Watch-Live $Target $info.version)) {
        if ($from) {
            Write-Step "AUTOMATIC ROLLBACK to $from"
            if (Switch-To $from $Target) {
                Add-History $state $Reason $from $Target "rolled back automatically to $from"
                Save-State $state
                Fail "The new version failed on the live site; $from is live again"
            }
        }
        Add-History $state $Reason $from $Target 'unhealthy, rollback impossible'
        Save-State $state
        Fail 'The new version is unhealthy and there is no previous version to return to'
    }

    $state.previous = $from
    $state.active = $Target
    Add-History $state $Reason $from $Target 'ok'
    Save-State $state
    Write-Host ''
    Write-Host "LIVE: $Target  ($($info.version), commit $($info.commit))" -ForegroundColor Green
    if ($from) { Write-Host "Instant rollback available: deploy.ps1 rollback   (back to $from)" }
}

# ------------------------------------------------------------------ commands

function Invoke-Install {
    Write-Step "Installing blue-green layout in $DeployRoot"
    foreach ($d in @($ReleasesDir, (Get-ReleaseDir 'blue'), (Get-ReleaseDir 'green'), $SharedDir, (Join-Path $SharedDir 'config'), $BackupDir)) {
        New-Item -ItemType Directory -Force -Path $d | Out-Null
    }
    # Shared data: copied ONCE from the project (never deleted from the project)
    foreach ($name in @('uploads', 'logs')) {
        $dest = Join-Path $SharedDir $name
        if (-not (Test-Path $dest)) {
            robocopy (Join-Path $Source $name) $dest /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP | Out-Null
            if ($LASTEXITCODE -ge 8) { Fail "Copying $name failed (robocopy $LASTEXITCODE)" }
            Write-Ok "shared\$name created from the project"
        } else { Write-Ok "shared\$name already exists (kept)" }
    }
    $secrets = Join-Path $SharedDir 'config\secrets.php'
    if (-not (Test-Path $secrets)) {
        Copy-Item -LiteralPath (Join-Path $Source 'config\secrets.php') -Destination $secrets
        Write-Ok 'shared\config\secrets.php created'
    } else { Write-Ok 'shared\config\secrets.php already exists (kept)' }

    if (-not (Test-Path $SiteConf)) { Write-TextFile $SiteConf (Get-SiteConfText $null) }
    $include = "IncludeOptional `"$(Convert-ToApachePath $SiteConf)`""
    $conf = [IO.File]::ReadAllText($ApacheConf)
    if ($conf -notmatch [regex]::Escape($include)) {
        $backup = "$ApacheConf.before-bluegreen-$(Get-Date -Format 'yyyyMMdd_HHmmss')"
        Copy-Item -LiteralPath $ApacheConf -Destination $backup
        [IO.File]::WriteAllText($ApacheConf, $conf.TrimEnd() + "`r`n`r`n# Blue-green deployment of $UrlPath (deploy\deploy.ps1)`r`n$include`r`n", $Utf8NoBom)
        Write-Ok "httpd.conf now includes $SiteConf (backup: $backup)"
    } else { Write-Ok 'httpd.conf already includes the site configuration' }
    $t = Test-ApacheConfig
    if (-not $t.Ok) { Fail "Apache configuration test failed:`n$($t.Output)" }
    Write-Ok 'Apache configuration test: Syntax OK'
    Write-Host ''
    Write-Host 'Next: restart Apache ONCE (XAMPP Control Panel: Stop, Start), then run: deploy.ps1 deploy' -ForegroundColor Yellow
    Write-Host "Until the first deploy, $UrlPath is still served from $Source."
}

function Invoke-Deploy {
    $state = Get-State
    $active = [string]$state.active
    $target = if ($active) { Get-OtherColor $active } else { 'blue' }
    $dir = Get-ReleaseDir $target
    $version = Get-Date -Format 'yyyyMMdd-HHmmss'
    Write-Step "Deploying $Source -> $target (live now: $(if ($active) { $active } else { 'development copy' }); version $version)"

    if (-not (Test-Path (Join-Path $SharedDir 'uploads'))) { Fail 'Not installed. Run: deploy.ps1 install' }
    if (-not $NoSwitch -and -not $RestartCommand -and -not (Test-IsAdmin)) {
        Fail 'Switching restarts Apache, which needs Administrator rights. Run PowerShell as Administrator (or use -NoSwitch to only prepare and test).'
    }

    # 1. Copy the code into the idle colour (shared folders unlinked first so nothing can touch them)
    Write-Step "1/5 Copying code into $target"
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
    Remove-Junction (Join-Path $dir 'uploads')
    Remove-Junction (Join-Path $dir 'logs')
    robocopy $Source $dir /MIR /XJ /R:1 /W:1 /NFL /NDL /NJH /NJS /NP `
        /XD .git .github .vscode .idea .claude node_modules uploads logs deploy `
        /XF secrets.php RELEASE.json *.log | Out-Null
    if ($LASTEXITCODE -ge 8) { Fail "Copying the code failed (robocopy exit $LASTEXITCODE)" }
    New-Junction (Join-Path $dir 'uploads') (Join-Path $SharedDir 'uploads')
    New-Junction (Join-Path $dir 'logs') (Join-Path $SharedDir 'logs')
    Write-SecretsLoader $dir
    Compare-Secrets

    $commit = 'unknown'
    try {
        $g = Invoke-Native 'git' @('-C', $Source, 'rev-parse', '--short', 'HEAD')
        if ($g.Code -eq 0) {
            $commit = $g.Output
            if ((Invoke-Native 'git' @('-C', $Source, 'status', '--porcelain')).Output) { $commit += '+uncommitted' }
        }
    } catch { }
    $release = [ordered]@{ color = $target; version = $version; commit = $commit; source = $Source;
        deployed_at = (Get-Date -Format 's'); deployed_by = $env:USERNAME }
    Write-TextFile (Join-Path $dir 'RELEASE.json') ($release | ConvertTo-Json)
    Write-Ok "Code copied (commit $commit), uploads/logs linked to shared, settings loaded from shared\config\secrets.php"

    # 2. PHP syntax of every file
    Write-Step '2/5 PHP syntax check'
    if ($SkipPreflight) { Write-Info 'Skipped (-SkipPreflight)' } else {
        $syntax = Test-PhpSyntax $dir
        if ($syntax.Errors.Count -gt 0) { $syntax.Errors | ForEach-Object { Write-Bad $_ }; Fail "Syntax errors: $target was NOT switched on. The live site is unchanged." }
        Write-Ok "$($syntax.Count) PHP files OK"
    }

    # 3. Real HTTP checks through the private preview URL
    Write-Step "3/5 Testing $target through $UrlPath-$target"
    if ($SkipPreflight) { Write-Info 'Skipped (-SkipPreflight)' } else {
        Start-Sleep -Seconds 2 # let OPcache notice the new files
        $problems = Test-Release "$UrlPath-$target" $target $version
        if ($problems.Count -gt 0) { $problems | ForEach-Object { Write-Bad $_ }; Fail "Checks failed: $target was NOT switched on. The live site is unchanged." }
        Write-Ok "All $($SmokePages.Count + 2) checks passed"
    }

    if ($NoSwitch) {
        Write-Host ''
        Write-Host "$target is ready and tested at $BaseUrl$UrlPath-$target (not live). Switch with: deploy.ps1 switch" -ForegroundColor Yellow
        Add-History $state 'deploy (no switch)' $active $target "prepared $version"
        Save-State $state
        return
    }

    # 4. Database backup  5. Switch + watch (automatic rollback)
    Write-Step '4/5 Database backup'
    Backup-Database "before_$target"
    Write-Step '5/5 Switch'
    Invoke-Switch $target "deploy $version" $false
}

function Invoke-Status {
    $state = Get-State
    Write-Host ''
    Write-Host "Deploy root : $DeployRoot"
    Write-Host "Active      : $(if ($state.active) { $state.active } else { '(none - development copy is live)' })"
    Write-Host "Previous    : $(if ($state.previous) { $state.previous } else { '-' })"
    $live = Get-LiveColor
    Write-Host "Live answer : $UrlPath -> $(if ($live) { $live } else { 'NO ANSWER' })"
    foreach ($c in $Colors) {
        $info = Get-ReleaseInfo $c
        $h = Invoke-Http "$BaseUrl$UrlPath-$c/health.php" 5
        $health = if ($h.Status -eq 200) { 'healthy' } elseif ($h.Status -eq 0) { 'no answer' } else { "HTTP $($h.Status)" }
        if ($info) { Write-Host ("  {0,-6} {1}  commit {2}  deployed {3}  preview: {4}" -f $c, $info.version, $info.commit, $info.deployed_at, $health) }
        else { Write-Host ("  {0,-6} (empty)" -f $c) }
    }
    $hist = @($state.history)
    if ($hist.Count -gt 0) {
        Write-Host ''
        Write-Host 'Recent:'
        $hist | Select-Object -First 8 | ForEach-Object { Write-Host ("  {0}  {1,-28} {2} -> {3}  {4}" -f $_.time, $_.action, $_.from, $_.to, $_.result) }
    }
}

# ------------------------------------------------------------------ main

if ($Command -ne 'install' -and $Command -ne 'status' -and -not (Test-Path $DeployRoot)) {
    Write-Host 'Not installed. Run: deploy.ps1 install' -ForegroundColor Red
    exit 1
}
if ($Command -eq 'status') { Invoke-Status; exit 0 }

New-Item -ItemType Directory -Force -Path $DeployRoot | Out-Null
# One deploy at a time
$lock = $null
try {
    $lock = [IO.File]::Open($LockFile, 'OpenOrCreate', 'ReadWrite', 'None')
} catch { Fail 'Another deploy.ps1 is running (deploy.lock is in use)' }

$exitCode = 0
try {
    switch ($Command) {
        'install' { Invoke-Install }
        'deploy' { Invoke-Deploy }
        'switch' {
            $state = Get-State
            if (-not $state.active) { Fail 'Nothing is live yet. Run: deploy.ps1 deploy' }
            if (-not $RestartCommand -and -not (Test-IsAdmin)) { Fail 'Run PowerShell as Administrator (Apache must be restarted)' }
            Invoke-Switch (Get-OtherColor $state.active) 'manual switch' $true
        }
        'rollback' {
            $state = Get-State
            if (-not $state.previous) { Fail 'There is no previous release to roll back to' }
            if (-not $RestartCommand -and -not (Test-IsAdmin)) { Fail 'Run PowerShell as Administrator (Apache must be restarted)' }
            Invoke-Switch ([string]$state.previous) 'rollback' $true
        }
    }
} catch {
    Write-Host ''
    Write-Host $_.Exception.Message -ForegroundColor Red
    $exitCode = 1
} finally {
    if ($lock) { $lock.Close() }
}
exit $exitCode
