# PHP-Beispiele zum Schiller-Entwurf

Die [API](../docs/proposals/2026-09-12-schiller-seiten-api.md) ist noch nicht implementiert. Die Dateien zeigen Aufrufe und erwartete Rückgaben, keine erfolgreichen Integrationstests. Adapterklassen sind ausschließlich dokumentierte Methodenstümpfe.

Die Anwendung verwendet endungslose, sprachneutrale Seiten-IDs: `/`, `/leistungen`, `/leistungen/diagnostik`. `getPage('/leistungen')` funktioniert unabhängig davon, ob der Adapter eine Legacy-Datei, eine Blattdatei oder eine Indexdatei lädt. Physische Pfade stehen nur in optionalen FileEntry-Referenzen und im technischen Dateilisting.

```php
require '/path/to/application/vendor/autoload.php';
$example = require '/path/to/leuffen-shiller-lib/examples/07-read-translations.php';
$translations = $example(phore_dir('/path/to/working-copy/docs'));
```

Die Beispiele 01–13 sowie 16–19 liefern jeweils eine Closure für PhoreDirectory. Beispiele 14/15 definieren Klassen mit dem gemeinsamen [Adapter-Interface](Adapter.php). Beispiel 18 nimmt zusätzlich ein optionales Formular-Array entgegen. Jeder funktionale Methodenstumpf wirft absichtlich LogicException. SiteStorage, DTOs und Composer-Anbindung bleiben Teil des Entwurfs; kein VCS oder Netzwerkzugriff.

## Konfiguration und Basisfixture

