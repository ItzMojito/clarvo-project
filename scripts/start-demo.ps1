# Menjalankan layanan SymPy dan aplikasi web untuk demo di laptop Windows.
# -Phone membuka aplikasi untuk ponsel di Wi-Fi yang sama.
param([switch]$Phone)

$root = Split-Path -Parent $PSScriptRoot
$python = Join-Path $root 'sympy-service\venv\Scripts\python.exe'

if (-not (Test-Path $python)) {
    Write-Error 'venv SymPy belum ada. Ikuti bagian Running di README terlebih dahulu.'
    exit 1
}

$webHost = if ($Phone) { '0.0.0.0' } else { '127.0.0.1' }

Start-Process -FilePath $python -ArgumentList '-m', 'uvicorn', 'main:app', '--port', '8001' -WorkingDirectory (Join-Path $root 'sympy-service')
Start-Process -FilePath 'php' -ArgumentList 'artisan', 'serve', "--host=$webHost", '--port=8000' -WorkingDirectory $root

# Beri kedua layanan waktu untuk siap sebelum diperiksa.
Start-Sleep -Seconds 4

Push-Location $root
try {
    php artisan clarvo:check
} finally {
    Pop-Location
}
