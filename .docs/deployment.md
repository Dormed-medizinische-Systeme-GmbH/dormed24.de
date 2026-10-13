# CI/CD & Deployment — test.dormed24.de

**Ziel:** Auf den Server muss man **nur noch für einen Sandbox-Reset** (`migrate:fresh --seed`). Alles andere macht der Deploy automatisch beim Push auf `main`.

### Die Workflows (`.github/workflows/`)

| Workflow | Läuft bei | Inhalt |
| --- | --- | --- |
| `tests.yml` | Push (develop/master/workos), PR, **+ `workflow_call`** | PHPStan + svelte-check + Vitest + PHPUnit (gegen **Postgres** + Redis als Service-Container) + Build, **PHP 8.4** |
| `lint.yml` | Push (develop/master/workos), PR, **+ `workflow_call`** | Pint + ESLint + Prettier (`--test`/`--check`, ändert nichts) |
| `deploy.yml` | **Push auf `main`** + manuell (`workflow_dispatch`) | ruft beide Gates auf, deployt danach test.dormed24.de per SSH und baut die Images für dev.dormed24.de (GHCR) und stößt dort den Coolify-Deploy an |
| `docker.yml` | PR mit Änderungen am Container-Setup (`Dockerfile.prod`, `compose.yaml`, `docker/**`, Lockfiles) | baut die Images (`app`, `ssr`), migriert als eigener Schritt, startet App, Queue, Scheduler, SSR, Postgres und Redis und prüft `/up`, SSR, Redis-Cache und Queue-Worker |

**Das „depends on":** `deploy.yml` ruft `tests.yml` und `lint.yml` als *reusable workflows* (`uses:`) auf und wartet per **`needs: [tests, lint]`**. Der Deploy-Job startet nur, wenn **beide grün** sind. Deshalb fehlt `main` bewusst in den `push`-Triggern von tests/lint — sonst liefen sie doppelt.

**PHP-Matrix:** nur `8.4`. `composer.json` verlangt `"php": "^8.4"` — **8.3 kann strukturell nicht funktionieren** und schlug deshalb immer fehl (früher fälschlich in der Matrix).

### Deploy-Modell (Server)

- **SSH-User: `deploy`** — niemals `root`, niemals `www-data`. Kein `sudo -u www-data`.
- **Webserver läuft weiter als `www-data`**; der braucht nur Lese-/Ausführrechte auf die App und **Schreibrechte auf `storage/`, `bootstrap/cache/` + `database/`**. Das `database/`-Verzeichnis ist kein Versehen: `SESSION_DRIVER=database` + `CACHE_STORE=database` heißt, **jeder einzelne Request schreibt in die SQLite-Datei** — fehlt hier Schreibrecht, gibt's einen sofortigen HTTP 500 auf jeder Seite (schon einmal passiert). `deploy.yml` erzwingt das bei jedem Lauf per `chmod -R g+w storage bootstrap/cache database` — läuft als `deploy` (Dateibesitzer), kein `sudo` nötig.
- **App-Pfad:** `/var/www/test.dormed24.de`
- Der Deploy führt aus: `git pull --ff-only` → `composer install --no-dev` → `npm ci` → **`npm run build:ssr`** → `optimize:clear` → **`migrate --force`** → `optimize` → **`inertia:stop-ssr`** (SSR-Reload).
- **`migrate --force` spielt nur NEUE Migrationen ein und löscht nie Daten.** Ein **`migrate:fresh --seed` (Sandbox-Reset) passiert ausschließlich manuell auf dem Server** — bewusst nie im Deploy.
- `git pull --ff-only` bricht ab statt zu mergen, falls der Server divergiert (lieber roter Deploy als Merge-Chaos).

### SSR läuft auf dem Server dauerhaft

