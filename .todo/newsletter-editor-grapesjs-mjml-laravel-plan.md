# Implementierungsplan: Newsletter-Editor mit GrapesJS + MJML

## 1. Ziel

Eigener Newsletter-Editor auf Basis von:

- Laravel – Backend/API, Persistenz, Rendering, Versand
- PostgreSQL – Persistenz
- Svelte – Frontend
- GrapesJS – visueller Drag-&-Drop-Editor
- MJML – responsive, E-Mail-kompatible HTML-Generierung

```text
Svelte
  │
  ▼
GrapesJS
  │
  ▼
MJML-Struktur / Editor-State
  │
  ▼
Laravel API
  │
  ├── PostgreSQL
  ├── MJML → HTML
  └── Versandpipeline
          │
          ▼
      Mail Provider
```

## 2. Architektur

### Source of Truth

Nicht das gerenderte HTML als primäre Datenquelle verwenden.

Speichern:

1. GrapesJS-Design/State – editierbare Quelle
2. MJML – generierte bzw. normalisierte E-Mail-Struktur
3. gerendertes HTML – Cache für Preview und Versand

```text
GrapesJS State
     │
     ├── speichern
     ▼
    MJML
     │
     ▼
  HTML rendern
     │
     ├── Preview
     └── Versand
```

## 3. PostgreSQL-Datenmodell

### `newsletters`

```text
newsletters
-----------
id
name
subject
preheader
status
design_json
mjml
rendered_html
created_by
created_at
updated_at
```

- `id` – UUID oder bigint
- `name` – interner Name
- `subject` – E-Mail-Betreff
- `preheader` – Preview-Text
- `status` – `draft`, `ready`, `scheduled`, `sending`, `sent`
- `design_json` – PostgreSQL `jsonb`
- `mjml` – TEXT
- `rendered_html` – TEXT
- `created_by` – User-ID

### Versionierung

Optional im MVP, langfristig empfohlen:

```text
newsletter_versions
-------------------
id
newsletter_id
version
design_json
mjml
rendered_html
created_by
created_at
```

## 4. Laravel Backend

### API

```text
GET    /api/newsletters
POST   /api/newsletters
GET    /api/newsletters/{newsletter}
PUT    /api/newsletters/{newsletter}
DELETE /api/newsletters/{newsletter}

POST   /api/newsletters/{newsletter}/render
GET    /api/newsletters/{newsletter}/preview

POST   /api/newsletters/{newsletter}/duplicate
POST   /api/newsletters/{newsletter}/send-test
```

Später:

```text
POST   /api/newsletters/{newsletter}/schedule
POST   /api/newsletters/{newsletter}/send
```

### Validation

Validieren:

- maximale JSON-Größe
- Status
- Betrefflänge
- Preheaderlänge
- erlaubte Eingabestrukturen

MJML/HTML nicht blind als vertrauenswürdigen User-Input behandeln.

## 5. MJML-Integration

Serverseitiges Rendering:

```text
GrapesJS
   ↓
MJML
   ↓
MJML Compiler
   ↓
HTML
```

Nicht bei jedem Auto-Save rendern. Rendering bei Save/Preview oder über einen dedizierten Job.

### Renderer Service

```text
app/Services/Newsletter/MjmlRenderer.php
```

Verantwortung:

```text
render(string $mjml): RenderResult
```

Der Service:

1. erhält MJML
2. ruft den MJML-Compiler auf
3. verarbeitet Fehler/Warnungen
4. gibt HTML zurück
5. speichert selbst keine Daten

## 6. GrapesJS + Svelte

Empfohlene Struktur:

```text
src/
└── lib/
    └── newsletter-editor/
        ├── NewsletterEditor.svelte
        ├── grapes-config.ts
        ├── blocks.ts
        ├── commands.ts
        ├── storage.ts
        └── types.ts
```

Svelte übernimmt:

- Toolbar
- Seitenlayout
- Save
- Preview
- Template-Auswahl
- Newsletter-Metadaten

GrapesJS übernimmt:

- Canvas
- Drag & Drop
- Blocks
- Komponenten
- Style-Editing

## 7. Newsletter Blocks

MVP:

```text
Text
Bild
Button
Divider
Spacer
1 Spalte
2 Spalten
3 Spalten
Social Links
Logo
Footer
```

Später:

```text
Hero
Product Card
Article Card
Image + Text
CTA
Dynamic Content
```

Jeder Block sollte kontrolliert auf gültiges MJML abgebildet werden.

## 8. Design-System

Zentral definieren:

```text
Brand Colors
Typography
Button Styles
Container Width
Border Radius
Spacing
```

Nicht jeden Block separat hardcoden.

```text
Newsletter Theme
      │
      ├── colors
      ├── fonts
      ├── buttons
      ├── spacing
      └── layout
```

## 9. Personalisierung

Definierte Variablen verwenden:

```text
{{ first_name }}
{{ last_name }}
{{ company_name }}
{{ unsubscribe_url }}
```

Der Editor erhält eine Liste erlaubter Variablen.

Laravel ersetzt sie erst beim Versand:

```text
Editor
  ↓
{{ first_name }}
  ↓
Versand
  ↓
Maria
```

## 10. Preview

```text
GET /api/newsletters/{newsletter}/preview
```

Ablauf:

```text
design_json
    ↓
MJML
    ↓
HTML
    ↓
Browser
```

Optional:

- Desktop Preview
- Mobile Preview
- Dark-Mode-Test
- Testdaten

