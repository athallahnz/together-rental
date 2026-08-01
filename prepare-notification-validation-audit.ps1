$ErrorActionPreference = "Stop"

# ============================================================
# TOGETHER KAMERA
# Notification + Form Validation Full Audit Package
# Jalankan dari root project yang memiliki artisan dan package.json
# ============================================================

$ProjectRoot = (Get-Location).Path
$Timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$PackageName = "Together-Kamera-Notification-Validation-Audit-$Timestamp"
$StagingRoot = Join-Path $env:TEMP $PackageName
$ZipPath = Join-Path $ProjectRoot "$PackageName.zip"

# ------------------------------------------------------------
# Validasi lokasi eksekusi
# ------------------------------------------------------------

$RequiredRootFiles = @(
    "artisan",
    "composer.json",
    "package.json"
)

foreach ($RequiredFile in $RequiredRootFiles) {
    $RequiredPath = Join-Path $ProjectRoot $RequiredFile

    if (-not (Test-Path $RequiredPath)) {
        throw "File '$RequiredFile' tidak ditemukan. Jalankan script dari root project together-rental."
    }
}

# Bersihkan staging lama jika ada
if (Test-Path $StagingRoot) {
    Remove-Item $StagingRoot -Recurse -Force
}

if (Test-Path $ZipPath) {
    Remove-Item $ZipPath -Force
}

New-Item -ItemType Directory -Path $StagingRoot -Force | Out-Null

# ------------------------------------------------------------
# Helper copy yang mempertahankan struktur folder
# ------------------------------------------------------------

