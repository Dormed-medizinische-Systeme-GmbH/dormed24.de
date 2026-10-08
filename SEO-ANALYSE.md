# SEO-Analyse dormed24.de

Stand 08.10.2026, Branch `development/funny-thompson-uwowuc` (Basis `00a53e0`).
Grundlage ist der Code im Repository. Live- und Testsystem (`dormed24.de`,
`test.dormed.de`) waren aus der Analyse-Umgebung nicht erreichbar (Proxy 403).
Alles, was vom Server-Setup abhängt, ist deshalb als **„prüfen“** markiert.

Ältere SEO-Punkte aus `ANALYSE.md` (V4 SEO-1 bis SEO-3) sind hier mit
aufgenommen.

---

## Kurzfazit

Die **On-Page-Grundlagen sind gut**: Jede öffentliche Shop-Seite setzt Title,
Description, Canonical, Open Graph und Twitter Card über eine zentrale
Komponente (`AppHead.svelte`). Es gibt Product-, BreadcrumbList-, FAQPage- und
Organization-JSON-LD, eine dynamische Sitemap und eine sinnvolle `robots.txt`.
Die meisten dieser Dinge sind durch Feature-Tests abgesichert.

Das **größte Problem ist technisch**: Server-Side Rendering ist in Produktion
aus. Ausgeliefert wird eine leere HTML-Hülle, alle Meta-Tags, Texte, Links und
das JSON-LD entstehen erst per JavaScript im Browser. Google rendert das mit
Verzögerung, Bing nur eingeschränkt, Social-Media-Vorschauen und die meisten
KI-Crawler gar nicht. Solange das so bleibt, wirkt ein großer Teil der guten
On-Page-Arbeit nur halb.

Danach folgen: Produkt-URLs ohne sprechenden Slug, fehlender Text auf den
Kategorieseiten, unskalierte Bilder ohne Lazy Loading und fehlende
Komprimierung bzw. Cache-Header im Docker-Nginx.

### Bewertung nach Bereichen

| Bereich | Bewertung | Kernaussage |
| --- | --- | --- |
| Rendering / Indexierbarkeit | 🔴 kritisch | SSR aus, HTML ohne Inhalt und ohne Meta-Tags |
| Meta-Tags (Title, Description, OG) | 🟢 gut | zentral gelöst; Lücken bei Canonicals der Infoseiten, App-Name im Build |
| Strukturierte Daten | 🟢 gut | Product, Breadcrumb, FAQ, Organization; kleine Fehler bei Organization und Product-URL |
| URL-Struktur | 🟠 verbesserungswürdig | `/products/{id}` ohne Slug, englischer Pfad in deutschem Shop |
| Sitemap / robots.txt | 🟡 ok | funktioniert; nicht verfügbare Produkte drin, Staging nicht gesperrt |
| Content | 🟠 dünn | Kategorien ohne Text, H1 auf Listen nur `sr-only` |
| Interne Verlinkung | 🟡 ok | Header/Footer/Breadcrumbs gut; Infinite Scroll ohne Paginierungs-Links |
| Performance / Core Web Vitals | 🟠 verbesserungswürdig | große Bilder, kein `width/height`, kein Lazy Loading, kein gzip |
| Staging / Duplicate Content | 🟠 Risiko | Testsysteme nicht auf `noindex` |

---

## 🔴 1. Rendering: SSR ist in Produktion aus

**Befund**

- `config/inertia.php`: `'enabled' => (bool) env('INERTIA_SSR_ENABLED', false)`.
- `.env.example`: `INERTIA_SSR_ENABLED=false`.
- `Dockerfile.prod`, Zeile 4: „Kein Node im Runtime-Image: SSR ist
  standardmäßig aus“. Gebaut wird nur `npm run build`, nicht `build:ssr`; ein
  `inertia:start-ssr`-Prozess fehlt in `docker/supervisord.conf`.
- `resources/app.blade.php` liefert dann nur
  `<title>{{ config('app.name') }}</title>` und ein leeres `<div id="app">`
  mit den Props als JSON.

**Folgen**

