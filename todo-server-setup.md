# Server-Setup-Checkliste — test.dormed24.de (für Codex auf dem Server)

Ziel: GitHub Actions (`.github/workflows/deploy.yml`) deployt beim Push auf `main`
per SSH als User `deploy`. Diese Checkliste bringt den Server in genau den Zustand,
den der Workflow erwartet.

**Nach dem Setup muss man auf den Server nur noch für den Sandbox-Reset**
(`php artisan migrate:fresh --seed`). Alles andere macht der Deploy.

---

## Bekannte Fakten zum Server (Stand: Setup)

| | |
| --- | --- |
| **IP** | `65.109.18.153` |
| **DNS** | ⚠️ `test.dormed24.de` löst **noch nicht** auf. Nur `dormed24.de` → `65.109.18.153`. |
| **SSH-Host-Key (ed25519)** | Fingerprint `SHA256:+XULRut13kJLW0b5nwO/7mxKlevF6u8uWqdRsA14Yn8` |
| **App-Pfad** | `/var/www/test.dormed24.de` |
| **SSH-User (Deploy)** | `deploy` — **nie** root, **nie** www-data |
| **Webserver-User** | `www-data` |

### ⚠️ Test und Produktion liegen auf DERSELBEN Maschine

`test.dormed24.de` und `dormed24.de` zeigen auf dieselbe IP. Der Sandbox-Reset
(`migrate:fresh --seed`) läuft also auf derselben Kiste wie die spätere Produktion.

**Zwingend:** getrennte Verzeichnisse **und getrennte Datenbanken**. Bei SQLite
heißt das: unterschiedlicher `DB_DATABASE`-Pfad in der jeweiligen `.env`.
Ein Reset im Test-Verzeichnis darf die Produktions-DB niemals erreichen.

---

## ⚠️ Zuerst verstehen: es gibt ZWEI Schlüsselpaare

Die werden gern verwechselt — beide sind nötig, sie zeigen in **entgegengesetzte Richtungen**:

| # | Richtung | Privater Teil | Öffentlicher Teil |
| - | -------- | ------------- | ----------------- |
| 1 | **GitHub Actions → Server** | GitHub-Secret `DEPLOY_SSH_KEY` | `/home/deploy/.ssh/authorized_keys` **auf dem Server** |
| 2 | **Server → GitHub** (für `git pull`) | `/home/deploy/.ssh/id_ed25519` **auf dem Server** | GitHub-Repo → Settings → **Deploy keys** (read-only) |

Schlüsselpaar 1 erzeugt Linus lokal. Schlüsselpaar 2 wird **auf dem Server** erzeugt (Schritt 3).

---

## 1. Systemvoraussetzungen prüfen

```bash
php --version        # MUSS 8.4.x sein  (composer.json: "php": "^8.4")
node --version       # MUSS >= 20.19 sein (Vite 8 crasht auf Node 18!)
npm --version
composer --version
git --version
```

- **PHP 8.4** ist Pflicht — 8.3 kann das Projekt nicht installieren.
- **Node >= 20.19** ist Pflicht — sonst stirbt `npm run build` mit
  `ReferenceError: CustomEvent is not defined`.
- Nötige PHP-Extensions: die Laravel-Standardliste (`mbstring`, `xml`, `curl`,
  `zip`, `intl`, `sqlite3` bzw. `pdo_mysql` je nach DB), plus `gd` für Bilder.

---

## 2. Deploy-User anlegen

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo mkdir -p /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
```

`deploy` bekommt **kein sudo** und **keine** Shell-Rechte auf `www-data`.
Der Webserver läuft weiterhin als `www-data`.

---

## 3. SSH einrichten

**3a. GitHub Actions → Server** (öffentlicher Teil von Schlüsselpaar 1, kommt von Linus):

```bash
sudo -u deploy tee -a /home/deploy/.ssh/authorized_keys <<'EOF'
ssh-ed25519 AAAA... github-actions-deploy
EOF
sudo chmod 600 /home/deploy/.ssh/authorized_keys
sudo chown -R deploy:deploy /home/deploy/.ssh
```

**3b. Server → GitHub** (Schlüsselpaar 2, hier auf dem Server erzeugen):

```bash
sudo -u deploy ssh-keygen -t ed25519 -C "test.dormed24.de deploy" \
     -f /home/deploy/.ssh/id_ed25519 -N ""
sudo -u deploy cat /home/deploy/.ssh/id_ed25519.pub
```

→ Diesen **public key** im GitHub-Repo eintragen unter
**Settings → Deploy keys → Add deploy key** (⚠️ *ohne* Write-Access — der Server
muss nur pullen).

Verbindung testen:
```bash
sudo -u deploy ssh -T git@github.com     # erwartete Antwort: "Hi ...! You've successfully authenticated"
```

**3c. Host-Fingerprint verifizieren** (für die GitHub-Variable `SSH_KNOWN_HOSTS`):

Der Host-Key wurde bereits von außen gescannt. **Auf dem Server gegenprüfen**, dass
er echt ist (verhindert, dass ein MITM-Scan übernommen wird):

```bash
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

