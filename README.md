# ZICER WooCommerce Sync Plugin

WordPress/WooCommerce plugin for synchronizing products with the ZICER marketplace platform.

## Installation

### Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

### Manual Installation

1. Download the latest release from [GitHub Releases](https://github.com/optimize-doo/zicer-woocommerce-plugin/releases)
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Upload the `zicer-woo-sync.zip` file
4. Click **Install Now**, then **Activate**

Alternatively, extract the zip and upload the `zicer-woo-sync` folder to `/wp-content/plugins/` via FTP.

### Updates

The plugin includes automatic update checking from GitHub:

- **Automatic notifications**: When a new version is available, you'll see an update notice in **Plugins → Installed Plugins**
- **One-click updates**: Click "Update Now" to install the latest version directly from WordPress admin
- **Manual updates**: Download the new release and follow the installation steps above (deactivate first)

Updates are checked against GitHub releases. Your settings and synced listings are preserved during updates.

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

## Token Management

To connect the plugin to ZICER, you need an API token:

1. **Create a token**: Log in to [ZICER](https://zicer.ba), go to your profile settings, and generate an API token
2. **Token format**: Tokens start with `zic_` prefix (e.g., `zic_abc123...`)
3. **Enter token**: In WordPress admin, go to **ZICER Sync → Settings** and paste your token
4. **Verify connection**: Click "Connect" to validate the token and retrieve your shop info

**Token permissions:**
- Tokens have full access to your ZICER shop listings
- Revoking a token on ZICER immediately disconnects the plugin
- Generate a new token anytime from your ZICER profile

**Switching accounts:**
- To connect a different ZICER account, disconnect first, then enter a new token
- Disconnecting does not delete synced listings from ZICER

## Internationalization

The plugin supports both English and Bosnian languages:
- Default language: English
- Translation file: `languages/zicer-woo-sync-bs_BA.po`
