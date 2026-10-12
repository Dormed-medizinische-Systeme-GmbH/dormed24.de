# CI/CD & Deployment — test.dormed24.de

**Ziel:** Auf den Server muss man **nur noch für einen Sandbox-Reset** (`migrate:fresh --seed`). Alles andere macht der Deploy automatisch beim Push auf `main`.

### Die Workflows (`.github/workflows/`)

| Workflow | Läuft bei | Inhalt |
| --- | --- | --- |
| `tests.yml` | Push (develop/master/workos), PR, **+ `workflow_call`** | PHPStan + svelte-check + Vitest + PHPUnit (gegen **Postgres** + Redis als Service-Container) + Build, **PHP 8.4** |
| `lint.yml` | Push (develop/master/workos), PR, **+ `workflow_call`** | Pint + ESLint + Prettier (`--test`/`--check`, ändert nichts) |
| `deploy.yml` | **Push auf `main`** + manuell (`workflow_dispatch`) | ruft beide Gates auf, deployt danach per SSH |
| `docker.yml` | PR mit Änderungen am Container-Setup (`Dockerfile.prod`, `compose.yaml`, `docker/**`, Lockfiles) | baut das Image, startet App + Postgres + Redis und prüft `/up`, Migrationen und Redis |

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
