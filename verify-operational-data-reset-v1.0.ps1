$ErrorActionPreference = "Stop"

Write-Host "[1/8] Clear Laravel caches" -ForegroundColor Cyan
herd php artisan optimize:clear

Write-Host "[2/8] Route check" -ForegroundColor Cyan
herd php artisan route:list --name=operations.reset

Write-Host "[3/8] PHP syntax" -ForegroundColor Cyan
$phpFiles = @(
    "app/Domain/Operations/OperationalDataResetService.php",
    "app/Http/Controllers/OperationalDataResetController.php",
    "app/Http/Requests/ResetOperationalDataRequest.php",
    "app/Http/Middleware/HandleInertiaRequests.php"
)
foreach ($file in $phpFiles) {
    herd php -l $file
}

Write-Host "[4/8] Targeted PHPStan" -ForegroundColor Cyan
herd php vendor/bin/phpstan analyse `
    app/Domain/Operations/OperationalDataResetService.php `
    app/Http/Controllers/OperationalDataResetController.php `
    app/Http/Requests/ResetOperationalDataRequest.php `
    app/Http/Middleware/HandleInertiaRequests.php

Write-Host "[5/8] Operational reset tests" -ForegroundColor Cyan
herd php artisan test --filter=OperationalDataResetTest

Write-Host "[6/8] Frontend quality" -ForegroundColor Cyan
npm run format
npm run lint:check
npm run types:check

Write-Host "[7/8] Production build" -ForegroundColor Cyan
$env:NODE_OPTIONS = "--max-old-space-size=8192"
try {
    npm run build
}
finally {
    Remove-Item Env:NODE_OPTIONS -ErrorAction SilentlyContinue
}

Write-Host "[8/8] Full regression" -ForegroundColor Cyan
herd php artisan test

Write-Host "Operational Data Reset v1.0 quality gate selesai." -ForegroundColor Green
