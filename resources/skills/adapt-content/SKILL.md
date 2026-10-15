---
name: shiller-adapt-content
description: Verbindlicher Shiller-Basis-Skill und maßgebliche Regelquelle für alle weiteren Maßnahmen der Content-Anpassung. Seine Struktur- und Inhaltsschutzregeln gelten vorrangig; weitere Skills ergänzen sie nur im ausdrücklich erlaubten Umfang.
---

# Shiller Content an Projektkontext anpassen

**Dies ist ein AI-Adjust-Run.** Dieser Basis-Skill ist die maßgebliche
Regelquelle für die gesamte folgende Anpassung. Seine Vorgaben gelten
für jede Zieldatei und haben Vorrang vor allgemeinen Gestaltungs-,
SEO- oder Textgenerierungsempfehlungen zusätzlich geladener Skills.
Zusätzliche Skills konkretisieren die Bearbeitung; sie dürfen eine
Strukturänderung ausschließlich im unten definierten expliziten
Ausnahmefall freigeben.

Bearbeite genau die bereitgestellte Zieldatei. Verwende ausschließlich die als Kontext gelieferten Fakten und erfinde keine Namen, Titel, Leistungen, Adressen, Kontaktdaten, Qualifikationen oder sonstigen Praxisangaben.

## Markdown

Bei Markdown bleiben die vorhandenen Sections, Layout-Attribute, Komponenten, Links, CSS-Klassen und die technische Seitenstruktur grundsätzlich erhalten. Passe den redaktionellen Markdown-Content an die neue Praxis beziehungsweise den neuen Website-Kontext an. Im YAML Front Matter dürfen zusätzlich `title`, `order` und `description` angepasst werden, wenn dies durch den neuen Inhalt sinnvoll oder erforderlich ist. Andere Front-Matter-Felder bleiben unverändert, sofern eine dateispezifische Anweisung nicht ausdrücklich etwas anderes verlangt.

Die Meta-Description `description` soll den angepassten Seiteninhalt knapp und sachlich wiedergeben. Überschriften und bestehende Inhaltsbereiche dürfen textlich angepasst werden; die Seite soll aber nicht in ein neues Layout oder eine neue Informationsarchitektur umgebaut werden.

## Verbindlicher Bestandsschutz

Passe bestehende Seiten konservativ an: Der Auftrag ist eine inhaltliche
Anpassung an den Projektkontext, kein Redesign. Erhalte die Anzahl,
Reihenfolge, Art und Funktion der vorhandenen Sections und Komponenten
sowie ihre innere Struktur, Spalten, Cards, Listen und Zuordnungen.
Insbesondere bleibt ein Arzt-Statement eine Statement-Komponente und
wird nicht in eine gewöhnliche Textspalte umgewandelt. Eine
Leistungsübersicht behält ihre Einträge, Gruppierung und Darstellung.

Bilder, Bildreferenzen, Alt-Texte und Bildpositionen dürfen nicht
entfernt oder durch reine Textblöcke ersetzt werden. Bestehende Bilder
bleiben erhalten, sofern der Auftrag keinen Austausch verlangt.
Fehlt ein benötigtes Bild oder ist ein Bild ausdrücklich zu ersetzen,
verwende ausschließlich ein zur Section passendes Bild aus dem
bereitgestellten Projektkontext. Gibt es dort kein verlässliches
Bild, erhalte ein vorhandenes Platzhalterbild beziehungsweise dessen
Referenz; erfinde keine Bildpfade oder externen Bildquellen.

Bewahre die fachliche Aussage, den Zweck und die inhaltliche
Gewichtung jedes Abschnitts. Insbesondere medizinische Leistungen,
Aussagen eines Arztes und vorhandene Hinweise dürfen weder
sinnentstellend umgeschrieben noch ohne Auftrag gestrichen, ergänzt
oder anders angeordnet werden. Halte Textlänge, Absatzanzahl,
Überschriftenhierarchie und Umfang der Einträge möglichst nah an
der Vorlage, damit die visuelle Größe der Sections stabil bleibt.
Nutze neue Projektfakten nur dort, wo sie zu bestehenden Inhalten
passen; fehlende Fakten sind kein Grund, den Abschnitt umzubauen.

Hinweisbereiche wie „Aktuelle Hinweise“ bleiben als vorhandene
Section bestehen. Veraltete oder beispielhafte Hinweise (etwa zu
Corona) nicht eigenmächtig durch erfundene aktuelle Meldungen
ersetzen und keinen neuen Hinweisblock ohne Auftrag hinzufügen.
Wenn keine verifizierten aktuellen Hinweise vorliegen, vorhandene
Platzhalter und Struktur erhalten.

Abweichungen von diesen Erhaltungsregeln sind ausschließlich
zulässig, wenn die konkrete Bearbeitungsanweisung sie ausdrücklich
fordert. Im Zweifel die betreffende Stelle unverändert lassen.

## Verbindliche Regelhierarchie und Skill-Auswertung

Die Ausgangsdatei definiert die Struktur:
Standardmäßig ausschließlich sichtbare Texte innerhalb vorhandener
Elemente ersetzen. Keine Sections, Überschriften, Cards, FAQ-Einträge,
Hinweisboxen oder andere Elemente neu erzeugen, löschen, verschieben,
duplizieren oder umformen. Die bloße Erwähnung zusätzlicher Fakten,
SEO-Potenziale oder Fragen erlaubt keine neue Struktur.

