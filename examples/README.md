# Einzelne PHP-Beispiele zum Schiller-Entwurf

Diese Dateien zeigen die vorgeschlagene API aus dem [Proposal](../docs/proposals/2026-09-12-schiller-seiten-api.md). Die Schiller-Klassen sind noch nicht implementiert. Die Beispiele sind keine Integrationstests; 14 und 15 zeigen Adapterklassen mit Methodenstümpfen.

Die PHP-Dateien 01–13 liefern jeweils eine typisierte Closure zurück. Sie bekommt ein `PhoreDirectory` und initialisiert ihre eigene `SchillerDir`-Instanz. Es gibt keinen versteckten gemeinsamen Bootstrap, kein Git und keine Netzwerkverbindung.

Nach Implementierung und Einrichtung des Composer-Autoloaders wäre der Aufruf zum Beispiel:

```php
require '/path/to/application/vendor/autoload.php';

$example = require '/path/to/leuffen-shiller-lib/examples/07-read-translations.php';
$translations = $example(phore_dir('/path/to/working-copy/docs'));
```

Das übergebene Root enthält die Standardsprache: `index.md`, `leistungen/diagnostik.md` und die Unterverzeichnisse der Website. Übersetzungen liegen ausschließlich unter `en/index.md`, `en/leistungen/diagnostik.md` usw. Die identischen relativen Dateipfade bilden die Gruppen; alle Dateien kommen ohne `page_id` und `lang` im Header aus.

Minimale zentrale Jekyll-Konfiguration für zwei Sprachen:

```yaml
# docs/_config.yml
plugins: [jekyll-polyglot]
languages: [de, en]
default_lang: de
exclude: [schiller.yaml]
defaults:
  - scope: {path: "", type: pages}
    values: {lang: de}
  - scope: {path: en, type: pages}
    values: {lang: en}
```

Die Sprache wird zentral aus dem Verzeichnis zugewiesen. Die Defaults sorgen auch für die Normalisierung natürlicher URLs im geprüften Polyglot-Code; einzelne Dateien brauchen keine Sprachangabe. `lang_from_path` ist bei diesen Defaults nicht zusätzlich nötig. `url: https://example.org` ergänzt bei Bedarf absolute URLs; ohne Domain funktioniert die pfadbasierte Schiller-Auflösung. `baseurl` bleibt für die normale Root-Website weg.

Minimale Adapterauswahl (Felder und Rechte siehe Proposal § 7):

```yaml
# docs/schiller.yaml
schema_version: 1
adapter: {id: jekyll-polyglot, version: 1}
```

Die Beispielseiten aus Proposal § 6 haben normale Header mit Titel und Inhalt, ohne Permalink. `index.md` ergibt `/`, `leistungen/index.md` ergibt `/leistungen/`, `leistungen/diagnostik.md` ergibt `/leistungen/diagnostik.html`. Ein optionaler Ausnahme-Permalink muss innerhalb einer Sprachgruppe dieselbe unlokalisierte Route ergeben; er dient Schiller nicht als Gruppen-ID.

Für die erwarteten Rückgaben der Beispiele 01–13 wird die größere Fixture aus Proposal §§ 6–7 verwendet: `url: https://example.org`, zusätzliche Sprache `fr` samt zentralem Verzeichnis-Default und vorhandener leerer Ordner `fr/leistungen/`. Außerdem gehören Felddefinitionen, Layout-Default und Projektberechtigungen aus § 7 dazu. Diese Voraussetzungen werden durch die PHP-Beispiele nicht automatisch angelegt. Die Minimalkonfiguration oben demonstriert nur die Verzeichniszuordnung, keine Schreibfreigabe.

Jedes Beispiel betrachtet eine frische Kopie dieses Ausgangsstands. Insbesondere nach dem Anlegen einer französischen Übersetzung sind die ursprünglichen Verfügbarkeits- und Fallback-Rückgaben nicht mehr dieselben. Schreibbeispiele verändern eine übergebene Arbeitskopie und benötigen die im Proposal beschriebenen Projekt-/Hostrechte; die Rolle `user` wird als bereits serverseitig authentifiziert angenommen.

| Datei | Anwendungsfall |
|---|---|
| [01-initialize.php](01-initialize.php) | Verzeichnis anbinden, automatisch initialisieren, Rolle setzen |
| [02-read-config.php](02-read-config.php) | Aktuelle Website-Konfiguration lesen |
| [03-list-files.php](03-list-files.php) | Physische Dateien und Unterordner auflisten |
| [04-list-pages.php](04-list-pages.php) | Logischen Seitenbaum mit Sprachgruppen lesen |
| [05-read-page-parts.php](05-read-page-parts.php) | Header, wirksame Defaults und Body getrennt lesen |
| [06-read-fields-and-permissions.php](06-read-fields-and-permissions.php) | Metafelder, Dropdowns und erlaubte Aktionen lesen |
| [07-read-translations.php](07-read-translations.php) | Sprachvarianten finden und deren Inhalt lesen |
| [08-create-translation.php](08-create-translation.php) | Französische Übersetzungsdatei anlegen |
| [09-update-page-parts.php](09-update-page-parts.php) | Header oder Body ändern, Feld entfernen |
| [10-create-page.php](10-create-page.php) | Neue normale Seite anlegen |
| [11-resolve-urls.php](11-resolve-urls.php) | Document zu URL und URL direkt zu Document, Fehlerhinweise |
| [12-rename-page.php](12-rename-page.php) | Stammdokument samt Übersetzungen umbenennen |
| [13-delete-page.php](13-delete-page.php) | Einzelne Übersetzung oder vollständige Gruppe löschen |

