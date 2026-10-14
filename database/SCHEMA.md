# Datenbankschema — dormed-shop

> **Wartungspflicht:** Diese Datei muss bei jeder Schemaänderung (Migration, Spalte, Index, FK) sofort aktualisiert werden. Sie ist die einzige Quelle der Wahrheit für die DB-Struktur und wird automatisch in den Agent-Kontext geladen.

Engine: **SQLite** (Entwicklung) — Produktionsumgebung: MySQL/MariaDB-kompatibel

---

## Tabellen-Übersicht

| Tabelle               | Zweck                                      |
|-----------------------|--------------------------------------------|
| `users`               | Veraltet – Admin kommt aus der Env (ADMIN_*) |
| `customers`           | Shop-Kunden (Frontend-Auth via Fortify)    |
| `products`            | Produkte                                   |
| `product_variants`    | Produktvarianten mit absolutem Endpreis    |
| `product_images`      | Produktbilder (max. 5, sortiert)           |
| `addresses`           | Adressen (Customer-Profil)                 |
| `manufacturers`       | Hersteller (FK von `products`)             |
| `payments`            | PayPal-Transaktionen (FK zu `orders`)      |
| `orders`              | Bestellungen (FK zu `customers`)           |
| `order_items`         | Bestellpositionen                          |
| `ratings`             | Produktbewertungen (FK zu `products`)      |
| `passkeys`            | WebAuthn-Passkeys (FK zu `customers`)      |
| `sessions`            | Laravel-Session-Store                      |
| `cache`               | Laravel-Cache                              |
| `cache_locks`         | Laravel-Cache-Locks                        |
| `jobs`                | Queue-Jobs                                 |
| `job_batches`         | Queue-Job-Batches                          |
| `failed_jobs`         | Fehlgeschlagene Queue-Jobs                 |
| `password_reset_tokens` | Passwort-Reset-Tokens                    |
| `migrations`          | Laravel-Migrationsstatus                   |

---

## users

**Veraltet.** Wird nicht mehr gelesen: Der einzige Admin kommt aus der Env (`ADMIN_EMAIL`, `ADMIN_PASSWORD`, siehe `config/auth.php` → `admin`). Die Tabelle kann nach dem Umstieg per Migration entfernt werden.

| Spalte           | Typ           | Nullable | Default | Hinweis              |
|------------------|---------------|----------|---------|----------------------|
| `id`             | integer       | NO       | —       | PK, autoincrement    |
| `name`           | varchar       | NO       | —       |                      |
| `email`          | varchar       | NO       | —       | UNIQUE               |
| `password`       | varchar       | NO       | —       |                      |
| `is_admin`       | tinyint(1)    | NO       | `0`     |                      |
| `remember_token` | varchar       | YES      | —       |                      |
| `created_at`     | datetime      | YES      | —       |                      |
| `updated_at`     | datetime      | YES      | —       |                      |

---

## customers

Shop-Kunden. Fortify-Auth, Passkeys, optionale 2FA.

| Spalte                      | Typ      | Nullable | Default | Hinweis           |
|-----------------------------|----------|----------|---------|-------------------|
| `id`                        | integer  | NO       | —       | PK, autoincrement |
| `name`                      | varchar  | NO       | —       |                   |
| `email`                     | varchar  | NO       | —       | UNIQUE            |
| `email_verified_at`         | datetime | YES      | —       |                   |
| `password`                  | varchar  | NO       | —       |                   |
| `remember_token`            | varchar  | YES      | —       |                   |
| `two_factor_secret`         | text     | YES      | —       |                   |
| `two_factor_recovery_codes` | text     | YES      | —       |                   |
| `two_factor_confirmed_at`   | datetime | YES      | —       |                   |
| `created_at`                | datetime | YES      | —       |                   |
| `updated_at`                | datetime | YES      | —       |                   |

Relationen:
- `1──N addresses` — Adressen im Customer-Profil
- `1──N orders` — Bestellungen

---

## addresses

Adressen, die von einem Customer gespeichert werden. Kein FK zu `orders` —
Bestellungen speichern ihre Adressen als JSON-Snapshot (immutable).

