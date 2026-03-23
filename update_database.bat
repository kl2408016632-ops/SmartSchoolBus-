@echo off
REM SmartSchoolBus Multi-Session Database Update Script
REM This script creates the user_sessions table for multi-concurrent login support

echo.
echo ====================================
echo SmartSchoolBus Database Migration
echo ====================================
echo.
echo This will create the user_sessions table to enable:
echo - Multiple concurrent users (one per role)
echo - Same browser, different tabs with different logins
echo.

REM Navigate to MySQL bin
cd /d C:\xampp\mysql\bin

REM Run the database update
mysql -u root smartschoolbus_db < "C:\xampp\htdocs\SmartSchoolBus\database.sql"

echo.
if %errorlevel% equ 0 (
    echo ✓ Database migration completed successfully!
    echo.
    echo You can now:
    echo - Login as Admin in one browser tab
    echo - Login as Staff in another tab
    echo - Login as Driver in a third tab
    echo - All simultaneously in the same browser!
    echo.
) else (
    echo ✗ Database migration failed!
    echo Please check your MySQL installation and try again.
    echo.
)

pause
