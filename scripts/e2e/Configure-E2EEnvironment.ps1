[CmdletBinding()]
param(
    [Parameter(Mandatory = $false)]
    [string] $ProjectRoot = (Get-Location).Path,

    [Parameter(Mandatory = $false)]
    [string] $Database = 'together_rental_e2e',

    [Parameter(Mandatory = $false)]
    [switch] $Force
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

function Set-EnvironmentValue {
    param(
        [Parameter(Mandatory = $true)][string] $Content,
        [Parameter(Mandatory = $true)][string] $Key,
        [Parameter(Mandatory = $true)][AllowEmptyString()][string] $Value
    )

    $escapedKey = [regex]::Escape($Key)
    $pattern = "(?m)^$escapedKey=.*$"
    $replacement = "$Key=$Value"

    if ([regex]::IsMatch($Content, $pattern)) {
        return [regex]::Replace($Content, $pattern, $replacement)
    }

    return $Content.TrimEnd() + "`r`n$replacement`r`n"
}

$resolvedRoot = (Resolve-Path -LiteralPath $ProjectRoot).Path
$sourcePath = Join-Path $resolvedRoot '.env'
$targetPath = Join-Path $resolvedRoot '.env.e2e'

if (-not $Database.EndsWith('_e2e', [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Nama database wajib berakhiran _e2e. Nilai diterima: $Database"
}

if (-not (Test-Path -LiteralPath (Join-Path $resolvedRoot 'artisan') -PathType Leaf)) {
    throw "ProjectRoot bukan root Laravel Together Kamera: $resolvedRoot"
}

if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
    throw "File .env lokal tidak ditemukan: $sourcePath"
}

if ((Test-Path -LiteralPath $targetPath -PathType Leaf) -and -not $Force) {
    throw 'File .env.e2e sudah ada. Periksa isinya atau jalankan ulang dengan -Force untuk membuat ulang dari .env.'
}

Copy-Item -LiteralPath $sourcePath -Destination $targetPath -Force
$content = [System.IO.File]::ReadAllText($targetPath)
$values = [ordered]@{
    APP_ENV = 'e2e'
    APP_DEBUG = 'false'
    APP_URL = 'http://127.0.0.1:8010'
    DB_URL = ''
    DB_HOST = '127.0.0.1'
    DB_DATABASE = $Database
    CACHE_STORE = 'array'
    QUEUE_CONNECTION = 'sync'
    SESSION_DRIVER = 'file'
    SESSION_COOKIE = 'together-rental-e2e-session'
    MAIL_MAILER = 'array'
    E2E_ALLOW_DATABASE_RESET = 'true'
    E2E_BASE_URL = 'http://127.0.0.1:8010'
    E2E_USER_PASSWORD = '"Playwright#2026"'
}

foreach ($entry in $values.GetEnumerator()) {
    $content = Set-EnvironmentValue -Content $content -Key $entry.Key -Value ([string] $entry.Value)
}

[System.IO.File]::WriteAllText(
    $targetPath,
    $content,
    (New-Object System.Text.UTF8Encoding($false))
)

Write-Host ''
Write-Host 'File .env.e2e berhasil dibuat.' -ForegroundColor Green
Write-Host "Path     : $targetPath"
Write-Host "Database : $Database"
Write-Host 'Periksa DB_PORT, DB_USERNAME, dan DB_PASSWORD sebelum menjalankan e2e:prepare.'
Write-Host 'Script ini tidak membuat database dan tidak menjalankan migration.'
