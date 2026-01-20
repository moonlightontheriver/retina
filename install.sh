#!/usr/bin/env bash
#
# Retina Installer for macOS and Linux
# Usage: curl -sSL https://raw.githubusercontent.com/moonlightontheriver/retina/main/install.sh | bash
#

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPO="moonlightontheriver/retina"
INSTALL_DIR="${INSTALL_DIR:-$HOME/.retina}"
BIN_DIR="${BIN_DIR:-/usr/local/bin}"

# Functions
print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_header() {
    echo ""
    echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║${NC}  ${GREEN}Retina Installer${NC}                   ${BLUE}║${NC}"
    echo -e "${BLUE}║${NC}  PocketMine-MP Plugin Static Analyzer ${BLUE}║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
    echo ""
}

check_requirements() {
    print_info "Checking system requirements..."
    
    # Check PHP
    if ! command -v php &> /dev/null; then
        print_error "PHP is not installed. Please install PHP 8.1 or higher."
        exit 1
    fi
    
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    PHP_MAJOR=$(php -r "echo PHP_MAJOR_VERSION;")
    PHP_MINOR=$(php -r "echo PHP_MINOR_VERSION;")
    
    if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 1 ]); then
        print_error "PHP 8.1 or higher is required. Found: $PHP_VERSION"
        exit 1
    fi
    
    print_success "PHP $PHP_VERSION detected"
    
    # Check Git
    if ! command -v git &> /dev/null; then
        print_error "Git is not installed. Please install Git."
        exit 1
    fi
    
    print_success "Git detected"
    
    # Check Composer
    if ! command -v composer &> /dev/null; then
        print_error "Composer is not installed. Please install Composer."
        exit 1
    fi
    
    print_success "Composer detected"
}

install_retina() {
    print_info "Installing Retina from source..."
    
    # Clone repository
    print_info "Cloning repository..."
    rm -rf "$INSTALL_DIR" 2>/dev/null
    
    if ! git clone --depth 1 "https://github.com/$REPO.git" "$INSTALL_DIR" --quiet 2>&1; then
        print_error "Failed to clone repository"
        exit 1
    fi
    
    print_success "Repository cloned"
    
    # Install dependencies
    print_info "Installing dependencies..."
    cd "$INSTALL_DIR" || {
        print_error "Cannot access install directory"
        exit 1
    }
    
    if ! composer install --no-dev --optimize-autoloader --quiet 2>&1; then
        print_error "Failed to install dependencies"
        exit 1
    fi
    
    print_success "Dependencies installed"
    
    # Make executable
    chmod +x "$INSTALL_DIR/bin/retina" || {
        print_error "Cannot make binary executable"
        exit 1
    }
    
    # Create symlink
    if [ -w "$BIN_DIR" ]; then
        ln -sf "$INSTALL_DIR/bin/retina" "$BIN_DIR/retina" || {
            print_error "Cannot create symlink in $BIN_DIR"
            exit 1
        }
        print_success "Retina installed to $BIN_DIR/retina"
    else
        print_warning "Cannot write to $BIN_DIR. Trying with sudo..."
        if sudo ln -sf "$INSTALL_DIR/bin/retina" "$BIN_DIR/retina" 2>/dev/null; then
            print_success "Retina installed to $BIN_DIR/retina (with sudo)"
        else
            print_error "Failed to create symlink. Try running with sudo."
            exit 1
        fi
    fi
}

verify_installation() {
    print_info "Verifying installation..."
    
    if command -v retina &> /dev/null; then
        VERSION=$(retina --version 2>/dev/null | head -n 1 || echo "unknown")
        print_success "Retina is installed: $VERSION"
        return 0
    else
        print_error "Retina command not found in PATH"
        return 1
    fi
}

show_usage() {
    echo ""
    print_success "Installation complete!"
    echo ""
    echo "Usage examples:"
    echo "  ${GREEN}retina run${NC}                    # Scan current directory"
    echo "  ${GREEN}retina run /path/to/plugin${NC}   # Scan specific plugin"
    echo "  ${GREEN}retina init${NC}                   # Create retina.yml config"
    echo "  ${GREEN}retina --help${NC}                 # Show all options"
    echo ""
    echo "Documentation: ${BLUE}https://github.com/$REPO${NC}"
    echo ""
}

uninstall() {
    print_info "Uninstalling Retina..."
    
    # Remove symlink
    rm -f "$BIN_DIR/retina"
    
    # Remove installation directory
    rm -rf "$INSTALL_DIR"
    
    print_success "Retina has been uninstalled"
    exit 0
}

main() {
    # Check for uninstall flag
    if [ "${1:-}" = "--uninstall" ] || [ "${1:-}" = "uninstall" ]; then
        uninstall
    fi
    
    print_header
    check_requirements
    install_retina
    
    # Verify installation
    if verify_installation; then
        show_usage
    else
        print_error "Installation verification failed"
        exit 1
    fi
}

# Run main function
main "$@"
