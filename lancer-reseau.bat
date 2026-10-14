@echo off
REM Lance le site accessible depuis les autres appareils du réseau local (port 8000).
cd /d "%~dp0"

REM Trouve PHP : dans le PATH, sinon dans le dossier d'installation winget
set "PHP=php"
where php >nul 2>nul
if errorlevel 1 (
    for /d %%D in ("%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.*") do set "PHP=%%D\php.exe"
)
"%PHP%" -v >nul 2>nul
if errorlevel 1 (
    echo PHP est introuvable. Installez-le avec : winget install PHP.PHP.8.3
    pause
    exit /b 1
)

echo Sur ce PC        : http://localhost:8000
echo Sur le reseau    :
for /f "usebackq delims=" %%I in (`powershell -NoProfile -Command "Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' -and $_.InterfaceAlias -notlike 'VMware*' } | ForEach-Object { '   http://' + $_.IPAddress + ':8000   (' + $_.InterfaceAlias + ')' }"`) do echo %%I
echo.
echo Ctrl+C pour arreter.
"%PHP%" -S 0.0.0.0:8000 router.php
pause