**Muss ergeben:**
```
256 SHA256:+XULRut13kJLW0b5nwO/7mxKlevF6u8uWqdRsA14Yn8 ...
```

Stimmt das **nicht** überein → **abbrechen und melden.**

Der Wert für die GitHub-Variable `SSH_KNOWN_HOSTS` lautet dann (Achtung: der Eintrag
muss zu dem Namen passen, mit dem verbunden wird = `DEPLOY_HOST`):

```
65.109.18.153 ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAILvH/6vSedqw5ULZLdDf8Ps7DC7QvlWQWcy8+guUC7Yo
```

Sobald der DNS-Eintrag `test.dormed24.de` → `65.109.18.153` existiert, stattdessen
den kombinierten Eintrag verwenden (deckt beide Namen ab):

```
test.dormed24.de,65.109.18.153 ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAILvH/6vSedqw5ULZLdDf8Ps7DC7QvlWQWcy8+guUC7Yo
```

---

## 4. Repository klonen

```bash
sudo mkdir -p /var/www/test.dormed24.de
sudo chown deploy:www-data /var/www/test.dormed24.de

sudo -u deploy git clone git@github.com:DormedGmbH/dormed24.de.git \
     /var/www/test.dormed24.de

cd /var/www/test.dormed24.de
sudo -u deploy git checkout main
```

