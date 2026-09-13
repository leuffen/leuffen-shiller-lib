# Seiten lesen, bearbeiten und übersetzen

## Eine vorhandene Übersetzung bearbeiten

Schiller öffnet ein extern bereitgestelltes Website-Quellverzeichnis. Dieser typische Ablauf liest eine Seite, bearbeitet ihre vorhandene englische Übersetzung und liefert deren Ziel-URL:

```php
$site = new SchillerDir(
    phore_dir('/srv/site/docs'),
    access: new AccessContext(role: 'user'),
);
$page = $site->getPage('/leistungen/diagnostik');
$english = $page->getTranslation('en');
$english->header['title'] = 'Our diagnostics';
$english->save();
echo $english->getUrl(); // /en/leistungen/diagnostik.html
```

SchillerDir ist der Einstieg, Document die bearbeitbare Seite, header das YAML-Array und content der Body. Die Seiten-ID enthält weder Sprache noch Dateiendung; ein optionales FileEntry zeigt die tatsächliche Quelle. Sprache und Speicherzustand gehören zum Document. Der Adapter übernimmt die Ablage; eine eigene Revisionsprüfung ist erst für später vorgesehen. [00-read-edit-save.php](00-read-edit-save.php) enthält den Einstieg als PHP-Ausschnitt.

## Darstellungsform und gemeinsamer Kontext

Die [API](../docs/proposals/2026-09-12-schiller-seiten-api.md) ist ein Vorschlag, noch keine implementierte Library. Die PHP-Dateien sind lesbare Anwendungsausschnitte mit erwarteten Ergebnissen; sie werden nicht als nacheinander auszuführende Skripte oder Demo-Closures eingebunden. 14/15 sind ausdrücklich Interface-Implementierungsskizzen mit werfenden Methodenstümpfen für Framework-Entwickler.

Einmaliger Namenskontext für die Anwendungsausschnitte:

```php
use Leuffen\Schiller\AccessContext;
use Leuffen\Schiller\Adapter\JekyllLegacyAdapter;
use Leuffen\Schiller\Adapter\JekyllPolyglotAdapter;
use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\UrlNotResolvableException;
```

Die Imports und Autoloading würden bei der Übernahme in eine Anwendung in deren PHP-Datei stehen; die Ausschnitte wiederholen sie nicht. Composer-Anbindung und SiteStorage sind noch Entwurfsbestandteile. Das vorhandene Repository-Grundgerüst verlangt PHP >=8.3 und hat noch nicht Schillers Namespace; die Beispiele behaupten keine bereits installierbare API.

Das Quellverzeichnis `/srv/site/docs` ist eine bereits von der Anwendung bereitgestellte Arbeitskopie. `$root` ist das in 01 geöffnete PhoreDirectory, `$site` je nach Kennzeichnung dessen lesender oder schreibender SchillerDir. Jedes Schreibbeispiel beginnt fachlich auf einer frischen Basisfixture; Ergebnisse aus 08–19 sind keine stillen Voraussetzungen späterer Beispiele. 16/17 ersetzen die Basisfixture ausdrücklich durch ihre angegebenen Ausgangsdateien. Rollen kommen ausschließlich aus der authentifizierten Anwendung.

## Gemeinsame Ausgangsdaten

`docs/_config.yml`:

```yaml
url: https://example.org
plugins: [jekyll-polyglot]
languages: [de, en, fr]
default_lang: de
exclude: [schiller.yaml]
defaults:
  - scope: {path: "", type: pages}
    values: {layout: default, lang: de}
  - scope: {path: en, type: pages}
    values: {lang: en}
  - scope: {path: fr, type: pages}
    values: {lang: fr}
```

`docs/schiller.yaml` für diese Beispiel-Arbeitskopie:

```yaml
schema_version: 1
adapter: {id: jekyll-polyglot, version: 1}
fields:
  title: {type: string, label: Seitentitel, required: true}
  short_title: {type: string, label: Kurztitel, max_length: 60}
  published: {type: boolean, label: Veröffentlicht, default: false}
  layout:
    type: select
    label: Layout
    options:
      - {value: default, label: Standard}
      - {value: landing, label: Landingpage}
presets:
  standard: [title, short_title, published, layout]
scopes:
  - {path: "**", presets: [standard]}
permissions:
  default: deny
  roles:
    reader:
      allow:
        - {path: "**", actions: [read]}
      deny:
        - {path: "schiller.yaml", actions: [read]}
        - {path: "_config.yml", actions: [read]}
    user:
      allow:
        - {path: "**", actions: [read, write, createFile, createDirectory, createTranslation, rename, delete]}
      deny:
        - {path: "schiller.yaml", actions: [read, write]}
        - {path: "_config.yml", actions: [read, write]}
    admin:
      allow:
        - {path: "**", actions: [read, write, createFile, createDirectory, createTranslation, rename, delete]}
```

Diese gemeinsame Fixture erlaubt die gezeigten Bearbeitungen; die Adaptergrenzen und eine zusätzliche Host-Policy gelten weiterhin. Ohne AccessContext wird reader verwendet. Ohne explizite Schreibfreigabe macht ein Methodenname wie createPage keinen Zugriff schreibbar. Engere Bereiche und zusätzliche Feldtypen zeigt Proposal § 7.

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

Die Basisfixture enthält keine französische Übersetzung und keine leistungen/index.md. Deshalb ist /leistungen zunächst eine reine Kategorie mit einer Unterseite. Kein einzelner Header enthält PID, lang oder einen Permalink. Die Startseite wäre index.md direkt im Root; Sprachvarianten spiegeln diesen Pfad unter en/ beziehungsweise fr/.