- Description, Canonical, OG-/Twitter-Tags und alle JSON-LD-Blöcke stehen nicht
  im ausgelieferten HTML. Sie entstehen erst in `<svelte:head>` im Browser.
- **Google** indexiert über die Rendering-Queue, also verzögert und mit
  Rendering-Budget. Bei einem kleinen Shop klappt das meist, ist aber
  unzuverlässig, besonders für Canonical und Structured Data.
- **Bing, DuckDuckGo, Ecosia** rendern JS nur eingeschränkt.
- **Link-Vorschauen** (WhatsApp, LinkedIn, Facebook, Slack, Teams, E-Mail)
  zeigen nur den App-Namen, ohne Bild und Beschreibung. Für einen B2B-Shop,
  dessen Produktlinks per Mail und Messenger geteilt werden, ist das sichtbar.
- **KI-Crawler und KI-Suchen** (ChatGPT, Perplexity, Claude u. a.) führen in
  der Regel kein JavaScript aus und sehen den Shop als leer.

**Was schon vorbereitet ist**

Die Codebasis ist SSR-fähig: `vite.config.ts` hat den SSR-Entry,
`package.json` hat `build:ssr`, `app.ts` schützt Browser-only-Code mit
`typeof window`, `AppHead` und `ShopBreadcrumbs` nutzen bewusst `appUrl` statt
`window.location`. Es fehlt nur der Betrieb.

**Empfehlung (höchste Priorität)**

1. Im Docker-Build `npm run build:ssr` statt `npm run build`.
2. Node ins Runtime-Image und `php artisan inertia:start-ssr` als weiteren
   Supervisor-Prozess.
3. `INERTIA_SSR_ENABLED=true` in der Server-Env.
4. Danach prüfen: `curl -s https://…/products/1 | grep -E 'og:title|ld\+json|canonical'`.
5. Einen Feature-Test ergänzen, der bei aktivem SSR-Gateway (Fake) prüft, dass
   der Head gerendert wird, oder zumindest einen Smoke-Check im Deploy-Skript.

Falls Node im Runtime-Image nicht gewollt ist: Minimallösung ist, die
wichtigsten Tags (Title, Description, Canonical, OG, JSON-LD) serverseitig in
`app.blade.php` aus einem `seo`-Prop zu rendern. Das ist aber doppelte Logik
und schlechter als echtes SSR.

---

## 🔴 2. App-Name im Produktions-Build (prüfen)

**Befund**

- Titel werden als `"{title} - {VITE_APP_NAME}"` gebaut (`app.ts`,
  `AppHead.svelte`), Fallback ist `'Laravel'`.
- `VITE_APP_NAME` wird **zur Build-Zeit** von Vite eingesetzt.
- `.dockerignore` schließt `.env` und `.env.*` aus, `Dockerfile.prod` setzt
  kein `VITE_APP_NAME`.

**Folge**

Wird das Docker-Image gebaut, endet jeder Seitentitel auf **„- Laravel“**
(z. B. „Ultraschallsysteme - Laravel“). Das landet so in Google und in
Browser-Tabs. Beim SSH-Deploy mit `npm run build` auf dem Server hängt es von
der dortigen `.env` ab (`APP_NAME` ist in `.env.example` ebenfalls `Laravel`).

**Empfehlung**

- Im Dockerfile `ARG VITE_APP_NAME=dormed24` plus `ENV VITE_APP_NAME=…` vor
  `npm run build`, oder den Namen aus dem geteilten `name`-Prop
  (`config('app.name')`) lesen statt aus `import.meta.env`.
- Auf dem Server `APP_NAME` prüfen. Empfehlung: `dormed24` bzw. `dormed24.de`.

---

## 🟠 3. URL-Struktur

| Seite | URL | Bewertung |
| --- | --- | --- |
| Startseite | `/` | gut |
| Alle Produkte | `/products` | englisch |
| Produkt | `/products/{id}` | **kein Keyword, nur ID** |
| Kategorie | `/ultraschallsysteme` usw. | gut, kurz und deutsch |
| Infoseiten | `/informationen/versand`, `/unternehmen/agb` | gut |

