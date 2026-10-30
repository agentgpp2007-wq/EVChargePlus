@echo off
title EVChargePlus Live Launcher
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0run_live_tunnel.ps1"
if %errorlevel% neq 0 pause
