# Schiller: typisierte Zugriffsschicht für Website-Seiten

| Datum | Benutzername | Kurzbeschreibung |
|---|---|---|
| 2026-09-12 | dermatthes | §§ 1–13: API-Entwurf mit Konfiguration, Beispielen, Rückgaben, bidirektionaler URL-Auflösung und Page-Builder-Zuordnung angelegt |
| 2026-09-12 | dermatthes | § 9.1: URL-Eingaben mit und ohne Domain oder Protokoll konkretisiert |
| 2026-09-12 | dermatthes | §§ 3–12: feste Sprachverzeichnisse ohne ID-/Sprachheader, Root-Startseite, optionale Permalinks und vereinfachte Anlagebeispiele |
| 2026-09-12 | dermatthes | §§ 1–13: ein Document mit Header-Array, Translation-Verfügbarkeit und Anlage, Root-Verweis, Rename/Delete sowie direkte URL-Auflösung mit Diagnose-Exception |
| 2026-09-12 | dermatthes | §§ 4, 10–12: TreeNode mit FileEntry, Kategorie-Seiten, vollständiger Sprachverfügbarkeit und konkreten Legacy-/Polyglot-Adapterentwürfen |
| 2026-09-12 | dermatthes | §§ 2, 5–11: Header-Definitionen, null als Root-Übersetzung, createIfMissing/isPersisted und Adapter-Interface mit Methodenstümpfen |

## § 1 Ziel und Umfang

**Vorschlag, noch keine implementierte API.** Der Aufrufer übergibt das Website-Quellverzeichnis als `PhoreDirectory`, beispielsweise das bereits extern bereitgestellte `docs/`. `SchillerDir` liest dessen aktuelle Konfiguration selbst und bietet einen typisierten Zugriff auf Dateien, Seiten, Sprachvarianten, Metafelder und Berechtigungen. Alle Schiller-Namen und Methoden in diesem Dokument sind vorgeschlagen; die ausdrücklich als bestehend bezeichneten Phore-Funktionen sind im Quellcode geprüft.

Der erste Ausbau umfasst normale Markdown-/HTML-Seiten, Front Matter, Inhalt, Übersetzungsdateien und berechnete Ziel-URLs. Git, Checkout, Commit, Push, Deploy, HTTP, Login, KI-Übersetzung und Bearbeitungssperren bleiben Aufgaben der Anwendung. `_data`-Editoren, Collections, Posts, Medienverwaltung und das Bearbeiten von Liquid-Layouts sind spätere Erweiterungen. Ihre Dateien dürfen bei erlaubtem Lesezugriff im Dateibaum erscheinen, werden aber nicht als normale Seiten interpretiert.

Empfehlung: **ein Einstiegspunkt `SchillerDir`, Seitenobjekte `Document`, einen internen `JekyllUrlResolver` und austauschbare Formatadapter**. Speicherzugriff und Website-Format sind zwei unabhängige Schnittstellen. Eine neue Polyglot-Version erfordert damit keinen neuen Filesystem-Connector.

## § 2 Was der bisherige Page Builder benötigt

Die folgenden Befunde stammen aus `micx-io/micx-pagebuilder`; dessen Quellcode dient ausschließlich als Referenz. Das Ziel-Repository enthält derzeit ein PHP-Grundgerüst, noch keine Schiller-Implementierung.

| Bisherige Stelle | Beobachtete Aufgabe | Vorgeschlagene Schiller-API |
|---|---|---|
| `PageListCtrl::__invoke()` | Sections mit `_section.yml`, Gruppierung nach `pid` und Sprache, Fehlerliste | `pages()` mit rekursivem Seitenbaum und `Diagnostic[]` |
| `PageCtrl` / `FrontMatterFile::ReadPage()` | Markdown/HTML und YAML-Header lesen | `getPage(path)` |
| `PageCtrl` / `FrontMatterFile::WritePage()` | Header und Inhalt speichern | Header/Body am `Document` ändern, `save()` |
| `PageCtrl::copyPage()` | Neue Sprachdatei aus vorhandener Seite kopieren | `document->getTranslation(language, createIfMissing: true)`, dann `save()` |
| `www/pages/edit-page.html` | Titel, Beschreibung, Layout, published, order, ptags, eigene Formulare | `document->getHeaderDefinitions()` und typisierte Feldwerte |
| `www/elements/page-list.html` | Seitengruppen, Sprachvarianten, Veröffentlichungsstatus | `PageTree`, `TranslationInfo`, Veröffentlichungsstatus |
| Vorschau-Link im Editor | URL bisher im Browser zusammengesetzt | `document->getUrl()` |
| Neue Anforderung für den Nachfolger | Von einer realen Website-URL zum bearbeitbaren Quelltext | `getDocumentByUrl(url): Document` |
| `_data/languages.yml` | Verfügbare Sprachen | `config()->languages` aus Jekyll/Polyglot |
| `FileCtrl` und Daten-/Fragmenteditoren | Beliebige YAML-Daten bearbeiten | Später; bewusst keine allgemeine Schreib-Hintertür im ersten Ausbau |
| `InfoCtrl`, `RepoCtrl`, Middleware | Preview-Host, Änderungsstatus, VCS und Notifications | Anwendung; optionaler URL-Kontext, kein VCS in Schiller |

Das alte Listing verlangt Sections und sprachcodierte Dateinamen. Der neue Standardadapter darf diese Voraussetzungen nicht übernehmen. Der alte Editor unterstützt außerdem Mehrfachauswahl und eine numerische Sortierung; deshalb schlägt § 7 neben Boolean und Dropdown auch `integer` und `multiselect` vor, ohne bereits einen Dateneditor zu entwerfen.

## § 3 Einstieg: ein Verzeichnis genügt

Der verbindliche Standard für das neue Polyglot-Profil ist eine Website mit `index.md` direkt im Root. Die Default-Sprache wird nicht in einen eigenen Sprachordner verschoben. Übersetzungen spiegeln die Root-Struktur unter ihrem Sprachcode. Die minimale Konfiguration steht zusätzlich in [examples/README.md](../../examples/README.md).

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

Jede öffentliche Operation prüft die Konfigurationsdateien erneut. Ein geladenes `Document` hält Header und Inhalt als bearbeitbaren Snapshot. Methoden prüfen die aktuelle Konfiguration, überschreiben jedoch keine ungespeicherten Änderungen. Eine Änderung des Adapters oder der Sprachstruktur macht bestehende Dokumente ungültig; sie müssen neu geladen werden. Innerhalb einer Operation gilt ein konsistenter Konfigurationsstand. Änderungen durch andere Prozesse während einer Operation müssen erkannt werden oder durch die externe Anwendung ausgeschlossen sein. Ein dauerhaft laufender Page Builder muss deshalb nach einer Konfigurationsänderung nicht neu konstruiert werden.

Beispiel für `SiteConfig`, hier als JSON dargestellt:

```json
{
  "adapter": {"id": "jekyll-polyglot", "version": 1},
  "languages": ["de", "en", "fr"],
  "defaultLanguage": "de",
  "url": "https://example.org",
  "baseurl": "",
  "schemaVersion": 1
}
```

`SiteConfig` ist eine geprüfte öffentliche Projektion, kein unbeschränkter Dump der Jekyll-Konfiguration oder aller Rollenregeln. Interne Konfigurationszugriffe geben dem Benutzer kein Recht, diese Dateien über `files()` oder andere Zugriffe auszulesen.

## § 4 Dateibaum und Seitenbaum

### § 4.1 Dateien: echte Quellpfade

