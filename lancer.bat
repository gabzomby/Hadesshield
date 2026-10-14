@echo off
REM Lance le site en local sur http://localhost:8000 avec SQLite activé.
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

REM Vérifie que SQLite est disponible
"%PHP%" -r "exit(in_array('sqlite', PDO::getAvailableDrivers()) ? 0 : 1);"
if errorlevel 1 (
    echo L'extension pdo_sqlite n'est pas activee dans php.ini.
    pause
    exit /b 1
)

echo Site disponible sur http://localhost:8000  (Ctrl+C pour arreter)
start "" http://localhost:8000
"%PHP%" -S localhost:8000 router.php
pause
