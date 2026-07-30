param(
    [string]$ProjectDir = "C:\xa\htdocs\comp",
    [int]$Port = 8899,
    [string]$PhpExe = "C:\xampp\php\php.exe",
    [string]$PhpUnit = "phpunit.phar"
)

function Cleanup {
    param($Job)
    if ($Job -and $Job.State -eq 'Running') {
        Stop-Job $Job -ErrorAction SilentlyContinue; Remove-Job $Job -ErrorAction SilentlyContinue
    }
    netstat -ano | Select-String ":$Port" | ForEach-Object {
        $procId = ($_ -split '\s+')[-1]
        if ($procId -match '^\d+$') { Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue }
    }
    $configFile = Join-Path $ProjectDir "config.local.php"
    if (Test-Path $configFile) { Remove-Item $configFile -Force }
}

Write-Host "=== Integration Test Runner ==="

# Kill any existing server on the port
netstat -ano | Select-String ":$Port" | ForEach-Object {
    $procId = ($_ -split '\s+')[-1]
    if ($procId -match '^\d+$') { Stop-Process -Id $procId -Force -ErrorAction SilentlyContinue }
}
Start-Sleep -Seconds 1

# Start PHP built-in server in background
Write-Host "Starting PHP server on port $Port..."
$serverJob = Start-Job -ScriptBlock {
    param($dir, $port, $php) Set-Location $dir; & $php -S "localhost:$port" -t $dir
} -ArgumentList $ProjectDir, $Port, $PhpExe

# Wait up to 15s for server
$ready = $false
for ($i = 0; $i -lt 15; $i++) {
    Start-Sleep -Milliseconds 500
    try { $null = curl.exe -s --connect-timeout 1 "http://localhost:$Port/" 2>&1 | Out-Null; if ($LASTEXITCODE -eq 0) { $ready = $true; break } } catch {}
}
if (-not $ready) {
    Write-Host "FAILED: Server did not start" -ForegroundColor Red
    Cleanup -Job $serverJob; exit 1
}
Write-Host "Server running."

# Run tests (TestDb helper handles DB setup/teardown)
Write-Host "Running PHPUnit integration tests..."
$testOutput = & $PhpExe (Join-Path $ProjectDir $PhpUnit) --testsuite integration 2>&1
$exitCode = $LASTEXITCODE
Write-Host $testOutput

# Cleanup
Cleanup -Job $serverJob

if ($exitCode -eq 0) { Write-Host "ALL INTEGRATION TESTS PASSED" -ForegroundColor Green }
else { Write-Host "INTEGRATION TESTS FAILED (exit: $exitCode)" -ForegroundColor Red }
exit $exitCode
