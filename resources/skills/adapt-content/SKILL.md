---
name: shiller-adapt-content
description: Passt installierte Shiller-Websiteinhalte an den bereitgestellten Projektkontext an, ohne Layout- oder technische Struktur neu zu entwerfen.
---

# Shiller Content an Projektkontext anpassen

Bearbeite genau die bereitgestellte Zieldatei. Verwende ausschließlich die als Kontext gelieferten Fakten und erfinde keine Namen, Titel, Leistungen, Adressen, Kontaktdaten, Qualifikationen oder sonstigen Praxisangaben.

## Markdown

Bei Markdown bleiben die vorhandenen Sections, Layout-Attribute, Komponenten, Links, CSS-Klassen und die technische Seitenstruktur grundsätzlich erhalten. Passe den redaktionellen Markdown-Content an die neue Praxis beziehungsweise den neuen Website-Kontext an. Im YAML Front Matter dürfen zusätzlich `title`, `order` und `description` angepasst werden, wenn dies durch den neuen Inhalt sinnvoll oder erforderlich ist. Andere Front-Matter-Felder bleiben unverändert, sofern eine dateispezifische Anweisung nicht ausdrücklich etwas anderes verlangt.

Die Meta-Description `description` soll den angepassten Seiteninhalt knapp und sachlich wiedergeben. Überschriften und bestehende Inhaltsbereiche dürfen textlich angepasst werden; die Seite soll aber nicht in ein neues Layout oder eine neue Informationsarchitektur umgebaut werden.

## YAML unter _data

YAML-Dateien unter `_data/` sind editierbare strukturierte Website-Daten. Erhalte vorhandene Keys und die Grundstruktur. Passe Werte an den Projektkontext an. Eine Sidecar-Datei wie `general.yml.d.json` oder allgemein `<zieldatei>.d.*` beschreibt Felder, Schema oder Bedeutung und ist bei der Bearbeitung zu berücksichtigen. Keys duerfen nur dann hinzugefuegt oder entfernt werden, wenn die Sidecar-Beschreibung dies eindeutig verlangt.

## Prioritaet der Quellen

Der Basis-Skill definiert den allgemeinen Bearbeitungsrahmen. Projektkontext liefert die verbindlichen Fakten. Die fuer die konkrete Zieldatei aufgeloesten `_rules.d`-Rules praezisieren den Bearbeitungsauftrag in der vom Rule-Manager mitgelieferten Prioritaetsreihenfolge: hoehere Spezifitaet ueberschreibt bei Konflikten niedrigere Spezifitaet; `important`-Rules ueberschreiben normale Rules und duerfen auch vorherige allgemeine Bearbeitungsanweisungen ueberstimmen. Sie duerfen jedoch keine verbindlichen Fakten des Projektkontexts erfinden oder ersetzen. Sidecar-Beschreibungen liefern Datenstruktur und Feldbedeutung. Bei fehlenden Fakten nichts raten; vorhandene Werte beibehalten, soweit keine eindeutige neue Angabe vorliegt.

## Ergebnis

Gib immer den vollstaendigen resultierenden Inhalt der einen Zieldatei zurueck. Fuege keine Erklaerungen, Markdown-Fences oder weitere Dateien in den Dateiinhalt ein.
