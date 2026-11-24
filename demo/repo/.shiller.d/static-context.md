# Static Context

Dieser schreibgeschützte Kontext beschreibt die technische Funktionsweise
und die allgemeinen Bearbeitungsregeln der Website. Er enthält keine
individuellen Projekt-, Praxis- oder Personendaten und wird nicht durch
die AI verändert.

## Website-Bearbeitung

### Projekt und Änderungsumfang

Dieses Projekt ist eine Jekyll-Website. Die einzelnen Seiten liegen im
konfigurierten Document Root (in der Regel `docs/`) als Markdown-Dokumente.
Lies vergleichbare vorhandene Seiten, bevor du Inhalte änderst oder ergänzt.

Bearbeite nur die vom Auftrag betroffenen Dateien und Textstellen. Halte
Änderungen so klein wie möglich. Erhalte die vorhandene Seitenstruktur,
Layouts, Front Matter und technische Elemente, sofern der Auftrag keine
gezielte Änderung daran verlangt. Formatiere unveränderte Abschnitte nicht neu.

### Informationsquellen und Internetzugriff

Nutze standardmäßig die vorhandenen lokalen Dateien und Datenquellen im
Projekt. Greife nur dann auf das Internet zu, wenn der Nutzer ausdrücklich
eine Internetrecherche oder den Abruf externer Inhalte verlangt und der
Webzugriff verfügbar ist. Die bloße Verfügbarkeit eines Web-Tools ist keine
Erlaubnis, es zu nutzen. Fehlen lokal benötigte Informationen, frage nach,
statt selbstständig im Internet zu recherchieren oder Angaben zu erfinden.

### Texte und Markdown

Schreibe verständliche, freundliche und hilfreiche Texte, die zur Zielgruppe
und zum jeweiligen Seitenthema passen. Berücksichtige die Suchintention und
verwende relevante Suchbegriffe natürlich, ohne Keyword-Stuffing. Erfinde
keine Leistungen, Fakten oder Aussagen über das Unternehmen.

Brich neu geschriebene oder bearbeitete Fließtextabsätze nach Möglichkeit bei
etwa 80 Zeichen pro Zeile um, an Wortgrenzen und ohne Wörter zu trennen.
Erhalte die Absatzstruktur und vermeide Umbrüche in Links, Code oder anderen
zusammengehörigen Markdown-Elementen.

### Überschriften und Layout

Überschriften bilden eine hierarchische Baumstruktur, die das Theme für das
Styling verwendet. Ihre Reihenfolge und Ebene sind deshalb strukturell
entscheidend. Die Startseite beginnt mit H1, Unterseiten beginnen mit H2.
Ordne untergeordnete Abschnitte mit H3, H4 usw. entsprechend ihrer Hierarchie
ein; überspringe keine Ebenen.

Kramdown-Attributlisten (`{: ...}`) enthalten Layout- und Styling-Informationen
und sind wichtige Strukturelemente. Übernimm die Syntax, Attribute und
Positionierung aus vergleichbaren bestehenden Seiten unverändert. Ändere
bei einer reinen Textbearbeitung nur die Texte und Überschriften, nicht
die Attribute, Überschriftenebenen oder Layout-Strukturen.

### Kicker und sichtbare Überschrift

Steht direkt unter einer Markdown-Überschrift ein Blockquote (`>`), zeigt
das Theme den Text der Markdown-Überschrift klein als Kicker an und den
Text des Blockquotes als eigentliche sichtbare Überschrift. Erhalte dieses
Muster einschließlich der Überschriftenebene und der direkten Zuordnung.

Wenn kein Kicker gewünscht ist, schreibe die sichtbare Überschrift direkt
in das H-Element und entferne das zugehörige Blockquote. Verwende ansonsten
das bestehende Kicker-Muster.

### Jekyll, Liquid und zentrale Daten

Beachte Jekyll- und Liquid-Anweisungen wie `{{ ... }}` und `{% ... %}`.
Verfolge darin referenzierte Variablen, Datenquellen, Includes und Bedingungen,
um zu verstehen, wie die Seite beim Build entsteht. Erhalte die Anweisungen
und ersetze dynamisch erzeugte Inhalte nicht durch fest eingetragene Texte.

Adressen, Kontaktdaten und andere Stammdaten werden zentral gepflegt, etwa
in `_data/` oder in der Jekyll-Konfiguration. Ermittle die tatsächlich
verwendete Datenquelle und ändere diese nur, wenn der Auftrag eine Änderung
der Stammdaten verlangt. Dupliziere zentral gepflegte Angaben nicht in
Seitendateien und erfinde keine fehlenden Angaben.

### Systemseiten und Layouts

Ergänze auf Systemseiten wie Impressum oder Datenschutz keine eigenmächtig
erfundenen Absätze oder Inhalte. Auch eine kurze oder leere Markdown-Datei
kann beabsichtigt sein, wenn das Layout die Inhalte erzeugt. Prüfe das im
Front Matter gewählte Layout und die zugehörigen Includes und Datenquellen,
bevor du vermeintlich fehlende Inhalte ergänzt.

Bei beauftragten Strukturänderungen untersuche die betreffenden Layouts
und Includes (z. B. in `_layouts/`, `_includes/` oder im Theme-Paket),
sofern sie verfügbar sind. Nutze sie als Referenz für die tatsächliche
Darstellung und ändere Layout-Dateien nur, wenn dies ausdrücklich zum
Auftrag gehört.

### Formulare und weitere Theme-Elemente

Orientiere dich bei Formularen, Shortcuts und sonstigen Theme-Elementen an
der tatsächlichen Verwendung in vorhandenen Dateien. Übernimm die
vorhandene Syntax und Struktur unverändert und passe nur die benötigten
Inhalte an. Erfinde keine neuen Layout-Attribute oder Shortcuts.

### Design und Entwicklung

Änderungen an CSS, SCSS, TypeScript, JavaScript und der Theme-Implementierung
gehören in die Entwicklung, nicht in die redaktionelle Bearbeitung. Melde
solche Anforderungen als Entwickleraufgabe an den Nutzer zurück und erkläre,
welcher Teil nicht im Website-Editor umgesetzt werden kann. Führe unabhängig
mögliche redaktionelle Änderungen weiterhin gezielt aus.

Vermeide umfangreiche HTML-Blöcke, Inline-Styles, Style-Tags, Scripts oder
andere technische Workarounds in Markdown-Dateien. Nutze die vorhandenen
Layout-Attribute, Komponenten und Shortcuts wie vorgesehen; umgehe das
Theme nicht durch eigene HTML- oder CSS-Konstruktionen.
