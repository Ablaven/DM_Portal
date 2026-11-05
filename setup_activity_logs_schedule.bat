@echo off
REM Setup Windows Task Scheduler for Activity Logs Archive
REM This creates a scheduled task that runs every 3 days at 10:00 PM

echo ========================================
echo Setup Activity Logs Archive Schedule
echo ========================================
echo.
echo This will create a Windows scheduled task that:
echo - Runs every 3 days at 10:00 PM
echo - Archives activity logs to Excel
echo - Saves to your Desktop (same as SQL backups)
echo - Deletes logs older than 3 days
echo.
echo Archive location: %USERPROFILE%\Desktop\
echo.
pause

REM Delete existing task if it exists
schtasks /Delete /TN "DM Portal Activity Logs Archive" /F >nul 2>&1

REM Create new scheduled task
REM /SC DAILY = Daily schedule
REM /MO 3 = Every 3 days
REM /ST 22:00 = Start time 10:00 PM
REM /TR = Task to run
REM /RU SYSTEM = Run as SYSTEM account (has permissions)
REM /RL HIGHEST = Run with highest privileges

schtasks /Create /TN "DM Portal Activity Logs Archive" /TR "C:\xampp\htdocs\archive_activity_logs.bat" /SC DAILY /MO 3 /ST 22:00 /RU SYSTEM /RL HIGHEST /F

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo SUCCESS! Scheduled task created.
    echo ========================================
    echo.
    echo Task Name: DM Portal Activity Logs Archive
    echo Schedule: Every 3 days at 10:00 PM
    echo Next Run: Check Task Scheduler
    echo.
    echo To view the task:
    echo 1. Open Task Scheduler (taskschd.msc)
    echo 2. Look for "DM Portal Activity Logs Archive"
    echo.
    echo To run the task manually now:
    echo schtasks /Run /TN "DM Portal Activity Logs Archive"
    echo.
) else (
    echo.
    echo ========================================
    echo ERROR! Failed to create scheduled task.
    echo ========================================
    echo.
    echo This script must be run as Administrator.
    echo Right-click and choose "Run as administrator"
    echo.
)

pause