**Befunde**

- Produkt-URLs bestehen nur aus der ID (`routes/products.php`). Ein Slug wie
  `/produkte/123-mindray-dc-80-ultraschallsystem` bringt Keywords in URL und
  Snippet und höhere Klickrate.
- Gemischte Sprache: `/products` neben `/informationen`, `/unternehmen` und
  deutschen Kategorie-Slugs.
- Kategorien liegen als Catch-all `/{category}` auf Root-Ebene
  (`routes/categories.php`). Das ist SEO-freundlich, aber jede künftige
  Top-Level-Route muss vor `categories.php` registriert werden.
- Aus dem Shopware-Import gibt es keine Redirects. Falls der alte Shop unter
  `dormed24.de` indexiert war, gehen dessen Rankings und Backlinks beim
  Livegang verloren (**prüfen**: Search Console, `site:dormed24.de`).

**Empfehlung**

1. Spalte `slug` an `products` (eindeutig, aus Name generiert, im Admin
   editierbar), Route `/produkte/{product:slug}` bzw. `{id}-{slug}`.
2. Alte `/products/{id}` per **301** auf die neue URL umleiten. Sitemap,
   Canonical, JSON-LD und Wayfinder-Links ziehen über
   `ProductController.show.url()` automatisch mit.
3. Vor dem Livegang: alte Shopware-URLs aus Search Console bzw. Logs exportieren
   und eine 301-Mapping-Tabelle anlegen.
4. Wenn umgestellt wird, dann **vor** dem Livegang, damit Google die URLs nur
   einmal lernt.

---

## 🟢/🟡 4. Meta-Tags

**Was gut ist**

- `AppHead.svelte` setzt zentral Title, Description, Canonical (absolut über
  `appUrl`), `og:type/url/title/description/image` und Twitter Card.
- Shop-Seiten haben eigene, sinnvolle Descriptions.
- Produkt: Description aus dem Produkttext (160 Zeichen), `og:image` = erstes
  Produktbild, `og:type=product`.
- Produkt- und Kategorielisten setzen das Canonical **ohne** Query-Parameter.
  Filter-, Sortier- und Seitenvarianten (`?sort=`, `?manufacturer=`, `?page=`,
  `?q=`) konsolidieren so auf die Basis-URL.
- `<html lang="de">` über `APP_LOCALE=de`.
- Google-Search-Console-Verifizierung ist eingebunden (`Welcome.svelte`).

**Lücken**

| # | Befund | Datei | Empfehlung |
| --- | --- | --- | --- |
| 4.1 | Infoseiten (AGB, Impressum, Datenschutz, FAQ, Versand, Zahlung, Widerruf) haben **kein Canonical** | `pages/Unternehmen/*`, `pages/Informationen/*` | `canonical={route-url}` ergänzen |
| 4.2 | FAQ-Titel ergibt „Häufige Fragen (FAQ) — dormed24.de - {App}“ (doppelter Markenname) | `FAQ.svelte:113` | Suffix „— dormed24.de“ entfernen |
| 4.3 | Startseiten-Titel „Willkommen - {App}“ ohne Keyword | `Welcome.svelte:217` | z. B. „Medizintechnik online kaufen für Praxis & Klinik“ |
| 4.4 | Produkt-Title nur Produktname, ohne Hersteller oder Kategorie | `Show.svelte:263` | „{Name} – {Hersteller} kaufen“, Länge ≤ 60 Zeichen beachten |
| 4.5 | Produkte ohne Beschreibung haben **keine** Meta-Description | `Show.svelte:126` | Fallback aus Name, Hersteller, Kategorie, Preis |
| 4.6 | Description wird hart bei 160 Zeichen abgeschnitten, auch mitten im Wort | `Show.svelte:128` | an Wortgrenze kürzen, „…“ anhängen |
| 4.7 | Startseite und Listen ohne `og:image` → Vorschau ohne Bild | `Welcome.svelte`, `AppHead.svelte` | Default-OG-Bild (1200×630, Logo + Claim) als Fallback in `AppHead` |
| 4.8 | Kein `og:site_name`, `og:locale` (`de_DE`) | `AppHead.svelte` | ergänzen |
| 4.9 | Suchergebnisseiten (`/products?q=…`) sind indexierbar | `Products/Index.svelte` | bei gesetztem `q` `noindex, follow` |
| 4.10 | Login, Registrierung, Passwort-vergessen, Warenkorb, Checkout ohne `noindex` | `pages/auth/*`, `pages/Checkout/*` | `noindex` setzen; Auth-Titel sind zudem englisch („Forgot password“) |

