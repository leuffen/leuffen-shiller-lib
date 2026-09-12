# Schiller: typisierte Zugriffsschicht für Website-Seiten

| Datum | Benutzername | Kurzbeschreibung |
|---|---|---|
| 2026-09-12 | dermatthes | §§ 1–13: API-Entwurf mit Konfiguration, Beispielen, Rückgaben, bidirektionaler URL-Auflösung und Page-Builder-Zuordnung angelegt |
| 2026-09-12 | dermatthes | § 9.1: URL-Eingaben mit und ohne Domain oder Protokoll konkretisiert |

## § 1 Ziel und Umfang

**Vorschlag, noch keine implementierte API.** Der Aufrufer übergibt das Website-Quellverzeichnis als `PhoreDirectory`, beispielsweise das bereits extern bereitgestellte `docs/`. `SchillerDir` liest dessen aktuelle Konfiguration selbst und bietet einen typisierten Zugriff auf Dateien, Seiten, Sprachvarianten, Metafelder und Berechtigungen. Alle Schiller-Namen und Methoden in diesem Dokument sind vorgeschlagen; die ausdrücklich als bestehend bezeichneten Phore-Funktionen sind im Quellcode geprüft.

Der erste Ausbau umfasst normale Markdown-/HTML-Seiten, Front Matter, Inhalt, Übersetzungsdateien und berechnete Ziel-URLs. Git, Checkout, Commit, Push, Deploy, HTTP, Login, KI-Übersetzung und Bearbeitungssperren bleiben Aufgaben der Anwendung. `_data`-Editoren, Collections, Posts, Medienverwaltung und das Bearbeiten von Liquid-Layouts sind spätere Erweiterungen. Ihre Dateien dürfen bei erlaubtem Lesezugriff im Dateibaum erscheinen, werden aber nicht als normale Seiten interpretiert.

Empfehlung: **ein Einstiegspunkt `SchillerDir`, Seitenobjekte `SchillerPage`, eine getrennte `JekyllUrlResolver`-Klasse und austauschbare Formatadapter**. Speicherzugriff und Website-Format sind zwei unabhängige Schnittstellen. Eine neue Polyglot-Version erfordert damit keinen neuen Filesystem-Connector.

## § 2 Was der bisherige Page Builder benötigt

Die folgenden Befunde stammen aus `micx-io/micx-pagebuilder`; dessen Quellcode dient ausschließlich als Referenz. Das Ziel-Repository enthält derzeit ein PHP-Grundgerüst, noch keine Schiller-Implementierung.

| Bisherige Stelle | Beobachtete Aufgabe | Vorgeschlagene Schiller-API |
|---|---|---|
| `PageListCtrl::__invoke()` | Sections mit `_section.yml`, Gruppierung nach `pid` und Sprache, Fehlerliste | `pages()` mit rekursivem Seitenbaum und `Diagnostic[]` |
| `PageCtrl` / `FrontMatterFile::ReadPage()` | Markdown/HTML und YAML-Header lesen | `page(path)->read()` |
| `PageCtrl` / `FrontMatterFile::WritePage()` | Header und Inhalt speichern | `page(path)->update(PagePatch)` |
| `PageCtrl::copyPage()` | Neue Sprachdatei aus vorhandener Seite kopieren | `page(path)->createTranslation(NewTranslation)` |
| `www/pages/edit-page.html` | Titel, Beschreibung, Layout, published, order, ptags, eigene Formulare | `page(path)->fields()` und typisierte Feldwerte |
| `www/elements/page-list.html` | Seitengruppen, Sprachvarianten, Veröffentlichungsstatus | `PageTree`, `TranslationSet`, `Publication` |
| Vorschau-Link im Editor | URL bisher im Browser zusammengesetzt | `page(path)->url(language)` |
| Neue Anforderung für den Nachfolger | Von einer realen Website-URL zum bearbeitbaren Quelltext | `resolveUrl(url)` mit Datei, angefragter Sprache und tatsächlicher Quellsprache |
| `_data/languages.yml` | Verfügbare Sprachen | `config()->languages` aus Jekyll/Polyglot |
| `FileCtrl` und Daten-/Fragmenteditoren | Beliebige YAML-Daten bearbeiten | Später; bewusst keine allgemeine Schreib-Hintertür im ersten Ausbau |
| `InfoCtrl`, `RepoCtrl`, Middleware | Preview-Host, Änderungsstatus, VCS und Notifications | Anwendung; optionaler URL-Kontext, kein VCS in Schiller |

Das alte Listing verlangt Sections und sprachcodierte Dateinamen. Der neue Standardadapter darf diese Voraussetzungen nicht übernehmen. Der alte Editor unterstützt außerdem Mehrfachauswahl und eine numerische Sortierung; deshalb schlägt § 7 neben Boolean und Dropdown auch `integer` und `multiselect` vor, ohne bereits einen Dateneditor zu entwerfen.

## § 3 Einstieg: ein Verzeichnis genügt

```php
use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\AccessContext;

// Checkout und Auswahl von docs/ erledigt die Anwendung.
$site = new SchillerDir(phore_dir('/srv/websites/customer/docs'));
$config = $site->config();             // SiteConfig
$files = $site->files();               // FileListing, eine Ebene
$pages = $site->pages();               // PageTree, rekursiv

// Rollen stammen aus der bereits authentifizierten Anwendung.
$site = new SchillerDir(
    phore_dir('/srv/websites/customer/docs'),
    access: new AccessContext(role: 'user'),
);
```

Ohne `AccessContext` verwendet Schiller die Rolle `reader`: ausschließlich lesend und nur für explizit freigegebene Bereiche. Fehlt `schiller.yaml`, ist die Inspektion normaler Seiten und ihrer Vorfahren read-only erlaubt; sonstige Dateien bleiben verborgen. Keine stillschweigenden Administratorrechte. Eine fehlende optionale Schiller-Konfiguration verhindert also nicht das Lesen einer bestehenden Website.

`_config.yml` und `schiller.yaml` werden ausschließlich im übergebenen Root gesucht. Keine Suche im Repository-Elternverzeichnis. Ein gesetztes Jekyll-`source`, das auf ein anderes Root zeigt, erzeugt `SOURCE_ROOT_MISMATCH`; Schiller wechselt das Root nicht selbst. Fehlende `_config.yml`, ungültiges YAML oder unbekannte explizite Adapter erzeugen einen `ConfigurationException` mit geeigneter Diagnose. Beide Dateien werden nicht durch das Öffnen angelegt oder verändert.

Jede öffentliche Operation prüft die Konfigurationsdateien erneut. Ein bereits erstelltes `SchillerPage` ist nur ein Pfadhandle und verwendet beim nächsten Aufruf ebenfalls den aktuellen Stand. Innerhalb einer Operation gilt ein konsistenter Konfigurationsstand. Änderungen durch andere Prozesse während einer Operation müssen erkannt werden oder durch die externe Anwendung ausgeschlossen sein. Ein dauerhaft laufender Page Builder muss deshalb nach einer Konfigurationsänderung nicht neu konstruiert werden.

Beispiel für `SiteConfig`, hier als JSON dargestellt:

```json
{
  "adapter": {"id": "jekyll-polyglot", "version": 1},
  "languages": ["de", "en", "fr"],
  "defaultLanguage": "de",
  "url": "https://example.org",
  "baseurl": "/praxis",
  "schemaVersion": 1
}
```

`SiteConfig` ist eine geprüfte öffentliche Projektion, kein unbeschränkter Dump der Jekyll-Konfiguration oder aller Rollenregeln. Interne Konfigurationszugriffe geben dem Benutzer kein Recht, diese Dateien über `files()` oder andere Zugriffe auszulesen.

