# EVChargePlus Live Tunnel Runner
$Host.UI.RawUI.WindowTitle = "EVChargePlus - Live Public Website"
Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host "         EVChargePlus - Starting Live Public Website             " -ForegroundColor Green
Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Start MySQL if not running
$mysql = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
if (-not $mysql) {
    Write-Host "[*] Starting MySQL..." -ForegroundColor Yellow
    Start-Process -FilePath "C:\xamppp\mysql\bin\mysqld.exe" -WindowStyle Hidden
    Start-Sleep -Seconds 2
} else {
    Write-Host "[OK] MySQL is active." -ForegroundColor Green
}

# 2. Start Apache if not running
$httpd = Get-Process -Name "httpd" -ErrorAction SilentlyContinue
if (-not $httpd) {
    Write-Host "[*] Starting Apache..." -ForegroundColor Yellow
    Start-Process -FilePath "C:\xamppp\apache\bin\httpd.exe" -WindowStyle Hidden
    Start-Sleep -Seconds 2
} else {
    Write-Host "[OK] Apache is active." -ForegroundColor Green
}

Write-Host ""
Write-Host "[*] Generating your unique live public HTTPS link..." -ForegroundColor Yellow
Write-Host "    (Please wait about 5 to 10 seconds)..." -ForegroundColor DarkGray

$psi = New-Object System.Diagnostics.ProcessStartInfo
$psi.FileName = "C:\xamppp\cloudflared.exe"
$psi.Arguments = "tunnel --url http://127.0.0.1:80"
$psi.RedirectStandardError = $true
$psi.UseShellExecute = $false
$psi.CreateNoWindow = $true

$proc = [System.Diagnostics.Process]::Start($psi)
$foundUrl = $null

while (-not $proc.HasExited) {
    $line = $proc.StandardError.ReadLine()
    if ($line) {
        if ($line -match "https://([a-zA-Z0-9\-]+)\.trycloudflare\.com") {
            $foundUrl = $matches[0]
            break
        }
    }
}

if ($foundUrl) {
    $siteUrl = "$foundUrl/evchargeplus%20original/"
    Write-Host ""
    Write-Host "==================================================================" -ForegroundColor Green
    Write-Host "  SUCCESS! YOUR WEBSITE IS NOW LIVE ON THE INTERNET:              " -ForegroundColor Green
    Write-Host ""
    Write-Host "  $siteUrl" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "==================================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host " [v] Link copied to your clipboard! (Press Ctrl+V to paste anywhere)" -ForegroundColor Yellow
    Write-Host " [v] Opening live website in your browser right now..." -ForegroundColor Yellow
    Write-Host ""
    Write-Host " NOTE: Keep this black window open as long as you want the site online." -ForegroundColor White
    Write-Host " When you are done, simply close this window." -ForegroundColor DarkGray
    Write-Host ""

    try { Set-Clipboard -Value $siteUrl } catch {}
    try { $siteUrl | Out-File -FilePath "$PSScriptRoot\LIVE_WEBSITE_LINK.txt" -Encoding utf8 } catch {}

    # Open default browser automatically
    Start-Process $siteUrl

    # Keep alive
    while (-not $proc.HasExited) {
        Start-Sleep -Seconds 1
    }
} else {
    Write-Host ""
    Write-Host "[!] Could not generate tunnel URL. Please check your internet connection." -ForegroundColor Red
    Write-Host ""
    Read-Host "Press Enter to exit"
}
