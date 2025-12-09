# ZICER WooCommerce Sync Plugin

WordPress/WooCommerce plugin for synchronizing products with the ZICER marketplace platform.

## Development

### Starting the Test Environment

```bash
# Start Docker containers and install WordPress + WooCommerce
make setup

# Or just start containers (if already installed)
make up
```

### Access

- **WordPress**: http://localhost:8088
- **Admin panel**: http://localhost:8088/wp-admin
  - Username: `admin`
  - Password: `admin123`
- **phpMyAdmin**: http://localhost:8081
  - Username: `wordpress`
  - Password: `wordpress`

### Useful Commands

```bash
make up          # Start containers
make down        # Stop containers
make logs        # Show logs
make shell       # Shell into WordPress container
make wp          # WP-CLI shell
make db-shell    # MySQL shell
make activate    # Activate plugin
make deactivate  # Deactivate plugin
make clean       # Remove everything (including database!)
```

### Project Structure

```
zicer.woo/
├── docker-compose.yml     # Docker configuration
├── Makefile               # Useful commands
├── scripts/
│   └── setup.sh           # Initial setup script
├── zicer-woo-sync/        # WordPress plugin
│   ├── zicer-woo-sync.php # Main plugin file
│   ├── includes/          # PHP classes
│   ├── admin/             # Admin resources
│   │   ├── css/
│   │   ├── js/
│   │   └── views/
│   └── languages/         # Translations (BS/EN)
└── thoughts/
    └── plans/             # Implementation plans
```

## Features

- Connect to ZICER platform via API token
- Real-time automatic product synchronization
- Bulk sync for existing products
- WooCommerce to ZICER category mapping
- Configurable description templates
- Image synchronization
- API rate limit compliance
- Optional title truncation
- Auto-remove unavailable products

## Internationalization

The plugin supports both English and Bosnian languages:
- Default language: English
- Translation file: `languages/zicer-woo-sync-bs_BA.po`

## Documentation

Detailed implementation plan: `thoughts/plans/2025-12-09-zicer-woo-sync-plugin.md`
