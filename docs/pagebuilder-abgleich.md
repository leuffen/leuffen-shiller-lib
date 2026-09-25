# Review: Schiller-API und Page Builder

Stand: 2026-09-25. Dies ist ein Abgleich des Vertrags und des ersten Library-Ausbaus, keine getestete Page-Builder-Integration. Maßgeblich sind [Proposal](proposals/2026-09-12-schiller-seiten-api.md), [Adapter-Interface](../examples/Adapter.php) und die unten verlinkten Originalquellen. Das Referenzrepository bleibt unverändert.

## Ergebnis und Integrationsgrenze

Die Seitenabläufe sind mit dem überarbeiteten Vertrag darstellbar. Ein unveränderter Austausch der bisherigen PHP-Helfer reicht allerdings nicht: Die alte UI erwartet flache pid/lang/ftype-Daten und Sections, während Schiller Documents mit sprachneutraler ID und TreeNodes liefert. Page Builder Neo soll direkt diese API verwenden. Alle Unterschiede der Quellformate, Header und Dateien löst der ausgewählte Schiller-Adapter; kein zusätzlicher Format- oder Übergangslayer ist erforderlich.

Ein vollständiger Ersatz sämtlicher heutiger Page-Builder-Funktionen ist noch nicht entworfen. Die YAML-Dateneditoren für Fragmentübersetzungen und Öffnungszeiten sind auf Nutzerwunsch für den ersten Page-Builder-Ausbau zurückgestellt. Freie Kopien aus beliebigen Quellsprachen sowie die Preview-/VCS-/Benachrichtigungsabläufe sind entweder bewusst ausgeschlossen oder Aufgabe der Anwendung. Legacy darf gemäß Produktentscheidung ausschließlich bestehende Seiten und Übersetzungen bearbeiten. Seine bisherige Kopierfunktion zur Neuanlage entfällt.

Die [TreeNode-Konvention v1](tree-node.md) ist der gemeinsame JSON-Vertrag für Seiten- und Dateiansicht, angelehnt an MUI Rich Tree View. PageTree::toArray liefert root/diagnostics, FileListing::toArray entries/diagnostics. Die UI übergibt [payload.root] beziehungsweise payload.entries an einen passenden Tree-Renderer; Schiller-spezifische Spalten lesen data.translations und data.metadata. Auswahl, Expansion und Seitenöffnung sind getrennte UI-Aktionen. Für eine reine Kategorie darf nicht der ganze Knoten disabled werden, da sonst auch Aufklappen ausfallen kann. Konvention und Beispiel sind noch Entwurf, keine getestete MUI-Integration.

## Was ein API-Nutzer am Aufruf erkennen muss

| Absicht / Aufruf | Sofortige Dateiänderung | Ergebnis und Reichweite |
|---|---|---|
| config, files, pages, getHeaderDefinitions | Nein | Aktueller geprüfter Bestand/Definitionen; keine Bodies im Listing, keine versteckten Einträge |
| getPage(id, language) | Nein | Exakt diese Sprache; vorhandener verwalteter Entwurf möglich; kein Sprachfallback |
| getTranslation() oder getTranslation(null) | Nein | Stammdokument; fehlend/gesperrt: NotFoundException |
| getTranslation('fr') | Nein | Vorhandene oder bereits vorbereitete Variante; andernfalls null |
| getTranslation('fr', createIfMissing: true) | Nein | Bestehende Variante oder Kopie des gespeicherten, unveränderten Originals; neues published=false |
| getTranslations() | Nein | Alle lesbaren konfigurierten Sprachen; exists zählt Dateien, published deren effektiven Status (fehlend: null) |
| createPage(id, header, content) | Nein | Neuer Root-Entwurf, file=null, eigener Anlagezustand; bei belegter Seitengruppe/Entwurf Exception; reine Kategorie kann eine Indexseite erhalten |
| header/content zuweisen oder unset | Nein | Ausschließlich lokales Document bearbeiten |
| save() | Ja, außer No-op | Dieses Document speichern; bei neuem Kind notwendige Elternpromotion aller vorhandenen Sprachen |
| saveDocuments(documents) | Ja, außer No-op | Explizite gemeinsame Speicherung dieser Documents; endgültige Gruppe gemeinsam prüfen |
| Root-Document.rename(newId) / site.rename(id, newId) | Ja, außer identischer ID | Gesamter Teilbaum, alle vorhandenen Sprachen/Begleitdateien; nötige Zielpromotion |
| Übersetzungs-Document.delete() | Ja | Nur diese Datei; auch bei Kategorieindex, Kinder bleiben |
| Root-Document.delete() | Ja | Nur Blattgruppe mit allen Varianten; keine Nachfahrenlöschung, / geschützt |
| getUrl(absolute) | Nein | Vorgeschlagene URL dieses Documents einschließlich lokaler Änderungen |
| getDocumentByUrl(url) | Nein | Gespeicherte Route zum tatsächlichen Document oder UrlNotResolvableException; bekannter Fallback kann Root liefern |