| Spalte          | Typ       | Nullable | Default     | Hinweis                           |
|-----------------|-----------|----------|-------------|-----------------------------------|
| `id`            | integer   | NO       | —           | PK, autoincrement                 |
| `customer_id`   | integer   | NO       | —           | FK → `customers.id` CASCADE       |
| `type`          | varchar   | NO       | `'both'`    | shipping / billing / both         |
| `company`       | varchar   | YES      | —           | Firmenname (für B2B)              |
| `salutation`    | varchar   | YES      | —           | Herr / Frau                       |
| `first_name`    | varchar   | NO       | —           |                                   |
| `last_name`     | varchar   | NO       | —           |                                   |
| `street`        | varchar   | NO       | —           |                                   |
| `house_number`  | varchar   | NO       | —           | Getrennt von Straße (z.B. "12a")  |
| `address_line2` | varchar   | YES      | —           | c/o, Adresszusatz                 |
| `zip`           | varchar   | NO       | —           |                                   |
| `city`          | varchar   | NO       | —           |                                   |
| `country`       | varchar   | NO       | `'DE'`      | ISO 3166-1 alpha-2                |
| `phone`         | varchar   | YES      | —           |                                   |
| `is_default`    | boolean   | NO       | `false`     | Standardadresse des Kunden        |
| `created_at`    | datetime  | YES      | —           |                                   |
| `updated_at`    | datetime  | YES      | —           |                                   |

Indexe:
- `addresses_customer_id_type_index` auf (`customer_id`, `type`)
- `addresses_zip_index` auf (`zip`)

---

## manufacturers

| Spalte          | Typ      | Nullable | Default | Hinweis           |
|-----------------|----------|----------|---------|-------------------|
| `id`            | integer  | NO       | —       | PK, autoincrement |
| `name`          | varchar  | NO       | —       |                   |
| `country`       | varchar  | YES      | —       |                   |
| `contact_email` | varchar  | YES      | —       |                   |
| `created_at`    | datetime | YES      | —       |                   |
| `updated_at`    | datetime | YES      | —       |                   |

---

## products

| Spalte            | Typ      | Nullable | Default | Hinweis                          |
|-------------------|----------|----------|---------|----------------------------------|
| `id`              | integer  | NO       | —       | PK, autoincrement                |
| `manufacturer_id` | integer  | YES      | —       | FK → `manufacturers.id` CASCADE  |
| `category_slug`   | varchar  | YES      | —       | INDEX; Slug aus `config/categories.php` (im Shop Pflicht) |
| `name`            | varchar  | NO       | —       | nach dem Anlegen unveränderlich  |
| `slug`            | varchar  | YES      | —       | UNIQUE; einmal beim Anlegen aus dem Namen erzeugt, Teil der Shop-URL |
| `description`     | text     | YES      | —       |                                  |
| `technical_specs` | json     | YES      | —       | Technische Daten (geordnete Liste) |
| `specialties`     | json     | YES      | —       | Fachrichtungs-Slugs aus `config/specialties.php` |
| `price`           | numeric  | NO       | —       | decimal(10,2)                    |
| `is_available`    | boolean  | NO       | `true`  | Shop-Verfügbarkeit               |
| `created_at`      | datetime | YES      | —       |                                  |
| `updated_at`      | datetime | YES      | —       |                                  |

---

## product_variants

Produktvarianten. `price` ist ein absoluter Endpreis, kein Auf-/Abschlag zum Produktpreis.

| Spalte       | Typ               | Nullable | Default | Hinweis                    |
|--------------|-------------------|----------|---------|----------------------------|
| `id`         | integer           | NO       | —       | PK, autoincrement          |
| `product_id` | integer           | NO       | —       | FK → `products.id` CASCADE |
| `label`      | varchar           | NO       | —       | Variantenbezeichnung       |
| `price`      | numeric           | NO       | —       | decimal(10,2)              |
| `sort_order` | smallint unsigned | NO       | `0`     | Sortierung im Frontend     |
| `is_default` | boolean           | NO       | `false` | Standardvariante           |
| `created_at` | datetime          | YES      | —       |                            |
| `updated_at` | datetime          | YES      | —       |                            |

---