- Server-`.env`: **`INERTIA_SSR_ENABLED=true`** (der Schalter lebt in der `.env` — die ist **gitignored**, wird also **nie** gepusht. Ein Push schaltet SSR nirgends ein, er liefert nur die Fähigkeit.)
- Der Deploy baut deshalb **`npm run build:ssr`** (Client **+** `bootstrap/ssr/app.js`).
- Ein **systemd-Dienst** (`inertia-ssr-test.service`, `Restart=always`) hält `php artisan inertia:start-ssr` dauerhaft am Laufen.
- **Reload nach dem Deploy:** `php artisan inertia:stop-ssr` fährt den Prozess per HTTP herunter, systemd startet ihn dank `Restart=always` sofort mit dem **neuen Bundle** neu. Kein `sudo` nötig. Ohne diesen Schritt würde SSR nach dem Deploy weiter den **alten** Code ausliefern.
- Die systemd-Unit läuft als **`deploy`**, nicht `www-data`: Node liegt unter `/home/deploy/.local/node-v22/` (Symlink `/usr/local/bin/node`), `www-data` kommt in ein Home mit Modus `750` typischerweise nicht hinein — mit `User=www-data` stirbt der Dienst mit „node not found" in einer Restart-Schleife (schon einmal passiert). Nebeneffekt: least privilege, der SSR-Prozess braucht ohnehin keine Webserver-Rechte.

### GitHub-Konfiguration

**Secret** (Settings → Secrets → Actions) — nur der private Key ist geheim:

| Name | Inhalt |
| --- | --- |
| `DEPLOY_SSH_KEY` | **privater** SSH-Key (der öffentliche liegt in `/home/deploy/.ssh/authorized_keys`) |

**Variables** (Settings → Variables → Actions) — nicht geheim, deshalb bewusst *keine* Secrets:

| Name | Wert |
| --- | --- |
| `DEPLOY_HOST` | `test.dormed24.de` |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_PATH` | `/var/www/test.dormed24.de` |
| `SSH_KNOWN_HOSTS` | Ausgabe von `ssh-keyscan test.dormed24.de` (öffentlicher Host-Key → verhindert MITM) |

**Kein `DEPLOY_PORT`** — Port 22 ist Default, ein Secret dafür wäre sinnlos.

### Server-Anforderungen (sonst bricht der Deploy)

- **PHP 8.4** (Projekt-Constraint).
- **Node ≥ 20.19** — Vite 8 crasht auf Node 18 (`CustomEvent is not defined`). Node muss im **PATH des `deploy`-Users bei nicht-interaktiver SSH-Session** liegen (nvm in `~/.bashrc` reicht dafür oft **nicht**!). Der Workflow prüft das explizit und bricht mit klarer Meldung ab.
- **Node muss auch für den SSR-Dienst auffindbar sein** — Node **systemweit** installieren bzw. per Symlink in `/usr/local/bin/node` erreichbar machen (nicht ausschließlich per nvm im Home eines einzelnen Users), sonst findet die systemd-Unit ihn nicht.
- **SSR-Dienst muss laufen** (`inertia-ssr-test.service`) — sonst läuft `INERTIA_SSR_ENABLED=true` ins Leere. Siehe SSR-Abschnitt oben.

### Gates

`lint.yml` prüft ohne zu verändern: `composer lint:check` (Pint `--test`), `npm run format:check`, `npm run lint:check`. `tests.yml` führt PHPStan, `npm run types:check` (svelte-check), Vitest und PHPUnit aus. Alle Schritte sind harte Gates für PR und Deploy; `composer ci:check` bündelt sie lokal. Die Wayfinder-Dateien (`@/actions`, `@/routes`) sind gitignored und entstehen erst im Build; lokal scheitern Vitest- und svelte-check-Läufe ohne sie an fehlenden Modulen.

### Sandbox-Reset — kein Skript, nur die nackten Befehle

Kein `update.sh` mehr: `deploy.yml` hält Code/Dependencies/Assets bei jedem Push
aktuell, ein Wrapper-Skript für die restlichen paar Befehle wäre unnötige
Abstraktion für eine seltene, bewusste Handaktion. Der Sandbox-Reset (Katalog auf
sauberen Demo-Stand zurücksetzen) läuft manuell auf dem Server:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed --force
php artisan optimize
php artisan inertia:stop-ssr   # systemd startet den SSR-Prozess per Restart=always neu
```

**`migrate:fresh --seed` passiert ausschließlich hier, manuell** — `deploy.yml`
macht das nie (nutzt `migrate --force`, das nur neue Migrationen einspielt und
nie Daten löscht).

## dev.dormed24.de: Docker-Stack über Coolify

Läuft parallel zu test.dormed24.de, mit eigenen Daten (Postgres, Redis). test bleibt unverändert (Host-PHP, SQLite, SSH-Deploy oben).

