# ZICER WooCommerce Sync Plugin

## Quick Reference

**What:** WordPress/WooCommerce plugin syncing products to ZICER marketplace (zicer.ba)
**Stack:** PHP 7.4+, WooCommerce 5.0+, Docker dev environment
**API:** `https://api.zicer.ba/api` with Bearer token auth (`zic_xxx`)

## File Locations

```
zicer-woo-sync/
├── zicer-woo-sync.php           # Entry point, update checker init
├── includes/
│   ├── class-zicer-api-client.php   # API requests, rate limiting (60/min)
│   ├── class-zicer-settings.php     # Admin pages, AJAX handlers, JS localization
│   ├── class-zicer-sync.php         # Core sync logic
│   ├── class-zicer-product-meta.php # Product meta box UI
│   ├── class-zicer-category-map.php # WC→ZICER category mapping
│   ├── class-zicer-queue.php        # Background job queue
│   └── class-zicer-logger.php       # Logging utility
├── admin/
│   ├── views/                   # PHP templates (settings-page, sync-status, sync-log, category-mapping)
│   ├── js/admin.js              # Admin JS (ZicerModal, AJAX calls)
│   └── css/admin.css            # Admin styles
├── languages/                   # EN default, BS translation
└── vendor/                      # Composer (plugin-update-checker for GitHub updates)
```

## Critical Patterns

### JavaScript Strings (i18n)
**NEVER hardcode strings in JS.** All strings via `zicerAdmin.strings.*`:
```php
// class-zicer-settings.php → enqueue_scripts()
wp_localize_script('zicer-admin', 'zicerAdmin', [
    'strings' => ['key' => __('Text', 'zicer-woo-sync')]
]);
```
```javascript
// admin.js
zicerAdmin.strings.key
```

### Modals
**NEVER use native `alert()`/`confirm()`.** Use `ZicerModal`:
```javascript
ZicerModal.alert(message, callback);
ZicerModal.confirm(message, onConfirm, onCancel);
ZicerModal.custom({ title, content, buttons, width });
```

### AJAX Handlers
```php
// 1. Register in init()
add_action('wp_ajax_zicer_action_name', [__CLASS__, 'ajax_action_name']);

// 2. Handler
public static function ajax_action_name() {
    check_ajax_referer('zicer_admin', 'nonce');
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Permission denied.', 'zicer-woo-sync'));
    }
    // ... logic ...
    wp_send_json_success($data);
}
```

## Data Model

### Product Meta (`postmeta`)
| Key | Value |
|-----|-------|
| `_zicer_listing_id` | ZICER UUID |
| `_zicer_last_sync` | Timestamp |
| `_zicer_sync_error` | Error message |
| `_zicer_condition` | Condition override |
| `_zicer_exclude` | yes/no |
| `_zicer_synced_images` | Image hash |

### Options (`wp_options`)
| Key | Purpose |
|-----|---------|
| `zicer_api_token` | API token (zic_xxx) |
| `zicer_connection_status` | Connection info array |
| `zicer_connected_user_id/email` | Persists across disconnects |
| `zicer_terms_accepted` | Cleared on disconnect |
| `zicer_default_region/city` | Location IDs |
| `zicer_realtime_sync` | Enable auto-sync |
| `zicer_delete_on_unavailable` | Auto-delete OOS |
| `zicer_category_mapping` | WC→ZICER map |
| `zicer_description_mode` | product/replace/prepend/append |
| `zicer_description_template` | Template with {variables} |
| `zicer_truncate_title/title_max_length` | Title truncation |
| `zicer_sync_images/max_images` | Image sync settings |
| `zicer_price_conversion` | Currency multiplier |
| `zicer_default_condition` | Default condition |
| `zicer_stock_threshold` | Min stock for available |

### Custom Tables
- `wp_zicer_sync_queue` - Background jobs
- `wp_zicer_sync_log` - Activity log

## API Reference

### Endpoints
```
GET  /me                    → Validate token
GET  /shop                  → User's shop (has region/city)
GET  /categories            → List categories
GET  /categories/suggest    → AI category suggestions
GET  /regions               → List regions
GET  /listings              → User's listings
POST /listings              → Create listing
PATCH /listings/{id}        → Update (merge-patch+json)
DELETE /listings/{id}       → Delete listing
POST /listings/{id}/media   → Upload image (multipart)
```

### Listing Payload
```php
[
    'title' => string,
    'description' => string (HTML),
    'shortDescription' => string,
    'sku' => string,
    'price' => int (KM),
    'condition' => 'Novo|Korišteno|Otvoreno|Popravljeno|Nije ispravno',
    'type' => 'Prodaja',
    'isActive' => bool,
    'isAvailable' => bool,
    'category' => '/api/categories/uuid',
    'region' => '/api/regions/uuid',
    'city' => '/api/cities/uuid',
]
```

### Rate Limits
60 req/min. Headers: `X-RateLimit-Remaining`, `X-RateLimit-Reset`. 429 when exceeded.

## WooCommerce Hooks
```php
// Product changes
woocommerce_update_product, woocommerce_new_product, before_delete_post, woocommerce_product_set_stock

// Admin UI
add_meta_boxes, woocommerce_process_product_meta, woocommerce_product_options_general_product_data
```

## Dev Commands

```bash
make setup      # Docker + WP/WooCommerce install
make up/down    # Start/stop containers
make activate   # Activate plugin
make pot        # Generate .pot file
make logs       # View logs
```

**URLs:** WordPress http://localhost:8088 (admin/admin123), phpMyAdmin http://localhost:8081

**Translation compile:** `docker exec zicer-woo-cli wp i18n make-mo /var/www/html/wp-content/plugins/zicer-woo-sync/languages/`

## Common Mistakes

1. Hardcoded JS strings → Use `zicerAdmin.strings.*`
2. Native alerts → Use `ZicerModal`
3. Missing nonce → Every AJAX needs `check_ajax_referer('zicer_admin', 'nonce')`
4. Missing capability check → `current_user_can('manage_woocommerce')`
5. Reading options after update → Read BEFORE updating when comparing old/new values
6. Forgetting .mo compile → Run make-mo after .po changes

## Coding Standards

- English for code/comments/variables
- Text domain: `zicer-woo-sync`
- Class prefix: `Zicer_`
- Always check `is_wp_error()` on API responses
- Use nonces + capability checks + sanitization

## Branding

- Primary: `#facc15` (ZICER yellow)
- Font: Poppins (Google Fonts)
