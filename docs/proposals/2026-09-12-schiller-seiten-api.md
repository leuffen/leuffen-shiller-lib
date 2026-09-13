# Schiller: typisierte Zugriffsschicht für Website-Seiten

| Datum | Benutzername | Kurzbeschreibung |
|---|---|---|
| 2026-09-12 | dermatthes | §§ 1–13: API-Entwurf mit Konfiguration, Beispielen, Rückgaben, bidirektionaler URL-Auflösung und Page-Builder-Zuordnung angelegt |
| 2026-09-12 | dermatthes | § 9.1: URL-Eingaben mit und ohne Domain oder Protokoll konkretisiert |
| 2026-09-12 | dermatthes | §§ 3–12: feste Sprachverzeichnisse ohne ID-/Sprachheader, Root-Startseite, optionale Permalinks und vereinfachte Anlagebeispiele |
| 2026-09-12 | dermatthes | §§ 1–13: ein Document mit Header-Array, Translation-Verfügbarkeit und Anlage, Root-Verweis, Rename/Delete sowie direkte URL-Auflösung mit Diagnose-Exception |
| 2026-09-12 | dermatthes | §§ 4, 10–12: TreeNode mit FileEntry, Kategorie-Seiten, vollständiger Sprachverfügbarkeit und konkreten Legacy-/Polyglot-Adapterentwürfen |
| 2026-09-12 | dermatthes | §§ 2, 5–11: Header-Definitionen, null als Root-Übersetzung, createIfMissing/isPersisted und Adapter-Interface mit Methodenstümpfen |
| 2026-09-12 | dermatthes | §§ 2–12: endungslose Seiten-IDs, Adapter-gesteuertes Laden/Schreiben, Indexumstellung, Teilbaum-Verschieben, Metadatenerhaltung und Legacy-Bestandsbearbeitung |
| 2026-09-12 | dermatthes | §§ 2–12: Review der Aktionswirkungen, Entwurfszustände, Editor-Konflikte, gemeinsames Speichern, Metadaten und Page-Builder-Abdeckung |
| 2026-09-13 | dermatthes | §§ 2, 5–6, 10–12: opaker Adapterzustand, ein Schreibauftrag, gemeinsame Übersetzungslogik und Examples nach neuer Lesereihenfolge |

## § 1 Ziel und Umfang

**Vorschlag, noch keine implementierte API.** Der Aufrufer übergibt das Website-Quellverzeichnis als `PhoreDirectory`, beispielsweise das bereits extern bereitgestellte `docs/`. `SchillerDir` liest dessen aktuelle Konfiguration selbst und bietet einen typisierten Zugriff auf Dateien, Seiten, Sprachvarianten, Metafelder und Berechtigungen. Alle Schiller-Namen und Methoden in diesem Dokument sind vorgeschlagen; die ausdrücklich als bestehend bezeichneten Phore-Funktionen sind im Quellcode geprüft.

Der erste Ausbau umfasst normale Markdown-/HTML-Seiten, Front Matter, Inhalt, Übersetzungsdateien und berechnete Ziel-URLs. Git, Checkout, Commit, Push, Deploy, HTTP, Login, KI-Übersetzung und Bearbeitungssperren bleiben Aufgaben der Anwendung. `_data`-Editoren, Collections, Posts, Medienverwaltung und das Bearbeiten von Liquid-Layouts sind spätere Erweiterungen. Ihre Dateien dürfen bei erlaubtem Lesezugriff im Dateibaum erscheinen, werden aber nicht als normale Seiten interpretiert.

Empfehlung: **ein Einstiegspunkt `SchillerDir`, Seitenobjekte `Document`, einen internen `JekyllUrlResolver` und austauschbare Formatadapter**. Speicherzugriff und Website-Format sind zwei unabhängige Schnittstellen. Eine neue Polyglot-Version erfordert damit keinen neuen Filesystem-Connector.

## § 2 Was der bisherige Page Builder benötigt

Die folgenden Befunde stammen aus `micx-io/micx-pagebuilder`; dessen Quellcode dient ausschließlich als Referenz. Das Ziel-Repository enthält derzeit ein PHP-Grundgerüst, noch keine Schiller-Implementierung.

| Bisherige Stelle | Beobachtete Aufgabe | Vorgeschlagene Schiller-API |
|---|---|---|
| `PageListCtrl::__invoke()` | Sections mit `_section.yml`, Gruppierung nach `pid` und Sprache, Fehlerliste | `pages()` mit rekursivem Seitenbaum und `Diagnostic[]` |
| `PageCtrl` / `FrontMatterFile::ReadPage()` | Markdown/HTML und YAML-Header lesen | `getPage(id)` |
| `PageCtrl` / `FrontMatterFile::WritePage()` | Header und Inhalt speichern | Header/Body am Document ändern, save(); Speicherstand verwaltet der Adapter |
| `PageCtrl::copyPage()` | Beliebige Quellsprache/PID kopieren; Permalink entfernen und sofort schreiben | Bewusst anderer Vertrag: neue Übersetzung nur aus dem Original vorbereiten und explizit speichern; Legacy legt keine an |
| `www/pages/edit-page.html` | Titel, Beschreibung, Layout, published, order, ptags, eigene Formulare | Header-Array und `getHeaderDefinitions()` mit Darstellungsangaben; Formrenderer auf diese Definitionen umstellen |
| `www/elements/page-list.html` | Seitengruppen, Sprachvarianten, Veröffentlichungsstatus | `PageTree`, `TranslationInfo`, Veröffentlichungsstatus |
| Vorschau-Link im Editor | URL bisher im Browser zusammengesetzt | `document->getUrl()` |
| Neue Anforderung für den Nachfolger | Von einer realen Website-URL zum bearbeitbaren Quelltext | `getDocumentByUrl(url): Document` |
| `_data/languages.yml` | Sprachcodes und Anzeigenamen | `config()->languages` plus `languageLabels`; Legacy liest seine Sprachliste intern |
| `FileCtrl` und Daten-/Fragmenteditoren | Beliebige YAML-Daten bearbeiten | Später; bewusst keine allgemeine Schreib-Hintertür im ersten Ausbau |
| `InfoCtrl`, `RepoCtrl`, Middleware | Preview-Host, Änderungsstatus, VCS und Notifications | Anwendung; optionaler URL-Kontext, kein VCS in Schiller |

