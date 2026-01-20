#
# Retina Installer for Windows (PowerShell)
# Usage: iwr -useb https://raw.githubusercontent.com/moonlightontheriver/retina/main/install.ps1 | iex
#

$ErrorActionPreference = "Continue"

# Configuration
$REPO = "moonlightontheriver/retina"
$INSTALL_DIR = if ($env:RETINA_INSTALL_DIR) { $env:RETINA_INSTALL_DIR } else { "$env:USERPROFILE\.retina" }

# Functions
function Print-Header {
    Write-Host ""
    Write-Host "╔════════════════════════════════════════╗" -ForegroundColor Blue
    Write-Host "║  " -NoNewline -ForegroundColor Blue
    Write-Host "Retina Installer" -NoNewline -ForegroundColor Green
    Write-Host "                   ║" -ForegroundColor Blue
    Write-Host "║  PocketMine-MP Plugin Static Analyzer ║" -ForegroundColor Blue
    Write-Host "╚════════════════════════════════════════╝" -ForegroundColor Blue
    Write-Host ""
}

function Print-Info {
    param([string]$Message)
    Write-Host "ℹ " -NoNewline -ForegroundColor Blue
    Write-Host $Message
}

function Print-Success {
    param([string]$Message)
    Write-Host "✓ " -NoNewline -ForegroundColor Green
    Write-Host $Message
}

function Print-Warning {
    param([string]$Message)
    Write-Host "⚠ " -NoNewline -ForegroundColor Yellow
    Write-Host $Message
}

function Print-Error {
    param([string]$Message)
    Write-Host "✗ " -NoNewline -ForegroundColor Red
    Write-Host $Message
}

function Check-Requirements {
    Print-Info "Checking system requirements..."
    
    # Check PHP
    try {
        $phpVersion = php -r "echo PHP_VERSION;" 2>$null
        $phpMajor = php -r "echo PHP_MAJOR_VERSION;" 2>$null
        $phpMinor = php -r "echo PHP_MINOR_VERSION;" 2>$null
        
        if ([int]$phpMajor -lt 8 -or ([int]$phpMajor -eq 8 -and [int]$phpMinor -lt 1)) {
            Print-Error "PHP 8.1 or higher is required. Found: $phpVersion"
            exit 1
        }
        
        Print-Success "PHP $phpVersion detected"
    }
    catch {
        Print-Error "PHP is not installed or not in PATH. Please install PHP 8.1 or higher."
        Write-Host "Download from: https://windows.php.net/download/" -ForegroundColor Cyan
        exit 1
    }
    
    # Check Git
    try {
        $null = git --version 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Git not working"
        }
        Print-Success "Git detected"
    }
    catch {
        Print-Error "Git is not installed. Cannot clone repository."
        Write-Host "Download from: https://git-scm.com/download/win" -ForegroundColor Cyan
        exit 1
    }
    
    # Check Composer
    try {
        $null = composer --version 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Composer not working"
        }
        Print-Success "Composer detected"
    }
    catch {
        Print-Error "Composer is required for source installation."
        Write-Host "Download from: https://getcomposer.org/download/" -ForegroundColor Cyan
        exit 1
    }
}

function Install-Retina {
    Print-Info "Installing Retina from source..."
    
    # Clone repository
    Print-Info "Cloning repository..."
    try {
        if (Test-Path $INSTALL_DIR) {
            Remove-Item -Path $INSTALL_DIR -Recurse -Force -ErrorAction Stop
        }
        
        $gitOutput = git clone --depth 1 "https://github.com/$REPO.git" $INSTALL_DIR --quiet 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "Git clone failed: $gitOutput"
        }
        
        Print-Success "Repository cloned"
    }
    catch {
        Print-Error "Failed to clone repository: $_"
        exit 1
    }
    
    # Install dependencies
    Print-Info "Installing dependencies..."
    try {
        Push-Location $INSTALL_DIR
        $composerOutput = composer install --no-dev --optimize-autoloader --quiet 2>&1
        if ($LASTEXITCODE -ne 0) {
            Pop-Location
            throw "Composer install failed: $composerOutput"
        }
        Pop-Location
        
        Print-Success "Dependencies installed"
    }
    catch {
        Print-Error "Failed to install dependencies: $_"
        exit 1
    }
    
    # Create batch wrapper
    try {
        $batchWrapper = @"
@echo off
php "$INSTALL_DIR\bin\retina" %*
"@
        $batchPath = "$INSTALL_DIR\retina.bat"
        Set-Content -Path $batchPath -Value $batchWrapper -Encoding ASCII -ErrorAction Stop
        
        # Add to PATH
        Add-ToPath $INSTALL_DIR
        
        Print-Success "Retina installed to $INSTALL_DIR"
    }
    catch {
        Print-Error "Failed to create batch wrapper: $_"
        exit 1
    }
}

function Add-ToPath {
    param([string]$Directory)
    
    try {
        $currentPath = [Environment]::GetEnvironmentVariable("Path", "User")
        
        if ($currentPath -notlike "*$Directory*") {
            Print-Info "Adding to PATH..."
            
            [Environment]::SetEnvironmentVariable(
                "Path",
                "$currentPath;$Directory",
                "User"
            )
            
            # Update current session PATH
            $env:Path = "$env:Path;$Directory"
            
            Print-Success "Added to PATH"
            Print-Info "You may need to restart your terminal for changes to take effect"
        }
    }
    catch {
        Print-Warning "Could not update PATH automatically. Please add manually: $Directory"
    }
}

function Verify-Installation {
    Print-Info "Verifying installation..."
    
    # Refresh PATH for current session
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path","Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path","User")
    
    try {
        $version = retina --version 2>$null | Select-Object -First 1
        Print-Success "Retina is installed: $version"
        return $true
    }
    catch {
        Print-Error "Retina command not found in PATH"
        Print-Info "Please restart your terminal and try running 'retina --version'"
        return $false
    }
}

function Show-Usage {
    Write-Host ""
    Print-Success "Installation complete!"
    Write-Host ""
    Write-Host "Usage examples:"
    Write-Host "  " -NoNewline
    Write-Host "retina run" -NoNewline -ForegroundColor Green
    Write-Host "                    # Scan current directory"
    Write-Host "  " -NoNewline
    Write-Host "retina run C:\path\to\plugin" -NoNewline -ForegroundColor Green
    Write-Host "   # Scan specific plugin"
    Write-Host "  " -NoNewline
    Write-Host "retina init" -NoNewline -ForegroundColor Green
    Write-Host "                   # Create retina.yml config"
    Write-Host "  " -NoNewline
    Write-Host "retina --help" -NoNewline -ForegroundColor Green
    Write-Host "                 # Show all options"
    Write-Host ""
    Write-Host "Documentation: " -NoNewline
    Write-Host "https://github.com/$REPO" -ForegroundColor Blue
    Write-Host ""
    Write-Host "Note: " -NoNewline -ForegroundColor Yellow
    Write-Host "If 'retina' command is not found, please restart your terminal."
    Write-Host ""
}

function Main {
    Print-Header
    Check-Requirements
    Install-Retina
    
    # Verify installation
    if (Verify-Installation) {
        Show-Usage
    }
    else {
        Print-Warning "Installation verification failed"
        Print-Info "Please restart your terminal and run: retina --version"
    }
}

# Run main function
Main