Hinweis zu 4.9/4.10: `robots.txt` sperrt `/checkout` und `/cart`, aber
`robots.txt` verhindert nur das Crawlen, nicht die Indexierung verlinkter URLs.
Für Seiten, die nicht in den Index sollen, ist `noindex` die richtige Wahl. Die
Disallow-Regel muss dann für diese Pfade entfallen, sonst sieht Google das
`noindex` nie.

Am einfachsten bekommt `AppHead` eine Prop `noindex?: boolean`.

---

## 🟢/🟡 5. Strukturierte Daten (JSON-LD)

| Typ | Seite | Status |
| --- | --- | --- |
| `Product` + `Offer`/`AggregateOffer` + `Brand` + `AggregateRating` | Produktseite | vorhanden, gut |
| `BreadcrumbList` | Produkt, Produktliste, Kategorie | vorhanden, an sichtbare Breadcrumbs gekoppelt |
| `FAQPage` | FAQ | vorhanden |
| `Organization` + `WebSite` | Startseite | vorhanden, fehlerhaft (s. u.) |

`<` wird überall escaped, Script-Breakout durch Admin-Inhalte ist damit
ausgeschlossen.

**Fehler und Lücken**

1. **Organization/WebSite-URLs im SSR leer** (`Welcome.svelte:177`):
   `siteUrl` kommt aus `window.location.origin`, im SSR ist das `''`. Ergebnis:
   `"url": "/"` und `"logo": "/logo.svg"`, also relative URLs, die Google
   ablehnt. Wie in `AppHead` auf `page.props.appUrl` umstellen.
2. **Product-`url`** (`Show.svelte:138`): gleiches Muster mit
   `window.location.origin`. Im SSR wird die URL relativ, außerdem kann sie bei
   `www`/non-`www` vom Canonical abweichen. Auf `appUrl` umstellen.
3. **`sku` = Datenbank-ID**: besser echte Artikelnummer bzw. Hersteller-MPN,
   dazu `mpn` und ggf. `gtin`. Für Google Shopping und Merchant Listings ist
   GTIN/MPN wichtig.
4. **Merchant-Listing-Felder fehlen**: `shippingDetails`
   (`OfferShippingDetails`), `hasMerchantReturnPolicy`
   (`MerchantReturnPolicy`, 14 Tage laut Widerrufsbelehrung), `seller`,
   `priceValidUntil`. Google meldet sie in der Search Console als Warnungen und
   zeigt sonst keine Versand- und Rückgabe-Infos im Snippet.
5. **Organization unvollständig**: `name` ist „dormed 24“ (mit Leerzeichen),
   rechtlicher Name „Dormed medizinische Systeme GmbH“, `address`
   (`PostalAddress`, Holzwickede), `sameAs` (dormed.de, LinkedIn o. ä.) fehlen.
   Empfehlung: Typ `MedicalBusiness` oder `Organization` + `OnlineStore`.
   `logo` als PNG mit mindestens 112×112 px statt SVG.
6. **WebSite ohne `SearchAction`**: Die Sitelinks-Suchbox ist bei Google
   abgeschafft. Der Punkt hat **keine Priorität**.
7. **Kategorie- und Produktlisten** ohne `ItemList` bzw. `CollectionPage`.
   Optional.
8. **Bewertungen**: Self-serving Reviews auf eigenen Produktseiten werden von
   Google als Sterne-Snippet nur noch eingeschränkt gezeigt. Das
   `AggregateRating` ist trotzdem korrekt. Laut `TODO.md` ist das
   Bewertungsformular aktuell deaktiviert.