Der [vollständige Page-Builder-Abgleich](../pagebuilder-abgleich.md) nennt für jeden gefundenen Ablauf die Schiller-Aktion, deren Wirkung und die verbleibende Anwendungsarbeit. Der Entwurf ist kein unveränderter Drop-in für alte Controller- oder JSON-Verträge. Die Formatübersetzung liegt vollständig im Adapter; der neue Page Builder verwendet direkt Schillers API. Daten-/Fragmenteditoren bleiben ausdrücklich ein eigener Ausbau.

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
  "languageLabels": {"de": "Deutsch", "en": "English", "fr": "Français"},
  "url": "https://example.org",
  "baseurl": "",
  "schemaVersion": 1
}
```

`languageLabels: array<string,string>` ergänzt die Sprachcodes um Anzeigenamen. Im Polyglot-Profil kommen optionale Namen aus `schiller.yaml` unter `language_labels`; ohne Angabe dient der Code als Label. Legacy übernimmt vorhandene Namen aus `_data/languages.yml`. Beide Profile liefern dieselbe öffentliche Form.

`SiteConfig` ist eine geprüfte öffentliche Projektion, kein unbeschränkter Dump der Jekyll-Konfiguration oder aller Rollenregeln. Interne Konfigurationszugriffe geben dem Benutzer kein Recht, diese Dateien über `files()` oder andere Zugriffe auszulesen.

## § 4 Dateibaum und Seitenbaum

### § 4.1 Dateien: echte Quellpfade

`files('leistungen', recursive: false)` bleibt die optionale physische Inspektion mit Root-relativen Dateipfaden. Ein `FileListing` enthält `entries: list<TreeNode>` und Diagnosen. In dieser Ansicht ist `node.path` der physische Pfad und `node.id` null; Dateien haben eine `FileEntry`-Referenz, Ordner haben `file: null`. Die eigentliche Seitenbedienung benutzt ausschließlich `pages()`, `getPage()` und endungslose IDs.

```php
$listing = $site->files('leistungen', recursive: true);
foreach ($listing->entries as $node) {
    echo $node->path; // nur im physischen Listing
}
```

Dateipfade sind Root-relativ, ohne führenden Slash; `''` bezeichnet das physische Root. `FileKind` kennt directory, page, asset, data, template und other. Kein Zugriff liefert einen rohen Filesystem-Handle. Nicht expandierte Ordner haben bei `recursive: false` leere `children`, aber `isLeaf()` prüft trotzdem lesbare Kinder.

### § 4.2 Seiten: endungslose IDs und optionale Datei

Die öffentliche Seiten-ID heißt `id`. Kanonische IDs sind `/`, `/leistungen`, `/leistungen/allgemeinmedizin`: führender Slash, keine abschließenden Slashes außer beim Root, kein Sprachpräfix und keine Dateiendung. `leistungen` darf am API-Eingang zu `/leistungen` normalisiert werden. `root` ist kein reservierter Alias. Eine ID ist weder Dateipfad noch Ziel-URL und wird nicht in den YAML-Header geschrieben. Die Identität eines Documents besteht aus ID plus Sprache.

```php
$tree = $site->pages();                       // PageTree
$category = $site->pages('/leistungen')->root; // TreeNode
$page = $site->getPage('/leistungen');        // Document
$child = $site->getPage('/leistungen/allgemeinmedizin');
$rootPage = $site->getPage('/');
```

Im Seitenbaum hat jeder `TreeNode` eine `id`, `path: null`, `file: ?FileEntry`, `children: list<TreeNode>`, `translations: array<string,TranslationInfo>` und optionale `metadata: array<string,YamlValue>`. Kategorienmetadaten, etwa eine alte Section-Beschreibung, bleiben unabhängig von einer Seitendatei erhalten. Eine Kategorie ohne Seite hat `file: null` und leere translations. Sie öffnet keinen Editor; Kinder sind aufklappbar. `isLeaf()` beschreibt allein, ob lesbare Kinder existieren.

`getDocument()` lädt die zugeordnete Seite oder liefert bei einem Knoten ohne Seite null. Eine nachträglich verschwundene/gesperrte Datei erzeugt NotFoundException. `FileEntry::path` bleibt ausschließlich die tatsächliche Dateireferenz für Diagnose und Dateizugriff. Indexdateien sind im Seitenbaum niemals zusätzliche Kinder. Listings laden keine vollständigen Bodies.

Beispiel nach dem Anlegen einer Unterseite; fr fehlt, die englische Elternseite existiert, für die Unterseite zunächst nur de:

```json
{
  "root": {
    "id": "/leistungen",
    "path": null,
    "file": {"path": "leistungen/index.md", "kind": "page"},
    "metadata": {},
    "translations": {
      "de": {"language": "de", "path": "leistungen/index.md", "exists": true, "isRootDocument": true, "published": true},
      "en": {"language": "en", "path": "en/leistungen/index.md", "exists": true, "isRootDocument": false, "published": true},
      "fr": {"language": "fr", "path": "fr/leistungen/index.md", "exists": false, "isRootDocument": false, "published": null}
    },
    "children": [{
      "id": "/leistungen/allgemeinmedizin",
      "path": null,
      "file": {"path": "leistungen/allgemeinmedizin.md", "kind": "page"},
      "metadata": {},
      "translations": {
        "de": {"language": "de", "path": "leistungen/allgemeinmedizin.md", "exists": true, "isRootDocument": true, "published": false},
        "en": {"language": "en", "path": "en/leistungen/allgemeinmedizin.md", "exists": false, "isRootDocument": false, "published": null},
        "fr": {"language": "fr", "path": "fr/leistungen/allgemeinmedizin.md", "exists": false, "isRootDocument": false, "published": null}
      },
      "children": []
    }]
  },
  "diagnostics": []
}
```

Alle konfigurierten lesbaren Sprachen werden mit exists ausgegeben. Verborgene Varianten/Kandidaten und Kinder werden ausgelassen; Dateinamen dürfen weder über Metadaten noch Diagnosen offengelegt werden. Die bevorzugte FileEntry verweist auf die lesbare Standardsprache, sonst auf die erste lesbare Variante; deren Sprache bleibt unverändert. Ein fehlendes Original verhindert nicht das Lesen bestehender Übersetzungen, wohl aber deren Ableitung vom Original.

Der physische Aufbau bestimmt keine zusätzliche öffentliche ID: `leistungen.md` und `leistungen/index.md` sind alternative Ablagen für `/leistungen`. Liegen beide in derselben Sprache vor, meldet der Adapter einen Konflikt. Gleiches gilt für konkurrierende md/html-Dateien oder andere Zuordnungen zur selben ID/Sprache.

Unveröffentlichte lesbare Seiten bleiben im Editor sichtbar. Excluded-Dateien sowie Daten, Layouts, Includes, Posts, Collections und Build-Ziele werden nicht als normale Seiten interpretiert. Der physische Dateibaum darf sie bei Leserecht zeigen. Markdown ohne Header erfordert das unterstützte optionale Front-Matter-Profil.

## § 5 Einzelne Datei: ein Document mit Header-Array

```php
$page = $site->getPage('/leistungen/diagnostik');
$page->id;                       // '/leistungen/diagnostik'
$page->language;                 // 'de'
$page->file->path;               // 'leistungen/diagnostik.md'
$page->header['custom_tracking'] = ['campaign' => 'sommer'];
$page->content = "## Neuer Inhalt\n";
$page->save();
```

Original und Übersetzungen sind Documents. `id`, `language`, `isRootDocument` und `file` sind außen nur lesbar. `header` ist ein direkt bearbeitbares Array, `content` ein String. `getEffectiveHeader()` ergänzt Jekyll-Defaults, ohne sie zurückzuschreiben. `YamlValue` bezeichnet rekursiv null, bool, int, float, string und entsprechende Listen/Maps.

Optionale und unbekannte Metadaten werden vollständig erhalten, dürfen manuell gesetzt werden und werden auch in neue Übersetzungen kopiert. Definitionen validieren bekannte Header-Schlüssel, bilden aber keine Positivliste erlaubter Zusatzfelder. Nur ein ausdrückliches `unset()` entfernt einen Schlüssel; die Zuweisung von null speichert YAML-null, sofern bei einem bekannten Feld zulässig. Verschachtelte eigene Werte bleiben erhalten. Der Legacy-Adapter hält seine Identitätsfelder konsistent; im neuen Profil werden dateilokale PID-/Sprachfelder als Profilkonflikt gemeldet, niemals still gelöscht.

```php
$new = $site->createPage(
    '/leistungen/vorsorge',
    header: ['title' => 'Vorsorge', 'custom_flag' => true],
    content: "## Vorsorge\n",
);
$new->file;                       // null, noch keine physische Datei
$new->isPersisted();              // false
$new->save();                     // erst hier anlegen und gegebenenfalls Eltern umstellen
$new->file->path;                 // 'leistungen/vorsorge.md'
$new->isPersisted();              // true
```

`createPage()` erzeugt im neuen Adapter ein ungespeichertes Stammdokument mit den Anlage-Defaults, etwa published=false. Der Zielpfad ist adapterspezifisch und wird nicht vom Aufrufer festgelegt. Bestehende Seitengruppen, Entwürfe oder Dateien werden nicht überschrieben. Eine vorhandene reine Kategorie ohne Seitengruppe darf hingegen mit createPage unter derselben ID ein Stammdokument erhalten; es wird direkt als index.md angelegt, vorhandene Kinder und Metadaten bleiben erhalten. Im Legacy-Adapter ist Neuanlage nicht unterstützt.

`isPersisted()` beschreibt den bestätigten Speicherzustand der Document-Instanz; lokale ungespeicherte Änderungen an einem bestehenden Dokument ändern den Wert nicht. Ein Entwurf hat `file: null`, nach erfolgreichem Speichern eine FileEntry. `hasChanges()` vergleicht Header und Body mit dem geladenen beziehungsweise zuletzt gespeicherten Stand; jeder neue Entwurf gilt als ungespeicherte Änderung. `adapterState` ist ein freies, internes Array ohne vorgeschriebene Schlüssel oder Revisionsklasse. Nur der Adapter setzt und interpretiert dessen Inhalt; beispielsweise kann er darin seinen Speicherstand halten. Für den Dokumenttransport verwendet er JSON-fähige Werte ohne Ressourcen, Zugangsdaten oder ein von Schiller vorgeschriebenes Feldschema. Es gehört weder zum YAML-Header noch zu Jekyll-Defaults. Beim Erzeugen einer neuen Seite oder Übersetzung initialisiert der Adapter einen neuen Zustand, statt den Zustand des Originals zu kopieren. Ein erfolgreiches Speichern aktualisiert diesen Stand; unverändertes `save()` schreibt keine Bytes. Externe Änderungen werden bei Operationen geprüft, nicht durch laufendes Polling von `isPersisted()` erkannt. [geändert]

Änderungen an einem bestehenden Dokument prüfen den geladenen Quellstand. Ein reines Body-Update bewahrt den Headerblock, ein reines Header-Update den Body. YAML-Neuserialisierung garantiert keine Erhaltung der Kommentare.

Innerhalb derselben SchillerDir-Instanz wird pro ID/Sprache dieselbe Document-Instanz verwendet. Eine automatische Umstellung auf Indexablage ändert nur die FileEntry, nicht die ID, Sprache oder Root-Beziehung. Schon ausgegebene Listings bleiben Snapshots und werden erneut abgefragt.

### § 5.1 Editor-Speicherung und Konflikte

`save()` speichert genau dieses Dokument, gegebenenfalls einschließlich der notwendigen Elternpromotion nach § 11.2. Der Adapter liest den erwarteten Speicherstand aus seinem `adapterState`, prüft ihn gegen den aktuellen Bestand und aktualisiert ihn nach erfolgreichem Schreiben. Die Anwendung gibt keinen Revisionsparameter an und wertet keine Zustandsfelder aus. Ein neuer Entwurf wird ausschließlich neu angelegt; ein gespeichertes Document mit fehlendem/ungültigem Zustand wird nicht als Neuanlage oder unbedingtes Überschreiben behandelt. Abweichungen führen vor Schreiben zu ConflictException. [geändert]

Bei getrennten HTTP-Anfragen muss der ursprüngliche Dokumentstand erhalten bleiben. Vorgeschlagener öffentlicher Transport: `Document::toArray(): array` liefert einen JSON-fähigen Stand aus id, language, header, content und `state`. `state` transportiert unverändert den internen Bearbeitungskontext einschließlich adapterState, Adapterbindung und Ausgangsstand für hasChanges; seine Felder sind keine von der Anwendung zu verwaltenden Revisionsparameter. `SchillerDir::restoreDocument(array $data): Document` bindet diesen Stand an einen neuen SchillerDir. Ein frisches getPage im POST darf den ursprünglichen Zustand nicht ersetzen. [geändert]

restoreDocument nimmt ausschließlich diesen definierten Transport entgegen, keine beliebigen PHP-Objekte oder Klassennamen. Es prüft Identität, Adapter-/Site-Bindung, Struktur und aktuelle Leserechte; Rollen, Storage-Verbindungen und FileEntry-Pfade werden nicht aus Clientdaten übernommen. Der Adapter muss Herkunft und Bindung seines zurückgegebenen Zustands spätestens vor der Mutation prüfen. Fehlender, manipulierter, veralteter oder zu einem anderen Document gehörender Zustand darf Konfliktprüfungen und Rechte nicht umgehen. Ein bereits unter derselben Identität verwaltetes Document wird nicht still überschrieben; importiert wird in eine frische Bearbeitungseinheit. [neu]

Die UI erhält den vollständigen Dokumentstand und verändert nur Header/Inhalt. Nicht sichtbare Headerkeys und der opake state bleiben erhalten; bewusstes Entfernen eines Headerkeys ist eine Löschanweisung. Ein partielles Formular wird in der Anwendung auf diesen erhaltenen Stand angewandt, nicht als kompletter Header interpretiert. getEffectiveHeader und allgemeine Requestfelder werden nicht zurückgeschrieben. [Beispiel 18](../../examples/18-editor-save.php) zeigt die beiden HTTP-Aktionen ohne externe Revisionsverwaltung. Der Transport ist Teil des Entwurfs, noch keine implementierte Hydration. [geändert]

`SchillerDir::saveDocuments(array $documents): void` speichert ausdrücklich mehrere Documents als eine vorbereitete Operation. Es verwendet denselben Adapteraufruf wie save: `write([$document])` beziehungsweise `write($documents)`. Doppelte Identitäten, fremde SchillerDir-Instanzen und leere Listen werden abgewiesen. Alle Zustände, Rechte, Feldwerte, Quellen und endgültigen Routen werden gemeinsam vorab validiert. Nicht aufgeführte Sprachvarianten werden nicht still mitgespeichert. Die Batch-/Wiederherstellungsregeln aus § 8 gelten; eine Schleife aus unabhängigen Einzelschreibvorgängen erfüllt diesen Vertrag nicht. [geändert]

### § 5.2 Objektzustand und erneute Abfragen

Pro SchillerDir und ID/Sprache gibt es genau eine verwaltete Instanz. `getPage()` und `getTranslation()` liefern auch einen bereits in dieser Instanz vorbereiteten Entwurf; `getTranslation(..., false)` bedeutet „keinen neuen Entwurf erzeugen“, nicht „nur gespeicherte Dateien“. Ohne gespeicherte Datei und ohne bekannten Entwurf liefert die Übersetzungsabfrage null, `getPage()` hingegen NotFoundException. Wiederholtes `createPage()` für eine bereits gespeicherte oder vorbereitete Identität wirft AlreadyExistsException.

Listen und URL-Rückwärtsauflösung beruhen auf dem gespeicherten Bestand: ein Entwurf erscheint nicht als vorhandene Seite, seine TranslationInfo bleibt `exists=false`. Bei einer später extern entstandenen Datei scheitert das Speichern des Entwurfs als Konflikt, statt diese Datei zu übernehmen. Explizites Löschen entfernt die gelöschte Instanz aus der Verwaltung und macht alte Referenzen ungültig. `getTranslation(null)` erfindet weiterhin kein fehlendes Original.

SchillerDir ist für eine begrenzte Bearbeitungseinheit vorgesehen, im HTTP-Editor normalerweise pro Request. Abbrechen bedeutet, die bearbeitete Instanz zu verwerfen; ein neuer SchillerDir lädt den gespeicherten Stand. Erneutes getPage an derselben Instanz ist kein Reload und verwirft keine Änderungen. Nach externen Konfigurations-/Speicherkonflikten lädt die Anwendung in einer neuen Bearbeitungseinheit und entscheidet sichtbar über erneutes Anwenden ihrer Änderungen.

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

| Quelle | Sprache | Seiten-ID | Standard-URL |
|---|---|---|---|
| `index.md` | de | `/` | `/` |
| `en/index.md` | en | `/` | `/en/` |
| `leistungen/index.md` | de | `/leistungen` | `/leistungen/` |
| `leistungen/diagnostik.md` | de | `/leistungen/diagnostik` | `/leistungen/diagnostik.html` |
| `en/leistungen/diagnostik.md` | en | `/leistungen/diagnostik` | `/en/leistungen/diagnostik.html` |

Die Startseiten brauchen ebenfalls nur ihren Titel und Inhalt: `index.md` beispielsweise mit `title: Startseite`, `en/index.md` mit `title: Home`. Ein leerer YAML-Header genügt zur Seitenerkennung ebenfalls. Ein Permalink ist nicht erforderlich.

### § 6.2 Übersetzungen auflisten und lesen

`getTranslations()` liefert je konfigurierter und lesbarer Sprache einen kleinen Eintrag, einschließlich Standardsprache. `exists` bezeichnet ausschließlich eine gespeicherte Quelldatei. `published: ?bool` liefert deren effektiven Veröffentlichungsstatus ohne Body-Laden; bei fehlender Datei ist der Wert null. exists und published sind unabhängig: eine unveröffentlichte Datei existiert weiterhin. Fehlende Varianten haben bereits ihren fest berechneten Zielpfad; weder Fallbacks noch ungespeicherte Entwürfe zählen als vorhanden.

```php
$page = $site->getPage('/leistungen/diagnostik');
$translations = $page->getTranslations(); // array<string, TranslationInfo>
$english = $page->getTranslation('en');   // ?Document, hier vorhanden
$french = $page->getTranslation('fr');    // null
$root = $english->getTranslation();     // identisch zu $page
assert($page->getTranslation() === $page);
assert($english->getTranslation('de') === $page);
```

```json
{
  "de": {"language": "de", "path": "leistungen/diagnostik.md", "exists": true, "isRootDocument": true, "published": true},
  "en": {"language": "en", "path": "en/leistungen/diagnostik.md", "exists": true, "isRootDocument": false, "published": true},
  "fr": {"language": "fr", "path": "fr/leistungen/diagnostik.md", "exists": false, "isRootDocument": false, "published": null}
}
```

Das Listing lädt keine vollständigen Dokumentinhalte. Verborgene vorhandene Varianten und nicht lesbare Kandidaten werden vollständig ausgelassen, niemals als fehlend ausgegeben. Direktzugriff auf einen nicht lesbaren Pfad wirft `NotFoundException`; nur ein erlaubter, tatsächlich fehlender Übersetzungspfad ohne bekannten Entwurf ergibt `null`. Eine unbekannte Sprache ist ein Fehler. `getTranslation()` ohne Sprachargument beziehungsweise `getTranslation(null)` liefert stets das Stammdokument oder wirft `NotFoundException`, falls es fehlt beziehungsweise nicht lesbar ist.

Ohne Sprachargument oder mit `null` liefert `getTranslation()` am Original dieselbe Objektinstanz und an einer Übersetzung das Stammdokument. Fehlt dessen Datei oder Leserecht, wird `NotFoundException` geworfen. Ein neu erzeugtes Original liefert vor dem ersten Speichern ebenfalls sich selbst. Bei einer anderen Sprache bleibt der Rückgabewert ohne Anlageoption `null`, sofern weder eine gespeicherte Variante noch ein bereits vorbereiteter Entwurf bekannt ist (§ 5.2).

Die Methoden getTranslation und getTranslations gehören zu Document/SchillerDir, nicht zum Adapter-Interface. Schiller normalisiert die gewünschte Sprache, verwendet den gemeinsamen Dokumentbestand und lädt bei Bedarf über `load(id, language)`. Bei einer fehlenden erlaubten Variante kopiert es die gespeicherten Root-Header/-Inhalte, setzt published=false und ruft `create(id, language, header, content)` auf. Der Adapter berechnet Ablage und eigenen Anlagezustand und setzt seine Grenzen durch; Legacy lehnt create stets ab. [neu]

Für das Listing liefert buildTree die vorhandenen lesbaren Varianten mit ID/Sprache/Quelle und Kategorienmetadaten. Schiller ergänzt anhand der konfigurierten Sprachen und `getSourcePath(id, language)` die fehlenden lesbaren Kandidaten mit exists=false. Der Quellpfad allein beweist keine Existenz und erteilt kein Leserecht. getSourcePath legt nichts an und wirft bei Mehrdeutigkeit einen Konflikt. Somit benötigt kein Adapter eine zweite Implementierung von getTranslation/getTranslations. [neu]

### § 6.3 Eine Übersetzungsdatei anlegen

```php
$french = $page->getTranslation('fr', createIfMissing: true); // Document oder Exception
if (!$french->isPersisted()) {
    // Kopie des Stammdokuments, keine maschinelle Übersetzung.
    // id='/leistungen/diagnostik'; file=null; published=false
    // Interner Zielkandidat: fr/leistungen/diagnostik.md
    $french->header['title'] = 'Diagnostic';
    $french->content = "## Diagnostic\n\nTexte français.\n";
    $french->save(); // erst hier entsteht die Datei
}
```

Eine existierende Übersetzung wird unverändert zurückgegeben. Bei fehlender Übersetzung erzeugt `createIfMissing: true` eine ungespeicherte Kopie von Header und Inhalt des Stammdokuments mit `published: false`, auch wenn der Aufruf von einer anderen Übersetzung ausgeht. Geerbte Defaults werden nicht kopiert. Im neuen Adapter werden keine ID-/Sprachfelder erzeugt. Der Zielpfad ist nicht frei veränderbar. Wiederholte Abfragen desselben Entwurfs liefern innerhalb derselben Instanz dasselbe Objekt, auch ohne gesetztes createIfMissing. isPersisted bleibt bis zum Speichern false.

`null`, ein weggelassenes Sprachargument und die konfigurierte Standardsprache liefern immer das Stammdokument; es wird nicht durch Klonen einer Übersetzung angelegt; `createIfMissing` erzeugt in diesem Fall kein neues Original. Bei null beziehungsweise der Standardsprache hat `createIfMissing` keine Wirkung. Zum Klonen muss das Stammdokument gespeichert, lesbar und ohne ungespeicherte Änderungen sein. `getTranslation(..., createIfMissing: true)` und anschließend `save()` prüfen `createFile` und `createTranslation` am Ziel sowie createDirectory für erforderliche neue Elternordner. Eine zwischenzeitlich angelegte Datei wird nicht überschrieben. Für unverändert vorhandene Übersetzungen genügt zum Abruf Leserecht.

Bei einer fehlenden Legacy-Übersetzung liefert `createIfMissing: false` null; `createIfMissing: true` wirft UnsupportedOperationException. Eine vorhandene Legacy-Übersetzung wird auch mit gesetzter Option normal zurückgegeben und kann nach Rechteprüfung bearbeitet werden. Bei neuen Polyglot-Übersetzungen bestimmt die aktuelle Ablage der Gruppe den Kandidaten: nach der Umstellung einer Elternseite also `<sprache>/leistungen/index.md`. Fehlende Sprachdateien werden durch die Umstellung nicht angelegt.

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
        - {path: "leistungen/**", actions: [write, createFile, createDirectory, rename, delete]}
        - {path: "en/**", actions: [write, createFile, createDirectory, createTranslation, rename, delete]}
        - {path: "fr/**", actions: [write, createFile, createDirectory, createTranslation, rename, delete]}
      deny:
        - {path: "intern/**", actions: [read, write, createFile, createDirectory, createTranslation, rename, delete]}
        - {path: "schiller.yaml", actions: [read, write]}
        - {path: "_config.yml", actions: [read, write]}
    admin:
      allow:
        - {path: "**", actions: [read, write, createFile, createDirectory, createTranslation, createTemplate, rename, delete]}
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

`getHeaderDefinitions()` ist auch als `SchillerDir::getHeaderDefinitions($id, $language = null)` verfügbar, damit ein Editor das Formular vor einer Neuanlage aufbauen kann. Die ID bezeichnet die geplante Seite, nicht den Elternordner. Schiller prüft Lesbarkeit des Kandidaten und berechnet dessen tatsächliche Zielablage; es erzeugt keine Datei und benötigt zum reinen Lesen der Definitionen kein Anlagerecht. Das Document delegiert mit seiner Identität.

Felddefinitionen können optionale `presentation`-Angaben enthalten: `widget` (input, textarea, select, switch, checkboxes), `placeholder` und `checkLabel`. Beispiel: `description: {type: string, presentation: {widget: textarea}}` unter fields. `editable=false` ist serverseitig durchzusetzen, max_length bleibt eine Validierungsregel. Legacy bildet text/textarea/select/switch/multi und unterstützte Attribute aus `_section.yml` auf denselben Vertrag ab; Layout-Auswahl, Sortierung und ptags werden eingebaute Definitionen. Trennlinien (`hr`) sind Kategorienmetadaten für das Formular, keine Header-Schlüssel. Unbekannte Darstellungshinweise bleiben als Metadaten erhalten und erzeugen Diagnosen; der Editor rendert sie nicht als ungeprüftes HTML.

`FieldType` ist ein string-backed Enum (`string`, `boolean`, `integer`, `select`, `multiselect`). Dropdown-Optionen sind `FieldOption`-Objekte mit `value: string` und `label: string`; Mehrfachauswahl speichert `list<string>`. `hasDefault` unterscheidet fehlenden Default von explizitem `null`; `nullable` regelt, ob `null` erlaubt ist. Unbekannte Metafelder bleiben erhalten, dürfen manuell angelegt werden und werden beim Erzeugen einer Übersetzung kopiert. Felddefinitionen validieren bekannte Schlüssel; zusätzliche Metadaten benötigen keine vorherige Registrierung. Adaptereigene Standardfelder wie `description` und `permalink` werden als eingebaute Definitionen bereitgestellt; dateilokale `page_id`, `pid` und `lang` sind im Polyglot-Profil nicht zulässig. Geerbtes `lang` aus `_config.yml` darf in `getEffectiveHeader()` auftauchen, wird jedoch niemals in den gespeicherten Header zurückgeschrieben. Neue Pflichtfelder dürfen Bestandsseiten lesbar lassen, verhindern jedoch ein nicht valides Speichern mit präzisen Feldfehlern.

## § 8 Rechte und erlaubte Aktionen

Die UI kann erlaubte Aktionen abfragen; jede Mutation prüft sie erneut. Ein `rename=true` bestätigt die Eignung der Quelle einschließlich ihres Teilbaums, nicht irgendein noch unbekanntes Ziel. Zielrechte, Konflikte und notwendige Promotionen werden erst bei rename geprüft. `write` bezieht sich auf gespeicherten Bestand; Entwürfe benötigen die Anlageaktionen. Capabilities sind keine dauerhafte Freigabe.

```php
$actions = $site->capabilities('/leistungen/diagnostik'); // Capabilities
if ($actions->write) {
    // UI darf den Speichern-Button aktivieren; save() prüft erneut.
}
$targetActions = $site->capabilities('/leistungen/diagnostik', language: 'fr');
```

```json
{
  "read": true,
  "write": true,
  "createFile": false,
  "createDirectory": false,
  "createTranslation": false,
  "createTemplate": false,
  "rename": true,
  "delete": true
}
```

Das Beispiel bezieht sich auf eine bereits vorhandene normale Seite als `user`; Erstellen ist für ein bestehendes Ziel nicht möglich. Für einen fehlenden französischen Zielpfad können `createFile` und `createTranslation` wahr sein. `Capabilities` sind die Schnittmenge aus Projektregeln, Host-Policy, Adapter-/Storage-Unterstützung und Ressourcenzustand. `createTemplate` bleibt im ersten Ausbau auch bei Admin-Freigabe `false`, weil noch kein Template-Editor/Writer implementiert wird. Feld-Presets sind davon unabhängig; Layout-Dateien und Seitenvorlagen sind nicht dasselbe wie Felddefinitionen.

Jede Operation prüft Rechte, nicht nur das Listing. Verweigertes Lesen ergibt bei Direktzugriff dieselbe `NotFoundException` wie eine fehlende Datei. Listen, Sprachgruppen, Zähler und Diagnosen enthalten keinerlei Namen, IDs oder Metadaten verborgener Dateien. Nur wenn eine Datei lesbar ist, darf fehlendes Schreibrecht als `AccessDeniedException` sichtbar werden. Nicht erlaubte Schreibaktionen bleiben auch dann verboten, wenn der Benutzer das JSON manuell manipuliert.

`deny` gewinnt immer gegenüber `allow`; keine Rollenvererbung im ersten Vertrag. Unbekannte Rollen haben keine Rechte. Für Anlageoperationen sind Ziel und notwendige Elternverzeichnisse zu prüfen. Der neue Adapter legt notwendige Elternordner innerhalb einer geprüften Speicheroperation an; dafür ist zusätzlich createDirectory am jeweiligen physischen Ziel nötig. Der Legacy-Adapter erstellt keine Verzeichnisse. Eine interne Existenzprüfung verhindert Überschreiben, ohne verborgene Ziele über unterschiedliche Fehlermeldungen offenzulegen.

Alle Speicheroperationen sind an das Root gebunden: keine absoluten physischen Dateipfade (der führende Slash einer Seiten-ID bezeichnet ausschließlich das logische Root), `..`, Nullbytes oder Symlinks; Symlinks werden im ersten Ausbau nicht verfolgt. Ein HTTP-Layer decodiert Transportdaten einmal, Schiller betreibt keine zusätzliche URL-Decodierung von Dateipfaden. Auch von Adaptern berechnete Pfade durchlaufen dieselbe Prüfung. Bloße String-Präfixprüfungen reichen nicht; der Connector muss seine Root-Grenze für reale Lese-/Schreibzugriffe durchsetzen. Fehlertexte enthalten keine absoluten Serverpfade.

### § 8.1 Umbenennen und Löschen

```php
$page = $site->getPage('/leistungen/diagnostik');
$page->rename('/medizin/diagnostik'); // sofort: Root und alle Übersetzungen
// leistungen/diagnostik.md -> medizin/diagnostik.md
// en/leistungen/diagnostik.md -> en/medizin/diagnostik.md
$page->getTranslation('en')?->delete(); // sofort: nur die englische Datei
$page->delete();                       // sofort: Root und verbleibende Übersetzungen
```

`rename()` ist nur am Stammdokument zulässig; Übersetzungspfade folgen automatisch. Die Ziel-ID ist sprachneutral und endungslos. Reine Kategorien ohne Document werden über `SchillerDir::rename($id, $newId)` verschoben; `Document::rename($newId)` delegiert an denselben Ablauf. Vorhandene Ziele werden nicht überschrieben; notwendige Zielordner unterliegen der createDirectory-Prüfung. Natürliche URLs folgen dem neuen Pfad, ein expliziter Permalink bleibt bestehen. Geladene Dokumentreferenzen werden mitgeführt. Löschen einer Übersetzung lässt Root und andere Sprachen bestehen: anschließend liefert `getTranslation($gelöschteSprache)` am verbliebenen Original `null`, der Listeneintrag `exists: false`. Polyglot kann danach wieder einen Root-Fallback ausgeben.

Gruppenoperationen prüfen vor dem ersten Schreiben sämtliche betroffenen Dateien, auch verborgene Varianten. Fehlt eine Berechtigung oder kollidiert ein Ziel, scheitert die ganze Aktion ohne Änderung und ohne Offenlegung verborgener Pfade. Rename benötigt `rename` und `write` an den Quellen sowie `createFile` und für Sprachziele `createTranslation` an den Zielen. Delete benötigt `delete` an allen betroffenen Dateien. `Capabilities` berücksichtigt bei Root-Dokumenten die vollständige Gruppe.

Nicht gespeicherte Entwürfe oder ungespeicherte Änderungen in betroffenen Dokumenten führen zu `UnsavedChangesException`; Rename/Delete speichern nicht implizit. Gelöschte Referenzen liefern `isPersisted() === false`, werden für weitere Dokumentoperationen ungültig und können per `save()` keine Datei wiederherstellen. Für mehrere Dateien benötigt der Storage eine vorbereitete Batch-Operation mit Wiederherstellung bei I/O-Fehlern. Bietet er diese Fähigkeit nicht, wird die Gruppenmutation vorab abgelehnt. Universelle atomare Dateisystemtransaktionen werden nicht versprochen; ein gescheiterter Rollback wird ausdrücklich als unvollständige Operation gemeldet.

Im Legacy-Profil bleibt nur das Schreiben bestehender Seitendateien erlaubt: write kann true sein; createFile, createDirectory, createTranslation, rename und delete bleiben false. Die Beschränkung gilt auch für Administratoren. Rename verschiebt auch Kategorien samt Nachfahren gemäß [Verschiebelogik](../verschieben.md); delete am Stammdokument mit Nachfahren bleibt bis zu einem gesonderten Löschvertrag abgewiesen. Die Beispiele 12/13 betreffen Blatt-Seitengruppen. Automatische Umstellung einer Elternseite nach § 11.2 ist eine interne Ablageoperation mit unveränderter ID.

Das Löschen einer Übersetzung außerhalb der Standardsprache entfernt immer nur deren konkrete Datei, auch bei einer Kategorie mit Kindern. Deren Kinder bleiben erhalten, der Knoten kann zur reinen Kategorie werden. Das Löschen des Stammdokuments ist nur ohne Nachfahren in sämtlichen Sprachen erlaubt; `isLeaf()` des bereinigten UI-Baums reicht dafür nicht. Die Root-Seitengruppe `/` kann nicht gelöscht werden. Begleitdateien und Ordner werden durch Document::delete niemals rekursiv entfernt, auch bei einer Blattgruppe nicht. Fremde Metadaten bleiben bestehen. Kein automatischer Rückbau von index zu Blatt.

## § 9 URLs gehören zum Dokument

`getUrl()` liefert den lokalen Zielpfad des konkreten Dokuments einschließlich `baseurl`. Die Sprache steht bereits durch das Dokument fest; es gibt keinen Sprachparameter. `getUrl(absolute: true)` liefert mit konfiguriertem `url` die absolute URL, andernfalls eine `ConfigurationException`.

```php
$page = $site->getPage('/leistungen/diagnostik');
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
); // Document: id='/leistungen/diagnostik', file.path='en/leistungen/diagnostik.md', language='en'
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

