@echo off
title Sundal Dev Server
cd /d "%~dp0"

echo Starting Laravel server on port 9090...
start "Laravel Server" cmd /k "php artisan serve --port=9090"

echo Waiting for Laravel to start...
timeout /t 3 /nobreak >nul

echo Starting Cloudflare tunnel...
start "Cloudflare Tunnel - CHECK HERE FOR URL" cmd /k "C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --url http://localhost:9090

echo.
echo ====================================================
echo  Both servers started!
echo  Check the "Cloudflare Tunnel" window for your URL
echo  It will look like: https://xxxx.trycloudflare.com
echo ====================================================
echo.
pause