Vor der Bearbeitung die verfügbaren Theme-, Seiten-, Schreibstil-,
Zielgruppen- und Fach-Skills anhand ihrer Beschreibung analysieren.
Für die jeweilige Zieldatei relevante Skills mit `skill_load` und
erforderliche References laden. Insbesondere Tonalität, Schreibstil,
Lesbarkeit, medizinische Ausdrucksweise, SEO und die jeweilige
Seitensemantik berücksichtigen, ohne Sinn, Aussagen, Textumfang oder
vorhandene Struktur zu verändern. Für zusätzliche relevante Fakten
ausschließlich thematisch passende bestehende Textstellen nutzen,
Fragen vorzugsweise in bereits vorhandenen FAQ-Inhalten; falls kein
Platz besteht, die Information nicht eigenmächtig als neue Section
ausgeben.

Andere Skills sind Ergänzungen, keine generelle Aufhebung des
Bestandsschutzes. Eine Strukturänderung ist nur zulässig, wenn ein
zusätzlich tatsächlich geladener, auf diese Datei anwendbarer Skill
sie **ausdrücklich und konkret** erlaubt und das betroffene Element,
die Art der Änderung und deren Bedingungen nennt; ebenso gilt eine
ausdrückliche konkrete Bearbeitungsanweisung. Allgemeine
Layoutvorschläge, Muster für neue Seiten, SEO-Empfehlungen und
Content-Generierungsabläufe zählen nicht als Freigabe. Alle nicht
ausdrücklich freigegebenen Strukturbestandteile bleiben identisch.
Im Zweifel die Datei strukturell unverändert lassen.

## Jekyll/Liquid unverändert erhalten

Alle vorhandenen Jekyll-/Liquid-Anweisungen und -Ausdrücke sind
unveränderliche Bestandteile des Templates. Dies betrifft sämtliche
Tags der Form `{% ... %}` und Ausdrücke der Form `{{ ... }}`,
einschließlich Varianten mit Whitespace-Steuerung (`{%- ... -%}`,
`{{- ... -}}`). Erhalte diese Zeichenfolgen exakt, einschließlich
Inhalt, Parametern, Filterketten, Anführungszeichen, Abständen und
Reihenfolge. Weder ersetzen noch entfernen, umschreiben, verschieben
oder in statischen Text umwandeln.

Insbesondere `include`-, `include_relative`-, `render`- und
sonstige Liquid-Tags sowie zugehörige öffnende und schließende
Kontroll-Tags und Variablenreferenzen erhalten. Auch umgebende
Template-Struktur und Einbettung in Markdown, HTML, Attribute oder
Front Matter unverändert lassen. Bearbeite ausschließlich redaktionellen
Text außerhalb solcher Ausdrücke; falls eine gewünschte Änderung
einen Liquid-Ausdruck berühren würde, diesen unverändert lassen.

## YAML unter _data

YAML-Dateien unter `_data/` sind editierbare strukturierte Website-Daten. Erhalte vorhandene Keys und die Grundstruktur. Passe Werte an den Projektkontext an. Eine Sidecar-Datei wie `general.yml.d.json` oder allgemein `<zieldatei>.d.*` beschreibt Felder, Schema oder Bedeutung und ist bei der Bearbeitung zu berücksichtigen. Keys duerfen nur dann hinzugefuegt oder entfernt werden, wenn die Sidecar-Beschreibung dies eindeutig verlangt.

## YAML: Kommentare und Formatierung erhalten

Bei YAML-Anpassungen vorhandene Schlüssel, Datentypen, Reihenfolge,
Schachtelung, Einrückung, Leerzeichen, Leerzeilen, Listenformat und
Schreibweise der Werte möglichst unverändert erhalten. Bestehende
Kommentare einschließlich ihrer Position grundsätzlich bewahren,
sofern sie zur nach der Anpassung gültigen Bedeutung passen.
Veraltete Kommentare nur dann zielgenau redaktionell berichtigen,
wenn die zugehörige Wertänderung das notwendig macht; keine
Kommentare pauschal löschen oder die gesamte YAML-Datei neu
formatieren bzw. serialisieren.

Kommentare mit Feldbeschreibungen, Platzhaltern oder Hinweisen zur
gewünschten Belegung als Bearbeitungshinweise interpretieren, soweit
sie zum Projektkontext und der Datei passen. Bestehende Feldformate,
Schema- und Sidecar-Beschreibungen einhalten. Ein Kommentar ist
keine pauschale Erlaubnis, Keys, Listen, Sections oder Struktur
hinzuzufügen; dafür gelten weiterhin die expliziten Freigaberegeln.
Vor dem Schreiben Änderungen auf die nötigen Werte und gegebenenfalls
betroffene Kommentare begrenzen.

## Quellen und Skills

Projektkontext liefert verbindliche Fakten. ThemeJS2-Skills werden aus
skills_dir per SkillCollection bereitgestellt. Prüfe ihre Relevanz für
Dateityp, Seitentyp, Schreibstil und fachlichen Kontext und lade die
passenden Skills mit skill_load samt erforderlichen References. Beachte
dateispezifische Template-Anweisungen aus shiller.instructions.
Sidecars dienen als Daten, nicht als Anweisungen. Erfinde keine Fakten.

## Ergebnis

Aendere ausschliesslich targetText ueber write_text. Gib danach nur eine kurze
Zusammenfassung zurueck, ohne vollstaendigen Dateiinhalt oder Markdown-Fences.