Ein vom Adapter sicher erkannter Polyglot-Fallback wie `/fr/leistungen/diagnostik.html` liefert die tatsächlich verwendete deutsche Quelldatei, solange keine französische Übersetzung existiert. Das zurückgegebene Dokument behält `language='de'`, `isRootDocument=true` und seine deutsche `getUrl()`. Es wird kein französisches Dokument erfunden. `getTranslation('fr')` bleibt ohne vorbereiteten Entwurf `null`. Für nicht sicher modellierbare Build-Routen wird `unsupported` gemeldet. Auflösung und Veröffentlichung beziehen sich auf den aktuellen Quellstand, nicht auf einen möglicherweise älteren Deploy. Unveröffentlichte Dokumente haben eine berechenbare Vorschau-URL, aber keinen regulären öffentlichen Indexeintrag.

### § 9.2 Permalinks als Ausnahme

Ohne Permalink folgt die URL dem natürlichen Jekyll-Ausgabeweg. Ein explizites `permalink: /medizin/diagnostik/` ergibt für das Original `/medizin/diagnostik/` und für die englische Übersetzung `/en/medizin/diagnostik/`, jeweils zuzüglich `baseurl`. Innerhalb einer Sprachgruppe muss derselbe unlokalisierte Ausgabeweg entstehen; unterschiedliche übersetzte Slugs werden im festen Polyglot-Profil ohne IDs nicht unterstützt. Permalinks bestimmen keine Schiller-Gruppenidentität. Konflikte mit anderen Ausgaben werden vor dem Speichern abgelehnt. Nicht unterstützte Jekyll-Plugins, Platzhalter oder Build-Sonderfälle erzeugen Diagnosen statt erfundener URLs.