Lesen ist kein verstecktes Speichern. rename/delete speichern keine lokalen Bearbeitungen mit. Alle Schreibaufrufe prüfen Rechte und die fachlichen Quell-/Zielbedingungen erneut; eine Revisionsprüfung ist zunächst nicht zugesagt. Fehlende Leserechte dürfen keine Existenz verraten. Ein Funktionsname mit get liefert insbesondere niemals heimlich eine neue Datei; createIfMissing bereitet nur ein Objekt vor.

## Abgleich der Originalfunktionen

| Originalablauf | Direkter Vertrag für Neo | Adapteraufgabe | Nötige Änderung / Grenze |
|---|---|---|---|
| Sections auflisten, Beschreibung und Fehler zeigen | pages()->root, label, children, data.metadata, diagnostics | Legacy liest _section.yml; Polyglot bildet Ordner/Index ab | UI rendert id/label/children/data statt sections[]. data.file=null öffnet keinen Seiteneditor; Aufklappen bleibt unabhängig |
| pid/lang als Editoradresse | getPage(id, language) | PID/Suffix intern zu ID/Sprache zuordnen, Identitätsheader erhalten | Neue Route/DTO mit separater ID/Sprache; ftype nicht mehr vom Browser vorgeben |
| Fehlende Seite: found=false | NotFoundException; fehlende Translation: null | Autorisierte Fehlfälle einheitlich behandeln | HTTP-Schicht bildet in ihre Fehlerantwort ab; kein implizites create |
| Header und Markdown/HTML laden | header, content, file, getEffectiveHeader | Bestehendes Format verlustarm lesen | Bearbeitbare Werte von geerbten Defaults und technischen Attributen trennen |
| Gesamtes Editorformular speichern | Header-Schlüssel/Body gezielt setzen, save() | Unbekannte Werte erhalten; Typen prüfen; unveränderte Teile bewahren | Vollständigen Dokumentstand aus GET erhalten; ausgelassene Felder nicht löschen; keine PID/lang-Manipulation |
| published, order, ptags, Layout | Felddefinitionen + direkte Headerwerte | Bool/Integer/Mehrfachauswahl/Layoutauswahl vereinheitlichen | JSON typisiert senden; published beim Lesen aus effektiven Werten bestimmen, nicht pauschal false |
| Section-Formulare text/textarea/select/switch/multi | getHeaderDefinitions(), presentation | Unterstützte Altdefinitionen auf denselben Vertrag abbilden | Renderer unterstützt Widgets, disabled/editable, maxlength; hr bleibt Layoutmetadatum |
| Eigene YAML-Headerwerte | Direkt im header-Array | Beim Lesen/Schreiben und Translation-Klonen bewahren | Fehlende Definition bedeutet keine Löschung und kein Schreibverbot |
| Sprachauswahl mit Namen | config.languages + languageLabels | Legacy _data/languages.yml bzw. Polyglot-Konfiguration lesen | Keine direkte YAML-Abfrage im Seitendialog nötig |
| Vorhandene Übersetzung editieren | getTranslation(language), header/content, save | Gemeinsame Gruppe anhand Format auflösen | In beiden Adaptern unterstützt |
| Bisher copyPage von beliebiger Sprache/PID | Polyglot getTranslation(language, true), dann save | Nur Original kopieren; Identität aus Pfad, Permalink übernehmen | Bewusste Änderung: kein beliebiger Copy-Endpunkt; Legacy keine Anlage |
| Neue Seite / Unterseite | createPage(id,...), save | Polyglot leitet md-/Indexablage und alle Promotionen ab | ID statt Dateiname; UI weist bei Unterseite auf mögliche URL-Änderung der Eltern hin |
| Umbenennen / Drag-and-drop | site.rename(id,newId), am Original auch document.rename | Teilbaum/Varianten/Begleitdateien und Zielpromotion gemeinsam | Sofortige Operation; danach Baum/Editorstand aktualisieren; nur Polyglot |
| Übersetzung / Seite entfernen | Passendes Document.delete | Einzeldatei oder ganze Blattgruppe nach Identität | UI muss Reichweite benennen; Kategorie-Rekursivlöschung bewusst nicht angeboten |
| Preview bisher aus Host + pid.lang/permalink | document.getUrl() plus externer Preview-Kontext | Tatsächliche Route aus Format/Jekyll-Konfiguration ableiten | URL-Join in Anwendung, keine zweite Dateiformatlogik; unpublished ggf. nur im Preview-Build |
| Von Website-URL in den Editor | getDocumentByUrl(url) | Normalisieren, gespeicherte Route/Fallback sicher zuordnen | Tatsächliche Rückgabesprache anzeigen; keine automatische Bearbeitung einer vermeintlichen Übersetzung |
| Manuelle Permalink-Änderung | Headerwerte der Gruppe setzen, saveDocuments | Endzustand inkl. Varianten/Kollisionen prüfen | Gruppenwirkung im Formular zeigen; kein sequentielles Speichern widersprüchlicher Varianten |
| FileCtrl: beliebige YAML-Dateien laden/speichern | Kein Seiten-Schreibzugriff dafür | Allenfalls erlaubtes physisches Listing | _data-Editor mit _editor und Übersetzungsdaten mit _hint benötigt separaten Folgeentwurf |
| Git/Preview-Konfiguration/Dirty-Status/Redis | Externe Anwendung nach erfolgreicher tatsächlicher Mutation | Keine Versionskontrolle im Adapter | Nicht bei GET, Entwurf, No-op oder fehlgeschlagenem Save als Änderung melden |