### Ablauf

1. Push auf `main` → `tests` + `lint` grün.
2. Job `images` baut `Dockerfile.prod` (Targets `app` und `ssr`) und pusht nach GHCR:
   - `ghcr.io/dormed-medizinische-systeme-gmbh/dormed24-app:dev` und `…:sha-<12 Zeichen>`
   - `ghcr.io/dormed-medizinische-systeme-gmbh/dormed24-ssr:dev` und `…:sha-<12 Zeichen>`
3. Job `deploy-dev` ruft die Coolify-API (`/api/v1/deploy`) auf. Coolify zieht die `:dev`-Images (`pull_policy: always`) und startet `compose.coolify.yaml`:
   - `migrate` läuft einmal (`migrate --force`); schlägt er fehl, starten die neuen Container nicht.
   - danach `ssr`, `app`, `queue`, `scheduler`.
4. Host-Nginx leitet `dev.dormed24.de` auf `127.0.0.1:8081`.

**Rollback:** In Coolify `APP_IMAGE`/`SSR_IMAGE` auf ein älteres `:sha-…`-Tag setzen und neu deployen. Migrationen werden dabei nicht zurückgedreht.

Solange `COOLIFY_URL`, `COOLIFY_DEV_UUID` oder `COOLIFY_TOKEN` fehlen, endet `deploy-dev` mit einer Warnung, ohne Fehler.

### Einmalige Einrichtung

**Server (root):**

- Coolify-Proxy auf „None“ (Coolify belegt sonst Port 80/443 neben dem Host-Nginx).
- GHCR-Zugang für den Docker-Daemon, den Coolify nutzt: `docker login ghcr.io` mit einem Token, das nur `read:packages` darf. Alternativ die beiden Pakete in GitHub auf „public“ stellen.
- Host-Nginx-vHost für `dev.dormed24.de`, TLS per Certbot:

  ```nginx
  server {
      server_name dev.dormed24.de;
      client_max_body_size 32m;

      location / {
          proxy_pass http://127.0.0.1:8081;
          proxy_set_header Host $host;
          proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
          proxy_set_header X-Forwarded-Proto $scheme;
          proxy_set_header X-Forwarded-Host $host;
          proxy_set_header X-Forwarded-Port $server_port;
          proxy_read_timeout 60s;
      }
  }
  ```

**Coolify:**

- Neue Ressource „Docker Compose“ aus diesem Repo, Branch `main`, Compose-Datei `compose.coolify.yaml`.
- **Keine Domain** eintragen und den automatischen Deploy bei Git-Push **aus**schalten: deployt wird nur über die API, nachdem die Images gebaut sind.
- Env-Variablen: `APP_KEY` (eigener), `APP_URL=https://dev.dormed24.de`, `APP_ENV`, `APP_DEBUG=false`, `DB_PASSWORD`, Mail- und PayPal-Zugangsdaten. Für dev empfohlen: `MAIL_MAILER=log`, damit keine echten Mails an kopierte Kundenadressen gehen. Hosts, Treiber, SSR und Proxy setzt `compose.coolify.yaml` selbst.
- Unter „API Tokens“ ein Token mit Deploy-Recht anlegen.

**GitHub** (Settings → Secrets and variables → Actions):

| Art | Name | Inhalt |
| --- | --- | --- |
| Variable | `COOLIFY_URL` | Basis-URL der Coolify-Instanz, von GitHub aus erreichbar |
| Variable | `COOLIFY_DEV_UUID` | UUID der Compose-Ressource in Coolify |
| Secret | `COOLIFY_TOKEN` | API-Token aus Coolify |

**Daten (einmalig, nach dem ersten Deploy):** SQLite von test per `db:import-sqlite` nach Postgres, Bilder aus `storage/app/public` ins Volume `storage` (Befehle im README, Abschnitt „Docker-Stack“; in Coolify per „Execute Command“ im `app`-Container). Danach einmal `php artisan product-images:convert` ausführen: wandelt alle Produktbilder in WebP + AVIF um (idempotent). Auch der Release, der die Umstellung einführt, braucht diesen Schritt nach `migrate`; bis dahin laufen Altbilder ohne AVIF weiter. Hosts ohne Docker (test.dormed24.de) brauchen PHP `gd` mit WebP/AVIF und `exif`.