Eine Änderung des gruppenweiten Permalinks wird durch direktes Setzen/Entfernen desselben Headerwerts an allen betroffenen vorhandenen Documents und `saveDocuments([...])` gespeichert. Die Validierung betrachtet deren gemeinsamen Endzustand; sequentielles save darf keinen vorübergehend widersprüchlichen Gruppenvertrag erzeugen. Nicht lesbare/schreibbare Varianten verhindern die Änderung der Gruppe. Fehlende Sprachen werden nicht angelegt. [Beispiel 19](../../examples/19-save-language-group.php) zeigt diesen Ausnahmefall ohne Patch-Objekte.

`getUrl()` berechnet die vorgeschlagene Route aus dem aktuellen Document, also auch aus ungespeicherten Headeränderungen. Dagegen löst getDocumentByUrl ausschließlich gespeicherte, veröffentlichbare Routen auf und liefert deren verwaltetes Document, das lokal bereits verändert sein kann. Deshalb ist eine Rückwärtsauflösung der Vorschau-URL vor save nicht garantiert. Neue Entwürfe und published=false erzeugen keine öffentliche Route; die tatsächliche Vorschau und der Deploystand bleiben Sache des Page Builders. Bei einem Polyglot-Fallback zeigt die UI ausdrücklich die tatsächlich gelieferte Sprache; ein Speichern des deutschen Documents bearbeitet Deutsch, nicht die angefragte französische URL.