## 11. Test-E-Mail

```text
POST /api/newsletters/{newsletter}/send-test
```

Ablauf:

```text
Newsletter
    ↓
Render HTML
    ↓
Test recipient
    ↓
Mail Provider
```

Testversand und normaler Versand sollten dieselbe Rendering-Pipeline verwenden.

## 12. Versandarchitektur

Nicht im normalen HTTP-Request versenden.

Laravel Queue:

```text
Campaign
   ↓
Dispatch Job
   ↓
Queue
   ↓
SendNewsletterJob
   ↓
Mail Provider
```

Bei größeren Empfängerlisten:

```text
Newsletter
   ↓
Recipients
   ↓
Batch Jobs
   ↓
Queue Workers
```

## 13. Versandstatus

Newsletter:

```text
draft
ready
scheduled
sending
sent
cancelled
```

Empfänger separat:

```text
pending
sent
failed
bounced
```

## 14. Sicherheit

Besonders beachten:

- MJML validieren
- HTML nicht ungeprüft vertrauen
- keine beliebigen Script-Tags
- externe Ressourcen kontrollieren
- Links normalisieren
- Personalisierung serverseitig ersetzen

### Bilder

```text
Upload
  ↓
Laravel Storage
  ↓
S3-kompatibler Storage
  ↓
HTTPS URL
```

Keine lokalen Dateipfade in Newsletter-HTML verwenden.

## 15. MVP-Reihenfolge

### Phase 1 – Grundsystem

- Laravel
- PostgreSQL
- Newsletter Model
- Migration
- CRUD API
- Svelte Newsletter Route

### Phase 2 – Editor

- GrapesJS integrieren
- Newsletter Canvas
- Basis-Blocks
- Save/Load
- JSONB Speicherung

### Phase 3 – MJML

- MJML Renderer
- GrapesJS → MJML
- MJML → HTML
- HTML speichern
- Preview

### Phase 4 – Assets

- Image Upload
- Asset Manager
- Storage
- HTTPS URLs

### Phase 5 – Personalisierung

- Variablen-System
- Variable Picker
- Preview-Daten
- serverseitiges Replacement

### Phase 6 – Versand

- Test-E-Mail
- Queue
- Mail Provider
- Versandstatus
- Fehlerbehandlung

### Phase 7 – Versionierung

- Newsletter Versions
- Restore
- Autosave/Manual Save
- Änderungsverlauf

## 16. MVP-Scope

```text
✔ Newsletter CRUD
✔ GrapesJS Editor
✔ Text
✔ Bild
✔ Button
✔ Divider
✔ Spacer
✔ 1/2/3 Spalten
✔ MJML Rendering
✔ HTML Preview
✔ Image Upload
✔ Save/Load
✔ Test-Mail
✔ einfache Variablen
```

Noch nicht:

```text
✘ komplexe Dynamic Content Regeln
✘ A/B Testing
✘ Analytics
✘ Campaign Automation
✘ umfangreiche Versionierung
✘ Template Marketplace
✘ eigener Mailserver
```

## 17. Projektstruktur

```text
Laravel
├── app/
│   ├── Models/
│   │   ├── Newsletter.php
│   │   └── NewsletterVersion.php
│   │
│   ├── Services/
│   │   └── Newsletter/
│   │       ├── MjmlRenderer.php
│   │       ├── NewsletterRenderer.php
│   │       └── PersonalizationService.php
│   │
│   ├── Jobs/
│   │   └── SendNewsletterJob.php
│   │
│   └── Http/
│       ├── Controllers/
│       │   └── NewsletterController.php
│       └── Requests/
│
└── database/
    └── migrations/

Svelte
├── routes/
│   └── newsletters/
│       └── [id]/
│           └── edit/
│
└── lib/
    └── newsletter-editor/
        ├── NewsletterEditor.svelte
        ├── grapes-config.ts
        ├── blocks.ts
        ├── commands.ts
        ├── storage.ts
        └── types.ts
```

## 18. Zentrale technische Entscheidung

GrapesJS und MJML nicht über eine HTML-Rückkonvertierung koppeln.

Empfohlener Flow:

```text
GrapesJS
    │
    │ editor state
    ▼
Newsletter representation
    │
    ▼
MJML
    │
    ▼
MJML compiler
    │
    ▼
HTML
```

Nicht:

```text
GrapesJS
   ↓
fertiges HTML
   ↓
HTML → MJML
```

Die Rückrichtung HTML → MJML sollte vermieden werden.

## 19. Erster vertikaler Slice

Nicht sofort den kompletten Editor bauen.

Zuerst:

```text
Svelte
  ↓
GrapesJS
  ↓
ein Text-Block
  ↓
MJML
  ↓
Laravel
  ↓
PostgreSQL
  ↓
HTML Preview
```

Wenn dieser Pfad funktioniert, werden weitere Blocks und Features ergänzt.

## 20. Definition of Done für MVP

Ein Newsletter kann:

1. erstellt werden
2. im GrapesJS Editor bearbeitet werden
3. gespeichert werden
4. erneut geöffnet werden
5. Bilder enthalten
6. Desktop/Mobile previewed werden
7. als MJML gerendert werden
8. als HTML gerendert werden
9. Personalisierungsvariablen enthalten
10. als Test-Mail verschickt werden
11. über eine Queue an Empfänger verschickt werden

Der Editor und die Newsletter-Daten bleiben vollständig unter eigener Kontrolle.
