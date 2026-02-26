#!/bin/sh
set -e

CONFIG_FILE="/var/www/html/app/config/config.php"
EXAMPLE_FILE="/var/www/html/app/config/example.config.php"

if [ ! -f "$CONFIG_FILE" ] && [ -f "$EXAMPLE_FILE" ]; then
    echo "config.php not found - copying from example.config.php"
    cp "$EXAMPLE_FILE" "$CONFIG_FILE"
fi

exec apache2-foreground