Die URL wird aus der aktuellen Dateiablage berechnet, nicht aus der endungslosen ID erraten. Bei `/leistungen` ändert eine automatische Umstellung von `leistungen.md` auf `leistungen/index.md` die natürliche URL von `/leistungen.html` auf `/leistungen/`; die ID bleibt gleich. Ein expliziter Permalink bleibt erhalten und kann die URL stabil halten. Der Routenindex wird nach dem Schreiben erneuert. Alte natürliche URLs werden nicht automatisch als Redirect weitergeführt; Redirect-Erzeugung ist ein gesondertes Thema.

## § 10 Typisierter öffentlicher Vertrag

Diese Signaturen sind ein API-Entwurf, keine lauffähige Implementierung. Die normale Anwendung kennt Seiten-IDs und Documents; Dateinamen werden nur über die optionale FileEntry sichtbar.

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
    public function pages(string $id = '/'): PageTree;
    public function rename(string $id, string $newId): void;
    public function capabilities(string $id, ?string $language = null): Capabilities;
    public function getPage(string $id, ?string $language = null): Document;
    /** @param array<string, YamlValue> $header */
    public function createPage(string $id, array $header = [], string $content = ''): Document;
    public function getDocumentByUrl(string $url): Document;
    public function getHeaderDefinitions(string $id, ?string $language = null): FieldSet;
    /** @param list<Document> $documents */
    public function saveDocuments(array $documents): void;
    /** Bindet einen mit Document::toArray erzeugten Bearbeitungsstand an diese Instanz. */
    public function restoreDocument(array $data): Document;
}