---

## 🟡 6. Sitemap und robots.txt

**Sitemap** (`SitemapController`, `resources/views/sitemap.blade.php`)

- ✅ Dynamisch, 1 h gecacht, enthält Startseite, Produktliste, Infoseiten,
  Kategorien und Produkte, `lastmod` bei Produkten.
- ✅ Test prüft, dass jede Sitemap-URL erreichbar ist (`StaticPagesTest`).
- ⚠️ **Nicht verfügbare Produkte sind enthalten**:
  `Product::select('id','updated_at')->get()` filtert nicht auf
  `available()`. Die Seiten sind bewusst erreichbar (Test
  `test_show_keeps_unavailable_products_reachable`), ihr Schema sagt
  `OutOfStock`. Entscheidung nötig: Entweder raus aus der Sitemap (und ggf.
  `noindex`), oder drin lassen, wenn die Produkte bald wieder kommen. Dauerhaft
  ausgelistete Produkte besser per 301 auf die Kategorie leiten oder `410`
  zurückgeben.
- ⚠️ Kein `lastmod` für Kategorien und statische Seiten. Für Kategorien kann
  `max(updated_at)` der enthaltenen Produkte dienen.
- ℹ️ `changefreq` und `priority` ignoriert Google. Kann bleiben, bringt nichts.
- ℹ️ Keine Bild-Sitemap (`image:image`). Optional, hilft bei der Google
  Bildersuche.
- ℹ️ Cache-Invalidierung: Neue Produkte erscheinen erst nach bis zu 1 h.
  Unkritisch.

**robots.txt** (`public/robots.txt`)

- ✅ Sperrt Admin, Warenkorb, Checkout, Kundenbereich, Settings, PayPal.
- ⚠️ Statische Datei mit fester URL `Sitemap: https://dormed24.de/sitemap.xml`.
  Auf den Testsystemen zeigt sie auf die Produktion und **erlaubt das Crawlen
  komplett** (siehe Punkt 7).
- ⚠️ `/products/search` (JSON-Endpunkt der Header-Suche) ist nicht gesperrt.
  Unkritisch, aber `Disallow: /products/search` ist sauberer.
- ⚠️ `/test` (Test-Landingpage) ist erreichbar und hat `noindex` nur
  clientseitig. Route vor dem Livegang entfernen (laut Kommentar in
  `routes/web.php` vorgesehen).

**Empfehlung**: `robots.txt` als Route ausliefern, die abhängig von der
Umgebung entweder die Produktivregeln mit `url('/sitemap.xml')` oder
`Disallow: /` ausgibt.

---

## 🟠 7. Staging und Duplicate Content

**Befund**

- Testsysteme (`test.dormed.de`, geplant `test.dormed24.de`) liefern dieselbe
  `robots.txt` und dieselben Seiten wie die Produktion, ohne
  `X-Robots-Tag: noindex`, ohne Passwortschutz.
- Canonicals zeigen auf `APP_URL`, auf Staging also auf die Staging-Domain.
  Google kann so die Staging-Domain als eigenständige Site indexieren, das
  ergibt Duplicate Content gegenüber der Produktion.
- Es gibt einen `TestModeBanner` bzw. `sandbox`-Prop für den PayPal-Modus, aber
  keine Kopplung an Indexierungsregeln.

**Empfehlung**

- Middleware, die außerhalb von `APP_ENV=production` den Header
  `X-Robots-Tag: noindex, nofollow` setzt, plus dynamische `robots.txt` mit
  `Disallow: /` (siehe 6).
- Besser zusätzlich HTTP Basic Auth auf Staging.
- **Prüfen** in der Search Console bzw. per `site:test.dormed.de`, ob schon
  etwas indexiert ist. Falls ja: Removal-Request und `noindex`.

---

## 🟠 8. Content und Seitenstruktur

**Kategorieseiten**

- `config/categories.php`: `description` ist bei **allen 4 Kategorien `null`**.
  Die Meta-Description fällt auf einen generischen Text zurück.
