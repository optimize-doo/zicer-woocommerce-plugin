#!/bin/bash

# ZICER WooCommerce Test Environment Setup Script
# Run this after docker-compose up -d

set -e

echo "Checking database connection..."
until docker exec zicer-woo-cli wp db check 2>/dev/null; do
    sleep 2
done
echo "Database ready!"

# Install WordPress
echo "Installing WordPress..."
docker exec zicer-woo-cli wp core install \
    --url="http://localhost:8088" \
    --title="ZICER Test Shop" \
    --admin_user="admin" \
    --admin_password="admin123" \
    --admin_email="admin@test.local" \
    --skip-email

# Install and activate WooCommerce
echo "Installing WooCommerce..."
docker exec zicer-woo-cli wp plugin install woocommerce --activate

# Configure WooCommerce
echo "Configuring WooCommerce..."
docker exec zicer-woo-cli wp option update woocommerce_store_address "Test Address 123"
docker exec zicer-woo-cli wp option update woocommerce_store_city "Sarajevo"
docker exec zicer-woo-cli wp option update woocommerce_default_country "BA"
docker exec zicer-woo-cli wp option update woocommerce_currency "BAM"
docker exec zicer-woo-cli wp option update woocommerce_price_thousand_sep "."
docker exec zicer-woo-cli wp option update woocommerce_price_decimal_sep ","

# Create some test product categories
echo "Creating test categories..."
docker exec zicer-woo-cli wp term create product_cat "Electronics" --description="Electronic devices"
docker exec zicer-woo-cli wp term create product_cat "Mobile Phones" --parent=1 --description="Mobile phones and smartphones"
docker exec zicer-woo-cli wp term create product_cat "Computers" --parent=1 --description="Computers and laptops"
docker exec zicer-woo-cli wp term create product_cat "Clothing" --description="Clothing and footwear"

# Create test products
echo "Creating test products..."
docker exec zicer-woo-cli wp wc product create \
    --name="iPhone 15 Pro Max 256GB" \
    --type="simple" \
    --regular_price="2499" \
    --description="<p>Latest Apple iPhone 15 Pro Max with 256GB storage.</p>" \
    --short_description="Apple iPhone 15 Pro Max" \
    --sku="IP15PM-256" \
    --manage_stock=true \
    --stock_quantity=5 \
    --categories='[{"id":2}]' \
    --user=1

docker exec zicer-woo-cli wp wc product create \
    --name="Samsung Galaxy S24 Ultra" \
    --type="simple" \
    --regular_price="1899" \
    --description="<p>Samsung Galaxy S24 Ultra with advanced AI features.</p>" \
    --short_description="Samsung flagship phone" \
    --sku="SGS24U" \
    --manage_stock=true \
    --stock_quantity=10 \
    --categories='[{"id":2}]' \
    --user=1

docker exec zicer-woo-cli wp wc product create \
    --name="MacBook Pro 14 M3 Pro" \
    --type="simple" \
    --regular_price="3499" \
    --description="<p>Apple MacBook Pro 14 inch with M3 Pro chip.</p>" \
    --short_description="Apple laptop for professionals" \
    --sku="MBP14-M3P" \
    --manage_stock=true \
    --stock_quantity=3 \
    --categories='[{"id":3}]' \
    --user=1

# Create a variable product
echo "Creating variable product..."
docker exec zicer-woo-cli wp wc product create \
    --name="Basic T-Shirt" \
    --type="variable" \
    --description="<p>Quality cotton t-shirt.</p>" \
    --short_description="Cotton t-shirt" \
    --sku="TSHIRT-BASIC" \
    --categories='[{"id":4}]' \
    --attributes='[{"name":"Size","options":["S","M","L","XL"],"visible":true,"variation":true}]' \
    --user=1

# Activate our plugin
echo "Activating ZICER Sync plugin..."
docker exec zicer-woo-cli wp plugin activate zicer-woo-sync || echo "Plugin not found yet - will activate manually"

# Set permalink structure
docker exec zicer-woo-cli wp rewrite structure '/%postname%/'

echo ""
echo "=========================================="
echo "Setup complete!"
echo "=========================================="
echo ""
echo "WordPress:    http://localhost:8088"
echo "Admin:        http://localhost:8088/wp-admin"
echo "              Username: admin"
echo "              Password: admin123"
echo ""
echo "phpMyAdmin:   http://localhost:8081"
echo "              Username: wordpress"
echo "              Password: wordpress"
echo ""
echo "WooCommerce has been installed with:"
echo "  - Currency: BAM (KM)"
echo "  - Country: Bosnia and Herzegovina"
echo "  - 4 test categories"
echo "  - 4 test products"
echo ""