/**
 * @property-read string $id
 * @property-read string $language
 * @property-read bool $isRootDocument
 * @property-read ?FileEntry $file
 */
final class Document
{
    /** @var array<string, YamlValue> */
    public array $header;
    public string $content;
    /** @internal Freies Adapter-Array; keine definierten Schlüssel, kein YAML-Header. */
    public array $adapterState = [];
    /** JSON-fähiger Dokumentstand; nur header/content durch die UI bearbeiten. */
    public function toArray(): array;
    /** @return array<string, YamlValue> */
    public function getEffectiveHeader(): array;
    public function getHeaderDefinitions(): FieldSet;
    public function getTranslation(?string $language = null, bool $createIfMissing = false): ?Document;
    /** @return array<string, TranslationInfo> */
    public function getTranslations(): array;
    public function getUrl(bool $absolute = false): string;
    public function isPersisted(): bool;
    public function hasChanges(): bool;
    public function save(): void;
    public function rename(string $id): void;
    public function delete(): void;
}

final class FileEntry
{
    public function __construct(public readonly string $path, public readonly FileKind $kind) {}
}

/**
 * @property-read ?string $id Seitenbaum; null im physischen Listing.
 * @property-read ?string $path Nur physisches Listing; null im Seitenbaum.
 * @property-read FileKind $kind
 * @property-read ?FileEntry $file
 * @property-read array<string, YamlValue> $metadata
 * @property-read array<string, TranslationInfo> $translations
 * @property-read list<TreeNode> $children
 */