## product_images

Produktbilder. Mehrere pro Produkt, Reihenfolge via `sort_order` (0 = Hauptbild, max. 5 pro Produkt). Jedes Bild liegt als WebP (`path`) und AVIF (gleicher Pfad mit Endung `.avif`) im Storage.

| Spalte       | Typ              | Nullable | Default | Hinweis                            |
|--------------|------------------|----------|---------|------------------------------------|
| `id`         | integer          | NO       | —       | PK, autoincrement                  |
| `product_id` | integer          | NO       | —       | FK → `products.id` CASCADE         |
| `path`       | varchar          | NO       | —       | Storage-Pfad der WebP-Datei (Public-Disk); AVIF liegt daneben |
| `width`      | smallint unsigned| YES      | —       | Breite in Pixeln (NULL bis `product-images:convert`) |
| `height`     | smallint unsigned| YES      | —       | Höhe in Pixeln (NULL bis `product-images:convert`)   |
| `sort_order` | smallint unsigned| NO       | `0`     | 0 = Hauptbild                      |
| `created_at` | datetime         | YES      | —       |                                    |
| `updated_at` | datetime         | YES      | —       |                                    |

Indexe:
- `product_images_product_id_sort_order_index` auf (`product_id`, `sort_order`)

---

## orders

Bestellungen mit JSON-Adress-Snapshots (immutable zum Zeitpunkt der Bestellung).

| Spalte                      | Typ      | Nullable | Default     | Hinweis                            |
|-----------------------------|----------|----------|-------------|-------------------------------------|
| `id`                        | integer  | NO       | —           | PK, autoincrement                   |
| `customer_id`               | integer  | **YES**  | —           | FK → `customers.id` **SET NULL** — Historie überlebt Kontolöschung (GoBD) |
| `status`                    | varchar  | NO       | `'pending'` | pending/paid/cancelled/failed/refunded |
| `payment_method`            | varchar  | YES      | —           | invoice / paypal / sepa / card      |
| `is_test`                   | boolean  | NO       | `false`     | true = Sandbox-/Testbestellung      |
| `total_amount`              | numeric  | NO       | —           | decimal(10,2)                       |
| `shipping_amount`           | numeric  | NO       | `0.00`      | decimal(10,2), Versandkosten        |
| `shipping_method_key`       | varchar(64) | YES   | —           | Snapshot: Key aus `config/shipping.php` |
| `shipping_method_name`      | varchar  | YES      | —           | Snapshot: Name der Versandart       |
| `shipping_address`          | JSON     | YES      | —           | Snapshot der Lieferadresse          |
| `billing_address`           | JSON     | YES      | —           | Snapshot der Rechnungsadresse       |
| `created_at`                | datetime | YES      | —           |                                     |
| `updated_at`                | datetime | YES      | —           |                                     |

> `customer_id` ist seit Migration `2026_07_07_175921` nullable mit
> `ON DELETE SET NULL` (Bestellhistorie überlebt die Kontolöschung).

> Versandarten stehen in `config/shipping.php`, nicht in der Datenbank
> (`.docs/adr/0006-admin-nur-inhaltspflege.md`). `shipping_method_key` und
> `shipping_method_name` sind bei Bestellungen vor Migration `2026_10_14_090000`
> leer; angezeigt wird immer der Snapshot, nie die aktuelle Config.

JSON-Struktur (shipping_address / billing_address):
```json
{
    "company": "Firma GmbH",
    "salutation": "Herr",
    "first_name": "Max",
    "last_name": "Mustermann",
    "street": "Musterstraße",
    "house_number": "12a",
    "address_line2": "c/o Empfang",
    "zip": "12345",
    "city": "Musterstadt",
    "country": "DE",
    "phone": "+49 176 12345678"
}
```

---

## order_items

Einzelne Positionen einer Bestellung.

