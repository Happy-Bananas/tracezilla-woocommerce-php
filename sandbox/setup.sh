#!/bin/sh
set -eu

until wp core is-installed --path=/var/www/html 2>/dev/null; do
    if [ -f /var/www/html/wp-config.php ]; then
        wp core install \
            --path=/var/www/html \
            --url=http://localhost:8080 \
            --title="Tracezilla WooCommerce Sandbox" \
            --admin_user=admin \
            --admin_password=tracezilla \
            --admin_email=developer@example.com \
            --skip-email && break
    fi
    sleep 2
done

wp plugin install woocommerce --activate --path=/var/www/html
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json --path=/var/www/html

echo "WooCommerce sandbox is ready at http://localhost:8080"
echo "WordPress login: admin / tracezilla"