final class TreeNode
{
    public function isLeaf(): bool;
    public function getDocument(): ?Document;
}

final class TranslationInfo
{
    public function __construct(
        public readonly string $language,
        public readonly string $path,
        public readonly bool $exists,
        public readonly bool $isRootDocument,
        public readonly ?bool $published,
    ) {}
}
```

`getPage('/leistungen', 'en')` kann eine vorhandene Übersetzung direkt laden, auch wenn das Stammdokument fehlt. Ohne Sprache wird die Standardsprache geladen. Derselbe Zugriff funktioniert in beiden Adaptern ohne Dateiendung. `getTranslation(null)` liefert das Stammdokument, am Original sich selbst. `createIfMissing: true` liefert bei fehlender, erlaubter neuer Polyglot-Variante ein ungespeichertes Document; Legacy lehnt Neuanlage ab.

`getHeaderDefinitions()` liefert FieldSet/FieldDefinition für bekannte Header-Einträge; zusätzliche manuelle Metadaten bleiben erlaubt. Headerwerte stehen direkt im Array. Die kleinen DTOs verwenden readonly-Eigenschaften und können mit Phore Schema validiert werden. Das Schiller-Grundgerüst verlangt PHP >=8.3, während der alte Page Builder PHP 8.1 nutzt; eine Einbindung benötigt daher eine explizite Laufzeit-/Paketentscheidung. Documents werden von Schiller mit Adapter-/Storage-Verbindung verwaltet. Der kontrollierte Transport über toArray/restoreDocument ersetzt keine Rechte- oder Speicherprüfung; beliebige Requestobjekte werden nicht als Documents hydratisiert. [geändert]

## § 11 Filesystem und austauschbare Formatadapter

SchillerDir und Document bilden die allgemeine Zugriffsschicht. Der Formatadapter übernimmt load/create, gemeinsames write, Quellpfade, vorhandenen Baum, URL-Zuordnung und strukturelle Dateioperationen. Übersetzungsauswahl, verfügbare Sprachen und Originalkopien orchestriert Schiller gemeinsam. Die Anwendung braucht keine zusätzliche Übersetzungsschicht vor Schiller. SiteStorage bleibt für rootgebundene Dateioperationen verantwortlich; der Adapter erhält einen kontrollierten Zugang, keine Möglichkeit zur Umgehung von Rechteprüfungen. [geändert]

PhoreDirectory und PhoreFile stellen Enumeration, Text, YAML und Front Matter bereit: genWalk(), get_contents(), get_yaml(), get_front_matter(), put_front_matter(). Die Header-Arrays werden direkt übernommen; kein zweiter YAML-Parser. Schiller prüft alle geplanten Quell-/Zielzugriffe, auch die vom Adapter intern berechneten. Wiederverwendbare Abläufe dürfen intern in einer AbstractAdapter-Basis liegen.

```yaml
schema_version: 1
adapter: {id: jekyll-polyglot, version: 1}
```

```yaml
schema_version: 1
adapter: {id: micx-legacy, version: 1}
```

Adapter-Versionen sind registrierte Schiller-Verträge, keine Gem-Versionen. Keine dynamischen PHP-Klassennamen aus YAML, keine automatische Migration bei Auswahlwechsel. Ohne Auswahl gilt das zur Plugin-Konfiguration passende registrierte Jekyll-Profil. Legacy wird explizit gewählt.

### § 11.1 Konkreter Adapterentwurf: gleiche IDs, andere Dateien

Das [Adapter-Interface](../../examples/Adapter.php) und die Beispiele [LegacyAdapter](../../examples/14-legacy-adapter.php)/[PolyglotAdapter](../../examples/15-polyglot-adapter.php) enthalten dokumentierte Methodenstümpfe. Sie werfen absichtlich LogicException. Der gemeinsame Kontext enthält Konfiguration, kontrollierten Storage, Rechteprüfung und Document-Verwaltung; die Klassen sind keine fertige Implementierung.

| Seiten-ID | Legacy de | Polyglot de | Polyglot en |
|---|---|---|---|
| `/` | vorhandene, im Legacy-Profil eindeutig belegte Root-Seite | `index.md` | `en/index.md` |
| `/leistungen` | `leistungen/index.de.md` oder `leistungen.de.md` | `leistungen.md` oder `leistungen/index.md` | spiegelbildlich `en/leistungen.md` oder `en/leistungen/index.md` |
| `/leistungen/diagnostik` | `leistungen/diagnostik.de.md` | `leistungen/diagnostik.md` | `en/leistungen/diagnostik.md` |

Die Legacy-ID wird aus dem tatsächlichen Bestand und dem PID-/Sprachsuffix abgeleitet; eine vorhandene index-Seite fällt auf die Kategorie-ID zusammen. Mehrdeutige Zuordnungen werden diagnostiziert, nicht geraten. `_section.yml` kann reine Kategorien samt optionalen Metadaten erzeugen. Das Root darf eine Kategorie ohne eigene Seite sein; eine beliebige home-Datei wird nicht still zur Root-Seite erklärt.

Legacy erlaubt das Bearbeiten vorhandener Seiten und Übersetzungen einschließlich eigener Header-Metadaten. Es legt keine Seiten, Sprachdateien oder Ordner an und führt kein Rename/Delete aus. PID/lang bleiben beim Schreiben vorhanden und müssen zur Quelldatei passen. Polyglot leitet Sprache ausschließlich aus den gespiegelten Pfaden ab und verwaltet alle Neuanlagen und Umstrukturierungen intern.

Das Adapter-Interface enthält genau einen Schreibauftrag `write(array $documents): void`. Es gibt weder writeMany noch Revisionsargumente. Jeder Adapter entscheidet selbst über seinen freien adapterState und prüft alle aufgeführten Documents gegen den aktuellen Speicherstand. Die gemeinsame Schiller-Schicht verwaltet Instanzen, Root-Beziehungen, Übersetzungsauswahl und Originalkopien; der Adapter stellt load/create mit expliziter Sprache, getSourcePath und den vorhandenen Baum bereit. [geändert]

Der kontrollierte Storage muss Lesen, Existenz-/Inventarprüfung, Root-Grenzen, Rechte und bedingtes gemeinsames Schreiben ermöglichen. PhoreDirectory ist der Standardzugang; SiteStorage bleibt der vorgeschlagene Connector-Vertrag und benötigt bei der Implementierung noch konkrete Methodensignaturen und Fehlergarantien. Es wird hier kein bereits vorhandener Remote-Connector behauptet. Adapter-Stümpfe binden den Storage sichtbar im Konstruktor, überspringen aber keine dieser Prüfungen. [neu]

### § 11.2 Neue Unterseite: Blatt wird Kategorie mit Index

```php
$parent = $site->getPage('/leistungen'); // file.path='leistungen.md'
$englishParent = $parent->getTranslation('en'); // 'en/leistungen.md'
$child = $site->createPage('/leistungen/allgemeinmedizin',
    header: ['title' => 'Allgemeinmedizin'],
    content: "## Allgemeinmedizin\n",
);
// Keine Dateiänderung; child.file=null, child.isPersisted()=false.
$child->save();
// parent.id bleibt '/leistungen'; parent.file.path='leistungen/index.md'
// englishParent.file.path='en/leistungen/index.md'
// child.id='/leistungen/allgemeinmedizin'; file.path='leistungen/allgemeinmedizin.md'
```

Eine neue Blattseite erhält im Polyglot-Adapter eine md-Datei. Sobald sie eine Unterseite bekommt, wird die bisherige Datei zur index.md im gleichnamigen Verzeichnis. Eine vorhandene HTML-Seite wird entsprechend zu index.html, ohne Konvertierung des Inhalts. Bereits vorhandene Indexablagen bleiben bestehen. Neue Unterseiten können wiederum später zu Kategorien werden. Root wird immer als Index behandelt.

Beim Speichern prüft der Adapter alle nötigen Vorfahren. Existierende Elternseiten werden über sämtliche tatsächlich vorhandenen Sprachen auf dieselbe relative Indexablage umgestellt; fehlende Übersetzungen bleiben fehlend. Auch das Speichern einer ersten Übersetzung nach der Umstellung verwendet den Indexpfad. Fehlende reine Ordner werden bei createDirectory-Recht angelegt, ohne Eltern-Dokumente zu erfinden. Alle Metadaten und Inhalte der verschobenen Eltern bleiben erhalten, einschließlich published und Permalink.

Die Umstellung und die neue Datei bilden eine gemeinsame vorbereitete Speicheroperation: alle Quellen, Zielkonflikte, Vorfahren, Rechte und Storage-Fähigkeiten werden vorab geprüft. Ein Konflikt, eine verborgene betroffene Datei ohne Berechtigung oder ungespeicherte Änderungen an einer betroffenen Elternseite verhindern jede Änderung. Die bereits definierten Batch-/Rollback-Regeln gelten. Eine erfolgreiche Operation aktualisiert geladene FileEntry-Referenzen und den URL-Index.

Das gleiche Prinzip gilt beim Verschieben einer bestehenden Seite oder Kategorie unter eine Blattseite. Sämtliche Nachfahren, Begleitdateien und vorhandenen Übersetzungen werden mit verschoben; Details und verpflichtende spätere Tests stehen in [docs/verschieben.md](../verschieben.md). Die IDs der verschobenen Quellen ändern sich entsprechend dem neuen Präfix, die ID einer lediglich zur Indexablage umgestellten Ziel-Elternseite bleibt unverändert.

## § 12 Fehler, Grenzen und spätere Abnahme

Konfigurationsfehler verhindern einen konsistenten Einstieg. Fehler einzelner lesbarer Dateien erscheinen als `Diagnostic` mit Code, relativem Pfad und Meldung im Listing; andere gültige Seiten bleiben sichtbar. Nicht lesbare Dateien erzeugen keine sichtbaren Diagnosen. Direkte Operationen werfen typisierte Exceptions: `ConfigurationException`, `NotFoundException`, `AccessDeniedException`, `FieldTypeException`, `ValidationException`, `AlreadyExistsException`, `ConflictException`, `UnsavedChangesException`, `UnsupportedOperationException`, `StorageException` oder `UrlNotResolvableException`.

Die spätere Implementierung muss insbesondere diese Verhaltensfälle prüfen:

- Gemeinsame Übersetzungslogik mit beiden Adaptern; create bekommt explizite Sprache und frischen eigenen Zustand.
- Fehlender/manipulierter/fremder Transportzustand darf nie Konfliktprüfungen umgehen; Kopien übernehmen keinen Speicherzustand des Originals.
- Derselbe Translation-Entwurf mit und ohne createIfMissing; getPage liefert dieselbe Instanz, Listings bleiben exists=false.
- Dokumenttransport aus GET/POST erhält den adapterState unverändert; der Adapter erkennt inzwischen geänderte Quellen ohne Revisionsparameter.
- Gemeinsamer Permalink-Wechsel: Endzustand statt einzelner Zwischenschritte validieren, Teilfehler vollständig zurücknehmen.
- Eine reine Kategorie mit createPage um ihre Indexseite ergänzen; keine Überschreibung oder Kinderverluste.
- Headerdefinitionen vor Neuanlage, Legacy-Formtypen und Sprachnamen ohne zusätzlichen Formatzugriffs-Layer.
- Übersetzungsindex einer Kategorie löschen erhält Kinder; Root-Löschung mit verborgenen Nachfahren wird abgewiesen.
- No-op, neue Entwürfe, Abbruch, externe Änderung und URL-Fallback mit tatsächlicher Zielsprache.

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

Für die spätere Implementierung sind die Szenarien aus [Verschieben und Testplan](../verschieben.md) verbindlich: Blattpromotion, Teilbaum-Rename, Verschieben unter ein Blatt, Sprachvarianten, Metadatenerhaltung und Fehlerwiederherstellung. Beispiel 16 zeigt Anlage, Beispiel 17 das Verschieben eines Teilbaums.

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

Empfohlene Reihenfolge: zunächst Root/Konfiguration, Rechte und Phore-Lesen; danach Seitenbaum, Polyglot und URL-Resolver; anschließend Dokumentspeicherung, Übersetzungsanlage und Gruppenmutationen. Legacy-Lesen und Bearbeiten vorhandener Seiten lässt sich separat ergänzen. Daten-/Collection-/Template-Editoren bleiben ein eigener Folgeentwurf. Der vorliegende PR entscheidet noch keine Implementierung und verändert keine Referenzwebsite.