| Spalte          | Typ       | Nullable | Default | Hinweis                       |
|-----------------|-----------|----------|---------|-------------------------------|
| `id`            | integer   | NO       | —       | PK, autoincrement             |
| `order_id`      | integer   | NO       | —       | FK → `orders.id` CASCADE      |
| `product_id`    | integer   | YES      | —       | nullable (Produkt gelöscht)   |
| `product_name`  | varchar   | NO       | —       |                               |
| `unit_price`    | numeric   | NO       | —       | decimal(10,2)                 |
| `quantity`      | integer   | NO       | —       |                               |
| `created_at`    | datetime  | YES      | —       |                               |
| `updated_at`    | datetime  | YES      | —       |                               |

---

## payments

PayPal-Transaktionen, verknüpft mit einer Bestellung.

| Spalte              | Typ      | Nullable | Default     | Hinweis                                |
|---------------------|----------|----------|-------------|----------------------------------------|
| `id`                | integer  | NO       | —           | PK, autoincrement                      |
| `order_id`          | integer  | NO       | —           | FK → `orders.id` CASCADE               |
| `paypal_order_id`   | varchar  | YES      | —           | PayPal-Order-ID (z. B. `1AB23456`)     |
| `paypal_payer_id`   | varchar  | YES      | —           | Payer-ID (z. B. `PAYER123456`)         |
| `paypal_capture_id` | varchar  | YES      | —           | Capture-/Transaktions-ID               |
| `status`            | varchar  | NO       | `'CREATED'` | CREATED / APPROVED / COMPLETED / FAILED / REFUNDED |
| `amount`            | numeric  | NO       | —           | decimal(10,2)                          |
| `currency`          | varchar  | NO       | `'EUR'`     | ISO 4217                               |
| `fee`               | numeric  | YES      | —           | decimal(10,2), PayPal-Gebühr           |
| `payer_email`       | varchar  | YES      | —           | Käufer-E-Mail                          |
| `payer_name`        | varchar  | YES      | —           | Käufer-Name                            |
| `response_data`     | json     | YES      | —           | Roher API-Response (Auditing)          |
| `created_at`        | datetime | YES      | —           |                                        |
| `updated_at`        | datetime | YES      | —           |                                        |

Indexe:
- `payments_paypal_order_id_index` auf (`paypal_order_id`)
- `payments_status_index` auf (`status`)

---

## ratings

Öffentliche Produktbewertungen ohne Login.

| Spalte       | Typ      | Nullable | Default | Hinweis                     |
|--------------|----------|----------|---------|-----------------------------|
| `id`         | integer  | NO       | —       | PK, autoincrement           |
| `product_id` | integer  | NO       | —       | FK → `products.id` CASCADE  |
| `stars`      | tinyint  | NO       | —       | Wertebereich 1–5            |
| `content`    | text     | NO       | —       | Bewertungstext              |
| `created_at` | datetime | YES      | —       |                             |
| `updated_at` | datetime | YES      | —       |                             |

Indexe:
- `ratings_product_id_created_at_index` auf (`product_id`, `created_at`)

---

## passkeys

WebAuthn-Passkeys, gehören zu einem `Customer`.

| Spalte          | Typ      | Nullable | Default | Hinweis                       |
|-----------------|----------|----------|---------|-------------------------------|
| `id`            | integer  | NO       | —       | PK, autoincrement             |
| `customer_id`   | integer  | NO       | —       | FK → `customers.id` CASCADE   |
| `name`          | varchar  | NO       | —       |                               |
| `credential_id` | varchar  | NO       | —       | UNIQUE                        |
| `credential`    | text     | NO       | —       | JSON                          |
| `last_used_at`  | datetime | YES      | —       |                               |
| `created_at`    | datetime | YES      | —       |                               |
| `updated_at`    | datetime | YES      | —       |                               |

Indexe:
- `passkeys_customer_id_index` auf (`customer_id`)

---

## sessions

| Spalte          | Typ     | Nullable | Hinweis        |
|-----------------|---------|----------|----------------|
| `id`            | varchar | NO       | PK             |
| `user_id`       | integer | YES      | nullable, enthält `customers.id` (Name von Laravel vorgegeben) |
| `ip_address`    | varchar | YES      |                |
| `user_agent`    | text    | YES      |                |
| `payload`       | text    | NO       |                |
| `last_activity` | integer | NO       | Index          |

Indexe:
- `sessions_user_id_index` auf (`user_id`)
- `sessions_last_activity_index` auf (`last_activity`)