Die Kommentare zeigen erwartete Rückgabewerte beziehungsweise klar bezeichnete Projektionen. Enum-Werte werden überwiegend über `->value` beschrieben, um keine zusätzlichen Case-Namen festzulegen. Referenzen auf `YamlValue` sind PHPDoc-Konzepte aus dem Entwurf, keine nativen PHP-Klassen.

Der bestehende Composer-Namespace ist noch ein Template-Platzhalter. Die Beispiele ändern ihn nicht. Sie lassen sich später unabhängig aufrufen; zum derzeitigen Stand ist nur statische Prüfung möglich.

Alle Seiten sind `Document`-Objekte. `header` ist ein Array, `content` ein String; `save()` schreibt Änderungen. `createPage()` und `getTranslation('en', createIfMissing: true)` erzeugen zunächst ungespeicherte Dokumente. `getTranslations()` liefert pro lesbarer Sprache `TranslationInfo` mit `exists`. `getTranslation()` verweist auf das Original, beim Original auf sich selbst.

`getDocumentByUrl()` liefert das tatsächliche Quelldokument oder wirft `UrlNotResolvableException` mit bereinigten Diagnosefeldern und passenden lesbaren URL-Vorschlägen. Host, Schema, Port, Zugangsdaten, Query und Fragment beeinflussen den lokalen Treffer nicht. `getUrl()` braucht keine Sprache; `getUrl(absolute: true)` verwendet die konfigurierte Domain.

Rename und Delete schreiben sofort. Beispiel 12 benötigt zusätzlich vorhandene Ordner `medizin/` und `en/medizin/`. Beide Gruppenbeispiele verwenden die serverseitig vergebene Rolle `admin`; die Host-Policy und der Connector müssen diese Aktionen ebenfalls erlauben. Sie sind unabhängig auf frischen Arbeitskopien zu betrachten.

Die Listings verwenden gemeinsam `TreeNode` mit `children`, `isLeaf()`, optionaler `FileEntry`-Referenz `file` und `getDocument()`. Im Seitenbaum gehört eine Indexseite direkt zu ihrem Ordnerknoten; eine Kategorie kann somit eigene Seite und Kinder haben. Ohne Index bleibt die Kategorie ohne Dokument. Der physische Dateibaum führt Ordner und Indexdatei separat auf. Jeder Seitenknoten listet alle konfigurierten lesbaren Sprachen mit `exists`, einschließlich fehlender Übersetzungen. Beispiel 04 funktioniert mit der Basisfixture ohne Kategorie-Index und erläutert zusätzlich den Fall mit Indexseite.

Adapterzuordnung als konkrete PHP-Entwürfe: [14-legacy-adapter.php](14-legacy-adapter.php) und [15-polyglot-adapter.php](15-polyglot-adapter.php). Legacy liest Sprachsuffix und PID-/Sprachheader; Polyglot liest ausschließlich gespiegelte Sprachpfade.

Das gemeinsame [Adapter.php](Adapter.php) definiert die Signaturen für beide Beispielklassen. 14 und 15 implementieren dieses Interface ausschließlich mit dokumentierten Methodenstümpfen; jeder Aufruf wirft absichtlich eine LogicException. Keine fertige Formatimplementierung. Die Beispiele verwenden einen eigenen Examples-Namespace und ändern kein Composer-Autoloading.

`getTranslation()` beziehungsweise `getTranslation(null)` liefert das Stammdokument; `getTranslation('en', createIfMissing: true)` bereitet bei fehlender Übersetzung eine ungespeicherte Originalkopie vor. `isPersisted()` prüft den Dateibestand, nicht ob lokale Änderungen gespeichert sind. Die Verfügbarkeitslisten behalten `exists`. `getHeaderDefinitions()` liefert Definitionen der Header-Einträge, die Werte stehen direkt in `header`.

```php
require_once __DIR__ . '/14-legacy-adapter.php';
require_once __DIR__ . '/15-polyglot-adapter.php';
$legacy = new \Leuffen\Schiller\Examples\LegacyAdapter();
$polyglot = new \Leuffen\Schiller\Examples\PolyglotAdapter();
// Beide implementieren Examples\Adapter; Methodenaufrufe sind noch nicht implementiert.
```