Beide Listings verwenden denselben Knotentyp `TreeNode`. `FileListing` und `PageTree` sind lediglich Ergebnishüllen für Knoten und Diagnosen. Es gibt keine separaten Klassen für Ordner, Seitengruppen oder Blattknoten.

```php
$listing = $site->files('leistungen', recursive: false); // FileListing
foreach ($listing->entries as $node) {                  // TreeNode
    echo $node->path;                                   // Root-relativer Quellpfad
    echo $node->kind->value;                            // FileKind
    $isLeaf = $node->isLeaf();                          // keine lesbaren Kinder
}
```

`files()` bildet die physische Ablage ab: Ordner und ihre Indexdateien bleiben getrennte Knoten; Sprachordner bleiben sichtbar. `FileKind` kennt `directory`, `page`, `asset`, `data`, `template`, `other`. Bei einer erkannten Seitendatei verweist `file` auf deren tatsächlichen Pfad; auch andere physische Dateien haben einen FileEntry mit ihrem passenden FileKind; physische Ordner haben `file: null`. `getDocument()` lädt nur bei einer zugeordneten Seitendatei das Document, sonst liefert es `null`. Keine rohen Phore-Dateiobjekte umgehen die Rechteprüfung.

Alle Pfade sind Root-relativ mit `/`, ohne führenden Slash; `''` bezeichnet das Root. Die Ausgabe ist deterministisch: Verzeichnisse zuerst, dann Name. Bei `recursive: true` sind Kinder vollständig rekursiv enthalten. Bei `false` enthält die Ergebnishülle eine Ebene; deren Knoten haben `children: []`, aber `isLeaf()` prüft dennoch, ob lesbare Kinder existieren. So wird ein nicht expandierter Ordner nicht fälschlich zum Blatt.

### § 4.2 Seiten: ein Knoten kann Seite und Elternknoten sein

```php
$tree = $site->pages();                // PageTree
$root = $tree->root;                   // TreeNode
$subtree = $site->pages('leistungen');  // PageTree, root.path='leistungen'
$category = $subtree->root;
$document = $category->getDocument();   // ?Document, z.B. leistungen/index.md
$translations = $category->translations; // array<string, TranslationInfo>
foreach ($category->children as $child) {
    $leaf = $child->isLeaf();
}
```

Ein `TreeNode` enthält `path: string`, `kind: FileKind`, `file: ?FileEntry`, `translations: array<string,TranslationInfo>` und `children: list<TreeNode>`. `isLeaf(): bool` beschreibt allein das Fehlen lesbarer Kinder, nicht das Vorhandensein einer Seite. `getDocument(): ?Document` lädt die durch `file->path` zugeordnete lesbare Seite über dieselbe SchillerDir-Instanz. Listings laden keine vollständigen Bodies. Verschwindet die referenzierte Datei nach dem Listing, wirft der Abruf `NotFoundException`; `null` bedeutet ausschließlich, dass dem Knoten keine Seite zugeordnet ist.

Der Formatadapter legt die Hierarchie und die optionale Seite eines Knotens fest. Im neuen Polyglot-Profil wird `index.md` dem Root-Knoten, `leistungen/index.md` dem Knoten `leistungen` zugeordnet. Eine Indexdatei erscheint im Seitenbaum nicht zusätzlich als Kind ihrer Kategorie. Normale Seiten wie `leistungen/diagnostik.md` sind weitere Kinder. Ein Ordner ohne Indexseite besitzt `file: null` und `translations: []`, kann aber Kinder haben. Ein leerer lesbarer Ordner ist ein Blatt ohne Dokument. Bei `file: null` öffnet die UI keinen Editor und keinen Seitenlink; vorhandene Kinder können aufgeklappt werden. Insbesondere eine alte `_section.yml`-Kategorie benötigt keine zugeordnete Seitendatei. Legacy-Adapter dürfen andere belegbare Kategoriezuordnungen verwenden, behalten aber denselben TreeNode-Vertrag.

Für jeden Knoten mit einer Seitengruppe enthält `translations` sämtliche konfigurierten und lesbaren Sprachvarianten, einschließlich fehlender Dateien mit `exists: false`. Die Einträge entsprechen `Document::getTranslations()` aus § 6: tatsächlicher oder berechneter Quellpfad, Sprache, Existenz und Root-Kennzeichen. Fehlende Dateien werden nicht durch Fallbacks ersetzt. Auch `files()` verwendet bei Seitendateien diese Sprachübersicht; bei Knoten ohne Seite ist sie leer. Verborgene Varianten und nicht lesbare Kandidaten werden vollständig ausgelassen.

Der Seitenbaum verwendet sprachneutrale Pfade; Übersetzungen stehen gemeinsam am jeweiligen Knoten. `file->path` ist normalerweise der Pfad des Stammdokuments. Fehlt dieses oder ist es verborgen, kann der Adapter die erste lesbare vorhandene Variante in konfigurierter Sprachreihenfolge zuordnen. Deren tatsächliche Sprache und Root-Beziehung bleiben unverändert. Sind alle Seitenvarianten verborgen, bleibt allenfalls ein unabhängig lesbarer Ordnerknoten ohne Seitenmetadaten. `isLeaf()`, Kinder und Zähler berücksichtigen ausschließlich sichtbare Knoten.

Dieses separate Rückgabebeispiel ergänzt die Fixture aus § 6 um Root- und Kategorie-Index sowie eine Kategorie ohne eigene Seite. Zu jeder gezeigten Seite existieren de und en; fr fehlt. In JSON wird die leere Sprach-Map als `{}` dargestellt, in PHP als `[]`.

```json
{
  "root": {
    "path": "",
    "kind": "directory",
    "file": {"path": "index.md", "kind": "page"},
    "translations": {
      "de": {
        "language": "de",
        "path": "index.md",
        "exists": true,
        "isRootDocument": true
      },
      "en": {
        "language": "en",
        "path": "en/index.md",
        "exists": true,
        "isRootDocument": false
      },
      "fr": {
        "language": "fr",
        "path": "fr/index.md",
        "exists": false,
        "isRootDocument": false
      }
    },
    "children": [
      {
        "path": "leistungen",
        "kind": "directory",
        "file": {"path": "leistungen/index.md", "kind": "page"},
        "translations": {
          "de": {
            "language": "de",
            "path": "leistungen/index.md",
            "exists": true,
            "isRootDocument": true
          },
          "en": {
            "language": "en",
            "path": "en/leistungen/index.md",
            "exists": true,
            "isRootDocument": false
          },
          "fr": {
            "language": "fr",
            "path": "fr/leistungen/index.md",
            "exists": false,
            "isRootDocument": false
          }
        },
        "children": [
          {
            "path": "leistungen/diagnostik.md",
            "kind": "page",
            "file": {"path": "leistungen/diagnostik.md", "kind": "page"},
            "translations": {
              "de": {
                "language": "de",
                "path": "leistungen/diagnostik.md",
                "exists": true,
                "isRootDocument": true
              },
              "en": {
                "language": "en",
                "path": "en/leistungen/diagnostik.md",
                "exists": true,
                "isRootDocument": false
              },
              "fr": {
                "language": "fr",
                "path": "fr/leistungen/diagnostik.md",
                "exists": false,
                "isRootDocument": false
              }
            },
            "children": []
          }
        ]
      },
      {
        "path": "wissen",
        "kind": "directory",
        "file": null,
        "translations": {},
        "children": [
          {
            "path": "wissen/ablauf.md",
            "kind": "page",
            "file": {"path": "wissen/ablauf.md", "kind": "page"},
            "translations": {
              "de": {
                "language": "de",
                "path": "wissen/ablauf.md",
                "exists": true,
                "isRootDocument": true
              },
              "en": {
                "language": "en",
                "path": "en/wissen/ablauf.md",
                "exists": true,
                "isRootDocument": false
              },
              "fr": {
                "language": "fr",
                "path": "fr/wissen/ablauf.md",
                "exists": false,
                "isRootDocument": false
              }
            },
            "children": []
          }
        ]
      }
    ]
  },
  "diagnostics": []
}
```

