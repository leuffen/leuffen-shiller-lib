# Verschieben und Indexablage im Polyglot-Adapter

Implementierungsentwurf, noch keine ausführbare Funktion. Er ergänzt [Proposal §§ 8 und 11](proposals/2026-09-12-schiller-seiten-api.md), [Beispiel 16](../examples/16-create-child-page.php) und [Beispiel 17](../examples/17-move-page-tree.php). Die spätere Implementierung muss die unten aufgeführten Fälle ausgiebig testen.

## Öffentliche Operation und Identität

`Document::rename('/leistungen/vorsorge')` benennt um oder verschiebt, je nach Ziel-ID. Reine Kategorien ohne Document werden mit `SchillerDir::rename($id, $newId)` verschoben; Document::rename delegiert an dieselbe Operation. Es ist kein zusätzlicher Aufruf zum Anlegen von Zielordnern nötig. Die Methode schreibt sofort; createPage bereitet dagegen nur einen Entwurf vor und führt die entsprechende Umstellung erst beim save aus.

Die ID enthält weder Dateiendung noch Sprache. Verschobene Teilbäume erhalten das neue ID-Präfix. Eine Ziel-Elternseite, die nur von einer Datei zur Indexablage umgestellt wird, behält ihre ID. Alle geladenen Documents werden innerhalb derselben SchillerDir-Instanz mitgeführt; bereits ausgegebene Tree-Listings werden erneut abgefragt.

Die Root-ID / darf nicht umbenannt oder verschoben werden. Quelle und Ziel dürfen identisch sein (No-op, keine writes). Ein Ziel innerhalb des eigenen Teilbaums ist verboten; der Vergleich erfolgt auf ID-Segmenten, nicht als bloßer String-Präfixvergleich.

## Fall A: Erste Unterseite unter einer Blattseite

Vorher bestehen leistungen.md und en/leistungen.md. createPage('/leistungen/allgemeinmedizin')->save() bewirkt:

| Quelle | Ziel |
|---|---|
| leistungen.md | leistungen/index.md |
| en/leistungen.md | en/leistungen/index.md |
| neuer deutscher Inhalt | leistungen/allgemeinmedizin.md |

Fehlendes fr bleibt fehlend. Die neue Unterseite hat zunächst keine eigene englische oder französische Datei. Der Adapter legt die benötigten Ordner nach Rechteprüfung an. Die Elternseite behält Header, Body, Veröffentlichungsstatus und Permalink; auch eigene verschachtelte Metadaten gehen nicht verloren.

## Fall B: Kategorie umbenennen

rename('/medizin') an /leistungen mit Index und Kindern bewegt den vollständigen physischen Unterbaum leistungen/ nach medizin/. Für jede vorhandene Sprache wird en/leistungen/ entsprechend nach en/medizin/ bewegt. Alle Nachfahren-IDs ändern ihr Präfix.

Das umfasst auch Begleitdateien, Bilder, manuelle YAML-Dateien und leere Unterordner unterhalb der Quelle. Das Seitenlisting ist keine vollständige Grundlage zum Verschieben: Ausgeschlossene oder verborgene physische Einträge dürfen weder vergessen noch still zurückgelassen werden. Liegen für eine Sprache noch eine Blattdatei und ein zugehöriger Unterordner vor, wird diese bestehende Kombination vollständig inventarisiert und konsistent zur Indexablage normalisiert; konkurrierende Indexdateien sind ein Konflikt.

## Fall C: Eigenständigen Teilbaum unter eine Blattseite verschieben

Vorher:

- leistungen.md und en/leistungen.md.
- vorsorge/index.md, vorsorge/kinder.md und vorsorge/bild.png.
- en/vorsorge/index.md und en/vorsorge/kinder.md.

Aufruf: `$site->getPage('/vorsorge')->rename('/leistungen/vorsorge')`.

Danach:

- leistungen/index.md und en/leistungen/index.md.
- leistungen/vorsorge/index.md, leistungen/vorsorge/kinder.md und leistungen/vorsorge/bild.png.
- en/leistungen/vorsorge/index.md und en/leistungen/vorsorge/kinder.md.

Ziel-Eltern werden zunächst im Plan zur Indexablage umgestellt. Dies umfasst auch vorhandene Sprachen der Ziel-Eltern, für die im verschobenen Quellteilbaum keine Übersetzung existiert. Reine Zielkategorien ohne eigene Seite bleiben ohne Datei. Fehlende Vorfahren werden nur als Ordner angelegt; es werden keine Inhalte erfunden.

## Ablauf der Implementierung