## § 4 Dateibaum und Seitenbaum

### § 4.1 Dateien: echte Quellpfade

```php
$listing = $site->files('leistungen', recursive: false); // FileListing
foreach ($listing->entries as $entry) {                 // FileEntry
    echo $entry->path;                                  // string, relativ zum Root
    echo $entry->kind->value;                           // FileKind enum
}
```

```json
{
  "path": "leistungen",
  "entries": [
    {"path": "leistungen/details", "kind": "directory", "children": []},
    {"path": "leistungen/diagnostik.md", "kind": "page", "children": []},
    {"path": "leistungen/index.md", "kind": "page", "children": []}
  ],
  "diagnostics": []
}
```

`FileKind` hat `directory`, `page`, `asset`, `data`, `template`, `other`. Bei `recursive: true` enthält `children` rekursiv `FileEntry[]`, bei `false` ist es leer. Alle Pfade sind Root-relativ mit `/`, ohne führenden Slash; `''` bezeichnet das Root. Die Ausgabe ist deterministisch: Verzeichnisse zuerst, dann Name. Das Listing liefert keine rohen `PhoreFile`-Objekte, mit denen die Rechteprüfung umgangen werden könnte.

### § 4.2 Seiten: logische Gruppen und physische Ordner

```php
$tree = $site->pages();                    // PageTree
$subtree = $site->pages('leistungen');     // PageTree für diesen Quell-Unterbaum
$page = $site->page('leistungen/diagnostik.md'); // SchillerPage
```

Ein `PageTree` enthält `root: PageFolder` und `diagnostics: list<Diagnostic>`. Jeder `PageFolder` hat `path`, `folders: list<PageFolder>` und `pages: list<PageGroup>`. `PageGroup` enthält `id`, `title`, `primaryPath` und `translations: list<TranslationSummary>`. Der Baum ist eine Inhaltsübersicht, kein aus Permalinks abgeleiteter Navigationsbaum. Die echte Ablage aller Übersetzungsdateien bleibt in `files()` sichtbar.

Die Gruppe wird einmal unter dem Ordner ihrer lesbaren Standardsprachdatei eingeordnet, sonst unter dem lexikographisch ersten lesbaren Variantenpfad. Eine Unterbaumabfrage wählt Gruppen anhand dieses `primaryPath`; die Sprachübersicht darf zusätzlich lesbare Varianten außerhalb dieses Unterbaums referenzieren. Rechtefilterung geschieht vor dieser Auswahl. Ausgeblendete Varianten beeinflussen weder Titel noch Einordnung oder Zähler.

Vollständiges kleines Rückgabebeispiel, unabhängig von der größeren Beispielfixture in § 6:

```json
{
  "root": {
    "path": "",
    "folders": [{
      "path": "leistungen",
      "folders": [],
      "pages": [{
        "id": "diagnostics",
        "title": "Diagnostik",
        "primaryPath": "leistungen/diagnostik.md",
        "translations": [{
          "language": "de",
          "path": "leistungen/diagnostik.md",
          "published": true,
          "targetPath": "/praxis/diagnostik/"
        }]
      }]
    }],
    "pages": []
  },
  "diagnostics": []
}
```

Im Seitenbaum erscheinen auch lesbare `published: false`-Seiten. Der Status beschreibt die Quellkonfiguration, nicht den Zustand der zuletzt ausgelieferten Website. Jekyll-ausgeschlossene Dateien sind im Dateibaum bei Leserecht sichtbar, im regulären Seitenbaum aber nicht. `_data`, `_includes`, `_layouts`, `_posts`, Collections und das Build-Ziel werden nicht versehentlich als Seiten aufgenommen. Markdown ohne Header wird nur mit unterstütztem `jekyll-optional-front-matter` als Seite eingeordnet; malformed Front Matter bleibt ein Fehler.

## § 5 Einzelne Datei: Header, Inhalt und abgeleitete Werte

```php
$page = $site->page('leistungen/diagnostik.md');
$document = $page->read();                       // PageDocument
$title = $document->header->string('title');     // ?string
$published = $document->effectiveHeader->bool('published', true); // bool
$body = $document->content;                     // string, Markdown oder HTML
$source = $document->header->all();              // array<string, YamlValue>
$layoutSource = $document->origins['layout'];    // ValueOrigin enum
```

`FrontMatter` bietet `string(key, default = null): ?string`, `bool(key, default = null): ?bool`, `int(key, default = null): ?int`, `strings(key): list<string>`, `has(key): bool`, `get(key): YamlValue` und `all(): array<string,YamlValue>`. Falsche Typen erzeugen `FieldTypeException`; etwa `"false"` wird nicht in `true` umgewandelt. `YamlValue` ist ein dokumentierter rekursiver PHPDoc-Typ für `null|bool|int|float|string|list<YamlValue>|array<string,YamlValue>`, kein erfundener nativer PHP-Typ.

```json
{
  "path": "leistungen/diagnostik.md",
  "format": "markdown",
  "header": {
    "page_id": "diagnostics", "lang": "de", "title": "Diagnostik",
    "short_title": "Diagnostik", "published": true, "permalink": "/diagnostik/"
  },
  "effectiveHeader": {
    "layout": "default", "page_id": "diagnostics", "lang": "de",
    "title": "Diagnostik", "short_title": "Diagnostik",
    "published": true, "permalink": "/diagnostik/"
  },
  "origins": {
    "layout": "jekyll_default", "page_id": "file", "lang": "file",
    "title": "file", "short_title": "file", "published": "file", "permalink": "file"
  },
  "content": "## Diagnostik\n\nBeispielinhalt.\n",
  "publication": "published"
}
```

`header` enthält ausschließlich die gespeicherten YAML-Werte. `effectiveHeader` ergänzt die wirksamen Jekyll-Defaults gemäß Pfad/Typ und Spezifität. Dateiwerte gewinnen. `ValueOrigin` kennt `file`, `jekyll_default`, `adapter_default`; Schiller-Felddefaults werden nur beim expliziten Anlegen verwendet und verändern nicht stillschweigend die Jekyll-Lesesemantik. `Publication` kennt `published`, `unpublished`, `excluded`, `unknown`. Unbekannte Zusatzfelder bleiben erhalten. `content` enthält den Quelltext nach dem schließenden Header-Delimiter, ohne Rendern von Liquid.

Für schreibende Beispiele wird eine berechtigte `user`-Instanz aus § 3 angenommen:

```php
use Leuffen\Schiller\PagePatch;
use Leuffen\Schiller\FrontMatterPatch;

$updated = $page->update(new PagePatch(
    header: new FrontMatterPatch(set: ['short_title' => 'Untersuchungen']),
)); // PageDocument: content und alle anderen Header-Werte bleiben erhalten

$updated = $page->update(new PagePatch(content: "## Neuer Inhalt\n"));
// PageDocument: Header semantisch unverändert

$updated = $page->update(new PagePatch(
    header: new FrontMatterPatch(remove: ['short_title']),
)); // explizites Entfernen; set: ['short_title' => null] wäre ein YAML-null
```

`PagePatch::$content === null` bedeutet unverändert, `''` leert den Body. `FrontMatterPatch` ersetzt nur genannte Schlüssel; strukturierte Werte werden je Schlüssel vollständig ersetzt. Die Kernfelder `page_id` und `lang` sind nicht frei patchbar. `layout`, `published` und `permalink` werden anhand ihrer Felddefinition und der zulässigen Ziel-URL geprüft. `update()` erzeugt keine neue Datei. Schreibfehler werden als Exception gemeldet, nicht als erfolgreiches DTO.