- Die Seite hat keinen sichtbaren Einleitungstext und **keine sichtbare H1**
  (`<h1 class="sr-only">`), nur Breadcrumb und Produktgrid.
- Kategorieseiten sind für generische Suchanfragen („Ultraschallgerät kaufen“,
  „Ultraschallsonde Linear“) die wichtigsten Landingpages. Ohne Text haben sie
  kaum Ranking-Chancen.

**Empfehlung**

- Sichtbare H1 je Kategorie.
- Einleitungstext von 2–4 Sätzen über dem Grid, dazu weiterführender Text
  (300–600 Wörter: Einsatzbereiche, Auswahlkriterien, Beratung, Einweisung,
  Finanzierung) **unter** dem Grid.
- `description` in `config/categories.php` pflegen. Mittelfristig Kategorien
  mit SEO-Text pflegbar machen (Config reicht für 4 Kategorien).
- Fachrichtungen (`config/specialties.php`) haben Suchpotenzial
  („Ultraschall Gynäkologie“, „Ultraschall Kardiologie“). Eigene indexierbare
  Landingpages je Fachrichtung, z. B. `/ultraschallsysteme/gynaekologie`, statt
  nur Filter-Parameter.

**Produktliste `/products`**: ebenfalls nur `sr-only`-H1. Sichtbare
Überschrift ergänzen.

**Produktseiten**

- ✅ Sichtbare H1 mit Produktnamen, H2-Gliederung (Beschreibung, technische
  Daten, Bewertungen, ähnliche Produkte), technische Daten, Fachrichtungen,
  ähnliche Produkte aus derselben Kategorie (gute interne Verlinkung).
- ⚠️ Beschreibung ist Plain Text aus dem Shopware-Import. Qualität und Länge
  **prüfen**: Herstellertexte, die auf vielen Shops identisch sind, sind
  Duplicate Content. Für die Top-Produkte eigene Texte schreiben.
- ⚠️ „Ähnliche Produkte“ lädt **alle** verfügbaren Produkte der Kategorie ohne
  Limit (`ProductController::show`). Mit wachsendem Sortiment wird die Seite
  schwer, und es entstehen sehr viele gleichartige Links. Auf 8–12 begrenzen.

**Startseite**

- ✅ Klare H1, mehrere H2-Sektionen, Leistungs- und Vertrauensinhalte.
- ⚠️ 8 zufällige Produkte (`inRandomOrder()`): Bei jedem Crawl andere interne
  Links. Für SEO besser feste Top-Seller bzw. Highlights, oder zumindest
  zusätzlich feste Links auf alle Kategorien im Content.
- ⚠️ Hero-Bild mit `alt=""`. Als dekoratives Bild korrekt, nur zur Kenntnis.

**Rechtstexte / Vertrauen (E-E-A-T)**

- Impressum, AGB, Datenschutz, Widerruf, Versand, Zahlung, FAQ vorhanden und im
  Footer verlinkt. Gut für Vertrauen und YMYL-nahe Themen (Medizinprodukte).
- Datenschutz ohne Datum bzw. Version (offen in `TODO.md`).
- Footer verlinkt Leistungsseiten auf `dormed.de`. Gut für Markenkontext.
  `sameAs` im Organization-Schema ergänzen (siehe 5).

---

## 🟡 9. Interne Verlinkung und Crawlbarkeit

- ✅ Inertia-`<Link>` rendert echte `<a href>`, Crawler können folgen.
- ✅ Header verlinkt alle Kategorien und „Alle Produkte“, Footer alle
  Info- und Rechtsseiten, Breadcrumbs auf Liste, Kategorie und Produkt.
- ⚠️ **Infinite Scroll ohne Paginierungs-Links** (`Products/Index.svelte`,
  `ByCategory.svelte`): Crawler sehen nur die ersten 24 Produkte. Produkte ab
  Position 25 sind nur über Sitemap und „ähnliche Produkte“ erreichbar. Bei
  aktuell kleinem Sortiment vertretbar, bei Wachstum ein Problem.
  - Empfehlung: zusätzlich echte `<a href="?page=2">`-Links rendern (für
    JS-Nutzer ausblendbar oder als „Mehr laden“-Fallback). `?page=N` selbst
    sollte dann ein **selbstreferenzierendes** Canonical bekommen, nicht auf
    Seite 1 zeigen.
