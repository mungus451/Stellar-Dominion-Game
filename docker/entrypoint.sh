#!/bin/bash

# Set proper permissions for log directory
chown -R www-data:www-data /var/log/stellar-dominion
chmod -R 755 /var/log/stellar-dominion

# Set proper permissions for application logs directory as fallback
chown -R www-data:www-data /var/www/html/logs 2>/dev/null || true
chmod -R 755 /var/www/html/logs 2>/dev/null || true

# Set proper permissions for uploads
chown -R www-data:www-data /var/www/html/public/uploads 2>/dev/null || true
chmod -R 755 /var/www/html/public/uploads 2>/dev/null || true

# Start Apache
exec apache2-foreground
