@echo off
echo ========================================
echo  Database Auto-Backup Setup
echo ========================================
echo.

REM Auto-detect paths
set "DESKTOP=%USERPROFILE%\Desktop"
set "PHP_PATH=C:\xampp\php\php.exe"
set "SCRIPT_PATH=C:\xampp\htdocs\php\auto_backup_db.php"

echo Checking configuration...
echo.

REM Check if PHP exists
if not exist "%PHP_PATH%" (
    echo ERROR: PHP not found at %PHP_PATH%
    echo Please edit this script and update PHP_PATH variable.
    echo.
    pause
    exit /b 1
)

REM Check if backup script exists
if not exist "%SCRIPT_PATH%" (
    echo ERROR: Backup script not found at %SCRIPT_PATH%
    echo.
    echo Make sure auto_backup_db.php is in C:\xampp\htdocs\php\
    echo.
    pause
    exit /b 1
)

echo [OK] PHP found: %PHP_PATH%
echo [OK] Script found: %SCRIPT_PATH%
echo [OK] Backup destination: %DESKTOP%
echo.

REM Update the backup script with current user's desktop path
echo Configuring backup destination...
powershell -Command "(Get-Content '%SCRIPT_PATH%') -replace 'C:\\\\Users\\\\Ablaven\\\\Desktop\\\\', '%DESKTOP:\=\\%\\' | Set-Content '%SCRIPT_PATH%'"

echo.
echo Creating scheduled task...
schtasks /create /tn "Digital Marketing Portal DB Backup" /tr "\"%PHP_PATH%\" \"%SCRIPT_PATH%\"" /sc daily /st 03:00 /f

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo  SUCCESS! Task created.
    echo ========================================
    echo.
    echo Testing backup now...
    echo.
    "%PHP_PATH%" "%SCRIPT_PATH%"
    echo.
    echo ========================================
    echo  Setup Complete!
    echo ========================================
    echo.
    echo Backup Location: %DESKTOP%
    echo Schedule: Daily at 3:00 AM
    echo Retention: 7 days
    echo.
    echo Check your Desktop for the backup file!
    echo.
) else (
    echo.
    echo ERROR: Failed to create task
    echo Try running this script as Administrator
    echo.
)

pause