Phore serialisiert beim Schreiben YAML erneut: Header-Kommentare, Einrückung und Schlüsselreihenfolge sind dabei nicht als erhalten garantiert. Ein reines Body-Update sollte daher den originalen Headerblock unangetastet lassen. Ein Header-Update bewahrt den Body bytegenau. Unveränderte Daten lösen keine Schreiboperation aus. Konfliktauflösung und Sperren sind kein Gegenstand dieses Entwurfs; die Anwendung serialisiert parallele Bearbeitungen, bis dafür ein eigener Vertrag besteht.

## § 6 Polyglot und Übersetzungen

### § 6.1 Konfiguration und Beispieldateien

Alle folgenden Website-Daten sind synthetische Beispiele. Die untersuchte bestehende Kundenwebsite wird durch diesen Entwurf weder umgestellt noch verändert.

`docs/_config.yml`:

```yaml
url: https://example.org
baseurl: /praxis
plugins: [jekyll-polyglot]
languages: [de, en, fr]
default_lang: de
exclude: [schiller.yaml]
exclude_from_localization: [assets]
defaults:
  - scope: {path: "", type: pages}
    values: {layout: default}
```

`docs/leistungen/diagnostik.md`:

```markdown
---
page_id: diagnostics
lang: de
title: Diagnostik
short_title: Diagnostik
published: true
permalink: /diagnostik/
---
## Diagnostik

Beispielinhalt.
```

`docs/en/diagnostics.md`:

```markdown
---
page_id: diagnostics
lang: en
title: Diagnostics
published: true
permalink: /diagnostics/
---
## Diagnostics
```