---

## cache

Laravel-Cache.

| Spalte       | Typ        | Nullable | Hinweis |
|--------------|------------|----------|---------|
| `key`        | varchar    | NO       | PK      |
| `value`      | mediumtext | NO       |         |
| `expiration` | bigint     | NO       | Index   |

---

## cache_locks

Laravel-Cache-Locks.

| Spalte       | Typ     | Nullable | Hinweis |
|--------------|---------|----------|---------|
| `key`        | varchar | NO       | PK      |
| `owner`      | varchar | NO       |         |
| `expiration` | bigint  | NO       | Index   |

---

## jobs

Laravel-Queue-Jobs.

| Spalte         | Typ               | Nullable | Hinweis           |
|----------------|-------------------|----------|-------------------|
| `id`           | integer           | NO       | PK, autoincrement |
| `queue`        | varchar           | NO       | Index             |
| `payload`      | longtext          | NO       |                   |
| `attempts`     | smallint unsigned | NO       |                   |
| `reserved_at`  | integer unsigned  | YES      |                   |
| `available_at` | integer unsigned  | NO       |                   |
| `created_at`   | integer unsigned  | NO       |                   |

---

## job_batches

Laravel-Queue-Batches.

| Spalte           | Typ        | Nullable | Hinweis |
|------------------|------------|----------|---------|
| `id`             | varchar    | NO       | PK      |
| `name`           | varchar    | NO       |         |
| `total_jobs`     | integer    | NO       |         |
| `pending_jobs`   | integer    | NO       |         |
| `failed_jobs`    | integer    | NO       |         |
| `failed_job_ids` | longtext   | NO       |         |
| `options`        | mediumtext | YES      |         |
| `cancelled_at`   | integer    | YES      |         |
| `created_at`     | integer    | NO       |         |
| `finished_at`    | integer    | YES      |         |

---

## failed_jobs

Fehlgeschlagene Laravel-Queue-Jobs.

| Spalte       | Typ      | Nullable | Hinweis           |
|--------------|----------|----------|-------------------|
| `id`         | integer  | NO       | PK, autoincrement |
| `uuid`       | varchar  | NO       | UNIQUE            |
| `connection` | varchar  | NO       |                   |
| `queue`      | varchar  | NO       |                   |
| `payload`    | longtext | NO       |                   |
| `exception`  | longtext | NO       |                   |
| `failed_at`  | datetime | NO       | Default: CURRENT_TIMESTAMP |

Indexe:
- `failed_jobs_connection_queue_failed_at_index` auf (`connection`, `queue`, `failed_at`)

---

## password_reset_tokens

Passwort-Reset-Tokens für Shop-Kunden.

| Spalte       | Typ      | Nullable | Hinweis |
|--------------|----------|----------|---------|
| `email`      | varchar  | NO       | PK      |
| `token`      | varchar  | NO       |         |
| `created_at` | datetime | YES      |         |

---

## migrations

Laravel-Migrationsstatus.

| Spalte      | Typ     | Nullable | Hinweis           |
|-------------|---------|----------|-------------------|
| `id`        | integer | NO       | PK, autoincrement |
| `migration` | varchar | NO       | Dateiname/Klasse   |
| `batch`     | integer | NO       | Migrations-Batch   |

---

## Fremdschlüssel-Übersicht

| Tabelle       | Spalte              | Ziel                   | ON DELETE |
|---------------|---------------------|------------------------|-----------|
| `products`    | `manufacturer_id`   | `manufacturers.id`     | CASCADE   |
| `product_variants` | `product_id`    | `products.id`          | CASCADE   |
| `addresses`   | `customer_id`       | `customers.id`         | CASCADE   |
| `orders`      | `customer_id`       | `customers.id`         | SET NULL  |
| `order_items` | `order_id`          | `orders.id`            | CASCADE   |
| `payments`    | `order_id`          | `orders.id`            | CASCADE   |
| `product_images` | `product_id`     | `products.id`          | CASCADE   |
| `ratings`     | `product_id`        | `products.id`          | CASCADE   |
| `passkeys`    | `customer_id`       | `customers.id`         | CASCADE   |
