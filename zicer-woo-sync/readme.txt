=== ZICER WooCommerce Sync ===
Contributors: optimizedoo
Tags: woocommerce, marketplace, sync, zicer, products
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Synchronize WooCommerce products with ZICER marketplace platform.
Sinkronizacija WooCommerce proizvoda sa ZICER marketplace platformom.

== Description ==

**English**

ZICER WooCommerce Sync allows you to seamlessly synchronize your WooCommerce products with the ZICER marketplace (zicer.ba).

**Features:**

* Connect to ZICER platform via API token
* Real-time automatic product synchronization
* Bulk sync for existing products
* WooCommerce to ZICER category mapping
* Configurable description templates
* Image synchronization
* API rate limit compliance
* Optional title truncation
* Auto-remove unavailable products

---

**Bosanski**

ZICER WooCommerce Sync omogućava jednostavnu sinkronizaciju vaših WooCommerce proizvoda sa ZICER marketplace platformom (zicer.ba).

**Mogućnosti:**

* Povezivanje sa ZICER platformom putem API tokena
* Automatska sinkronizacija proizvoda u realnom vremenu
* Skupna sinkronizacija postojećih proizvoda
* Mapiranje WooCommerce kategorija na ZICER kategorije
* Prilagodljivi predlošci opisa
* Sinkronizacija slika
* Poštivanje API ograničenja
* Opciono skraćivanje naslova
* Automatsko uklanjanje nedostupnih proizvoda

== Installation ==

**English**

1. Download the latest release from [GitHub Releases](https://github.com/optimize-doo/zicer-woocommerce-plugin/releases)
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**
3. Upload the `zicer-woo-sync.zip` file
4. Click **Install Now**, then **Activate**

Alternatively, extract the zip and upload the `zicer-woo-sync` folder to `/wp-content/plugins/` via FTP.

---

**Bosanski**

1. Preuzmite najnoviju verziju sa [GitHub Releases](https://github.com/optimize-doo/zicer-woocommerce-plugin/releases)
2. U WordPress adminu, idite na **Dodaci → Dodaj novi → Učitaj dodatak**
3. Učitajte `zicer-woo-sync.zip` datoteku
4. Kliknite **Instaliraj**, zatim **Aktiviraj**

Alternativno, raspakirajte zip i učitajte `zicer-woo-sync` folder u `/wp-content/plugins/` putem FTP-a.

== Configuration ==

**English**

1. **Create a token**: Log in to [ZICER](https://zicer.ba), go to your profile settings, and generate an API token
2. **Token format**: Tokens start with `zic_` prefix (e.g., `zic_abc123...`)
3. **Enter token**: In WordPress admin, go to **ZICER Sync → Settings** and paste your token
4. **Verify connection**: Click "Connect" to validate the token and retrieve your shop info

---

**Bosanski**

1. **Kreirajte token**: Prijavite se na [ZICER](https://zicer.ba), idite na postavke profila i generirajte API token
2. **Format tokena**: Tokeni počinju sa `zic_` prefiksom (npr. `zic_abc123...`)
3. **Unesite token**: U WordPress adminu, idite na **ZICER Sync → Postavke** i zalijepite vaš token
4. **Provjerite konekciju**: Kliknite "Poveži" da potvrdite token i preuzmete informacije o vašoj radnji

== Frequently Asked Questions ==

= What is ZICER? / Šta je ZICER? =

ZICER (zicer.ba) is a marketplace platform for buying and selling products in Bosnia and Herzegovina.

ZICER (zicer.ba) je marketplace platforma za kupovinu i prodaju proizvoda u Bosni i Hercegovini.

= Do I need a ZICER account? / Trebam li ZICER račun? =

Yes, you need a ZICER account with a shop to use this plugin. You can create one at zicer.ba.

Da, potreban vam je ZICER račun sa radnjom za korištenje ovog dodatka. Možete ga kreirati na zicer.ba.

= How do I get an API token? / Kako dobiti API token? =

Log in to your ZICER account, go to profile settings, and generate an API token. Tokens start with `zic_` prefix.

Prijavite se na vaš ZICER račun, idite na postavke profila i generirajte API token. Tokeni počinju sa `zic_` prefiksom.

= Will disconnecting delete my listings? / Hoće li odspajanje obrisati moje oglase? =

No, disconnecting the plugin does not delete synced listings from ZICER. You can manage them directly on zicer.ba.

Ne, odspajanje dodatka ne briše sinkronizirane oglase sa ZICER-a. Možete ih upravljati direktno na zicer.ba.

== Changelog ==

= 1.0.1 =
* Help page with user manual / Stranica pomoći sa korisničkim uputstvom
* Plugin readme.txt for WordPress plugin details / Plugin readme.txt za detalje WordPress dodatka
* Bilingual documentation / Dvojezična dokumentacija
* GPL-2.0 license file / GPL-2.0 licenca

= 1.0.0 =
* Initial release / Inicijalno izdanje
* Product synchronization with ZICER marketplace / Sinkronizacija proizvoda sa ZICER marketplace-om
* Category mapping / Mapiranje kategorija
* Image synchronization / Sinkronizacija slika
* Bulk sync functionality / Skupna sinkronizacija
* Real-time sync option / Opcija sinkronizacije u realnom vremenu
* Bosnian and English translations / Bosanski i engleski prijevodi

== Upgrade Notice ==

= 1.0.1 =
Added help page and documentation.
Dodana stranica pomoći i dokumentacija.

= 1.0.0 =
Initial release of ZICER WooCommerce Sync plugin.
Inicijalno izdanje ZICER WooCommerce Sync dodatka.