Polyglot verbindet Sprachdateien über `page_id`, andernfalls über den passenden Permalink. `lang` bezeichnet die Sprache; optional kann `lang_from_path` Sprache aus Pfadsegmenten ableiten. Die Default-Sprache hat kein zusätzliches Sprachpräfix, weitere Sprachen erhalten es. Fehlende Übersetzungen können auf die Default-Sprache zurückfallen. Diese tatsächlichen Polyglot-Regeln bilden die Adaptergrundlage; Schiller setzt darauf einen eigenen, versionsstabilen Rückgabetyp. [Polyglot-Dokumentation](https://github.com/untra/polyglot#how-to-use-it)

Neue Übersetzungsgruppen erhalten vorzugsweise explizites `page_id`. Bei bestehenden Seiten ohne ID wird eine deterministische ID aus dem vom Adapter berechneten unlokalisierten Pfad gebildet, ohne dabei Dateien zu ändern. Ohne eindeutigen Gruppenschlüssel darf Schiller nicht aufgrund ähnlicher Titel raten. Doppelte Kombinationen von Gruppen-ID und Sprache erzeugen eine Diagnose und sperren das Schreiben der betroffenen Gruppe.

### § 6.2 Übersetzungen samt Fallback anzeigen

```php
$translations = $page->translations(); // TranslationSet
$english = $site->page('en/diagnostics.md')->read(); // PageDocument
```

```json
{
  "pageId": "diagnostics",
  "availableLanguages": ["de", "en"],
  "missingLanguages": ["fr"],
  "items": [
    {"language": "de", "state": "existing", "path": "leistungen/diagnostik.md", "sourceLanguage": "de", "published": true, "targetPath": "/praxis/diagnostik/", "canCreate": false},
    {"language": "en", "state": "existing", "path": "en/diagnostics.md", "sourceLanguage": "en", "published": true, "targetPath": "/praxis/en/diagnostics/", "canCreate": false},
    {"language": "fr", "state": "fallback", "path": null, "sourceLanguage": "de", "published": null, "targetPath": "/praxis/fr/diagnostik/", "canCreate": true}
  ]
}
```

`TranslationState` hat `existing`, `fallback`, `missing`. `existing` bedeutet eine wirkliche lesbare Quelldatei, auch wenn `published: false`. Fallback bedeutet keine eigene Datei; `path` bleibt `null`, und `sourceLanguage` zeigt die lesbare Ausgangssprache. Ohne geeignete veröffentlichbare Ausgangsseite lautet der Zustand `missing`, `targetPath` bleibt `null`. Schillers `missingLanguages` meint konsequent fehlende Quelldateien; diese Definition ist bewusst unabhängig von Polyglots möglicherweise anders definierter, beim Build erzeugter Variable gleichen Namens.

Bei verweigertem Leserecht wird die betreffende Variante vollständig aus `items`, `availableLanguages` und `missingLanguages` entfernt, einschließlich URL und Quelle. Eine vorhandene verborgene Variante wird niemals als fehlend angeboten. Berechtigungseingeschränkte Rückgaben beanspruchen keine vollständige Inventarisierung. Vorhandene Varianten werden pro tatsächlichem Pfad geprüft; für fehlende Varianten bestimmt `translation_path` den Kandidaten, dessen Leserecht ebenfalls erforderlich ist. Fallback-Inhalte werden nur aus lesbaren Quellen abgeleitet.

### § 6.3 Eine Übersetzungsdatei anlegen

```php
use Leuffen\Schiller\NewTranslation;

$french = $page->createTranslation(new NewTranslation(
    language: 'fr',
    path: 'fr/diagnostics.md',
    permalink: '/diagnostic/',
    title: 'Diagnostic',
    content: "## Diagnostic\n\nTexte français.\n",
)); // PageDocument
```

```json
{
  "path": "fr/diagnostics.md",
  "header": {"page_id": "diagnostics", "lang": "fr", "title": "Diagnostic", "permalink": "/diagnostic/", "published": false},
  "publication": "unpublished"
}
```

Die letzte Rückgabe ist eine Projektion des vollständigen `PageDocument`. Die Datei wird standardmäßig unveröffentlicht angelegt. Übernommene benutzerdefinierte Metafelder werden validiert; Übersetzungs-ID, Sprache, Titel, Permalink und Veröffentlichungsstatus setzt Schiller kontrolliert. Es findet keine maschinelle Übersetzung statt. Wird `content` weggelassen, kann die bestehende Quelle als Arbeitskopie dienen, bleibt aber unveröffentlicht. `path` ist optional, wenn `translation_path` konfiguriert ist; `permalink` ist für neue Varianten explizit anzugeben, damit der Quellpfad nicht versehentlich zum Ziel-Link wird.

Benötigt werden Lesen der Quelle, `createTranslation` und `createFile` am Ziel sowie Schreiben aller tatsächlich zu ändernden Quellen. Existiert die Datei oder die Sprachvariante schon, folgt `AlreadyExistsException`; niemals überschreiben. Fehlt der Quelle ein explizites `page_id`, erhält die neue Datei als `page_id` den bereits wirksamen Gruppenschlüssel nur dann, wenn das gewählte Adapterprofil diese gemischte Zuordnung nachweislich unterstützt. Andernfalls `IdentityRequiredException`: zuerst eine spätere explizite Gruppen-Normalisierung, kein verstecktes Umschreiben mehrerer Dateien.

## § 7 schiller.yaml: Metafelder, Presets und Bereiche

Die Datei liegt im übergebenen Root, also hier `docs/schiller.yaml`. `schema_version` versioniert ausschließlich die Schiller-YAML-Struktur. `adapter.version` bezeichnet einen Schiller-Kompatibilitätsvertrag, **nicht** die Gem-Version von Polyglot.

```yaml
schema_version: 1
adapter:
  id: jekyll-polyglot
  version: 1
  options:
    translation_path: "{lang}/{page_id}.md"

fields:
  title:
    type: string
    label: Seitentitel
    description: Titel der Seite
    required: true
  short_title:
    type: string
    label: Kurztitel
    description: Kurzer Titel für die Navigation
    max_length: 60
  published:
    type: boolean
    label: Veröffentlicht
    default: false
  layout:
    type: select
    label: Layout
    options:
      - {value: default, label: Standard}
      - {value: landing, label: Landingpage}
  order:
    type: integer
    label: Sortierung
  ptags:
    type: multiselect
    label: Navigation
    options:
      - {value: nav, label: Hauptnavigation}
      - {value: subnav, label: Unternavigation}

presets:
  standard: [title, published, layout]
  navigation: [short_title, order, ptags]

scopes:
  - path: "**"
    presets: [standard]
  - path: "leistungen/**"
    presets: [navigation]
  - path: "en/**"
    presets: [navigation]
  - path: "fr/**"
    presets: [navigation]

permissions:
  default: deny
  roles:
    reader:
      allow:
        - {path: "**", actions: [read]}
      deny:
        - {path: "intern/**", actions: [read]}
        - {path: "schiller.yaml", actions: [read]}
        - {path: "_config.yml", actions: [read]}
    user:
      allow:
        - {path: "**", actions: [read]}
        - {path: "leistungen/**", actions: [write, createFile]}
        - {path: "en/**", actions: [write, createFile, createTranslation]}
        - {path: "fr/**", actions: [write, createFile, createTranslation]}
      deny:
        - {path: "intern/**", actions: [read, write, createFile, createTranslation]}
        - {path: "schiller.yaml", actions: [read, write]}
        - {path: "_config.yml", actions: [read, write]}
    admin:
      allow:
        - {path: "**", actions: [read, write, createFile, createTranslation, createTemplate]}
```

Diese `permissions` sind Projektregeln. Eine von der Host-Anwendung gesetzte `AccessPolicy` begrenzt sie zusätzlich und kann niemals durch YAML erweitert werden. Die Rolle wird ausschließlich serverseitig übergeben; `admin` im Request-Body ist keine Rollenquelle. Konfigurationsdateien, ausführbare Plugins und Policies sind über die Seiten-API auch für `admin` nicht schreibbar. Eine spätere Konfigurationsverwaltung benötigt einen eigenen vertrauenswürdigen Einstieg.

Alle passenden `scopes` werden in Dokumentreihenfolge angewendet, Preset-Feldlisten additiv ohne Duplikate; die erste Position bestimmt die Anzeigeordnung. Unbekannte Presets/Felder und widersprüchliche Definitionen sind Konfigurationsfehler. Im ersten Vertrag definieren Scopes nur die Feldauswahl, keine impliziten Typüberschreibungen. `path` bezieht sich auf die tatsächliche Datei, auch bei Übersetzungen. Das vereinfacht insbesondere die Übereinstimmung mit Pfadberechtigungen.

Für Schiller-Pfade gilt: `*` trifft innerhalb eines Segments, `**` über Segmentgrenzen, `leistungen/**` umfasst auch den Ordner `leistungen`. Ein exakter Dateipfad ist ebenfalls zulässig. Das ist Schillers eigene Glob-Semantik, nicht ungeprüft die Semantik von Jekyll-Defaults oder Polyglot-Regulärausdrücken. Verzeichnis-Leserecht ist Voraussetzung zum Traversieren; eine gesperrte Elternstruktur wird nicht durch eine Kindfreigabe offengelegt.

```php
$fields = $page->fields();                     // FieldSet
$shortTitle = $fields->get('short_title');      // FieldDefinition
$value = $document->header->string('short_title'); // ?string
```

Beispielprojektion einer Felddefinition:

```json
{
  "key": "short_title",
  "type": "string",
  "label": "Kurztitel",
  "description": "Kurzer Titel für die Navigation",
  "required": false,
  "nullable": false,
  "maxLength": 60,
  "hasDefault": false,
  "default": null,
  "options": [],
  "editable": true
}
```

`FieldType` ist ein string-backed Enum (`string`, `boolean`, `integer`, `select`, `multiselect`). Dropdown-Optionen sind `FieldOption`-Objekte mit `value: string` und `label: string`; Mehrfachauswahl speichert `list<string>`. `hasDefault` unterscheidet fehlenden Default von explizitem `null`; `nullable` regelt, ob `null` erlaubt ist. Unbekannte vorhandene Metafelder bleiben beim Lesen und Schreiben erhalten, dürfen über das reguläre Patch-API aber nicht neu gesetzt werden, solange keine Felddefinition existiert. Adaptereigene Standardfelder wie `description` und `permalink` werden als eingebaute Definitionen bereitgestellt; `page_id` und `lang` bleiben schreibgeschützt. Neue Pflichtfelder dürfen Bestandsseiten lesbar lassen, verhindern jedoch ein nicht valides Speichern mit präzisen Feldfehlern.

## § 8 Rechte und erlaubte Aktionen

```php
$actions = $site->capabilities('leistungen/diagnostik.md'); // Capabilities
if ($actions->write) {
    // UI darf den Speichern-Button aktivieren; update() prüft erneut.
}
$targetActions = $site->capabilities('fr/diagnostics.md');
```

```json
{
  "read": true,
  "write": true,
  "createFile": false,
  "createTranslation": false,
  "createTemplate": false
}
```

Das Beispiel bezieht sich auf eine bereits vorhandene normale Seite als `user`; Erstellen ist für ein bestehendes Ziel nicht möglich. Für einen fehlenden französischen Zielpfad können `createFile` und `createTranslation` wahr sein. `Capabilities` sind die Schnittmenge aus Projektregeln, Host-Policy, Adapter-/Storage-Unterstützung und Ressourcenzustand. `createTemplate` bleibt im ersten Ausbau auch bei Admin-Freigabe `false`, weil noch kein Template-Editor/Writer implementiert wird. Feld-Presets sind davon unabhängig; Layout-Dateien und Seitenvorlagen sind nicht dasselbe wie Felddefinitionen.

Jede Operation prüft Rechte, nicht nur das Listing. Verweigertes Lesen ergibt bei Direktzugriff dieselbe `NotFoundException` wie eine fehlende Datei. Listen, Sprachgruppen, Zähler und Diagnosen enthalten keinerlei Namen, IDs oder Metadaten verborgener Dateien. Nur wenn eine Datei lesbar ist, darf fehlendes Schreibrecht als `AccessDeniedException` sichtbar werden. Nicht erlaubte Schreibaktionen bleiben auch dann verboten, wenn der Benutzer das JSON manuell manipuliert.

`deny` gewinnt immer gegenüber `allow`; keine Rollenvererbung im ersten Vertrag. Unbekannte Rollen haben keine Rechte. Für Anlageoperationen sind Ziel und notwendige Elternverzeichnisse zu prüfen. V1 verlangt vorhandene Elternordner; automatische Verzeichniserstellung wird nicht implizit mit freigegeben. Eine interne Existenzprüfung verhindert Überschreiben, ohne verborgene Ziele über unterschiedliche Fehlermeldungen offenzulegen.

Alle Speicheroperationen sind an das Root gebunden: keine absoluten Eingabepfade, `..`, Nullbytes oder Symlinks; Symlinks werden im ersten Ausbau nicht verfolgt. Ein HTTP-Layer decodiert Transportdaten einmal, Schiller betreibt keine zusätzliche URL-Decodierung von Dateipfaden. Auch von Adaptern berechnete Pfade durchlaufen dieselbe Prüfung. Bloße String-Präfixprüfungen reichen nicht; der Connector muss seine Root-Grenze für reale Lese-/Schreibzugriffe durchsetzen. Fehlertexte enthalten keine absoluten Serverpfade.

## § 9 Ziel-URLs als separate, typisierte Berechnung

```php
$url = $page->url('en');                  // PageUrl
echo $url->targetPath;                   // /praxis/en/diagnostics/
echo $url->absoluteUrl;                  // https://example.org/praxis/en/diagnostics/

// Gleicher Resolver auch separat, mit bereits autorisierten Eingaben nutzbar:
$resolver = new JekyllUrlResolver($site->config());
$url = $resolver->resolve($page->urlInput('en')); // UrlInput -> PageUrl
```

```json
{
  "language": "en",
  "sourceLanguage": "en",
  "permalink": "/diagnostics/",
  "targetPath": "/praxis/en/diagnostics/",
  "absoluteUrl": "https://example.org/praxis/en/diagnostics/",
  "publication": "published",
  "resolution": "resolved"
}
```

`UrlInput` enthält bereits adapterseitig bestimmte Sprachzuordnung, unlokalisierten Seitenpfad, lokalen Permalink, Format, Publication und Fallback-Quelle. Es enthält keine unautorisierten Varianten. Der Resolver macht selbst keinen Filesystem-Zugriff und interpretiert nicht willkürlich andere Sprachdateien. `PageUrl` ist eine Berechnung gemäß Quellstand; es bestätigt weder Build-Erfolg noch Erreichbarkeit. Ein Preview-Host kann über einen expliziten `UrlContext` in der Anwendung ersetzt werden, ohne `_config.yml` umzuschreiben.

Berechnungsregeln für das initiale Profil:

1. Seitenpfad nach Jekyll bestimmen: dateilokaler `permalink` oder Standardausgabe von Quellpfad/Format; `index.md` wird `index.html` mit Verzeichnis-URL. Seiten unterstützen ihre eigenen Platzhalter `:path`, `:basename`, `:output_ext`. Globale Post-Permalinkmuster dürfen nicht als Post-Logik auf normale Seiten angewendet werden. Die aktuell dokumentierte Besonderheit, dass `permalink` aus Front-Matter-Defaults für Pages ignoriert wird, wird ausdrücklich berücksichtigt. [Jekyll Permalinks](https://jekyllrb.com/docs/permalinks/)
2. Für die gewünschte Sprache deren Variante wählen; wenn zulässig und keine eigene Variante vorliegt, veröffentlichbare Default-Quelle als Fallback verwenden. `exclude_from_localization` beachten; ausgeschlossene Ziele werden nicht einfach mit einem Sprachpräfix erfunden.
3. `baseurl`, eventuell Sprachpräfix und unlokalisierten Pfad genau einmal zusammenfügen. Ein Quellordner `en/` darf nicht zusätzlich zu einem bereits lokalisierten Ausgabepfad gezählt werden. Bei expliziten Permalinks bestimmt der Permalink die Route, nicht der Quellordner.
4. `url` aus der effektiven Konfiguration ergibt die absolute URL. Ohne gültige Basis ist `absoluteUrl: null`, aber `targetPath` kann vorhanden sein. Keine Domain aus `CNAME` erraten. Nur HTTP(S)-Origins sind als absolute Basis erlaubt; Permalinks dürfen keine externen URLs oder Query-/Fragmentteile einschleusen.
5. Nicht unterstützte Liquid-Ausdrücke, URL-verändernde Plugins oder uneindeutige Platzhalter liefern `resolution: unresolved`, nullable Zielwerte und Diagnose. Nicht raten. Für unveröffentlichte Seiten darf eine berechenbare potenzielle Ziel-URL vorliegen; `publication` bleibt `unpublished`.

| Eingang | Sprache | Erwarteter `targetPath` bei obiger Konfiguration |
|---|---|---|
| `index.md`, kein Permalink | de | `/praxis/` |
| `leistungen/diagnostik.md`, kein Permalink | de | `/praxis/leistungen/diagnostik.html` |
| `permalink: /diagnostik/` | de | `/praxis/diagnostik/` |
| `permalink: /diagnostics/` | en | `/praxis/en/diagnostics/` |
| Französische Datei fehlt, deutsche Quelle ist publizierbar | fr | `/praxis/fr/diagnostik/`, ausdrücklich Fallback |
| `published: false`, `permalink: /neu/` | de | `/praxis/neu/`, keine Aussage über öffentliche Erreichbarkeit |

Jekyll-Defaults werden für andere Felder wie Layout, Sprache oder published gemäß Pfad-/Typregeln aufgelöst; spezifischere Scopes und anschließend Dateiwerte haben Vorrang. URL-Sonderregeln werden getrennt behandelt. [Jekyll Front Matter Defaults](https://jekyllrb.com/docs/configuration/front-matter-defaults/)

### § 9.1 Rückrichtung: reale URL zu Quelldatei und Sprache

```php
$match = $site->resolveUrl(
    'https://example.org/praxis/en/diagnostics/?campaign=mail#details'
); // UrlLookupResult

if ($match->status === UrlMatchStatus::Matched) {
    $source = $site->page($match->match->path)->read(); // PageDocument
    echo $match->match->requestedLanguage;            // en
    echo $match->match->sourceLanguage;               // en
}
```

```json
{
  "status": "matched",
  "match": {
    "pageId": "diagnostics",
    "path": "en/diagnostics.md",
    "requestedLanguage": "en",
    "sourceLanguage": "en",
    "isFallback": false,
    "targetPath": "/praxis/en/diagnostics/",
    "absoluteUrl": "https://example.org/praxis/en/diagnostics/"
  },
  "diagnostics": []
}
```

Für `https://example.org/praxis/fr/diagnostik/` liefert dieselbe Fixture:

```json
{
  "status": "matched",
  "match": {
    "pageId": "diagnostics",
    "path": "leistungen/diagnostik.md",
    "requestedLanguage": "fr",
    "sourceLanguage": "de",
    "isFallback": true,
    "targetPath": "/praxis/fr/diagnostik/",
    "absoluteUrl": "https://example.org/praxis/fr/diagnostik/"
  },
  "diagnostics": []
}
```

Die französische URL zeigt hier deutschen Fallback-Quelltext. Der Editor kann damit ausdrücklich „französische Übersetzung anlegen“ anbieten. Er darf nicht unbemerkt die deutsche Datei als französische Übersetzung bearbeiten. `path` identifiziert immer die tatsächlich vorhandene Quelle; eine fehlende Sprachdatei wird nicht erfunden.

Der Resolver invertiert den **gleichen berechneten Routenindex**, den auch die Vorwärtsauflösung benutzt. Er schneidet nicht bloß `.html` ab oder entfernt ein Sprachpräfix vom URL-Pfad. Pro publizierbarer, vom Adapter unterstützter Route werden Zielpfad, Gruppen-ID, angefragte Sprache und Quelldatei indiziert; so funktionieren auch völlig unterschiedliche Permalinks und Fallback-Routen. Alle echten Kollisionen werden intern erkannt, bevor die autorisierte Projektion erstellt wird. Ein durch private Kandidaten blockierter Treffer wird ohne Details als `not_found` behandelt.

`UrlMatchStatus` kennt `matched`, `not_found`, `ambiguous`, `unsupported`. `match` ist nur bei `matched` ein `UrlMatch`, sonst `null`. `ambiguous` gilt für mehrere erlaubte mögliche Quellen; es gibt keinen willkürlichen ersten Treffer. `unsupported` bedeutet, dass die Route durch einen bekannten, aber nicht unterstützten Generator/Redirect/Plugin nicht sicher auflösbar ist. Bei vollständig dynamischen, statisch unbekannten Routen kann nur `not_found` plus allgemeine Site-Diagnose über unvollständige Routenabdeckung geliefert werden. Verborgene Quellen erzeugen keine sichtbaren Kandidaten oder Fehlerdetails.

```json
{"status": "not_found", "match": null, "diagnostics": []}
```

Für absolute URLs werden Scheme, Host und effektiver Port mit dem konfigurierten Origin verglichen; kein Fetch und kein Folgen von Weiterleitungen. Query und Fragment werden für die Dateizuordnung ignoriert. Der `baseurl` muss an einer Segmentgrenze passen: `/praxis2/` gehört nicht zu `/praxis`. Case des Pfades bleibt erhalten. Ungültige Prozentkodierung, kodierte Separatoren, Nullbytes und Traversal-Segmente werden als ungültige Eingabe zurückgewiesen; erlaubte UTF-8-Segmente werden konsistent mit der Vorwärtsauflösung normalisiert. Pfadseparatoren werden nicht durch wiederholtes Decodieren eingeschleust.

`resolveUrl()` akzeptiert Eingaben **mit und ohne Domain sowie mit und ohne HTTP(S)-Protokoll**. Ein reiner Pfad braucht keine konfigurierte Domain. Andere Hosts, alternative Domains oder Preview-Hosts gelten nur bei explizitem vertrauenswürdigem `UrlContext` als gleichwertig. Fehlende `url` in Jekyll erlaubt weiterhin pfadbasierte Aufrufe, aber keine automatische Zuordnung einer angegebenen Domain. [geändert]

| Gleichwertiger Aufruf bei `baseurl: /praxis` | Interpretation |
|---|---|
| `resolveUrl('https://example.org/praxis/en/diagnostics/')` | Vollständige absolute URL |
| `resolveUrl('http://example.org/praxis/en/diagnostics/')` | Absolute URL; abweichendes Scheme nur mit ausdrücklich erlaubtem Origin |
| `resolveUrl('//example.org/praxis/en/diagnostics/')` | Domain mit protokollrelativer Schreibweise |
| `resolveUrl('example.org/praxis/en/diagnostics/')` | Domain ohne Protokoll; gegen den konfigurierten Host prüfen |
| `resolveUrl('/praxis/en/diagnostics/')` | Origin-relativer Pfad, ohne Domain/Protokoll |
| `resolveUrl('praxis/en/diagnostics/')` | Gleicher Origin-relativer Pfad ohne führenden Slash |

Die Tabelle definiert akzeptierte Schreibweisen; die HTTP-Zeile wird erst bei passender Origin-Freigabe gleichwertig. Ohne angegebenes Scheme wird bei einer Host-Eingabe der konfigurierte Origin verwendet. Domainlose Pfade beziehen sich immer auf das Origin-Root und enthalten daher einen vorhandenen `baseurl`; sie werden nicht relativ zu einer zufälligen aktuell geöffneten Seite interpretiert. [neu]

Ein erster Abschnitt wie `example.org` wird nur dann als Host akzeptiert, wenn er einem konfigurierten Host beziehungsweise Alias entspricht. Andere erkennbar hostförmige Eingaben werden abgelehnt, nicht still zu lokalen Dateien umgedeutet. Für einen lokalen ersten Pfadabschnitt mit Punkt sorgt ein führender Slash für Eindeutigkeit. Das Ergebnis aller erlaubten gleichwertigen Schreibweisen ist derselbe `UrlMatch`; es erfolgt weder ein Netzwerkzugriff noch ein implizites Ändern der Website-Domain. [neu]

```php
$absolute = $site->resolveUrl('https://example.org/praxis/en/diagnostics/');
$withHost = $site->resolveUrl('example.org/praxis/en/diagnostics/');
$pathOnly = $site->resolveUrl('/praxis/en/diagnostics/');
$barePath = $site->resolveUrl('praxis/en/diagnostics/');
// Jeweils: match->path === 'en/diagnostics.md', sourceLanguage === 'en'.
```

Schiller kennt im ersten Vertrag exakt die generierten Routen und deren Jekyll-Indexalias: `/x/` und `/x/index.html` können dieselbe erzeugte Indexdatei bezeichnen. `/x`, `/x/` und `/x.html` werden ansonsten nicht pauschal gleichgesetzt. CDN-/Webserver-Rewrites, historische Deployments und Redirects sind ohne zusätzlich bereitgestellte Regeln nicht zuverlässig aus Quelldateien umkehrbar. Die Antwort beschreibt den aktuellen Quellstand, nicht einen garantierten aktuellen Live-Deploy. Unveröffentlichte Seiten werden im Standard-Routenindex nicht als echte öffentliche Treffer ausgegeben; `page()->url()` darf weiterhin ihre potenzielle URL anzeigen.

Auch separat ist die Rückrichtung verfügbar: `JekyllUrlResolver::lookup(string $url, RouteIndex $routes): UrlLookupResult`. Den geprüften Index erzeugt der Website-Adapter unter Kontrolle von `SchillerDir`; die normale Anwendung braucht ihn nicht selbst aufzubauen. Nach Änderungen an Permalinks, Sprache, Publication oder Konfiguration wird er invalidiert. Die Grundinvariante lautet: Eine eindeutige veröffentlichbare Variante muss beim Vorwärts- und anschließenden Rückwärtsauflösen wieder dieselbe Quelldatei und beide Sprachangaben ergeben.

## § 10 Kleine öffentliche API und DTO-Vertrag

Die folgenden Interface-Signaturen beschreiben den Vertrag der vorgeschlagenen Klassen, keine bereits ausführbare Bibliothek:

```php
interface SiteAccess
{
    public function config(): SiteConfig;
    public function files(string $path = '', bool $recursive = false): FileListing;
    public function pages(string $path = ''): PageTree;
    public function page(string $path): SchillerPage;
    public function resolveUrl(string $url): UrlLookupResult;
    public function capabilities(string $path): Capabilities;
    public function createPage(NewPage $page): PageDocument;
}

interface PageAccess
{
    public function read(): PageDocument;
    public function fields(): FieldSet;
    public function translations(): TranslationSet;
    public function url(?string $language = null): PageUrl;
    public function urlInput(?string $language = null): UrlInput;
    public function update(PagePatch $patch): PageDocument;
    public function createTranslation(NewTranslation $translation): PageDocument;
}
```

`SchillerDir` implementiert `SiteAccess`, `SchillerPage` implementiert `PageAccess`. Arrays sind nur Listen/Maps mit angegebenem Elementtyp oder bewusst dynamisches YAML. DTOs serialisieren string-backed Enums als Strings; fehlende optionale Werte werden `null`, leere Listen `[]`, leere Maps `{}`. Benutzerrollen bleiben Strings, damit Kunden eigene Rollen definieren können. Sprachcodes sind validierte Strings aus der Site-Konfiguration, keine fest eingebauten Enums.

Beispiel eines tatsächlichen PHP-Typentwurfs:

```php
enum ContentFormat: string { case Markdown = 'markdown'; case Html = 'html'; }
enum Publication: string {
    case Published = 'published';
    case Unpublished = 'unpublished';
    case Excluded = 'excluded';
    case Unknown = 'unknown';
}
final class PageDocument
{
    public string $path;
    public ContentFormat $format;
    public FrontMatter $header;
    public FrontMatter $effectiveHeader;
    /** @var array<string, ValueOrigin> */
    public array $origins;
    public string $content;
    public Publication $publication;
}
```

Weitere DTOs sind verbindlich wie folgt strukturiert; `?` bezeichnet nullable Werte:

| Typ | Felder / Inhalt |
|---|---|
| `SiteConfig` | `adapter: AdapterSelection`, `languages: list<string>`, `defaultLanguage: ?string`, `url: ?string`, `baseurl: string`, `schemaVersion: int`; interne Resolver-Konfiguration nicht ungeprüft serialisieren |
| `FileListing`, `FileEntry` | `path`, `entries: list<FileEntry>`, `diagnostics: list<Diagnostic>`; Entry: `path`, `kind: FileKind`, `children: list<FileEntry>` |
| `TranslationSet` | `pageId: string`, `availableLanguages: list<string>`, `missingLanguages: list<string>`, `items: list<TranslationInfo>` |
| `TranslationInfo` | `language: string`, `state: TranslationState`, `path: ?string`, `sourceLanguage: ?string`, `published: ?bool`, `targetPath: ?string`, `canCreate: bool` |
| `FieldSet` | `fields: list<FieldDefinition>` sowie `get(string): FieldDefinition` |
| `FieldDefinition` | `key`, `type: FieldType`, `label`, `description`, `required`, `nullable`, `maxLength: ?int`, `hasDefault`, `default: YamlValue`, `options: list<FieldOption>`, `editable: bool` |
| `Capabilities` | Boolean-Felder `read`, `write`, `createFile`, `createTranslation`, `createTemplate` |
| `PageUrl` | `language: ?string`, `sourceLanguage: ?string`, `permalink: ?string`, `targetPath: ?string`, `absoluteUrl: ?string`, `publication: Publication`, `resolution: UrlResolution` |
| `UrlLookupResult` | `status: UrlMatchStatus`, `match: ?UrlMatch`, `diagnostics: list<Diagnostic>` |
| `UrlMatch` | `pageId: string`, `path: string`, `requestedLanguage: ?string`, `sourceLanguage: ?string`, `isFallback: bool`, `targetPath: string`, `absoluteUrl: ?string` |
| `Diagnostic` | `code: string`, `severity: Severity`, `path: ?string`, `field: ?string`, `message: string` |
| `NewPage` | `path: string`, `header: FrontMatter`, `content: string`; Format aus zulässiger Endung |
| `NewTranslation` | `language: string`, `permalink: string`, `title: string`, `path: ?string = null`, `content: ?string = null` |
| `PagePatch` | `header: ?FrontMatterPatch = null`, `content: ?string = null` |
| `FrontMatterPatch` | `set: array<string,YamlValue> = []`, `remove: list<string> = []`; Überschneidungen sind Fehler |

`UrlResolution` hat `resolved`, `unresolved`; `Severity` hat `warning`, `error`. Für eine nicht mehrsprachige Site ist eine unbekannte Sprache `null`, statt ein ungesichertes `de` zu erfinden. Ein vom Jekyll-Profil akzeptiertes `lang` kann als einzige Sprache verwendet werden. Übersetzungsanlage erfordert eine explizite mehrsprachige Konfiguration.

Eine neue normale Seite anlegen:

```php
$new = $site->createPage(new NewPage(
    path: 'leistungen/vorsorge.md',
    header: new FrontMatter([
        'title' => 'Vorsorge',
        'page_id' => 'prevention',
        'lang' => 'de',
        'permalink' => '/vorsorge/',
        'layout' => 'default',
    ]),
    content: "## Vorsorge\n",
)); // PageDocument; published wird aus dem Anlage-Default false ergänzt
```

Rückgabeprojektion: `path = 'leistungen/vorsorge.md'`, `publication = Publication::Unpublished`, `header->bool('published') = false`. Die Anlage braucht `createFile`; Übersetzungsanlage braucht zusätzlich `createTranslation`. `createPage` darf das nicht umgehen: gehört die neue ID zu einer bestehenden Sprachgruppe, ist der Übersetzungsweg mit seinen zusätzlichen Rechten zwingend. Vorhandene Dateien werden nicht überschrieben.

## § 11 Connector, Adapter und Versionierung

Der normale Konstruktor nimmt `PhoreDirectory|SiteStorage` an. Eine Directory-Instanz wird intern in `PhoreStorage` gewrappt. `SiteStorage` ist eine optionale Erweiterungsnaht für andere bereits bereitgestellte Dateisysteme, kein Git-Client. V1 implementiert zunächst nur `PhoreStorage`; kein zweites Filesystem-Framework.

| Schicht | Aufgabe | Bestehende Basis |
|---|---|---|
| `PhoreStorage` | Root-gebundene Enumeration, Text, YAML, Front Matter, geprüfte Dateioperationen | `PhoreDirectory::genWalk()`, `PhoreFile::get_contents()`, `get_yaml()`, `get_front_matter()`, `put_front_matter()` |
| `SiteAdapter` | Seitenerkennung, Gruppierung, Sprachauflösung, Feld-/URL-Eingaben, Planung von Quelldateiänderungen | Jekyll-/Polyglot- beziehungsweise Legacy-Regeln |
| `SchillerDir` / `SchillerPage` | Rechte, DTOs, Validierung und orchestrierte Ausführung | eigene kleine öffentliche API |
| `JekyllUrlResolver` | Reine Ziel-URL-Berechnung aus geprüften Eingaben | Jekyll-Konfiguration und Profil |

Der Adapter bekommt ausschließlich einen kontrollierten Storage-Zugang und kann Rechte nicht umgehen. Schreibpläne werden vor jeder Ausführung zentral auf alle betroffenen Pfade und Aktionen geprüft. Weder Adapter noch YAML können beliebige PHP-Klassennamen nachladen; die Anwendung registriert bekannte Adapter unter festen IDs.

| Auswahl | Geplanter Vertrag |
|---|---|
| `jekyll`, Version `1` | Normale Jekyll-Seiten einschließlich unterstützter optionaler Front Matter |
| `jekyll-polyglot`, Version `1` | Jekyll-Seiten plus Polyglot-Sprachgruppen und Fallback |
| `micx-legacy`, Version `1` | Optionaler zunächst lesender Adapter für `_section.yml`, `pid`, Sprachsuffixe und alte Formdefinitionen |

Fehlt die Adapterauswahl, wird ausschließlich zwischen bekannten Jekyll-Profilen anhand der Plugins gewählt: Polyglot explizit vorhanden → Polyglot, sonst Jekyll; widersprüchliche Konfiguration → Fehler. Das Legacy-Format wird nicht automatisch aus Dateinamen geraten. Ein Adapterwechsel verändert nur die Interpretation, migriert weder Inhalte noch Build-Konfiguration und installiert kein Plugin.

```yaml
schema_version: 1
adapter:
  id: micx-legacy
  version: 1
```

Die Legacy-Klasse liefert dieselben DTOs; nicht belegbare URLs sind `unresolved`, nicht unterstützte Schreibfunktionen liefern falsche Capabilities und `UnsupportedOperationException`. Für neue inkompatible Semantik wird später beispielsweise `jekyll-polyglot`, Version `2` registriert. Alte Versionen bleiben parallel auswählbar. Ein unbekanntes Profil darf niemals still auf die neueste Version fallen. Jeder veröffentlichte Adapter muss die tatsächlich getesteten Jekyll-/Gem-Versionen dokumentieren; eine universelle Garantie für alle zukünftigen Plugins ist aus statischem Dateizugriff nicht ableitbar.

Phore Filesystem besitzt bereits `FrontMatterFile` mit `filename`, `header` und `content` sowie die oben genannten Lese-/Schreibmethoden. Die optionale Klassenhydration von `get_front_matter($class)` benutzt derzeit `phore/hydrator`; sie ist nicht automatisch die Hydration aus `phore/schema`. Daher zunächst untypisierten Header lesen, in Schillers `FrontMatter` einbetten und die feste DTO-Hülle typisieren. Kein eigener konkurrierender YAML-Parser.

`phore/schema` bietet bereits Schema-Erzeugung, Validierung, Hydration und string-backed Enums. Es eignet sich optional für die festen DTOs und die Übertragung an einen späteren Page-Builder-Client:

```php
$schema = phore_schema_class(PageDocument::class)->toJsonSchema()->data();
```

Der Aufruf ist eine vorhandene Phore-API; `PageDocument` bleibt hier ein Entwurf. Die dynamischen Metafelder aus `schiller.yaml` benötigen eine eigene Abbildung ihrer geprüften Definitionen auf Schematypen beziehungsweise Validierung. `phore/schema` wird nicht als bereits vorhandener Schiller-YAML-Interpreter dargestellt und nicht allein für Boolean-/Dropdown-Prüfungen verpflichtend gemacht. Die Composer-Namen und Namespaces des Zielprojekts sind noch Template-Platzhalter; sie werden in diesem Dokumentations-PR nicht geändert.

## § 12 Diagnosen und Abnahmefälle für die spätere Implementierung

Lesbare fehlerhafte Einzelseiten erzeugen eine `Diagnostic` im Baum, andere gültige Seiten bleiben verfügbar. Direkte Reads einer fehlerhaften Seite werfen `PageParseException`. Nicht lesbare Pfade dürfen nicht über Diagnosemeldungen auftauchen. Bei Konflikten mit versteckten Seiten lautet eine öffentliche Fehlermeldung beispielsweise `TARGET_UNAVAILABLE`, ohne den kollidierenden Pfad oder dessen Titel zu nennen. Ein zentraler Konfigurationsfehler bricht die Operation ab.

```json
{
  "code": "INVALID_FIELD_TYPE",
  "severity": "error",
  "path": "leistungen/diagnostik.md",
  "field": "published",
  "message": "Boolean erwartet; String erhalten."
}
```

Vor Implementierungsfreigabe dienen diese Fälle als konkrete spätere Abnahme:

| Fall | Erwartetes Verhalten |
|---|---|
| Nur Website-Root übergeben | Konfiguration selbst lesen, kein Zugriff außerhalb |
| `_config.yml` zwischen zwei Aufrufen ändern | Neue URL-/Sprachwerte werden ohne neue Instanz sichtbar |
| Plain Jekyll ohne Schiller-YAML | Read-only-Seitenbaum ohne Legacy-Voraussetzungen |
| Verschachtelte Ordner, Index, Markdown/HTML | Physischer Baum und logische Gruppen bleiben unterscheidbar |
| published fehlt / false / String | True gemäß Jekyll-Standard / unpublished / Typfehler |
| Dateiwerte und mehrere Defaults | Korrekte Priorität und Herkunft; kein Persistieren geerbter Werte |
| Unterschiedliche Permalinks mit gleicher page_id | Eine Gruppe, korrekte sprachabhängige URLs |
| Übersetzung fehlt / unveröffentlicht / verboten | Fallback getrennt / tatsächliche Variante getrennt / keinerlei Metadatenleck |
| Fehlende URL-Basis, Liquid-Permalink, unbekanntes Plugin | Nullable/unresolved statt erfundener URL |
| Reale URL mit sprachspezifischem Permalink | Exakte Quelldatei und Sprache über denselben Routenindex |
| Rückauflösung einer Fallback-URL | Angefragte Sprache und tatsächliche Quellsprache getrennt |
| Vorwärts-/Rückwärts-Roundtrip | Gleiche Quelle, Sprache und Fallback-Zuordnung für eindeutige öffentliche Routen |
| Fremder Host, Query/Fragment, baseurl-Grenze, kodierter Pfad | Origin prüfen, Query/Fragment ignorieren, keine Pfadverwechslung |
| Mehrere Routenkandidaten / verborgene Quelle | Ambiguous bei erlaubten Kollisionen / keine Offenlegung |
| Verbotener Pfad, Symlink, Traversal | Listing und Direktzugriff geschützt; kein Write außerhalb Root |
| Neue Sprachdatei bei bestehender Gruppe | Kein Überschreiben; zusätzliche Rechte werden geprüft |
| Body-only und Header-only Update | Unbetroffener Dateibereich bleibt erhalten |
| Boolean/Dropdown/Mehrfachauswahl | Strikte Typen und Optionsprüfung mit Feldpfad |
| Alter oder unbekannter Adapter | Stabiler Legacy-Vertrag beziehungsweise expliziter Fehler |

Für die erste Implementierung empfehlen sich kleine synthetische Fixtures und ein Abgleich der URL-Ausgaben mit tatsächlich gepinnten Jekyll-/Polyglot-Builds. Dieser Entwurf verspricht keine bereits bestandenen Laufzeittests. Das aktuelle Dokument ist auf konsistente Beispielkonfiguration, JSON-Rückgaben und Methodenbenennung zu prüfen; kein Testcode oder Implementierung ist Teil dieses PRs.

## § 13 Quellen und Entscheidungsstand

Quellstand geprüft am 2026-09-12. Die Kundenwebsite wurde ausschließlich intern als Strukturreferenz betrachtet; ihre Inhalte und Konfiguration werden hier nicht veröffentlicht. Beispiele verwenden eigene Texte und `example.org`.

| Öffentliche Referenz | Relevanz |
|---|---|
| [MICX PageListCtrl](https://github.com/micx-io/micx-pagebuilder/blob/main/src/Ctrl/PageListCtrl.php) | Alte Section-/Sprach-Gruppierung |
| [MICX PageCtrl](https://github.com/micx-io/micx-pagebuilder/blob/main/src/Ctrl/PageCtrl.php) | Lesen, Schreiben, Kopieren |
| [MICX Editor](https://github.com/micx-io/micx-pagebuilder/blob/main/www/pages/edit-page.html) | Metafelder und Preview-Link |
| [MICX FrontMatter-Helfer](https://github.com/micx-io/micx-pagebuilder/blob/main/src/Helper/FrontMatterFile.php) | Bisherige pid-/Dateinamenskonvention |
| [PhoreFile](https://github.com/phore/phore-filesystem/blob/master/src/PhoreFile.php) | Vorhandene Front-Matter- und YAML-Funktionen |
| [PhoreDirectory](https://github.com/phore/phore-filesystem/blob/master/src/PhoreDirectory.php) | Vorhandene Enumeration |
| [Phore Schema](https://github.com/phore/phore-schema/blob/main/.ai-usage-info.md) | Typmodelle, Hydration, Enum-Unterstützung |
| [Polyglot](https://github.com/untra/polyglot) | Sprachzuordnung und Fallback |
| [Jekyll Permalinks](https://jekyllrb.com/docs/permalinks/) | Page-URL-Regeln und Grenzen |
| [Jekyll Defaults](https://jekyllrb.com/docs/configuration/front-matter-defaults/) | Effektive Metadaten und Prioritäten |

Empfohlene Reihenfolge: zunächst Root/Konfiguration, Rechte und Phore-Lesen; danach Seitenbaum, Polyglot und URL-Resolver; anschließend geprüfte Seiten-/Übersetzungsanlage und Metafeld-Patches. Legacy-Lesen lässt sich separat ergänzen. Daten-/Collection-/Template-Editoren bleiben ein eigener Folgeentwurf. Der vorliegende PR entscheidet noch keine Implementierung und verändert keine Referenzwebsite.