function Copy-AuditPath {
    param(
        [Parameter(Mandatory = $true)]
        [string] $RelativePath
    )

    $SourcePath = Join-Path $ProjectRoot $RelativePath
    $DestinationPath = Join-Path $StagingRoot $RelativePath

    if (-not (Test-Path $SourcePath)) {
        Write-Host "SKIP  $RelativePath" -ForegroundColor DarkYellow

        return
    }

    $SourceItem = Get-Item $SourcePath

    if ($SourceItem.PSIsContainer) {
        New-Item `
            -ItemType Directory `
            -Path $DestinationPath `
            -Force | Out-Null

        Get-ChildItem $SourcePath -Force | ForEach-Object {
            Copy-Item `
                -Path $_.FullName `
                -Destination $DestinationPath `
                -Recurse `
                -Force
        }
    } else {
        $DestinationDirectory = Split-Path $DestinationPath -Parent

        New-Item `
            -ItemType Directory `
            -Path $DestinationDirectory `
            -Force | Out-Null

        Copy-Item `
            -Path $SourcePath `
            -Destination $DestinationPath `
            -Force
    }

    Write-Host "COPY  $RelativePath" -ForegroundColor Green
}

# ------------------------------------------------------------
# Folder aplikasi
# ------------------------------------------------------------
#
# app/
# Mencakup:
# - seluruh controller semua modul
# - FormRequest dan validasi backend
# - Domain service dan business exceptions
# - Models, enums, policies, providers, middleware
# - notification/mail/event/listener bila tersedia
#

$ApplicationPaths = @(
    "app",
    "bootstrap",
    "config",

    "resources\js",
    "resources\views",
    "resources\css",

    "routes",

    "database\migrations",
    "database\factories",
    "database\seeders",

    "tests",

    "lang",
    "resources\lang",

    ".github\workflows"
)

foreach ($Path in $ApplicationPaths) {
    Copy-AuditPath $Path
}

# ------------------------------------------------------------
# File konfigurasi dan dependency
# ------------------------------------------------------------

$RootFiles = @(
    "artisan",

    "composer.json",
    "composer.lock",

    "package.json",
    "package-lock.json",
    "pnpm-lock.yaml",
    "yarn.lock",

    "tsconfig.json",
    "tsconfig.app.json",
    "tsconfig.node.json",

    "vite.config.ts",
    "vite.config.js",

    "eslint.config.js",
    "eslint.config.mjs",
    "eslint.config.ts",

    "components.json",

    "phpunit.xml",
    "phpunit.xml.dist",

    "phpstan.neon",
    "phpstan.neon.dist",

    "pint.json",

    ".editorconfig",
    ".prettierrc",
    ".prettierrc.json",
    ".prettierignore",

    ".env.example",

    "README.md"
)

foreach ($File in $RootFiles) {
    Copy-AuditPath $File
}

# ------------------------------------------------------------
# Hapus file yang tidak diperlukan atau berisiko
# ------------------------------------------------------------

$ExcludedDirectories = @(
    "node_modules",
    "vendor",
    ".git",
    ".idea",
    ".vscode",
    "public\build",
    "storage",
    "bootstrap\cache"
)

foreach ($ExcludedDirectory in $ExcludedDirectories) {
    $Target = Join-Path $StagingRoot $ExcludedDirectory

    if (Test-Path $Target) {
        Remove-Item $Target -Recurse -Force
    }
}

$SensitiveFiles = @(
    ".env",
    "auth.json",
    "database.sqlite"
)

foreach ($SensitiveFile in $SensitiveFiles) {
    Get-ChildItem `
        -Path $StagingRoot `
        -Filter $SensitiveFile `
        -Recurse `
        -Force `
        -ErrorAction SilentlyContinue |
        Remove-Item -Force
}

# Hapus log, cache, dan hasil test lokal jika ikut tersalin
$GeneratedPatterns = @(
    "*.log",
    ".phpunit.result.cache",
    ".phpunit.cache",
    "*.sqlite",
    "*.sqlite-journal"
)

foreach ($Pattern in $GeneratedPatterns) {
    Get-ChildItem `
        -Path $StagingRoot `
        -Filter $Pattern `
        -Recurse `
        -Force `
        -ErrorAction SilentlyContinue |
        Remove-Item -Force
}

# ------------------------------------------------------------
# Metadata Git
# ------------------------------------------------------------

function Get-SafeCommandOutput {
    param(
        [Parameter(Mandatory = $true)]
        [scriptblock] $Command
    )

    try {
        return (& $Command 2>&1 | Out-String).Trim()
    } catch {
        return "Tidak tersedia: $($_.Exception.Message)"
    }
}

$GitBranch = Get-SafeCommandOutput {
    git branch --show-current
}

$GitCommit = Get-SafeCommandOutput {
    git rev-parse HEAD
}

$GitLog = Get-SafeCommandOutput {
    git log -5 --oneline
}

$GitStatus = Get-SafeCommandOutput {
    git status --short
}

$PhpVersion = Get-SafeCommandOutput {
    herd php --version
}

$NodeVersion = Get-SafeCommandOutput {
    node --version
}

$NpmVersion = Get-SafeCommandOutput {
    npm --version
}

$MetadataContent = @"
# Together Kamera — Notification & Validation Audit

Generated at : $(Get-Date -Format "yyyy-MM-dd HH:mm:ss zzz")
Project path : $ProjectRoot
Git branch   : $GitBranch
Git commit   : $GitCommit

## Objective

Audit dan standardisasi untuk seluruh modul:

- Global success/error/warning/info notification
- Session flash Laravel dan Inertia
- Validation error backend
- Client-side form validation
- Business-rule validation
- Confirmation dialog untuk aksi kritis
- Loading dan disabled state
- Pencegahan double submit
- Error mapping per field
- Global exception handling
- Konsistensi redaksi pesan
- Accessibility form dan feedback
- Automated validation tests

## Environment

PHP:
$PhpVersion

Node:
$NodeVersion

NPM:
$NpmVersion

## Last Five Commits

$GitLog

## Working Tree

$GitStatus

## Security Exclusions

Paket ini tidak menyertakan:

- .env
- vendor
- node_modules
- .git
- storage
- public/build
- database SQLite lokal
- log dan cache
"@

Set-Content `
    -Path (Join-Path $StagingRoot "_AUDIT_METADATA.md") `
    -Value $MetadataContent `
    -Encoding UTF8

# ------------------------------------------------------------
# Daftar route Laravel
# ------------------------------------------------------------

try {
    Push-Location $ProjectRoot

    herd php artisan route:list |
        Out-File `
            -FilePath (Join-Path $StagingRoot "_ROUTE_LIST.txt") `
            -Encoding UTF8

    Pop-Location
} catch {
    if ((Get-Location).Path -ne $ProjectRoot) {
        Pop-Location
    }

    "Route list gagal dibuat: $($_.Exception.Message)" |
        Set-Content `
            -Path (Join-Path $StagingRoot "_ROUTE_LIST.txt") `
            -Encoding UTF8
}

# ------------------------------------------------------------
# Daftar file paket
# ------------------------------------------------------------

$AuditFileList = Get-ChildItem `
    -Path $StagingRoot `
    -File `
    -Recurse |
    ForEach-Object {
        $_.FullName.Substring($StagingRoot.Length + 1)
    } |
    Sort-Object

$AuditFileList |
    Set-Content `
        -Path (Join-Path $StagingRoot "_AUDIT_FILE_LIST.txt") `
        -Encoding UTF8

$FileCount = $AuditFileList.Count

$TotalBytes = (
    Get-ChildItem `
        -Path $StagingRoot `
        -File `
        -Recurse |
    Measure-Object `
        -Property Length `
        -Sum
).Sum

$TotalSizeMB = [math]::Round($TotalBytes / 1MB, 2)

# ------------------------------------------------------------
# Buat ZIP
# ------------------------------------------------------------

Write-Host ""
Write-Host "Membuat ZIP..." -ForegroundColor Cyan

Compress-Archive `
    -Path (Join-Path $StagingRoot "*") `
    -DestinationPath $ZipPath `
    -CompressionLevel Optimal `
    -Force

# Verifikasi ZIP
if (-not (Test-Path $ZipPath)) {
    throw "ZIP gagal dibuat."
}

$ZipSizeMB = [math]::Round(
    (Get-Item $ZipPath).Length / 1MB,
    2
)

# Bersihkan staging
Remove-Item $StagingRoot -Recurse -Force

Write-Host ""
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "AUDIT PACKAGE BERHASIL DIBUAT" -ForegroundColor Green
Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "File       : $ZipPath"
Write-Host "Total file : $FileCount"
Write-Host "Source size: $TotalSizeMB MB"
Write-Host "ZIP size   : $ZipSizeMB MB"
Write-Host ""
Write-Host "Paket tidak menyertakan .env, vendor, node_modules, storage, dan .git." -ForegroundColor Yellow