@echo off
REM Retina Installer for Windows (Batch)
REM Usage: install.bat

setlocal enabledelayedexpansion

REM Configuration
set "REPO=moonlightontheriver/retina"
set "INSTALL_DIR=%USERPROFILE%\.retina"

REM Check for uninstall flag
if "%1"=="--uninstall" goto :uninstall
if "%1"=="uninstall" goto :uninstall

REM Print header
echo.
echo ========================================
echo   Retina Installer
echo   PocketMine-MP Plugin Static Analyzer
echo ========================================
echo.

REM Check PHP
echo [INFO] Checking system requirements...
where php >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP is not installed or not in PATH.
    echo Please install PHP 8.1 or higher from: https://windows.php.net/download/
    exit /b 1
)

REM Get PHP version
for /f "tokens=*" %%i in ('php -r "echo PHP_VERSION;"') do set PHP_VERSION=%%i
for /f "tokens=*" %%i in ('php -r "echo PHP_MAJOR_VERSION;"') do set PHP_MAJOR=%%i
for /f "tokens=*" %%i in ('php -r "echo PHP_MINOR_VERSION;"') do set PHP_MINOR=%%i

if %PHP_MAJOR% LSS 8 (
    echo [ERROR] PHP 8.1 or higher is required. Found: %PHP_VERSION%
    exit /b 1
)
if %PHP_MAJOR% EQU 8 if %PHP_MINOR% LSS 1 (
    echo [ERROR] PHP 8.1 or higher is required. Found: %PHP_VERSION%
    exit /b 1
)

echo [OK] PHP %PHP_VERSION% detected

REM Check Git
where git >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Git is not installed. Cannot clone repository.
    echo Download from: https://git-scm.com/download/win
    exit /b 1
)

echo [OK] Git detected

REM Check Composer
where composer >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Composer is required for source installation.
    echo Download from: https://getcomposer.org/download/
    exit /b 1
)

echo [OK] Composer detected

REM Install Retina
call :install_retina

REM Verify installation
echo [INFO] Verifying installation...
where retina >nul 2>&1
if errorlevel 1 (
    echo [WARN] Retina command not found in PATH
    echo Please restart your terminal and try running: retina --version
) else (
    echo [OK] Retina is installed
    call :show_usage
)

exit /b 0

:install_retina
echo [INFO] Installing Retina from source...

REM Clone repository
echo [INFO] Cloning repository...
if exist "%INSTALL_DIR%" (
    rmdir /s /q "%INSTALL_DIR%" 2>nul
)

git clone --depth 1 "https://github.com/%REPO%.git" "%INSTALL_DIR%" --quiet 2>nul
if errorlevel 1 (
    echo [ERROR] Failed to clone repository
    exit /b 1
)

echo [OK] Repository cloned

REM Install dependencies
echo [INFO] Installing dependencies...
set "ORIGINAL_DIR=%CD%"
cd /d "%INSTALL_DIR%"
composer install --no-dev --optimize-autoloader --quiet 2>nul
set COMPOSER_EXIT=%ERRORLEVEL%
cd /d "%ORIGINAL_DIR%"

if %COMPOSER_EXIT% NEQ 0 (
    echo [ERROR] Failed to install dependencies
    exit /b 1
)

echo [OK] Dependencies installed

REM Create batch wrapper
(
echo @echo off
echo php "%INSTALL_DIR%\bin\retina" %%*
) > "%INSTALL_DIR%\retina.bat"

if errorlevel 1 (
    echo [ERROR] Cannot create batch wrapper
    exit /b 1
)

REM Add to PATH
echo %PATH% | findstr /C:"%INSTALL_DIR%" >nul
if errorlevel 1 (
    echo [INFO] Adding to PATH...
    setx PATH "%PATH%;%INSTALL_DIR%" >nul 2>&1
    if errorlevel 1 (
        echo [WARN] Could not update PATH automatically
        echo [INFO] Please add manually: %INSTALL_DIR%
    ) else (
        echo [OK] Added to PATH
        echo [INFO] Please restart your terminal for changes to take effect
    )
)

echo [OK] Retina installed to %INSTALL_DIR%
exit /b 0

:show_usage
echo.
echo [OK] Installation complete!
echo.
echo Usage examples:
echo   retina run                    # Scan current directory
echo   retina run C:\path\to\plugin  # Scan specific plugin
echo   retina init                   # Create retina.yml config
echo   retina --help                 # Show all options
echo.
echo Documentation: https://github.com/%REPO%
echo.
echo Note: If 'retina' command is not found, please restart your terminal.
echo.
goto :eof

:uninstall
echo [INFO] Uninstalling Retina...

REM Remove installation directory
if exist "%INSTALL_DIR%" (
    rmdir /s /q "%INSTALL_DIR%"
)

REM Note: Removing from PATH requires manual intervention or registry editing
echo [OK] Retina has been uninstalled
echo [INFO] You may need to manually remove Retina paths from your PATH environment variable
exit /b 0
