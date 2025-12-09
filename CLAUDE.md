# ZICER WooCommerce Sync Plugin

## Project Overview

WordPress/WooCommerce plugin that synchronizes products with ZICER marketplace (https://zicer.ba). The plugin creates, updates, and deletes ZICER listings based on WooCommerce product changes.

## Tech Stack

- **WordPress** plugin (PHP 7.4+)
- **WooCommerce** 5.0+ integration
- **ZICER API** - REST API at `https://api.zicer.ba/api`
- **Docker** development environment (MariaDB + WordPress + WP-CLI)

## Project Structure

```
zicer.woo/
├── zicer-woo-sync/           # WordPress plugin (main code)
│   ├── zicer-woo-sync.php    # Plugin entry point
│   ├── includes/             # PHP classes (Zicer_*)
│   ├── admin/views/          # Admin page templates
│   ├── admin/css/            # Admin styles
│   ├── admin/js/             # Admin scripts
│   └── languages/            # Translations (EN default, BS)
├── docker-compose.yml        # Dev environment
├── scripts/setup.sh          # Initial setup script
├── Makefile                  # Dev commands
└── thoughts/plans/           # Implementation plans
```

## Development Environment

```bash
make setup    # First time: start Docker + install WP/WooCommerce + test data
make up       # Start containers
make down     # Stop containers
make clean    # Remove all data (careful!)
make activate # Activate plugin
make status   # Check plugin status
make pot      # Generate translation template
```

**URLs:**
- WordPress: http://localhost:8088 (admin / admin123)
- phpMyAdmin: http://localhost:8081 (wordpress / wordpress)

## Plugin Architecture

### Classes (in `includes/`)

| Class | File | Purpose |
|-------|------|---------|
| `Zicer_API_Client` | class-zicer-api-client.php | API requests, rate limiting |
| `Zicer_Settings` | class-zicer-settings.php | Admin settings pages |
| `Zicer_Sync` | class-zicer-sync.php | Core sync logic |
| `Zicer_Product_Meta` | class-zicer-product-meta.php | Product meta box |
| `Zicer_Category_Map` | class-zicer-category-map.php | Category mapping |
| `Zicer_Queue` | class-zicer-queue.php | Background job queue |
| `Zicer_Logger` | class-zicer-logger.php | Logging utility |

### Database Tables

- `wp_zicer_sync_queue` - Background job queue
- `wp_zicer_sync_log` - Sync activity log

### Product Meta Keys

- `_zicer_listing_id` - ZICER listing UUID
- `_zicer_last_sync` - Last sync timestamp
- `_zicer_sync_error` - Last error message
- `_zicer_condition` - Product condition override
- `_zicer_exclude` - Exclude from sync (yes/no)
- `_zicer_synced_images` - Hash of synced images

### Options (wp_options)

- `zicer_api_token` - API token (zic_xxx format)
- `zicer_connection_status` - Connection info array
- `zicer_default_region` / `zicer_default_city` - Location IDs
- `zicer_realtime_sync` - Enable real-time sync
- `zicer_delete_on_unavailable` - Auto-delete when out of stock
- `zicer_category_mapping` - WC→ZICER category map
- `zicer_description_mode` - product/replace/prepend/append
- `zicer_description_template` - Template with {variables}
- `zicer_truncate_title` / `zicer_title_max_length`
- `zicer_sync_images` / `zicer_max_images`
- `zicer_price_conversion` - Currency multiplier
- `zicer_default_condition` - Default product condition
- `zicer_stock_threshold` - Min stock to be "available"

## ZICER API Reference

**Base URL:** `https://api.zicer.ba/api`
**Auth:** `Authorization: Bearer zic_xxx`

### Key Endpoints

```
GET  /me                    - Validate token, get user
GET  /shop                  - Get user's shop (has region/city)
GET  /categories            - List categories
GET  /categories/suggest    - AI category suggestions
GET  /regions               - List regions
GET  /listings              - List user's listings
POST /listings              - Create listing
PATCH /listings/{id}        - Update listing (merge-patch+json)
DELETE /listings/{id}       - Delete listing
POST /listings/{id}/media   - Upload image (multipart)
```

### Listing Payload

```php
[
    'title' => 'Product name',
    'description' => '<p>HTML description</p>',
    'shortDescription' => 'Brief text',
    'sku' => 'SKU123',
    'price' => 99,  // Integer, KM
    'condition' => 'Novo',  // Novo|Korišteno|Otvoreno|Popravljeno|Nije ispravno
    'type' => 'Prodaja',
    'isActive' => true,
    'isAvailable' => true,
    'category' => '/api/categories/uuid',
    'region' => '/api/regions/uuid',
    'city' => '/api/cities/uuid',
]
```

### Rate Limits

- 60 requests per minute
- Headers: `X-RateLimit-Remaining`, `X-RateLimit-Reset`
- 429 response when exceeded

## WooCommerce Hooks Used

```php
// Product changes
add_action('woocommerce_update_product', ...);
add_action('woocommerce_new_product', ...);
add_action('before_delete_post', ...);
add_action('woocommerce_product_set_stock', ...);

// Admin
add_action('add_meta_boxes', ...);
add_action('woocommerce_process_product_meta', ...);
add_action('woocommerce_product_options_general_product_data', ...);
```

## Coding Standards

- **Language:** English for code, comments, variables
- **i18n:** Use `__()`, `esc_html__()` with text domain `zicer-woo-sync`
- **Classes:** `Zicer_` prefix, one class per file
- **Hooks:** Check `is_wp_error()` on API responses
- **Security:** Use nonces, capability checks, sanitization

## Testing Workflow

1. Edit files in `zicer-woo-sync/` (mounted in Docker)
2. Changes are live immediately
3. Test at http://localhost:8088/wp-admin
4. Check logs: `make logs`
5. Create test product: `make test-product`

## Implementation Plan

See `thoughts/plans/2025-12-09-zicer-woo-sync-plugin.md` for detailed implementation phases.

## Quick Commands

```bash
# WP-CLI in container
docker exec zicer-woo-cli wp plugin list
docker exec zicer-woo-cli wp option get zicer_api_token

# View plugin tables
docker exec zicer-woo-cli wp db query "SELECT * FROM wp_zicer_sync_queue"
docker exec zicer-woo-cli wp db query "SELECT * FROM wp_zicer_sync_log"

# Deactivate/reactivate to re-run activation hook
docker exec zicer-woo-cli wp plugin deactivate zicer-woo-sync
docker exec zicer-woo-cli wp plugin activate zicer-woo-sync
```