1. **Inventarisieren:** Quelle, alle Nachfahren und zugehörigen Sprachpfade vollständig erfassen. Ziel-ID normalisieren und alle Zielvorfahren prüfen. Legacy lehnt die gesamte Strukturaktion ab.
2. **Plan erstellen:** nötige Elternumstellungen, Ordneranlage, vollständige Dateibewegungen, neue IDs und gegebenenfalls neue Kinddatei bestimmen. Eine vorhandene HTML-Elternseite bleibt HTML und wird index.html.
3. **Vorab prüfen:** jede Quelle und jedes Ziel, einschließlich versteckter/ausgeschlossener Begleitdateien, auf Rechte und Konflikte prüfen. Für Bewegung gelten read/write/rename an Quellen und createFile/createDirectory sowie passende Sprachrechte an Zielen. Schiller muss erforderliche Rechte auch für Dateien prüfen, die nicht im UI sichtbar sind. Kein verborgenes Detail in Fehlermeldungen.
4. **Kollisionen ablehnen:** keine bestehenden Zieldateien überschreiben und keine fremden Zielverzeichnisse automatisch zusammenführen. Reine gemeinsame Vorfahren dürfen bestehen. Blatt- und Indexdatei für dieselbe ID/Sprache, md/html-Doppelbelegung sowie kollidierende Ausgabe-URLs erzeugen einen Fehler.
5. **Zustand absichern:** ungespeicherte Änderungen/Entwürfe an betroffenen geladenen Documents führen zu UnsavedChangesException. Ändert sich der erfasste Storage-/Konfigurationsstand zwischen Prüfung und Anwendung, abbrechen statt einen veralteten Plan ausführen; externe Bearbeitung muss koordiniert werden.
6. **Gemeinsam ausführen:** ein vorbereiteter Batch mit Wiederherstellung, soweit vom Storage unterstützt. Kein Erfolg bei Teilfehlern. Fehlt diese Fähigkeit, vor Beginn ablehnen. Scheitert auch die Wiederherstellung, den unvollständigen Zustand ausdrücklich melden; keine universelle atomare Filesystem-Transaktion behaupten.
7. **Aktualisieren:** IDs der verschobenen Documents, FileEntries, Verfügbarkeits-/Gruppenindex und URL-Index aktualisieren. Alte IDs lösen nach dem Erfolg nicht mehr auf. Root-Beziehungen bleiben dieselben Objektbeziehungen.

Alle physischen Pfade bleiben innerhalb des vorgegebenen Roots. Kein Traversal, kein Folgen von Symlinks und kein Verschieben in reservierte Sprach-/Build-/Konfigurationsbereiche. Eine fehlende Berechtigung an nur einer betroffenen Sprachdatei verhindert die gesamte Aktion.

## URLs, Inhalt und Metadaten

Die ID ist kein URL-Vertrag. Ohne Permalink ändert sich beim Umstellen von leistungen.md nach leistungen/index.md die URL von /leistungen.html nach /leistungen/. Ein expliziter Permalink bleibt unverändert; eventuell daraus entstehende URL-Kollisionen werden vorab erkannt. Der Reverse-Index kennt danach die neuen Routen, keine automatisch erfundenen Redirects.

Eigene Headerwerte, Body, Begleitdateien und Kategoriemetadaten werden vollständig erhalten. Kein pauschales Neusetzen von published bei einem Move. Geerbte Jekyll-Defaults können sich am neuen Pfad ändern und werden neu ausgewertet, aber nicht in den gespeicherten Header kopiert. Der resultierende effektive Header und die Ausgabewege müssen deshalb bereits bei der Planung geprüft werden.

Relative Links im Inhalt werden nicht automatisch umgeschrieben. Innerhalb eines gemeinsam verschobenen Unterbaums können sie weiter passen; bei Umstellung einer Blattseite oder Verweisen auf äußere Ziele ist das nicht garantiert. Link-Rewriting und Redirect-Erzeugung benötigen einen eigenen Vertrag. Beim Entfernen des letzten Kindes bleibt die Elternseite als Index erhalten; kein automatisches Zurückverschieben.

## Verpflichtende spätere Tests

Diese Matrix ist eine Abnahmeanforderung für die spätere Implementierung, kein Bericht bereits gelaufener Tests.