Bei diesem vollständigen Baum gilt: Root und `leistungen` haben jeweils eine eigene Seite und `isLeaf() === false`; `wissen` hat keine eigene Seite und ebenfalls Kinder; `leistungen/diagnostik.md` ist ein Blatt mit eigener Seite. Veröffentlichungsstatus und Links liest die Anwendung am gewünschten Document über `getEffectiveHeader()['published'] ?? true` und `getUrl()`; dafür wird kein zweites Übersetzungsmodell eingeführt.

Im Seitenbaum erscheinen auch lesbare `published: false`-Seiten. Der Status beschreibt die Quellkonfiguration, nicht den letzten Deploy. Jekyll-ausgeschlossene Dateien sind bei Leserecht im Dateibaum sichtbar, aber keine regulären Seiten. `_data`, `_includes`, `_layouts`, `_posts`, Collections und Build-Ziel werden nicht als normale Seiten aufgenommen. Markdown ohne Header wird nur mit unterstütztem `jekyll-optional-front-matter` als Seite eingeordnet; ungültiger Front Matter bleibt ein Fehler.

Ein `FileEntry` enthält den tatsächlichen Root-relativen `path` und `kind: FileKind`. Er ist eine schreibgeschützte Dateireferenz, kein weiterer Baumknoten und kein unkontrollierter Filesystem-Handle. `TreeNode::path` beschreibt die Position im jeweiligen Baum; `TreeNode::file->path` beschreibt die zugeordnete physische Datei. Dateireferenzen werden beim Lesen erneut auf Rechte geprüft. Nach Rename/Delete ist ein bereits erhaltenes Listing ein Snapshot und wird neu abgefragt; geladene Document-Identitäten folgen weiterhin § 8.1.

## § 5 Einzelne Datei: ein Document mit Header-Array

Original, neue Seite und Übersetzung verwenden dieselbe Klasse `Document`. Der gespeicherte YAML-Header ist ein direkt bearbeitbares Array, der Body ein String. Zusätzliche Änderungs- oder Anlageobjekte sind nicht erforderlich.

```php
$page = $site->getPage('leistungen/diagnostik.md'); // Document
$title = $page->header['title'] ?? null;           // YamlValue
$body = $page->content;                           // string
$effective = $page->getEffectiveHeader();          // array<string, YamlValue>
$layout = $effective['layout'] ?? null;            // 'default'

$page->header['short_title'] = 'Untersuchungen';
unset($page->header['description']);
$page->content = "## Neuer Inhalt\n";
$page->save();                                   // void; jetzt wird geschrieben
```

```json
{
  "path": "leistungen/diagnostik.md",
  "language": "de",
  "isRootDocument": true,
  "isPersisted": true,
  "header": {"title": "Diagnostik", "short_title": "Diagnostik", "published": true},
  "content": "## Diagnostik\n\nBeispielinhalt.\n"
}
```

Diese Projektion beschreibt das geladene Dokument vor der Änderung. `getEffectiveHeader()` ergänzt beispielsweise `layout: default` und `lang: de` aus Jekyll-Defaults, ohne sie in `header` zurückzuschreiben. Dateiwerte gewinnen entsprechend Jekylls Defaults-Regeln. Schiller-Felddefaults werden nur bei der Anlage angewandt. `YamlValue` ist ein rekursiver PHPDoc-Typ für `null|bool|int|float|string|list<YamlValue>|array<string,YamlValue>`, kein nativer PHP-Typ.

`unset()` entfernt einen Schlüssel; eine Zuweisung von `null` speichert YAML-null, wenn die Felddefinition dies erlaubt. `content = ''` leert den Body. `save()` validiert Felder und Berechtigungen anhand des aktuellen Stands; Typen werden nicht stillschweigend konvertiert. Unveränderte Daten lösen keinen Schreibvorgang aus. Bei reiner Body-Änderung bleibt der ursprüngliche Headerblock unangetastet, bei reiner Header-Änderung der Body bytegenau. YAML-Neuserialisierung garantiert keine Erhaltung von Kommentaren oder Formatierung.

```php
$new = $site->createPage(
    'leistungen/vorsorge.md',
    header: ['title' => 'Vorsorge', 'published' => false],
    content: "## Vorsorge\n",
); // Document: isPersisted()=false, language='de', isRootDocument=true
$new->save(); // isPersisted()=true; vorhandenes Ziel wird niemals überschrieben
```

`createPage()` nimmt einen sprachneutralen Root-Pfad an. Eine Übersetzung entsteht über § 6.3. Anlage und Änderungen bleiben bis `save()` im Speicher. Neue Dateien werden kollisionssicher angelegt; Änderungen an bestehenden Dateien prüfen den beim Laden erfassten Quellstand und melden einen Konflikt, statt fremde Änderungen zu überschreiben. Die Anwendung koordiniert parallele Bearbeitung.

`path`, `language` und `isRootDocument` sind von außen nur lesbar. Pfade ändern sich ausschließlich durch `rename()`. Das ist ein API-Vertrag über Getter beziehungsweise PHPDoc-`@property-read`, kein natives PHP-`readonly` für einen intern veränderlichen Pfad. Innerhalb einer `SchillerDir`-Instanz verweist jede geladene Quelldatei auf dasselbe Dokumentobjekt. [geändert]

Mit `isPersisted(): bool` prüft die Anwendung, ob das Dokument bereits als Datei im Storage existiert. Ein neuer Entwurf liefert `false`, nach erfolgreichem `save()` `true`; lokale Änderungen an einem bereits gespeicherten Dokument ändern diesen Wert nicht. Die Methode sagt ausdrücklich nicht, ob alle Änderungen gespeichert sind. In den kleinen `TranslationInfo`-Listeneinträgen bleibt `exists` als Eigenschaft bestehen. Die JSON-Projektion oben verwendet `isPersisted` nur als Darstellung des Methodenwerts. [neu]

## § 6 Polyglot und Übersetzungen

### § 6.1 Konfiguration und Beispieldateien

Alle folgenden Website-Daten sind synthetische Beispiele. Die untersuchte bestehende Kundenwebsite wird durch diesen Entwurf weder umgestellt noch verändert.

`docs/_config.yml`:

```yaml
url: https://example.org
plugins: [jekyll-polyglot]
languages: [de, en, fr]
default_lang: de
exclude: [schiller.yaml]
exclude_from_localization: [assets]
defaults:
  - scope: {path: "", type: pages}
    values: {layout: default, lang: de}
  - scope: {path: en, type: pages}
    values: {lang: en}
  - scope: {path: fr, type: pages}
    values: {lang: fr}
```

`docs/leistungen/diagnostik.md`:

```markdown
---
title: Diagnostik
short_title: Diagnostik
published: true
---
## Diagnostik

Beispielinhalt.
```

`docs/en/leistungen/diagnostik.md`:

```markdown
---
title: Diagnostics
published: true
---
## Diagnostics
```

