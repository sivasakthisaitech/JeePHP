@echo off
where php >nul 2>nul
if not errorlevel 1 goto use_path

if exist "%~dp0..\..\php\php.exe" goto use_xampp

echo PHP CLI was not found. Install PHP 8.1+ and add it to PATH, or use a XAMPP PHP installation.
exit /b 1

:use_path
php "%~dp0artisan" %*
exit /b %errorlevel%

:use_xampp
"%~dp0..\..\php\php.exe" "%~dp0artisan" %*
exit /b %errorlevel%
