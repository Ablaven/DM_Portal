@echo off
REM ========================================
REM Setup Automatic Database Backup at 10 PM
REM ========================================

echo.
echo ========================================
echo   Setup Automatic Database Backup
echo ========================================
echo.
echo This will schedule a daily backup at 10 PM
echo Backups will be saved to your Desktop
echo.
pause

REM Get the script directory
set SCRIPT_DIR=%~dp0

echo.
echo Setting up scheduled task...
echo.

REM Create the scheduled task using PowerShell
powershell -Command "Register-ScheduledTask -TaskName 'Digital Marketing Portal DB Backup' -Trigger (New-ScheduledTaskTrigger -Daily -At '22:00') -Action (New-ScheduledTaskAction -Execute 'C:\xampp\php\php.exe' -Argument '%SCRIPT_DIR%php\auto_backup_db.php') -Description 'Exports database to Desktop daily at 10 PM' -Force"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo   SUCCESS!
    echo ========================================
    echo.
    echo Automatic backup is now scheduled!
    echo   ^> Runs daily at 10:00 PM
    echo   ^> Saves to Desktop
    echo   ^> Keeps backups for 7 days
    echo.
    echo Next backup: Tonight at 10 PM
    echo.
) else (
    echo.
    echo ========================================
    echo   ERROR!
    echo ========================================
    echo.
    echo Failed to create scheduled task.
    echo Make sure you run this as Administrator!
    echo.
    echo Right-click SETUP.bat and select
    echo "Run as administrator"
    echo.
)

pause
