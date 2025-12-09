# ZICER WooCommerce Sync Plugin

WordPress/WooCommerce plugin za sinhronizaciju proizvoda sa ZICER marketplace platformom.

## Razvoj

### Pokretanje test okruženja

```bash
# Pokreni Docker kontejnere i instaliraj WordPress + WooCommerce
make setup

# Ili samo pokreni kontejnere (ako je već instalirano)
make up
```

### Pristup

- **WordPress**: http://localhost:8080
- **Admin panel**: http://localhost:8080/wp-admin
  - Username: `admin`
  - Password: `admin123`
- **phpMyAdmin**: http://localhost:8081
  - Username: `wordpress`
  - Password: `wordpress`

### Korisne komande

```bash
make up          # Pokreni kontejnere
make down        # Zaustavi kontejnere
make logs        # Prikaži logove
make shell       # Shell u WordPress kontejner
make wp          # WP-CLI shell
make db-shell    # MySQL shell
make activate    # Aktiviraj plugin
make deactivate  # Deaktiviraj plugin
make clean       # Obriši sve (uključujući bazu!)
```

### Struktura projekta

```
zicer.woo/
├── docker-compose.yml     # Docker konfiguracija
├── Makefile               # Korisne komande
├── scripts/
│   └── setup.sh           # Skripta za inicijalno podešavanje
├── zicer-woo-sync/        # WordPress plugin
│   ├── zicer-woo-sync.php # Glavni plugin fajl
│   ├── includes/          # PHP klase
│   ├── admin/             # Admin resursi
│   │   ├── css/
│   │   ├── js/
│   │   └── views/
│   └── languages/         # Prijevodi
└── thoughts/
    └── plans/             # Implementacijski planovi
```

## Funkcionalnosti

- Povezivanje sa ZICER platformom putem API tokena
- Automatska sinhronizacija proizvoda u realnom vremenu
- Bulk sinhronizacija postojećih proizvoda
- Mapiranje WooCommerce kategorija na ZICER kategorije
- Konfigurabilni opisi (šabloni)
- Sinhronizacija slika
- Poštovanje API rate limita
- Opciono skraćivanje naslova
- Automatsko uklanjanje nedostupnih proizvoda

## Dokumentacija

Detaljan implementacijski plan: `thoughts/plans/2025-12-09-zicer-woo-sync-plugin.md`
