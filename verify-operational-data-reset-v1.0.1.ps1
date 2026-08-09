$ErrorActionPreference = "Stop"

Write-Host "[1/6] Clear Laravel caches" -ForegroundColor Cyan
herd php artisan optimize:clear

Write-Host "[2/6] PHP syntax" -ForegroundColor Cyan
herd php -l app/Domain/Operations/OperationalDataResetService.php
herd php -l app/Http/Requests/ResetOperationalDataRequest.php

Write-Host "[3/6] Targeted PHPStan" -ForegroundColor Cyan
herd php vendor/bin/phpstan analyse `
    app/Domain/Operations/OperationalDataResetService.php `
    app/Http/Controllers/OperationalDataResetController.php `
    app/Http/Requests/ResetOperationalDataRequest.php `
    app/Http/Middleware/HandleInertiaRequests.php

Write-Host "[4/6] Operational reset test" -ForegroundColor Cyan
herd php artisan test --filter=OperationalDataResetTest

Write-Host "[5/6] Frontend quality" -ForegroundColor Cyan
npm run format
npm run lint:check
npm run types:check

Write-Host "[6/6] Full regression" -ForegroundColor Cyan
herd php artisan test

Write-Host "Operational Data Reset v1.0.1 quality gate selesai." -ForegroundColor Green