```yaml
# docs/_config.yml
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

```yaml
# docs/schiller.yaml; Felder und Rechte im Proposal § 7
schema_version: 1
adapter: {id: jekyll-polyglot, version: 1}
```

Die Basisfixture enthält leistungen/diagnostik.md und en/leistungen/diagnostik.md mit Titel und published=true; fr fehlt. Keine IDs, Sprache oder Permalinks im einzelnen Header. Die Kategorie /leistungen hat in dieser Fixture keine eigene Seite. Die vollständigen Headerdefinitionen und Pfadrechte stehen in Proposal §§ 6–7. Die Minimalauswahl allein erteilt keine Schreibrechte.

Jedes Schreibbeispiel betrachtet eine frische Arbeitskopie. Beispiele 16/17 haben ausdrücklich eigene Fixtures. Die Anwendung vergibt Rollen serverseitig; der Host und Storage müssen erforderliche Aktionen ebenfalls erlauben.

| Datei | Aufgabe |
|---|---|
| [01-initialize.php](01-initialize.php) | Verzeichnis/Connector initialisieren |
| [02-read-config.php](02-read-config.php) | Konfiguration lesen |
| [03-list-files.php](03-list-files.php) | Physische Inspektion mit Dateipfaden |
| [04-list-pages.php](04-list-pages.php) | Baum mit IDs, optionalen Dateien und allen Sprachzuständen |
| [05-read-page-parts.php](05-read-page-parts.php) | Header/Body lesen |
| [06-read-fields-and-permissions.php](06-read-fields-and-permissions.php) | Headerdefinitionen und Rechte |
| [07-read-translations.php](07-read-translations.php) | Varianten und Root über getTranslation(null) |
| [08-create-translation.php](08-create-translation.php) | Originalkopie vorbereiten und speichern |
| [09-update-page-parts.php](09-update-page-parts.php) | Bekannte und eigene Metadaten bearbeiten |
| [10-create-page.php](10-create-page.php) | Neue Seite anhand ihrer ID |
| [11-resolve-urls.php](11-resolve-urls.php) | URL zum Document mit ID/Sprache |
| [12-rename-page.php](12-rename-page.php) | Blattgruppe umbenennen/verschieben |
| [13-delete-page.php](13-delete-page.php) | Blattgruppe oder einzelne Übersetzung löschen |
| [14-legacy-adapter.php](14-legacy-adapter.php) | Vorhandene Legacy-Dateien laden und bearbeiten |
| [15-polyglot-adapter.php](15-polyglot-adapter.php) | Neue Ablage, Schreiben und Teilbaumoperationen |
| [16-create-child-page.php](16-create-child-page.php) | Blattseite wird beim ersten Kind zur Indexseite |
| [17-move-page-tree.php](17-move-page-tree.php) | Teilbaum unter eine Blattseite verschieben |
| [18-editor-save.php](18-editor-save.php) | GET/POST mit Formularrevision, gezielten Headeränderungen und Konflikt |
| [19-save-language-group.php](19-save-language-group.php) | Permalink einer Sprachgruppe ausdrücklich gemeinsam speichern |

## Verhalten

Document.id bleibt bei einer internen Umstellung auf Indexablage gleich. file ist bei Entwürfen null, nach dem Speichern eine FileEntry. isPersisted() beschreibt den bestätigten Speicherzustand, nicht ungespeicherte Änderungen. hasChanges() zeigt lokale Änderungen; revision schützt mit save(expectedRevision) vor veralteten Formularständen. Header ist ein Array; auch zusätzliche manuelle Werte bleiben erhalten und werden in neue Übersetzungen kopiert. getHeaderDefinitions() beschreibt bekannte Schlüssel und optionale Darstellungshinweise; am SchillerDir auch vor Neuanlage mit ID/Sprache abfragbar.

getTranslation() oder null liefert das Stammdokument. createIfMissing:true liefert im Polyglot-Profil bei fehlender Sprache eine ungespeicherte Originalkopie mit published=false. Vorhandene Varianten bleiben unangetastet. Ein bereits vorbereiteter Entwurf wird auch ohne createIfMissing erneut zurückgegeben, bleibt im Listing aber exists=false. Die Listen zeigen alle lesbaren Sprachen mit exists, auch fehlende. Eine Kategorie ohne Seite hat file=null und kann ausschließlich ihre Kinder öffnen.

Beim ersten Kind beziehungsweise beim Verschieben unter eine Blattseite stellt Polyglot deren bestehende Sprachdateien gemeinsam auf index.md/.html um. Rename einer Kategorie bewegt den gesamten Teilbaum einschließlich Begleitdateien und Übersetzungen. Neue Elternordner werden nach Rechteprüfung angelegt. Natürliche URLs können sich dabei ändern; IDs und explizite Permalinks werden nur nach ihrem jeweiligen Vertrag geändert. Ausführliche [Verschiebelogik und spätere Tests](../docs/verschieben.md).

Legacy unterstützt nur das Bearbeiten vorhandener Seiten/Sprachdateien; keine Anlage, kein Rename/Delete, keine Indexumstellung. Der Adapter bildet IDs intern auf die vorhandenen pid.lang.md/html-Dateien ab. Die Formatabbildung liegt vollständig im Adapter. Die alte Controller-/UI-Struktur muss dennoch auf die Document-/TreeNode-Verträge umgestellt werden; der [Page-Builder-Abgleich](../docs/pagebuilder-abgleich.md) nennt jeden Ablauf und die noch ausgeschlossenen Dateneditoren.

save() speichert ein Document, saveDocuments([...]) ausdrücklich mehrere als gemeinsamen Endzustand. rename/delete schreiben dagegen sofort und speichern keine lokalen Bearbeitungen mit. delete einer Übersetzung entfernt nur deren Datei, auch bei einem Kategorieindex; delete am Original ist nur ohne Nachfahren in sämtlichen Sprachen erlaubt. Die Root-Gruppe / ist geschützt. Baum und Editorrevisionen nach Strukturänderungen erneut laden.

Eine reine Kategorie kann im Polyglot-Profil eine eigene Seite erhalten: createPage('/leistungen', header: ['title' => 'Leistungen'])->save() legt in der Basisfixture leistungen/index.md an; bestehende Kinder bleiben erhalten. Bereits vorhandene Seitengruppen werden nicht überschrieben. TranslationInfo.published zeigt den effektiven Veröffentlichungsstatus ohne Body-Laden; bei exists=false ist published=null.