## Reviewfälle aus beiden Perspektiven

| Fall | Erwartung des API-Nutzers | Pflicht der Implementierung / spätere Abnahme |
|---|---|---|
| Übersetzung vorbereiten, erneut ohne Option laden | Dasselbe bearbeitete Objekt; noch keine Datei | Identitätsverwaltung berücksichtigt Entwürfe; exists bleibt false |
| Dialog abbrechen und später öffnen | Keine Speicherung | Neue Bearbeitungseinheit lädt Bestand; kein Reload durch getPage derselben Instanz suggerieren |
| Headerfeld im Formular ausgelassen | Bisherigen Wert erhalten | Nur explizite Zuweisung/unset anwenden; eigene verschachtelte Daten mitprüfen |
| Zwei Browser bearbeiten dieselbe Seite | Im ersten Ausbau keine eigene Erkennung veralteter Editorstände | Externer Git-/Anwendungsablauf; spätere Revisionsprüfung kann ConflictException verwenden |
| Erste Unterseite erzeugen | Neues Kind; Elternseiten werden zu Index | Sämtliche vorhandenen Elternsprachen und Vorfahren vorab planen, nie fehlende Übersetzungen erzeugen |
| Zwei Kinder gemeinsam speichern | Ein gemeinsamer Vorgang | Gemeinsame Promotion deduplizieren, Endzustand prüfen |
| Kategorie mit unsichtbarem Kind verschieben/löschen | Keine versteckte Teilmutation | Vollständige Inventarisierung unabhängig vom bereinigten UI-Baum; Root-Delete abweisen |
| Englischen Kategorieindex löschen | Nur englische Kategorieseite verschwindet | Kinder und Begleitdateien erhalten, andere Sprachen unverändert |
| Gruppe mit festen Permalinks verschieben | Permalinks bleiben; Dateien/IDs ändern sich | Natürliche URLs, Jekyll-Defaults und Kollisionsindex neu berechnen |
| Gruppen-Permalink bearbeiten | Alle ausgewählten Varianten gemeinsam ändern | Endzustand statt Zwischenzustände prüfen; unvollständige Gruppe ablehnen, keine anderen Varianten still verändern |
| Französische URL fällt auf Deutsch zurück | Tatsächlich geöffnetes Deutsch erkennen | Document.language de beibehalten; save schreibt Deutsch; fr explizit vorbereiten |
| Legacy-Datei mit fremden Metadaten speichern | Identität und Zusatzwerte bleiben erhalten | PID/lang schützen, bestehende Datei benutzen; keine Umbenennung/Neuanlage |
| I/O-Fehler in Gruppenaktion | Kein falscher Erfolg | Wiederherstellung versuchen; gescheiterten Rollback als unvollständig melden |
| Unbekanntes Formularwidget / nicht modellierte Route | Sichtbare, nachvollziehbare Grenze | Diagnose statt Datenverlust, ungeprüftem HTML oder erfundener URL |

## Umsetzungskriterien

Die Implementierung braucht weitere Vertragsprüfungen für beide Adapter, Storage-Tests mit kontrollierten Fehlern und Integrationstests an temporären Verzeichnissen. Für den Editor sind insbesondere GET/POST mit leerem Adapterzustand, Erhaltung unbekannter Header, UI-Sprachfallback und alle Formtypen der Referenz zu prüfen. Die [Verschiebematrix](verschieben.md) bleibt verbindlich. PHP-Beispiele sind Entwürfe und ersetzen diese Tests nicht.