- ⚠️ Kategorieseite ohne Unterkategorien und ohne Querverweise auf andere
  Kategorien im Content.
- ⚠️ Keine eigene 404-Seite (Laravel-Standard ohne Shop-Navigation). Der
  Statuscode stimmt (Tests vorhanden), aber Nutzer und Crawler landen in einer
  Sackgasse. Inertia-Fehlerseite mit Header, Suche und Kategorien ergänzen.
- ℹ️ Trailing Slash und `www`/non-`www`: Keine App-seitige Weiterleitung.
  Sollte am Reverse Proxy per 301 auf eine Variante normalisiert werden
  (**prüfen**). Das Canonical fängt es nur teilweise ab.

---

## 🟠 10. Performance / Core Web Vitals

| # | Befund | Wirkung | Empfehlung |
| --- | --- | --- | --- |
| 10.1 | Produktbilder unskaliert aus dem Shopware-Import, teils > 300 KB (`ANALYSE.md`, V4 SEO-3) | LCP, Datenvolumen mobil | Resize auf max. ~800 px WebP beim Upload und einmalig für den Bestand; mittelfristig `srcset` |
| 10.2 | `<img>` in `ProductCard`, Produktgalerie und Startseite ohne `width`/`height` | CLS | Maße setzen (quadratisch, Container ist `aspect-square`, Risiko daher mäßig) |
| 10.3 | Kein `loading="lazy"` auf Karten-Bildern unterhalb des Viewports | LCP, Bandbreite | `loading="lazy"` + `decoding="async"` für Karten; erstes Produktbild `fetchpriority="high"` |
| 10.4 | Docker-Nginx (`docker/nginx.conf`) ohne `gzip`/`brotli` | TTFB, Transfergröße | `gzip on;` für HTML, JS, CSS, JSON, SVG, XML |
| 10.5 | Keine Cache-Header für `/build/*` (Vite-Hash-Assets) und `/storage/*` | wiederholte Ladezeit | `location /build/ { expires 1y; add_header Cache-Control "public, immutable"; }`, Bilder z. B. 30 Tage |
| 10.6 | Ohne SSR: Inhalt erst nach JS-Download und Hydration sichtbar | LCP deutlich schlechter | siehe Punkt 1 |
| 10.7 | ✅ `AddLinkHeadersForPreloadedAssets` aktiv, Fonts über Vite-Fonts-Plugin (lokal gebündelt, kein Google-Fonts-Request) | gut | – |

Die Zahlen sollten nach den Maßnahmen mit PageSpeed Insights bzw. dem
CrUX-Report in der Search Console gemessen werden. Aus der Analyse-Umgebung war
das nicht möglich.

---

## 11. Was bereits gut gelöst ist

- Zentrale Head-Komponente mit absoluten Canonicals über `appUrl`
  (SSR-fest), getestet (`test_app_url_is_shared_for_absolute_canonical_tags`).
- Canonicals der Listen ignorieren Filter- und Sortier-Parameter, das
  verhindert Duplicate Content durch Facetten.
- JSON-LD für Product, Breadcrumb und FAQ, XSS-sicher escaped, Breadcrumb-Schema
  direkt aus den sichtbaren Breadcrumbs abgeleitet.
- Dynamische Sitemap mit Erreichbarkeitstest.
- `robots.txt` sperrt alle privaten Bereiche.
- Nicht verfügbare Produkte fliegen aus Listen und Suche, bleiben aber
  erreichbar (keine 404 für verlinkte Produkte), Schema `OutOfStock`.
- Unbekannte Kategorien und Produkte liefern 404 (getestet).
- Sprechende deutsche Kategorie-URLs auf Root-Ebene.
- `lang="de"`, Viewport, Favicons inkl. Apple Touch Icon.
- Alt-Texte bei Produktbildern (Produktname), dekorative Bilder mit `alt=""`.
- Search-Console-Verifizierung eingebunden.