| Szenario | Erwartung |
|---|---|
| Erste Unterseite unter md-Blatt | Eltern nach index.md, Kinddatei anlegen, Eltern-ID bleibt |
| Erste Unterseite unter html-Blatt | Index bleibt HTML; Inhalt wird nicht konvertiert |
| Eltern bereits als Index | Kein zweiter Index, kein unnötiges Umbenennen |
| Mehrere fehlende Vorfahren | Nur erlaubte Ordner erzeugen; keine erfundenen Elternseiten |
| Reine Kategorie ohne Seitendatei verschieben | Ordner und Kinder bewegen, file bleibt null |
| Kategorie umbenennen | Alle Nachfahren und Dateien erhalten neues Präfix |
| Teilbaum unter Blatt verschieben | Ziel-Elternpromotion und Quellbewegung erfolgen gemeinsam |
| Teilbaum unter vorhandene Kategorie | Keine unnötige Promotion, Kinder vollständig bewegen |
| Unterschiedliche Sprachabdeckung | Alle vorhandenen Varianten bewegen, fehlende fehlen weiterhin |
| Ziel-Eltern mit zusätzlicher Sprache | Auch diese Elternvariante auf Index umstellen |
| Eigene verschachtelte Metadaten/Dateien | Werte und Bytes bleiben erhalten |
| Bilder, ausgeschlossene Dateien, leere Ordner | Vollständiger Move trotz fehlender Seitenlisteneinträge |
| Kollision an einer Sprachdatei/Index/md-html | Gesamte Operation unverändert abweisen |
| Verbotene oder versteckte Quelldatei | Gesamte Operation abweisen, keine Namen offenlegen |
| Fehler beim N-ten Schritt | Vorzustand wiederherstellen; nie falschen Erfolg melden |
| Fehler bei Wiederherstellung | Expliziten Teilfehler samt kontrollierter Diagnose melden |
| Gleichzeitige externe Änderung | Konflikt vor Überschreiben erkennen |
| Ungespeicherte betroffene Documents | Abweisen, keine implizite Speicherung |
| Root oder Ziel im eigenen Teilbaum | Abweisen; echte Segmentgrenzen berücksichtigen |
| Traversal/Symlink/reservierter Bereich | Vor Schreibbeginn abweisen |
| Geladene Root-/Übersetzungs-/Kindreferenzen | Neue IDs/Dateien und unveränderte Objektbeziehungen |
| Permalink oder geänderte Defaults | Werte erhalten; neue wirksame Regeln und Kollisionen prüfen |
| URL-Rückwärtsauflösung nach Move | Neues Quelldokument finden; keine veralteten Indexeinträge |
| Legacy | Vorhandene Header/Body editierbar; alle Struktur-/Anlageaktionen abweisen |
| Vor save()/erneuter No-op | Keine vorzeitigen bzw. unnötigen Dateioperationen |

Die Tests benötigen ein kontrollierbares Storage-Testdouble für Fehler an jedem Batch-Schritt und Integrationstests mit echten temporären Verzeichnissen. Zusätzlich Vorher-/Nachher-Manifeste aller Sprach- und Begleitdateien vergleichen, damit weder Datenverlust noch unbeabsichtigte Neuanlage verborgen bleiben.

## Review-Vertrag für Aufrufer

Ein rename-Button kann aus capabilities die Eignung der Quelle ableiten. Ohne Ziel-ID ist damit keine Aussage über Zielrechte oder Konflikte möglich. Der Aufruf rename(sourceId, targetId) ist die verbindliche Prüfung und schreibt bei Erfolg sofort. Bei unveränderter ID ist es ein No-op; bestehende Zugriffs- und Adaptergrenzen gelten dennoch. Legacy unterstützt auch dadurch keine Strukturaktionen.

Eine Promotion beim save ist die ausdrücklich dokumentierte Nebenwirkung einer neuen Unterseite: alle betroffenen Elternvarianten werden bewegt, ihre Inhalte werden nicht mitgespeichert. Ein vorbereiteter, noch nicht gespeicherter Übersetzungsentwurf an einer betroffenen Elternseite verhindert die Strukturaktion wie andere ungespeicherte Änderungen. Auch saveDocuments muss sämtliche Promotionen gemeinsam planen und darf dieselbe Elternseite nicht mehrfach bewegen.

Nach einer erfolgreichen Bewegung sind alte Listing-Objekte und die im Browser gespeicherten Dokumentstände veraltet. Die Anwendung liest Baum und bearbeitete Documents erneut für die nächste Anzeige; innerhalb derselben SchillerDir-Instanz sind IDs, FileEntries und Adapterzustände bereits aktualisiert. Die HTTP-Schicht nimmt IDs und Rollen nicht aus veränderbaren Headerfeldern.

Löschen ist keine verkürzte Variante von rename: Das Stammdokument darf nur ohne Nachfahren in allen Sprachen gelöscht werden, die Root-Gruppe / niemals. Eine Übersetzung darf auch dann als einzelne Datei gelöscht werden, wenn Sprach-Unterseiten bestehen; diese bleiben erhalten. Begleitdateien und Ordner werden nicht rekursiv gelöscht. isLeaf im gefilterten Seitenbaum beweist keine vollständige physische Kinderlosigkeit.

Zusätzliche spätere Abnahmefälle: zwei Kindanlagen in einem saveDocuments mit gemeinsamer Elternpromotion; transiente Elternübersetzung; Kategorieindex einer einzelnen Sprache löschen; verborgene Nachfahren beim Root-Delete; nach Move veraltete Editorzustand beim Speichern; No-op mit verweigerter Operation. Prüfung des adapterState und Commit müssen im Storage gegen gleichzeitige Änderungen abgesichert sein. Ein bloßer Vergleich vor einem ungeschützten write genügt nicht; ohne geeignete Connector-Fähigkeit oder garantierte externe Serialisierung ist die Mutation abzuweisen.

Jede gemeinsame Speicherung, auch mehrerer neuer Kinder, wird über genau einen Adapteraufruf write(list<Document>) ausgeführt. Der Adapter prüft die eigenen Zustände der aufgeführten Documents und die zusätzlich betroffenen Quellen, plant Promotionen einmalig und aktualisiert nach Erfolg die internen Zustände. Originalkopien erhalten einen neuen Anlagezustand; sie übernehmen keine Revision oder andere technischen Metadaten des Originals. Öffentliche getTranslation/getTranslations werden dafür nicht im Adapter dupliziert.