Revisionsvergleich und bedingtes Schreiben sind ein späterer Ausbau; die mitgelieferten Adapter werden wegen fehlender Revisionsunterstützung nicht abgewiesen. Der externe Git-/Anwendungsablauf koordiniert Versionsverwaltung und parallele Bearbeitung. Schiller garantiert zunächst keine Erkennung veralteter Editorstände. Die getrennt beschriebenen Quell-/Zielprüfungen und Wiederherstellung bei Gruppenoperationen bleiben bestehen.

Der ursprüngliche Page-Builder-Container verwendet PHP 8.1; die inzwischen vorhandenen Schiller-Laufzeitklassen verlangen in composer.json PHP >=8.3. Der neue Builder benötigt deshalb eine PHP-8.3-Laufzeit. Die vorhandene Implementierung erfüllt noch nicht den gesamten in diesem Dokument beschriebenen Vertrag; insbesondere Feldrechte, URL-Kollisionen und der HTTP-Dokumenttransport benötigen weitere Prüfung.

## Geprüfte Referenzen

- [PageCtrl](https://github.com/micx-io/micx-pagebuilder/blob/main/src/Ctrl/PageCtrl.php): Lesen, Schreiben, Kopieren.
- [PageListCtrl](https://github.com/micx-io/micx-pagebuilder/blob/main/src/Ctrl/PageListCtrl.php): Sections, Gruppen und Diagnosen.
- [Seiteneditor](https://github.com/micx-io/micx-pagebuilder/blob/main/www/pages/edit-page.html): Headerformulare, Kopieren, Preview.
- [Seitenliste](https://github.com/micx-io/micx-pagebuilder/blob/main/www/elements/page-list.html): alte Listing-Struktur und Sprachwahl.
- [FileCtrl](https://github.com/micx-io/micx-pagebuilder/blob/main/src/Ctrl/FileCtrl.php), [Dateneditor](https://github.com/micx-io/micx-pagebuilder/blob/main/www/pages/edit-data.html), [Übersetzungsdaten](https://github.com/micx-io/micx-pagebuilder/blob/main/www/pages/translation.html): bewusst noch offene Datenfunktionen.
- [Änderungsmeldung](https://github.com/micx-io/micx-pagebuilder/blob/main/src/Mw/SendRedisMessageMw.php), [Dockerfile](https://github.com/micx-io/micx-pagebuilder/blob/main/Dockerfile): externe Benachrichtigungen und PHP-Basis.

## Vereinfachter Adaptervertrag und Dokumenttransport

getTranslation und getTranslations bleiben am Document. Schiller setzt sie mit gemeinsamem Instanzbestand, expliziter Sprache für load/create, vorhandenem Baum und getSourcePath um. Insbesondere berechnet der Adapter weiterhin Legacy-Suffixe beziehungsweise gespiegelte Sprachpfade; dafür braucht der Page Builder keinen Übergangslayer.

Im Adapter gibt es genau write(array $documents), auch für den Ein-Dokument-Fall. Der freie adapterState jedes Documents ist ausschließlich Adapterangelegenheit und wird nicht im Seitenheader gespeichert. Gemeinsam zu speichernde Dokumente werden vorab als ein Endzustand validiert; ein Einzelschreib-Loop ist kein Ersatz.

Der neue HTTP-Entwurf toArray/restoreDocument transportiert den vollständigen Bearbeitungsstand samt opakem Zustand. Er benötigt bei der Implementierung eine geprüfte Site-/Adapter-/Identitätsbindung und darf keine Clientrollen übernehmen. Ein leerer adapterState ist im ersten Ausbau zulässig; Identität und Rechte werden unabhängig davon geprüft. Die UI bearbeitet nur header/content und transportiert den übrigen Zustand unverändert. Beispiel 18 macht diese Einbindung sichtbar; eine installierte Transportimplementierung wird noch nicht behauptet.

JekyllPolyglotAdapter und JekyllLegacyAdapter werden mitgeliefert. Der SchillerDir-Konstruktor akzeptiert eine optionale Adapterinstanz; diese hat Vorrang vor schiller.yaml. Ohne beides gilt JekyllPolyglotAdapter. Die Anwendung konstruiert Adapter ohne Storage-Argument, SchillerDir bindet den kontrollierten Dateizugriff einmalig intern. ConflictException ist als spätere Erweiterung vorgesehen, kein aktuelles Abnahmegate für Legacy oder Polyglot.