---

## Maßnahmenplan

### Vor dem Livegang (P0)

1. **SSR in Produktion aktivieren** (Punkt 1).
2. **`VITE_APP_NAME` / `APP_NAME` korrekt setzen**, Titel prüfen (Punkt 2).
3. **Staging auf `noindex`** setzen (Header + `robots.txt`) und Indexierung
   prüfen (Punkt 7).
4. **Produkt-Slugs** einführen, falls gewollt. Später ist es teurer
   (Punkt 3).
5. **301-Mapping der alten Shopware-URLs**, falls der alte Shop indexiert war
   (Punkt 3).
6. `/test`-Route entfernen (Punkt 6).

### Kurzfristig (P1, wenige Stunden je Punkt)

7. Organization- und Product-JSON-LD auf `appUrl` umstellen (Punkt 5.1, 5.2).
8. Canonicals für Infoseiten, `noindex` für Suche, Auth, Checkout, FAQ-Titel
   fixen (Punkt 4).
9. Default-OG-Bild, `og:site_name`, `og:locale` (Punkt 4.7, 4.8).
10. Fallback-Meta-Description für Produkte ohne Text (Punkt 4.5).
11. Sitemap: Umgang mit nicht verfügbaren Produkten entscheiden (Punkt 6).
12. Nginx: gzip + Cache-Header (Punkt 10.4, 10.5).
13. `loading="lazy"`, `width`/`height` auf Bildern (Punkt 10.2, 10.3).
14. „Ähnliche Produkte“ limitieren (Punkt 8).

### Mittelfristig (P2)

15. Kategorietexte schreiben, sichtbare H1 auf Listen (Punkt 8).
16. Bild-Pipeline (Resize/WebP/`srcset`, braucht freigegebene Abhängigkeit)
    (Punkt 10.1).
17. Merchant-Listing-Felder im Product-Schema (Versand, Rückgabe, MPN/GTIN)
    (Punkt 5.3, 5.4).
18. Crawlbare Paginierung neben Infinite Scroll (Punkt 9).
19. Eigene 404-Seite mit Navigation (Punkt 9).
20. Fachrichtungs-Landingpages (Punkt 8).
21. Eigene Produkttexte für Top-Produkte (Punkt 8).

### Laufend

- Google Search Console und Bing Webmaster Tools: Sitemap einreichen,
  Indexabdeckung, Rich-Results-Status und Core Web Vitals überwachen.
- Rich-Results-Test für Produkt- und FAQ-Seite nach jedem Schema-Umbau.
- Auf ein Analytics-Tool verzichtet der Shop bisher (keine Einbindung im Code
  gefunden). Für SEO-Erfolgsmessung reicht die Search Console. Ein
  datenschutzfreundliches Tool (z. B. Matomo/Plausible, consent-frei
  konfigurierbar) wäre eine Option.

---

## Anhang: Geprüfte Dateien

- Routing: `routes/web.php`, `routes/products.php`, `routes/categories.php`
- Controller: `ProductController`, `CategoryController`, `HomeController`,
  `SitemapController`
- Middleware: `HandleInertiaRequests`, `bootstrap/app.php`
- Templates: `resources/app.blade.php`, `resources/views/sitemap.blade.php`
- Frontend: `resources/js/app.ts`, `components/AppHead.svelte`,
  `components/ShopBreadcrumbs.svelte`, `components/ProductCard.svelte`,
  `components/ShopHeader.svelte`, `components/AppFooter.svelte`,
  `pages/Welcome.svelte`, `pages/Products/*`, `pages/Informationen/*`,
  `pages/Unternehmen/*`, `pages/auth/*`, `pages/Checkout/*`
- Konfiguration: `config/inertia.php`, `config/categories.php`,
  `config/app.php`, `.env.example`, `vite.config.ts`, `package.json`
- Betrieb: `Dockerfile.prod`, `.dockerignore`, `docker/nginx.conf`,
  `docker/supervisord.conf`, `nginx.template.conf`, `public/robots.txt`
- Tests: `tests/Feature/Shop/*`