Falls Git meckert („dubious ownership"):
```bash
sudo -u deploy git config --global --add safe.directory /var/www/test.dormed24.de
```

---

## 5. Rechte-Modell

Grundregel: **`deploy` besitzt die Dateien** (schreibt beim Deploy),
**`www-data` liest sie** — und schreibt **nur** in die Laravel-Runtime-Ordner.

```bash
cd /var/www/test.dormed24.de

# Besitzer: deploy, Gruppe: www-data
sudo chown -R deploy:www-data .

# App-Dateien: www-data darf lesen/ausführen, nicht schreiben
sudo find . -type d -exec chmod 2755 {} \;    # 2 = setgid -> neue Dateien erben die Gruppe
sudo find . -type f -exec chmod 644 {} \;

# NUR diese beiden brauchen Schreibrechte für www-data:
sudo chmod -R 2775 storage bootstrap/cache
```

Symlink für Produktbilder (public-Disk):
```bash
sudo -u deploy php artisan storage:link
```

---

## 6. `.env` anlegen — ⚠️ WICHTIGSTER PUNKT

```bash
sudo -u deploy cp .env.example .env
sudo -u deploy php artisan key:generate
sudo -u deploy nano .env
```

### ❗ `APP_ENV` darf auf diesem Server **NICHT** `production` sein

Das ist der Test-/Sandbox-Server. `APP_ENV=production` hätte **zwei fatale Effekte**:

1. **PayPal würde LIVE laufen.** `App\Support\PaymentMode` leitet sandbox/live
   **ausschließlich** aus `APP_ENV` ab (`production` = live). Echtes Geld auf dem Testsystem.
2. **Der Sandbox-Reset wäre blockiert.** `DB::prohibitDestructiveCommands()` verbietet
   `migrate:fresh` in Produktion, und der `ProductSeeder` bricht dort per Guard ab.
   Genau das, was hier funktionieren *muss*.

**Also:**
```dotenv
APP_ENV=staging            # NICHT production!
APP_DEBUG=false            # trotzdem: keine Stacktraces nach außen
APP_URL=https://test.dormed24.de
SESSION_SECURE_COOKIE=true # bei HTTPS

INERTIA_SSR_ENABLED=true   # SSR läuft dauerhaft -> braucht den Dienst aus Schritt 7b!
```

> ⚠️ `INERTIA_SSR_ENABLED=true` **ohne** den SSR-Dienst (Schritt 7b) macht die Seite
> langsamer, nicht schneller: Laravel würde bei jedem Request vergeblich versuchen,
> die Seite an `127.0.0.1:13714` zu schicken. Schalter und Dienst gehören zusammen.

Datenbank, Mail-/PayPal-Zugangsdaten nach Bedarf (PayPal-Credentials werden ohnehin
im Admin unter Einstellungen gepflegt, nicht in der `.env`).

---

## 7. Erstinstallation (einmalig von Hand)

```bash
cd /var/www/test.dormed24.de
sudo -u deploy composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
sudo -u deploy npm ci
sudo -u deploy npm run build:ssr      # baut Client-Assets UND bootstrap/ssr/app.js
sudo -u deploy php artisan migrate --force
sudo -u deploy php artisan optimize
```

Admin-Account anlegen:
```bash
sudo -u deploy php artisan add:admin
```

---

## 7b. ❗ SSR-Dienst einrichten (Pflicht — SSR läuft hier dauerhaft)

SSR braucht **einen zweiten, dauerhaft laufenden Node-Prozess** neben PHP-FPM.
Er nimmt von Laravel das Seiten-Objekt entgegen, rendert die Svelte-Seite zu
fertigem HTML und gibt es zurück. Läuft er nicht, bringt `INERTIA_SSR_ENABLED=true`
nur Nachteile.

**systemd-Unit anlegen** — `/etc/systemd/system/inertia-ssr-test.service`:

```ini
[Unit]
Description=Inertia SSR (test.dormed24.de)
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/test.dormed24.de
ExecStart=/usr/bin/php artisan inertia:start-ssr
# Restart=always ist ZWINGEND: der Deploy fährt den Prozess mit
# "inertia:stop-ssr" herunter, damit systemd ihn mit dem NEUEN Bundle neu
# startet. Ohne Restart=always bliebe er nach jedem Deploy tot.
Restart=always
RestartSec=2
# Node muss auffindbar sein (systemwerte Installation -> /usr/bin/node)
Environment=PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

[Install]
WantedBy=multi-user.target
```

Aktivieren und prüfen:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now inertia-ssr-test
sudo systemctl status inertia-ssr-test          # muss "active (running)" sein

cd /var/www/test.dormed24.de
sudo -u deploy php artisan inertia:check-ssr    # muss "SSR server is running" melden
```

**Warum kein `sudo` im Deploy nötig ist:** Der Deploy ruft `php artisan inertia:stop-ssr`
— das ist nur ein HTTP-Shutdown-Request an `127.0.0.1:13714`, kein Systemdienst-Befehl.
Der Prozess beendet sich, **systemd startet ihn dank `Restart=always` sofort mit dem
frischen Bundle neu**. Der `deploy`-User braucht dafür keinerlei Sonderrechte.

---

## 8. ❗ Node im nicht-interaktiven PATH — der häufigste Stolperstein

GitHub Actions ruft `ssh deploy@host 'befehle'` auf. Das ist eine **nicht-interaktive,
nicht-Login-Shell** — `~/.bashrc` / `~/.profile` werden dabei oft **nicht** geladen.
Wenn Node per **nvm** installiert ist, ist es dann **nicht im PATH** und der Deploy
stirbt mit `npm: command not found`.

**Der Acid-Test — genau so muss es funktionieren:**
```bash
ssh deploy@test.dormed24.de 'node --version; npm --version; php --version; composer --version; git --version'
```

Kommt hier etwas nicht, dann **Node systemweit installieren** (empfohlen), z. B. via NodeSource:
```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt-get install -y nodejs
```
(Damit liegt Node in `/usr/bin` und ist in jeder Shell verfügbar.)

---

## 9. Webserver (nginx/apache)

- DocumentRoot: `/var/www/test.dormed24.de/**public**` (nicht der Repo-Root!)
- Läuft als `www-data`.
- PHP-FPM mit **PHP 8.4**.
- HTTPS für `test.dormed24.de`.

---

## 10. Abschluss-Verifikation

```bash
# 1) SSH von außen als deploy (mit dem Actions-Key) funktioniert?
ssh -i <actions_private_key> deploy@test.dormed24.de 'echo OK'

# 2) Alle Tools im nicht-interaktiven PATH? (Schritt 8)
ssh deploy@test.dormed24.de 'cd /var/www/test.dormed24.de && node -v && php -v && composer -V && git status --short'

# 3) Kann deploy pullen?
sudo -u deploy git -C /var/www/test.dormed24.de pull --ff-only

# 4) Läuft die App?
curl -sI https://test.dormed24.de | head -1     # erwartet: HTTP/2 200

# 5) Läuft SSR wirklich? (muss "SSR server is running" sagen)
sudo systemctl status inertia-ssr-test
sudo -u deploy php artisan inertia:check-ssr

# 6) Kommt das HTML schon fertig gerendert vom Server?
#    Der Marker "data-server-rendered" MUSS auftauchen - sonst ist SSR nicht aktiv.
curl -s https://test.dormed24.de | grep -c "data-server-rendered"    # erwartet: >= 1

# 7) Sandbox-Reset funktioniert? (der EINE Grund, warum man noch auf den Server muss)
sudo -u deploy php artisan migrate:fresh --seed
```

- Läuft Punkt 6 ins Leere (`0`), ist entweder `INERTIA_SSR_ENABLED` nicht `true`
  oder der Dienst aus Schritt 7b läuft nicht.
- Läuft Punkt 7 durch, ist `APP_ENV` korrekt gesetzt (in `production` würde es
  absichtlich abbrechen).

---

## Danach: GitHub konfigurieren (macht Linus)

**Secret:** `DEPLOY_SSH_KEY` = privater Teil von Schlüsselpaar 1
**Variables:** `DEPLOY_HOST=test.dormed24.de` · `DEPLOY_USER=deploy` ·
`DEPLOY_PATH=/var/www/test.dormed24.de` · `SSH_KNOWN_HOSTS=<ssh-keyscan-Ausgabe>`

Erst **danach** auf `main` pushen — sonst läuft der Deploy-Workflow rot, weil die
Secrets fehlen.
