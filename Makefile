.PHONY: up down setup logs shell db-shell restart clean

# Start Docker containers
up:
	docker-compose up -d
	@echo "Waiting for services to start..."
	@sleep 5
	@echo "WordPress: http://localhost:8080"
	@echo "phpMyAdmin: http://localhost:8081"

# Stop Docker containers
down:
	docker-compose down

# Full setup (start + install WordPress + WooCommerce + test data)
setup: up
	@echo "Running setup script..."
	./scripts/setup.sh

# View logs
logs:
	docker-compose logs -f

# WordPress logs only
logs-wp:
	docker-compose logs -f wordpress

# Shell into WordPress container
shell:
	docker exec -it zicer-woo-wordpress bash

# WP-CLI shell
wp:
	docker exec -it zicer-woo-cli sh

# MySQL shell
db-shell:
	docker exec -it zicer-woo-db mysql -u wordpress -pwordpress wordpress

# Restart containers
restart:
	docker-compose restart

# Clean everything (removes volumes!)
clean:
	docker-compose down -v
	@echo "All data has been removed."

# Activate plugin
activate:
	docker exec zicer-woo-cli wp plugin activate zicer-woo-sync

# Deactivate plugin
deactivate:
	docker exec zicer-woo-cli wp plugin deactivate zicer-woo-sync

# Watch plugin files and sync
watch:
	@echo "Plugin files are mounted directly - changes are immediate"

# Export database
db-export:
	docker exec zicer-woo-cli wp db export /var/www/html/wp-content/plugins/zicer-woo-sync/backup.sql
	@echo "Database exported to zicer-woo-sync/backup.sql"

# Import database
db-import:
	docker exec zicer-woo-cli wp db import /var/www/html/wp-content/plugins/zicer-woo-sync/backup.sql
	@echo "Database imported"

# Create test product
test-product:
	docker exec zicer-woo-cli wp wc product create \
		--name="Test Product $(shell date +%s)" \
		--type="simple" \
		--regular_price="99" \
		--description="Test product for ZICER sync" \
		--sku="TEST-$(shell date +%s)" \
		--manage_stock=true \
		--stock_quantity=10 \
		--user=1

# Check plugin status
status:
	@docker exec zicer-woo-cli wp plugin status zicer-woo-sync || echo "Plugin not installed"
	@echo ""
	@docker exec zicer-woo-cli wp option get zicer_connection_status --format=json 2>/dev/null || echo "Not connected to ZICER"

# Generate translation template
pot:
	docker exec zicer-woo-cli wp i18n make-pot \
		/var/www/html/wp-content/plugins/zicer-woo-sync \
		/var/www/html/wp-content/plugins/zicer-woo-sync/languages/zicer-woo-sync.pot \
		--domain=zicer-woo-sync

# Help
help:
	@echo "Available commands:"
	@echo "  make up          - Start Docker containers"
	@echo "  make down        - Stop Docker containers"
	@echo "  make setup       - Full setup (WordPress + WooCommerce + test data)"
	@echo "  make logs        - View container logs"
	@echo "  make shell       - Shell into WordPress container"
	@echo "  make wp          - WP-CLI shell"
	@echo "  make db-shell    - MySQL shell"
	@echo "  make activate    - Activate ZICER plugin"
	@echo "  make deactivate  - Deactivate ZICER plugin"
	@echo "  make status      - Check plugin status"
	@echo "  make test-product- Create a test product"
	@echo "  make pot         - Generate translation template"
	@echo "  make clean       - Remove all data (careful!)"