## Lesereihe

| Datei | Neue Leserfrage |
|---|---|
| [00-read-edit-save.php](00-read-edit-save.php) | Wie sieht ein vollständiger typischer Bearbeitungsablauf aus? |
| [01-initialize.php](01-initialize.php) | Wie öffne ich das Root und wechsle Zugriffskontext oder Connector? |
| [02-read-config.php](02-read-config.php) | Welche Site-Konfiguration und Sprachen wurden erkannt? |
| [03-list-files.php](03-list-files.php) | Welche tatsächlichen Dateien gibt es? |
| [04-list-pages.php](04-list-pages.php) | Wie unterscheide ich Kategorien, Seiten und Sprachzustände im Baum? |
| [05-read-page-parts.php](05-read-page-parts.php) | Welche Werte sind gespeichert und welche nur geerbt? |
| [06-read-fields-and-permissions.php](06-read-fields-and-permissions.php) | Wie baue ich ein Formular auf und erkenne erlaubte Aktionen? |
| [07-read-translations.php](07-read-translations.php) | Welche Übersetzungen existieren und wie komme ich zum Original? |
| [08-create-translation.php](08-create-translation.php) | Wie bereite ich eine fehlende Übersetzung vor und speichere sie? |
| [09-update-page-parts.php](09-update-page-parts.php) | Wie ändere oder entferne ich einzelne Headerwerte und den Body? |
| [10-create-page.php](10-create-page.php) | Wie lege ich eine Seite oder eine Kategorieseite an? |
| [11-resolve-urls.php](11-resolve-urls.php) | Wie komme ich vom Document zur URL und zurück, auch bei Fehlern? |
| [12-rename-page.php](12-rename-page.php) | Wie verschiebe ich eine Seite einschließlich ihrer Sprachen? |
| [13-delete-page.php](13-delete-page.php) | Wie lösche ich eine Sprachdatei oder eine Blattgruppe? |
| [14-legacy-adapter.php](14-legacy-adapter.php) | Wie implementiert ein Adapter den bisherigen Bestand? |
| [15-polyglot-adapter.php](15-polyglot-adapter.php) | Welche Ablageregeln setzt der neue Adapter um? |
| [16-create-child-page.php](16-create-child-page.php) | Was geschieht beim ersten Kind einer Blattseite? |
| [17-move-page-tree.php](17-move-page-tree.php) | Was geschieht beim Verschieben eines Teilbaums unter ein Blatt? |
| [18-editor-save.php](18-editor-save.php) | Wie überlebt der Dokumentstand getrennte HTTP-Anfragen? |
| [19-save-language-group.php](19-save-language-group.php) | Wie ändere ich den Permalink einer Sprachgruppe gemeinsam? |

## Zuständigkeiten und Grenzen

Mitgeliefert werden JekyllPolyglotAdapter und JekyllLegacyAdapter. SchillerDir wählt zuerst den expliziten adapter-Konstruktorparameter, sonst die YAML-Auswahl und sonst JekyllPolyglotAdapter. Die Instanzen werden ohne Argumente erzeugt und intern einmalig über bind(SiteStorage) angebunden; Beispiel 01 zeigt die Auswahl.

Revisionskonflikte werden im ersten Ausbau nicht durch Schiller behandelt. Versionsverwaltung und Zusammenführung liegen beim externen Git-/Anwendungsablauf. ConflictException bleibt als Typ für eine spätere Erweiterung vorgesehen; fehlende Revisionsunterstützung blockiert die normalen Adapter nicht. Rechte, Neuanlage-Kollisionen, Validierung und die Wiederherstellung bei fehlgeschlagenen Gruppenoperationen bleiben erforderlich.

Document.save und SchillerDir.saveDocuments führen beide zum selben Adapterauftrag write(list<Document>). Der Adapter kann seinen freien adapterState selbst verwenden; die beiden mitgelieferten Adapter dürfen ihn zunächst leer lassen. Es gibt keine Revisionsparameter und kein Schema für adapterinterne Schlüssel. Das kontrollierte toArray/restoreDocument transportiert den Bearbeitungsstand im Webeditor; Headeränderungen brauchen weiterhin keine Patch-Objekte.

Document.getTranslation/getTranslations bleiben öffentliche Komfortmethoden. Ohne Argument, mit null oder mit der konfigurierten Standardsprache liefert getTranslation dasselbe Stammdokument; bei default_lang: de sind getTranslation(null) und getTranslation('de') gleichbedeutend. Am Original wird dieselbe Instanz zurückgegeben. createIfMissing erzeugt bei diesen Aufrufen kein neues Original. Schiller verwaltet Instanzen, konfigurierten Sprachumfang und Originalkopien. Adapter liefern vorhandene Quellen, laden/bereiten eine explizite Sprache vor und bestimmen auch fehlende Quellpfade. Das [Interface](Adapter.php) enthält deshalb keine zusätzlichen Übersetzungsmethoden.

Legacy bearbeitet ausschließlich vorhandene Dateien. Polyglot unterstützt auch Anlage, Elternpromotion und Teilbaumoperationen; fehlende Übersetzungen werden bei Bewegungen nicht erzeugt. Die [Verschiebelogik](../docs/verschieben.md) beschreibt vollständige Reichweite und spätere Testfälle. Der [Page-Builder-Abgleich](../docs/pagebuilder-abgleich.md) trennt unterstützte Seitenabläufe von nötigen UI-Anpassungen und späteren Dateneditoren.