Für Schillers neuen Polyglot-Adapter gilt fest: Standardsprache im Root, Übersetzungen unter `<sprache>/<gleicher relativer Dateipfad>`. Weder `page_id` noch `lang` stehen in einzelnen Headern. Es gibt keine alternative ID-/Header-Zuordnung, keine anderswo liegenden Übersetzungen und keine konfigurierbare Pfadschablone. `index.md` ist die Root-Startseite, `en/index.md` deren englische Übersetzung. Schiller leitet Sprache und Gruppen-ID ausschließlich aus dieser Struktur ab. Diese Festlegung ist Schillers vereinfachter Vertrag; Polyglot selbst bietet weitere Modi.

Die zentrale Jekyll-Konfiguration setzt die Sprache über Verzeichnis-Defaults. Das hält die einzelnen Dateien frei von Sprachmetadaten und berücksichtigt im geprüften Polyglot-Code `coordinate_documents()` die Bedingung für die Normalisierung natürlicher URLs. `lang_from_path` wird hier nicht zusätzlich benötigt: Die Sprache wird bereits zentral zugewiesen. Der Default für alle Seiten muss zuerst stehen, die spezifischen Sprachordner folgen. Bei neuen Sprachen werden `languages` und der zugehörige Default ergänzt. Keine Permalink-Defaults setzen. [Polyglot-Quellcode](https://github.com/untra/polyglot/blob/main/lib/jekyll/polyglot/patches/jekyll/site.rb)

Sprachordner sind auf der obersten Ebene reserviert. Eine deutsche Datei wird nicht zusätzlich unter `de/` abgelegt. Sprachcodes in tieferen Ordner-/Dateinamensegmenten, von denen Polyglots allgemeine Normalisierung eine andere Route ableiten könnte, werden als nicht unterstützte Pfade diagnostiziert. Dateilokale `lang`-/ID-Felder werden als Abweichung vom Profil gemeldet; keine stillschweigende Migration oder alternative Zuordnung.

| Quelle | Sprache | Interne Gruppen-ID | Standard-URL |
|---|---|---|---|
| `index.md` | de | `index.md` | `/` |
| `en/index.md` | en | `index.md` | `/en/` |
| `leistungen/index.md` | de | `leistungen/index.md` | `/leistungen/` |
| `leistungen/diagnostik.md` | de | `leistungen/diagnostik.md` | `/leistungen/diagnostik.html` |
| `en/leistungen/diagnostik.md` | en | `leistungen/diagnostik.md` | `/en/leistungen/diagnostik.html` |

Die Startseiten brauchen ebenfalls nur ihren Titel und Inhalt: `index.md` beispielsweise mit `title: Startseite`, `en/index.md` mit `title: Home`. Ein leerer YAML-Header genügt zur Seitenerkennung ebenfalls. Ein Permalink ist nicht erforderlich.

### § 6.2 Übersetzungen auflisten und lesen

`getTranslations()` liefert je konfigurierter und lesbarer Sprache einen kleinen Eintrag, einschließlich Standardsprache. `exists` bezeichnet ausschließlich eine gespeicherte Quelldatei. Fehlende Varianten haben bereits ihren fest berechneten Zielpfad; weder Fallbacks noch ungespeicherte Entwürfe zählen als vorhanden.

```php
$page = $site->getPage('leistungen/diagnostik.md');
$translations = $page->getTranslations(); // array<string, TranslationInfo>
$english = $page->getTranslation('en');   // ?Document, hier vorhanden
$french = $page->getTranslation('fr');    // null
$root = $english->getTranslation();     // identisch zu $page
assert($page->getTranslation() === $page);
assert($english->getTranslation('de') === $page);
```

```json
{
  "de": {"language": "de", "path": "leistungen/diagnostik.md", "exists": true, "isRootDocument": true},
  "en": {"language": "en", "path": "en/leistungen/diagnostik.md", "exists": true, "isRootDocument": false},
  "fr": {"language": "fr", "path": "fr/leistungen/diagnostik.md", "exists": false, "isRootDocument": false}
}
```

Das Listing lädt keine vollständigen Dokumentinhalte. Verborgene vorhandene Varianten und nicht lesbare Kandidaten werden vollständig ausgelassen, niemals als fehlend ausgegeben. Direktzugriff auf einen nicht lesbaren Pfad wirft `NotFoundException`; nur ein erlaubter, tatsächlich fehlender Übersetzungspfad ergibt `null`. Eine unbekannte Sprache ist ein Fehler. `getTranslation()` ohne Sprachargument beziehungsweise `getTranslation(null)` liefert stets das Stammdokument oder wirft `NotFoundException`, falls es fehlt beziehungsweise nicht lesbar ist. [geändert]

Ohne Sprachargument oder mit `null` liefert `getTranslation()` am Original dieselbe Objektinstanz und an einer Übersetzung das Stammdokument. Fehlt dessen Datei oder Leserecht, wird `NotFoundException` geworfen. Ein neu erzeugtes Original liefert vor dem ersten Speichern ebenfalls sich selbst. Bei einer anderen Sprache bleibt der Rückgabewert für eine fehlende Variante ohne Anlageoption `null`. [geändert]

### § 6.3 Eine Übersetzungsdatei anlegen

```php
$french = $page->getTranslation('fr', createIfMissing: true); // Document oder Exception
if (!$french->isPersisted()) {
    // Kopie des Stammdokuments, keine maschinelle Übersetzung.
    // path='fr/leistungen/diagnostik.md'; published=false
    $french->header['title'] = 'Diagnostic';
    $french->content = "## Diagnostic\n\nTexte français.\n";
    $french->save(); // erst hier entsteht die Datei
}
```

Eine existierende Übersetzung wird unverändert zurückgegeben. Bei fehlender Übersetzung erzeugt `createIfMissing: true` eine ungespeicherte Kopie von Header und Inhalt des Stammdokuments mit `published: false`, auch wenn der Aufruf von einer anderen Übersetzung ausgeht. Geerbte Defaults werden nicht kopiert. Im neuen Adapter werden keine ID-/Sprachfelder erzeugt. Der Zielpfad ist nicht frei veränderbar. Wiederholte Abfragen desselben Entwurfs liefern innerhalb derselben Instanz dasselbe Objekt. [geändert]

`null`, ein weggelassenes Sprachargument und die konfigurierte Standardsprache liefern immer das Stammdokument; es wird nicht durch Klonen einer Übersetzung angelegt; `createIfMissing` erzeugt in diesem Fall kein neues Original. Bei null beziehungsweise der Standardsprache hat `createIfMissing` keine Wirkung. Zum Klonen muss das Stammdokument gespeichert, lesbar und ohne ungespeicherte Änderungen sein. `getTranslation(..., createIfMissing: true)` und anschließend `save()` prüfen `createFile` und `createTranslation` am Ziel sowie vorhandene Elternordner. Eine zwischenzeitlich angelegte Datei wird nicht überschrieben. Für unverändert vorhandene Übersetzungen genügt zum Abruf Leserecht. [geändert]

## § 7 schiller.yaml: Metafelder, Presets und Bereiche

Die Datei liegt im übergebenen Root, also hier `docs/schiller.yaml`. `schema_version` versioniert ausschließlich die Schiller-YAML-Struktur. `adapter.version` bezeichnet einen Schiller-Kompatibilitätsvertrag, **nicht** die Gem-Version von Polyglot.

```yaml
schema_version: 1
adapter:
  id: jekyll-polyglot
  version: 1

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
        - {path: "leistungen/**", actions: [write, createFile, rename, delete]}
        - {path: "en/**", actions: [write, createFile, createTranslation, rename, delete]}
        - {path: "fr/**", actions: [write, createFile, createTranslation, rename, delete]}
      deny:
        - {path: "intern/**", actions: [read, write, createFile, createTranslation, rename, delete]}
        - {path: "schiller.yaml", actions: [read, write]}
        - {path: "_config.yml", actions: [read, write]}
    admin:
      allow:
        - {path: "**", actions: [read, write, createFile, createTranslation, createTemplate, rename, delete]}
```

Diese `permissions` sind Projektregeln. Eine von der Host-Anwendung gesetzte `AccessPolicy` begrenzt sie zusätzlich und kann niemals durch YAML erweitert werden. Die Rolle wird ausschließlich serverseitig übergeben; `admin` im Request-Body ist keine Rollenquelle. Konfigurationsdateien, ausführbare Plugins und Policies sind über die Seiten-API auch für `admin` nicht schreibbar. Eine spätere Konfigurationsverwaltung benötigt einen eigenen vertrauenswürdigen Einstieg.

Alle passenden `scopes` werden in Dokumentreihenfolge angewendet, Preset-Feldlisten additiv ohne Duplikate; die erste Position bestimmt die Anzeigeordnung. Unbekannte Presets/Felder und widersprüchliche Definitionen sind Konfigurationsfehler. Im ersten Vertrag definieren Scopes nur die Feldauswahl, keine impliziten Typüberschreibungen. `path` bezieht sich auf die tatsächliche Datei, auch bei Übersetzungen. Das vereinfacht insbesondere die Übereinstimmung mit Pfadberechtigungen.

Für Schiller-Pfade gilt: `*` trifft innerhalb eines Segments, `**` über Segmentgrenzen, `leistungen/**` umfasst auch den Ordner `leistungen`. Ein exakter Dateipfad ist ebenfalls zulässig. Das ist Schillers eigene Glob-Semantik, nicht ungeprüft die Semantik von Jekyll-Defaults oder Polyglot-Regulärausdrücken. Verzeichnis-Leserecht ist Voraussetzung zum Traversieren; eine gesperrte Elternstruktur wird nicht durch eine Kindfreigabe offengelegt.

```php
$fields = $page->getHeaderDefinitions();                     // FieldSet
$shortTitle = $fields->get('short_title');      // FieldDefinition
$value = $page->header['short_title'] ?? null; // ?string
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

`FieldType` ist ein string-backed Enum (`string`, `boolean`, `integer`, `select`, `multiselect`). Dropdown-Optionen sind `FieldOption`-Objekte mit `value: string` und `label: string`; Mehrfachauswahl speichert `list<string>`. `hasDefault` unterscheidet fehlenden Default von explizitem `null`; `nullable` regelt, ob `null` erlaubt ist. Unbekannte vorhandene Metafelder bleiben beim Lesen und Schreiben erhalten, dürfen durch Header-Zuweisung aber nicht neu gesetzt werden, solange keine Felddefinition existiert. Adaptereigene Standardfelder wie `description` und `permalink` werden als eingebaute Definitionen bereitgestellt; dateilokale `page_id`, `pid` und `lang` sind im Polyglot-Profil nicht zulässig. Geerbtes `lang` aus `_config.yml` darf in `getEffectiveHeader()` auftauchen, wird jedoch niemals in den gespeicherten Header zurückgeschrieben. Neue Pflichtfelder dürfen Bestandsseiten lesbar lassen, verhindern jedoch ein nicht valides Speichern mit präzisen Feldfehlern.

## § 8 Rechte und erlaubte Aktionen

Die UI kann erlaubte Aktionen abfragen; jede Mutation prüft sie erneut.

```php
$actions = $site->capabilities('leistungen/diagnostik.md'); // Capabilities
if ($actions->write) {
    // UI darf den Speichern-Button aktivieren; save() prüft erneut.
}
$targetActions = $site->capabilities('fr/leistungen/diagnostik.md');
```

```json
{
  "read": true,
  "write": true,
  "createFile": false,
  "createTranslation": false,
  "createTemplate": false,
  "rename": true,
  "delete": true
}
```

Das Beispiel bezieht sich auf eine bereits vorhandene normale Seite als `user`; Erstellen ist für ein bestehendes Ziel nicht möglich. Für einen fehlenden französischen Zielpfad können `createFile` und `createTranslation` wahr sein. `Capabilities` sind die Schnittmenge aus Projektregeln, Host-Policy, Adapter-/Storage-Unterstützung und Ressourcenzustand. `createTemplate` bleibt im ersten Ausbau auch bei Admin-Freigabe `false`, weil noch kein Template-Editor/Writer implementiert wird. Feld-Presets sind davon unabhängig; Layout-Dateien und Seitenvorlagen sind nicht dasselbe wie Felddefinitionen.

Jede Operation prüft Rechte, nicht nur das Listing. Verweigertes Lesen ergibt bei Direktzugriff dieselbe `NotFoundException` wie eine fehlende Datei. Listen, Sprachgruppen, Zähler und Diagnosen enthalten keinerlei Namen, IDs oder Metadaten verborgener Dateien. Nur wenn eine Datei lesbar ist, darf fehlendes Schreibrecht als `AccessDeniedException` sichtbar werden. Nicht erlaubte Schreibaktionen bleiben auch dann verboten, wenn der Benutzer das JSON manuell manipuliert.

`deny` gewinnt immer gegenüber `allow`; keine Rollenvererbung im ersten Vertrag. Unbekannte Rollen haben keine Rechte. Für Anlageoperationen sind Ziel und notwendige Elternverzeichnisse zu prüfen. V1 verlangt vorhandene Elternordner; automatische Verzeichniserstellung wird nicht implizit mit freigegeben. Eine interne Existenzprüfung verhindert Überschreiben, ohne verborgene Ziele über unterschiedliche Fehlermeldungen offenzulegen.

Alle Speicheroperationen sind an das Root gebunden: keine absoluten Eingabepfade, `..`, Nullbytes oder Symlinks; Symlinks werden im ersten Ausbau nicht verfolgt. Ein HTTP-Layer decodiert Transportdaten einmal, Schiller betreibt keine zusätzliche URL-Decodierung von Dateipfaden. Auch von Adaptern berechnete Pfade durchlaufen dieselbe Prüfung. Bloße String-Präfixprüfungen reichen nicht; der Connector muss seine Root-Grenze für reale Lese-/Schreibzugriffe durchsetzen. Fehlertexte enthalten keine absoluten Serverpfade.

### § 8.1 Umbenennen und Löschen

```php
$page = $site->getPage('leistungen/diagnostik.md');
$page->rename('medizin/diagnostik.md'); // sofort: Root und alle Übersetzungen
// leistungen/diagnostik.md -> medizin/diagnostik.md
// en/leistungen/diagnostik.md -> en/medizin/diagnostik.md
$page->getTranslation('en')?->delete(); // sofort: nur die englische Datei
$page->delete();                       // sofort: Root und verbleibende Übersetzungen
```

`rename()` ist nur am Stammdokument zulässig; Übersetzungspfade folgen automatisch. Der Zielpfad ist sprachneutral. Vorhandene Ziele werden nicht überschrieben; alle Zielordner müssen existieren. Natürliche URLs folgen dem neuen Pfad, ein expliziter Permalink bleibt bestehen. Geladene Dokumentreferenzen werden mitgeführt. Löschen einer Übersetzung lässt Root und andere Sprachen bestehen: anschließend liefert `getTranslation()` dort `null`, der Listeneintrag `exists: false`. Polyglot kann danach wieder einen Root-Fallback ausgeben.

Gruppenoperationen prüfen vor dem ersten Schreiben sämtliche betroffenen Dateien, auch verborgene Varianten. Fehlt eine Berechtigung oder kollidiert ein Ziel, scheitert die ganze Aktion ohne Änderung und ohne Offenlegung verborgener Pfade. Rename benötigt `rename` und `write` an den Quellen sowie `createFile` und für Sprachziele `createTranslation` an den Zielen. Delete benötigt `delete` an allen betroffenen Dateien. `Capabilities` berücksichtigt bei Root-Dokumenten die vollständige Gruppe.

Nicht gespeicherte Entwürfe oder ungespeicherte Änderungen in betroffenen Dokumenten führen zu `UnsavedChangesException`; Rename/Delete speichern nicht implizit. Gelöschte Referenzen liefern `isPersisted() === false`, werden für weitere Dokumentoperationen ungültig und können per `save()` keine Datei wiederherstellen. Für mehrere Dateien benötigt der Storage eine vorbereitete Batch-Operation mit Wiederherstellung bei I/O-Fehlern. Bietet er diese Fähigkeit nicht, wird die Gruppenmutation vorab abgelehnt. Universelle atomare Dateisystemtransaktionen werden nicht versprochen; ein gescheiterter Rollback wird ausdrücklich als unvollständige Operation gemeldet. [geändert]

## § 9 URLs gehören zum Dokument

`getUrl()` liefert den lokalen Zielpfad des konkreten Dokuments einschließlich `baseurl`. Die Sprache steht bereits durch das Dokument fest; es gibt keinen Sprachparameter. `getUrl(absolute: true)` liefert mit konfiguriertem `url` die absolute URL, andernfalls eine `ConfigurationException`.

```php
$page = $site->getPage('leistungen/diagnostik.md');
$page->getUrl();                        // '/leistungen/diagnostik.html'
$page->getUrl(absolute: true);          // 'https://example.org/leistungen/diagnostik.html'
$english = $page->getTranslation('en');
$english->getUrl();                     // '/en/leistungen/diagnostik.html'
$english->getTranslation()->getUrl();   // '/leistungen/diagnostik.html'
```

### § 9.1 Von einer URL direkt zum Document

```php
$document = $site->getDocumentByUrl(
    'https://user@example.org:8443/en/leistungen/diagnostik.html?preview=1#details'
); // Document: path='en/leistungen/diagnostik.md', language='en'
$root = $document->getTranslation(); // Document: language='de'
```

Die Methode liefert immer ein tatsächliches `Document` oder wirft `UrlNotResolvableException`. Kein Result-Wrapper und kein `null`. Es gibt keinen Netzwerkzugriff. Schema, Zugangsdaten, Host, Port, Query und Fragment werden für die Suche entfernt; auch ein abweichender Host verhindert den lokalen Treffer nicht.

| Eingabe | Gesuchter Pfad |
|---|---|
| `https://other.example:8443/en/leistungen/diagnostik.html?q=1#details` | `/en/leistungen/diagnostik.html` |
| `//example.org/en/leistungen/diagnostik.html` | `/en/leistungen/diagnostik.html` |
| `example.org/en/leistungen/diagnostik.html` | `/en/leistungen/diagnostik.html` |
| `/en/leistungen/diagnostik.html` | `/en/leistungen/diagnostik.html` |
| `en/leistungen/diagnostik.html` | `/en/leistungen/diagnostik.html` |

Die Normalisierung verwendet einen URL-Parser. Ein domainähnliches erstes Segment ohne Schema wird als Authority behandelt; lokale Pfade mit einem solchen Segment müssen mit `/` beginnen. Ein konfiguriertes `baseurl` wird nur an Segmentgrenzen entfernt, nicht irgendwo im Pfad. Ungültige Kodierung, Traversal, Nullbytes und kodierte Pfadseparatoren werden abgewiesen; URL-Decodierung erfolgt genau einmal. Groß-/Kleinschreibung wird nicht willkürlich vereinheitlicht.

Der interne Index beruht auf den vom Adapter berechneten Ausgabewegen. `leistungen/diagnostik.md` ergibt normalerweise `/leistungen/diagnostik.html`; `leistungen/index.md` ergibt `/leistungen/`. `/leistungen/index.html` ist ein Alias dieser Indexausgabe; `/leistungen` darf auf dieselbe eindeutige Verzeichnisroute zeigen. `/leistungen.html` ist dagegen eine eigenständige Dateiroute. Slash- und HTML-Varianten werden nur anhand der Konfiguration und tatsächlichen Ausgabewege zugeordnet, niemals pauschal angehängt oder entfernt. Mehrdeutige Ausgaben scheitern, statt zufällig eine Datei auszuwählen.

```php
try {
    $site->getDocumentByUrl('/en/leistungen/diagnostk.html');
} catch (UrlNotResolvableException $error) {
    echo $error->normalizedPath; // '/en/leistungen/diagnostk.html'
    echo $error->reason;         // 'not_found'
    // $error->suggestions === ['/en/leistungen/diagnostik.html']
    // $error->hints: list<string>, beispielsweise Hinweis auf ähnlichen Dateinamen
}
```

Die Exception enthält typisierte Eigenschaften `inputUrl: string` (bereinigt), `normalizedPath: ?string`, `reason: string`, `suggestions: list<string>` und `hints: list<string>`. Gründe sind `invalid_url`, `not_found`, `ambiguous` oder `unsupported`. Zugangsdaten und Query-/Fragmentwerte werden weder in Meldungen noch in Diagnosefeldern weitergegeben. Hinweise unterscheiden beispielsweise Schreibfehler, Baseurl-Mismatch, fehlende Sprachdatei oder falsche Slash-/HTML-Form. Höchstens fünf deterministisch sortierte Vorschläge stammen ausschließlich aus lesbaren, bekannten Routen; ähnliche Treffer werden niemals automatisch ausgewählt. Ein verborgenes Ziel verhält sich wie ein nicht vorhandenes und liefert keine verräterischen Hinweise.

Ein vom Adapter sicher erkannter Polyglot-Fallback wie `/fr/leistungen/diagnostik.html` liefert die tatsächlich verwendete deutsche Quelldatei, solange keine französische Übersetzung existiert. Das zurückgegebene Dokument behält `language='de'`, `isRootDocument=true` und seine deutsche `getUrl()`. Es wird kein französisches Dokument erfunden. `getTranslation('fr')` bleibt `null`. Für nicht sicher modellierbare Build-Routen wird `unsupported` gemeldet. Auflösung und Veröffentlichung beziehen sich auf den aktuellen Quellstand, nicht auf einen möglicherweise älteren Deploy. Unveröffentlichte Dokumente haben eine berechenbare Vorschau-URL, aber keinen regulären öffentlichen Indexeintrag.

### § 9.2 Permalinks als Ausnahme

Ohne Permalink folgt die URL dem natürlichen Jekyll-Ausgabeweg. Ein explizites `permalink: /medizin/diagnostik/` ergibt für das Original `/medizin/diagnostik/` und für die englische Übersetzung `/en/medizin/diagnostik/`, jeweils zuzüglich `baseurl`. Innerhalb einer Sprachgruppe muss derselbe unlokalisierte Ausgabeweg entstehen; unterschiedliche übersetzte Slugs werden im festen Polyglot-Profil ohne IDs nicht unterstützt. Permalinks bestimmen keine Schiller-Gruppenidentität. Konflikte mit anderen Ausgaben werden vor dem Speichern abgelehnt. Nicht unterstützte Jekyll-Plugins, Platzhalter oder Build-Sonderfälle erzeugen Diagnosen statt erfundener URLs.

## § 10 Typisierter öffentlicher Vertrag

Die folgenden Signaturen beschreiben den Entwurf; sie sind keine lauffähige Klassenimplementierung. `Document` wird durch `SchillerDir` erzeugt, nicht mit einem frei gesetzten Übersetzungspfad konstruiert.

```php
final class SchillerDir
{
    public function __construct(
        PhoreDirectory|SiteStorage $root,
        ?AccessContext $access = null,
        ?AccessPolicy $policy = null,
        ?AdapterRegistry $adapters = null,
    );
    public function config(): SiteConfig;
    public function files(string $path = '', bool $recursive = false): FileListing;
    public function pages(string $path = ''): PageTree;
    public function capabilities(string $path): Capabilities;
    public function getPage(string $path): Document;
    /** @param array<string, YamlValue> $header */
    public function createPage(string $path, array $header = [], string $content = ''): Document;
    public function getDocumentByUrl(string $url): Document;
}

/**
 * @property-read string $path
 * @property-read string $language
 * @property-read bool $isRootDocument
 */
final class Document
{
    /** @var array<string, YamlValue> */
    public array $header;
    public string $content;

    /** @return array<string, YamlValue> */
    public function getEffectiveHeader(): array;
    public function getHeaderDefinitions(): FieldSet;
    public function getTranslation(?string $language = null, bool $createIfMissing = false): ?Document;
    /** @return array<string, TranslationInfo> */
    public function getTranslations(): array;
    public function getUrl(bool $absolute = false): string;
    public function isPersisted(): bool;
    public function save(): void;
    public function rename(string $path): void;
    public function delete(): void;
}

final readonly class FileEntry
{
    public function __construct(public string $path, public FileKind $kind) {}
}

/**
 * Einheitlicher Knoten für physische und logische Listings.
 * @property-read string $path
 * @property-read FileKind $kind
 * @property-read ?FileEntry $file
 * @property-read array<string, TranslationInfo> $translations
 * @property-read list<TreeNode> $children
 */
final class TreeNode
{
    public function isLeaf(): bool;
    public function getDocument(): ?Document;
}

final readonly class TranslationInfo
{
    public function __construct(
        public string $language,
        public string $path,
        public bool $exists,
        public bool $isRootDocument,
    ) {}
}
```

`getHeaderDefinitions(): FieldSet` liefert Definitionen der Header-Schlüssel (Datentyp, Beschreibung und Optionen); `FieldSet`/`FieldDefinition` bleiben die bestehenden Entwurfstypen, die Werte selbst stehen im Header-Array. [geändert]

`getPage()` akzeptiert den tatsächlichen Root-relativen Dateipfad, auch `en/leistungen/diagnostik.md`. Es lädt sofort oder wirft `NotFoundException`. `getTranslation('en', createIfMissing: true)` liefert bei Erfolg immer ein Dokument; der nullable Rückgabetyp deckt ausschließlich den fehlenden Treffer bei `createIfMissing: false` ab. Listen-DTOs bleiben typisiert: `FileListing`/`PageTree` mit gemeinsamen `TreeNode`-Knoten gemäß § 4, `SiteConfig` gemäß § 3 und `FieldSet`/`FieldDefinition` gemäß § 7. `TranslationInfo` ist in Dokumenten und beiden Listings dieselbe Verfügbarkeitsinformation einschließlich fehlender Sprachen. [geändert]

Die einfachen DTOs können mit Phore Schema validiert werden. Das bearbeitbare `Document` besitzt dagegen eine interne Verbindung zu seiner `SchillerDir` und wird nicht als beliebiges Request-Array hydratisiert. Rollen, Pfade und Root-Beziehungen sind nicht durch Headerwerte überschreibbar.

## § 11 Filesystem und austauschbare Formatadapter

`SchillerDir` adaptiert ein übergebenes `PhoreDirectory` intern auf einen `SiteStorage`. Alternative Connectoren liefern dieselben rootgebundenen Lese-/Schreiboperationen. Die Anwendung stellt das Verzeichnis bereit; Versionskontrolle gehört nicht zu diesem Vertrag.

Die vorhandenen Methoden `PhoreDirectory::genWalk()`, `PhoreFile::get_contents()`, `get_yaml()`, `get_front_matter()` und `put_front_matter()` bilden die Basis von `PhoreStorage`. Phore liefert bereits ein `FrontMatterFile` mit `filename`, `header` und `content`; Schiller übernimmt das Header-Array ohne weiteren Wrapper. Die optionale Phore-Klassenhydration verwendet derzeit `phore/hydrator` und ist nicht automatisch `phore/schema`. Es wird kein konkurrierender YAML-Parser eingeführt. Die konkrete Phore-Datenstruktur wird nicht zum zweiten öffentlichen Header-Modell. Phore Schema kann Konfiguration und einfache DTOs prüfen, ersetzt aber weder Zugriffsprüfung noch Dateisystemgrenzen.

Ein interner Formatadapter übernimmt Seitenerkennung, Sprachpfade, Gruppierung, Jekyll-Defaults und Ausgabewege. Der interne `JekyllUrlResolver` baut daraus den vorwärts und rückwärts verwendeten Routenindex. Öffentliche Aufrufe bleiben `Document::getUrl()` und `SchillerDir::getDocumentByUrl()`.

```yaml
schema_version: 1
adapter: {id: jekyll-polyglot, version: 1}
```

Für Altbestände wird ein gesonderter, zunächst lesender Vertrag vorgesehen:

```yaml
schema_version: 1
adapter: {id: micx-legacy, version: 1}
```

Der Legacy-Adapter darf die bisherigen PID-/Sprachinformationen im Header oder Dateinamen und `_section.yml` auswerten. Der neue Polyglot-Adapter verwendet ausschließlich die feste Struktur aus § 6. Die gemeinsame Document-API verlangt vom Adapter die entsprechenden Fähigkeiten; nicht unterstützte Schreiboperationen werden vorab abgelehnt.

`adapter.version` wählt eine registrierte Schiller-Vertragsversion. Keine dynamisch aus YAML geladenen PHP-Klassen, keine automatische Migration und kein Rückfall auf einen anderen Adapter bei unbekannter Version. Fehlt die optionale Auswahl, entscheidet die Plugin-Konfiguration zwischen registrierten Profilen: mit Polyglot gilt `jekyll-polyglot`, andernfalls `jekyll`, jeweils Version 1. Widersprüchliche Konfiguration ist ein Fehler; Legacy benötigt eine explizite Auswahl. Neue Polyglot-Kompatibilität kann als weitere Vertragsversion registriert werden, ohne alte Kunden automatisch umzustellen.

### § 11.1 Konkreter Adapterentwurf: gleiche Knoten, andere Dateien

Die Beispiele definieren jetzt ein gemeinsames [Adapter-Interface](../../examples/Adapter.php) und zwei Klassen mit `implements Adapter`: [LegacyAdapter](../../examples/14-legacy-adapter.php) und [PolyglotAdapter](../../examples/15-polyglot-adapter.php). Die Methoden besitzen gleiche Signaturen, kurze Beschreibungen und konkrete Rückgabebeispiele. Ihre Rümpfe werfen absichtlich `LogicException`; sie enthalten keine fertige Implementierung und keine scheinbar erfolgreichen Dummy-Rückgaben. [geändert]

| Bedeutung | micx-legacy v1 | jekyll-polyglot v1 |
|---|---|---|
| Kategorie | `leistungen/_section.yml` | Ordner `leistungen/` |
| Kategorie-Seite de | `leistungen/index.de.md` | `leistungen/index.md` |
| Kategorie-Seite en | `leistungen/index.en.md` | `en/leistungen/index.md` |
| Unterseite de | `leistungen/diagnostik.de.md` | `leistungen/diagnostik.md` |
| Unterseite en | `leistungen/diagnostik.en.md` | `en/leistungen/diagnostik.md` |
| Logischer Unterseitenpfad | `leistungen/diagnostik.md` | `leistungen/diagnostik.md` |
| Header de | `pid: leistungen/diagnostik`, `lang: de`, `title: Diagnostik` | nur `title: Diagnostik` |
| Header en | `pid: leistungen/diagnostik`, `lang: en`, `title: Diagnostics` | nur `title: Diagnostics` |

Die Legacy-Konvention `<pid>.<lang>.md|html` sowie der Header-Abgleich sind im [alten FrontMatter-Helfer](https://github.com/micx-io/micx-pagebuilder/blob/main/src/Helper/FrontMatterFile.php) belegt; dessen [Listing](https://github.com/micx-io/micx-pagebuilder/blob/main/src/Ctrl/PageListCtrl.php) verwendet `_section.yml`. Die Zuordnung von `index.de.md` als eigene Kategorie-Seite ist eine ausdrückliche Schiller-Adapterkonvention dieses Entwurfs, keine Behauptung über das bisherige UI. Fehlerhafte PID-/Sprachheader werden diagnostiziert, nicht still korrigiert. Die Legacy-Sprachen kommen aus der von Schiller normalisierten SiteConfig; ihre historische Konfigurationsquelle muss der Legacy-Loader gesondert abbilden.

Beide Implementierungen liefern über `buildPageTree()` dieselben logischen Knoten, aber unterschiedliche FileEntry-Pfade. `getTranslationPath()` berechnet Kandidaten, ohne Dateien anzulegen; Header, URL-Regeln und Schreibunterstützung bleiben formatspezifisch. `loadConfig()` normalisiert die jeweilige Website-Konfiguration. Ein kontrollierter SiteStorage wird nur dort übergeben, wo tatsächlich Quelldaten benötigt werden. Der gemeinsame URL-Index und die Document-Lebenszyklen bleiben in Schiller. [geändert]

Der gemeinsame, von `buildPageTree()` genutzte Baumaufbau verarbeitet die gefundenen Dateien in zwei Schritten: zuerst pro logischem Knoten und Sprache die tatsächlich vorhandenen Dateien sammeln, dann pro konfigurierter lesbarer Sprache den Kandidatenpfad berechnen und `exists` über den kontrollierten Storage prüfen. Für fehlendes fr entsteht also `leistungen/diagnostik.fr.md` beziehungsweise `fr/leistungen/diagnostik.md`, jeweils mit `exists: false`. Ein Kandidat ist keine vorhandene FileEntry am Knoten. Mehrere Quellen für denselben Knoten und dieselbe Sprache (etwa konkurrierende md/html-Indexseiten) sind eine Diagnose, keine zufällige Auswahl. Unterschiedliche Endungen innerhalb einer Legacy-Gruppe müssen anhand vorhandener Dateien erkannt werden; neue Kandidaten übernehmen zunächst die Endung des Stammdokuments. [geändert]

Ordnerknoten werden zuerst erzeugt und danach um ihre optionale Index-Dateireferenz ergänzt; dadurch überschreibt eine Indexseite keine zuvor gesammelten Kinder. Für leere Kategorien bleibt `file: null`. Das Root hat `path=''`. Den bevorzugten lesbaren FileEntry, Sprachlisten und `isLeaf()` bestimmt die gemeinsame Schiller-Schicht nach § 4. Permalinks beeinflussen die URL-Berechnung, nicht diese Hierarchie. Im Legacy-Profil darf eine bekannte explizite Route verwendet werden; ohne belegbare alte Build-Regeln wird keine moderne Polyglot-URL erfunden. Der Legacy-Adapter bleibt zunächst lesend.

## § 12 Fehler, Grenzen und spätere Abnahme

Konfigurationsfehler verhindern einen konsistenten Einstieg. Fehler einzelner lesbarer Dateien erscheinen als `Diagnostic` mit Code, relativem Pfad und Meldung im Listing; andere gültige Seiten bleiben sichtbar. Nicht lesbare Dateien erzeugen keine sichtbaren Diagnosen. Direkte Operationen werfen typisierte Exceptions: `ConfigurationException`, `NotFoundException`, `AccessDeniedException`, `FieldTypeException`, `ValidationException`, `AlreadyExistsException`, `ConflictException`, `UnsavedChangesException`, `UnsupportedOperationException`, `StorageException` oder `UrlNotResolvableException`.

Die spätere Implementierung muss insbesondere diese Verhaltensfälle prüfen:

- Root-`index.md`, normale Unterseiten und gespiegelte Sprachdateien ohne ID-/Sprachheader oder Permalink.
- Einheitliche TreeNode-Knoten: Kategorie mit/ohne Indexseite, Blatt mit/ohne Dokument, keine doppelte Indexdatei; sämtliche lesbaren Sprachen samt exists auch im Seitenbaum.
- Nicht expandierte physische Ordner und verborgene Kinder: isLeaf() bleibt korrekt, ohne versteckte Struktur offenzulegen.
- Direktes Ändern, Entfernen und Leeren von Header/Body; keine Speicherung geerbter Defaults.
- Anlage schreibt erst bei `save()`; parallele Zielanlage überschreibt keine Datei.
- Übersetzungslisten einschließlich fehlender Sprachen; keine Existenz aus Fallbacks ableiten.
- Root-Identität, Übersetzung zur Standardsprache und Kopie des gespeicherten Roots bei `createIfMissing: true`.
- Rename aller Sprachdateien, Delete einer Übersetzung und Delete der vollständigen Gruppe.
- Vorabprüfung aller Gruppenrechte und Kollisionen; Fehler und Wiederherstellung bei Storage-Ausfällen.
- URL mit und ohne Authority, mit Zugangsdaten/Port/Query/Fragment, baseurl und sprachspezifischem Dokument.
- Index-Aliasse, natürliche HTML-Ausgabe und optionale Permalinks ohne pauschale Endungsersetzung.
- Eindeutiger Fallback auf das tatsächliche Quelldokument; Mehrdeutigkeit und unbekannte Routen als Exception.
- Bereinigte Fehlermeldungen, begrenzte URL-Vorschläge und keinerlei Leaks verborgener Seiten.

Dieser PR enthält ausschließlich Entwurf und Beispiel-PHP-Dateien. Sie sind keine ausführbaren Integrationstests. Daten-/Collection-/Template-Editoren, Übersetzungsdienste, Deploy, Git und ein Sperrsystem bleiben Folgearbeiten beziehungsweise Aufgaben der Anwendung.

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

Empfohlene Reihenfolge: zunächst Root/Konfiguration, Rechte und Phore-Lesen; danach Seitenbaum, Polyglot und URL-Resolver; anschließend Dokumentspeicherung, Übersetzungsanlage und Gruppenmutationen. Legacy-Lesen lässt sich separat ergänzen. Daten-/Collection-/Template-Editoren bleiben ein eigener Folgeentwurf. Der vorliegende PR entscheidet noch keine Implementierung und verändert keine Referenzwebsite.
