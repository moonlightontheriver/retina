#!/bin/bash

# update PocketMine-MP source for stubs
# run this script to get the latest pm5 stable source

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PM_DIR="$PROJECT_DIR/pocketmine-src"

echo "Updating PocketMine-MP source..."

if [ -d "$PM_DIR" ]; then
    echo "Pulling latest changes..."
    cd "$PM_DIR"
    git fetch origin stable
    git reset --hard origin/stable
else
    echo "Cloning PocketMine-MP..."
    git clone --depth 1 --branch stable https://github.com/pmmp/PocketMine-MP.git "$PM_DIR"
fi
if [ ! -L "$PROJECT_DIR/stubs/pocketmine" ]; then
    echo "Creating symlink..."
    ln -sf ../pocketmine-src/src "$PROJECT_DIR/stubs/pocketmine"
fi
echo "Done! PM source updated to latest stable."
echo "Version info:"
grep -m1 "VERSION" "$PM_DIR/src/VersionInfo.php" | head -1
