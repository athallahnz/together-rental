[CmdletBinding()]
param(
    [Parameter(Mandatory = $false)]
    [string] $ProjectRoot = (Get-Location).Path,

    [Parameter(Mandatory = $false)]
    [string] $EvidenceParent = '',

    [Parameter(Mandatory = $false)]
    [string] $TestPath = '',

    [Parameter(Mandatory = $false)]
    [ValidateSet('on', 'off')]
    [string] $TraceMode = 'on'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

$resolvedRoot = (Resolve-Path -LiteralPath $ProjectRoot).Path

if (-not (Test-Path -LiteralPath (Join-Path $resolvedRoot 'artisan') -PathType Leaf)) {
    throw "ProjectRoot bukan root Laravel Together Kamera: $resolvedRoot"
}

if (-not (Test-Path -LiteralPath (Join-Path $resolvedRoot '.env.e2e') -PathType Leaf)) {
    throw 'File .env.e2e tidak ditemukan. Konfigurasikan environment E2E terlebih dahulu.'
}

$runId = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
$startedAt = Get-Date
$evidenceParent = if ([string]::IsNullOrWhiteSpace($EvidenceParent)) {
    Join-Path $resolvedRoot 'storage\framework\testing\e2e-evidence'
}
elseif ([System.IO.Path]::IsPathRooted($EvidenceParent)) {
    [System.IO.Path]::GetFullPath($EvidenceParent)
}
else {
    [System.IO.Path]::GetFullPath((Join-Path $resolvedRoot $EvidenceParent))
}

New-Item -ItemType Directory -Path $evidenceParent -Force | Out-Null

$evidenceDrive = Get-PSDrive -PSProvider FileSystem |
    Where-Object {
        $evidenceParent.StartsWith(
            $_.Root,
            [System.StringComparison]::OrdinalIgnoreCase
        )
    } |
    Sort-Object { $_.Root.Length } -Descending |
    Select-Object -First 1

if ($null -eq $evidenceDrive) {
    throw "Drive evidence tidak dapat ditemukan untuk path: $evidenceParent"
}

$minimumFreeBytes = if ($TraceMode -eq 'on') { 5GB } else { 1GB }
if ($evidenceDrive.Free -lt $minimumFreeBytes) {
    $freeGB = [math]::Round($evidenceDrive.Free / 1GB, 2)
    $minimumFreeGB = [math]::Round($minimumFreeBytes / 1GB, 0)
    throw "Evidence dengan trace $TraceMode membutuhkan minimal $minimumFreeGB GB ruang kosong. Drive $($evidenceDrive.Name) hanya memiliki $freeGB GB."
}

$freeBeforeGB = [math]::Round($evidenceDrive.Free / 1GB, 2)
$evidenceRoot = Join-Path $evidenceParent $runId
$consoleLog = Join-Path $evidenceRoot 'console.log'
$summaryPath = Join-Path $evidenceRoot 'run-summary.txt'
$archivePath = "$evidenceRoot.zip"

New-Item -ItemType Directory -Path $evidenceRoot -Force | Out-Null

$previousLocation = (Get-Location).Path
$previousEvidenceMode = [Environment]::GetEnvironmentVariable('E2E_EVIDENCE', 'Process')
$previousEvidenceDir = [Environment]::GetEnvironmentVariable('E2E_EVIDENCE_DIR', 'Process')
$previousEvidenceTraceMode = [Environment]::GetEnvironmentVariable('E2E_EVIDENCE_TRACE_MODE', 'Process')
$transcriptStarted = $false
$prepareExit = -1
$playwrightExit = -1
$finalExit = 0
$failureMessage = ''
$gitHead = ''

try {
    Set-Location -LiteralPath $resolvedRoot
    Start-Transcript -LiteralPath $consoleLog -Force | Out-Null
    $transcriptStarted = $true

    $gitHead = (& git rev-parse HEAD).Trim()
    if ($LASTEXITCODE -ne 0) {
        throw 'Tidak dapat membaca Git HEAD untuk metadata evidence.'
    }

    [Environment]::SetEnvironmentVariable('E2E_EVIDENCE', 'true', 'Process')
    [Environment]::SetEnvironmentVariable('E2E_EVIDENCE_DIR', $evidenceRoot, 'Process')
    [Environment]::SetEnvironmentVariable('E2E_EVIDENCE_TRACE_MODE', $TraceMode, 'Process')

    Write-Host ''
    Write-Host 'Preparing dedicated E2E database...' -ForegroundColor Cyan
    & npm.cmd run e2e:prepare
    $prepareExit = $LASTEXITCODE

    if ($prepareExit -ne 0) {
        $finalExit = $prepareExit
        $failureMessage = 'E2E database preparation failed.'
    }
    else {
        Write-Host ''
        Write-Host 'Running Playwright in evidence mode...' -ForegroundColor Cyan
        $playwrightArguments = @('playwright', 'test')
        if (-not [string]::IsNullOrWhiteSpace($TestPath)) {
            $playwrightArguments += $TestPath
        }
        & npx.cmd @playwrightArguments
        $playwrightExit = $LASTEXITCODE

        if ($playwrightExit -ne 0) {
            $finalExit = $playwrightExit
            $failureMessage = 'Playwright evidence run failed.'
        }
    }
}
catch {
    $finalExit = 1
    $failureMessage = $_.Exception.Message
    Write-Host $failureMessage -ForegroundColor Red
}
finally {
    $completedAt = Get-Date
    $status = if ($finalExit -eq 0) { 'PASS' } else { 'FAIL' }
    $summary = @(
        "Run ID: $runId"
        "Status: $status"
        "Git HEAD: $gitHead"
        "Evidence parent: $evidenceParent"
        "Free space before run: $freeBeforeGB GB"
        "Test scope: $(if ([string]::IsNullOrWhiteSpace($TestPath)) { 'full suite' } else { $TestPath })"
        "Trace mode: $TraceMode"
        "Started at: $($startedAt.ToString('o'))"
        "Completed at: $($completedAt.ToString('o'))"
        "E2E prepare exit: $prepareExit"
        "Playwright exit: $playwrightExit"
        "Failure: $failureMessage"
    )

    [System.IO.File]::WriteAllLines(
        $summaryPath,
        $summary,
        (New-Object System.Text.UTF8Encoding($false))
    )

    if ($transcriptStarted) {
        Stop-Transcript | Out-Null
    }

    [Environment]::SetEnvironmentVariable('E2E_EVIDENCE', $previousEvidenceMode, 'Process')
    [Environment]::SetEnvironmentVariable('E2E_EVIDENCE_DIR', $previousEvidenceDir, 'Process')
    [Environment]::SetEnvironmentVariable('E2E_EVIDENCE_TRACE_MODE', $previousEvidenceTraceMode, 'Process')
    Set-Location -LiteralPath $previousLocation
}

try {
    Compress-Archive -Path (Join-Path $evidenceRoot '*') -DestinationPath $archivePath -CompressionLevel Optimal -Force
}
catch {
    $finalExit = 1
    Write-Host "Evidence tersedia sebagai folder, tetapi ZIP gagal dibuat: $($_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host ''
Write-Host "Evidence folder : $evidenceRoot" -ForegroundColor Green
if (Test-Path -LiteralPath $archivePath -PathType Leaf) {
    Write-Host "Evidence ZIP    : $archivePath" -ForegroundColor Green
}
Write-Host "Summary         : $summaryPath"
Write-Host "Console log     : $consoleLog"

exit $finalExit
