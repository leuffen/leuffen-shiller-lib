# AI-Projektkontext

Shiller konfiguriert den vom Aufrufer bereitgestellten AI Harness anhand
von `.shiller.yml`. Die Anwendung entscheidet ueber die Bearbeitungsrechte,
die Speicherung und die Benutzerinteraktion.

## Konfiguration

Die [kommentierte .shiller.yml](.shiller.yml) enthält die vollständige
Konfiguration einschließlich aller AI-Felder, Defaults und Voraussetzungen.
Der folgende Ausschnitt zeigt die AI-Projektkonfiguration.

Die Projektwurzel ist der Ordner der .shiller.yml, nicht der Document Root.
Bei getrennten Roots beide beim Repository angeben.

```yaml
version: 1.0
adapter: jekyll-polyglot:1.0
context_files:
  - .shiller.d/static-context.md
  - .shiller-context.d/project.md
user_rules_file: .shiller.d/user-rules.md
skills_dir:
  - node_modules/@leuffen/themejs2/skills
  - .shiller-skills
onsite_editor:
  instruction_file: .shiller-onsite-editor.md
ai:
  web_access: true
```

skills_dir akzeptiert weiterhin einen einzelnen String. Der Harness entdeckt
alle Skills rekursiv; Skripte werden nicht freigegeben. Ohne Angabe gilt der
bisherige ThemeJS2-Default. Das bestehende Konfigurationsfeld `onsite_editor.instruction_file` bleibt
erhalten und gilt fuer alle konfigurierten AI-Kontexte. Die optionale Instruction-Datei wird relativ zur
Projektwurzel aufgelöst und als SystemPrompt gesendet. Projektkontext aus
`context_files` (Dateipfad oder geordnete Liste) und die vorhandenen
AI-Einstellungen bleiben aktiv. Ohne Feld wird nur eine vorhandene
`.shiller-context.d/project.md` geladen. Andere Dateien werden nicht
automatisch als Projektkontext eingebunden.
Webzugriff ist bei aktivierter Konfiguration verfuegbar. Die Anwendung
kann die Nutzung auf ausdrueckliche Benutzeranfragen beschraenken.

## AI Harness eines Projekts konfigurieren

Die projektweite AI-Konfiguration erfolgt auf einem vom Aufrufer
bereitgestellten, noch nicht gestarteten Context. Der ShillerRepository
verwendet dazu seine bereits geladene .shiller.yml.

```php
$site = new \Leuffen\Shiller\Access\ShillerRepository('/srv/site/docs', projectRoot: '/srv/site');
$context = new \Phore\AiHarness\AiContext();
$site->configureAiHarness($context);
```

Der Context enthaelt danach Projektkontext, User Rules, Skills, konfigurierte
System-Anweisungen und Runtime-/Web-Einstellungen. Die Instanz bleibt
unveraendert in ihrer Identitaet; es gibt keinen Clone. Die Methode
registriert Templates und konfigurierte Kontextdateien als read-only
Dateisysteme sowie die einzelne konfigurierte User-Rules-Datei.
Mit `allowUserRulesEdit: true` kann ein autorisierter Aufrufer ausschliesslich
diese bestehende Datei zur Bearbeitung freigeben. Ohne Freigabe bleibt sie
read-only. Neue Regeln duerfen nur nach ausdruecklichem Speicherauftrag oder
Bestaetigung gespeichert werden.

Das Website-Dateisystem registriert der Aufrufer separat mit eigener Policy.
Dabei muss `getAiResourceWriteIgnore()` zusaetzlich zu den auftragsbezogenen
Schreibbeschraenkungen in `FileSystemPolicy::writeIgnore` eingehen. So bleiben
die separat registrierten Ressourcen auch bei breiten Website-Presets
geschuetzt.

Standalone-CLI-Workflows nutzen denselben internen Konfigurator und
ergaenzen ihre eigenen Quellen und Basis-Skills. Explizite Workflow-Overrides
bleiben auf den jeweiligen Workflow begrenzt.

## Static Context und dauerhafte User Rules

`.shiller.d/static-context.md` beschreibt die technische Funktionsweise und
allgemeinen Schreib- und Bearbeitungsregeln der Website, ohne individuelle
Daten. Über `context_files` wird sie read-only als Kontext eingebunden.
Design- und Entwicklungsaufgaben werden gemeldet; keine HTML-, CSS- oder
Script-Workarounds in Markdown.


Mit `user_rules_file` wird eine einzelne Markdown-Datei in der Projektwurzel
als dauerhafte Sammlung bestätigter Projektfakten und Benutzerregeln eingerichtet. Die Datei muss
bereits existieren; ohne Eintrag oder mit `null` bleibt die Funktion aus.
Verwende eine separate Regeldatei, keinen System-Prompt, Skill oder
Konfigurationsdatei. Beispiel für ihren Inhalt:

```markdown
# User Rules

## Global
- Alle Texte in verständlicher Sprache formulieren.
- Keine unbelegten Versprechen.

## Page: index.md
- Einleitung kurz halten.

## Page: _intl/en/index.md
- Britisches Englisch verwenden.
```

Die gemeinsame Repository-Konfiguration liest die Datei vor der Bearbeitung.
Globale Regeln gelten für alle Seiten, Page-Regeln nur für den exakten
Document-Root-relativen Quellpfad und haben dort bei Stilpräferenzen Vorrang.
Die AI soll beim ersten Zugriff und bei jeder Folgeanfrage die aktuelle
Regeldatei lesen, beachten und nur auf ausdrücklichen Benutzerwunsch ändern.
Einmalige Änderungswünsche werden nicht automatisch zu dauerhaften Regeln.
Bei neuen oder geänderten Stammdaten fragt die AI, ob diese dauerhaft in
`user-rules.md` übernommen werden sollen. Erst nach ausdrücklicher Zustimmung
oder direktem Speicherauftrag darf sie die Datei ändern. Die Datei ist die
zentrale Wissensquelle für bestätigte Fakten; die tatsächlichen Datenquellen
der Website bleiben für die Darstellung maßgeblich.

Die einzelne bestehende Regeldatei ist nur nach expliziter Freigabe
ueber `configureAiHarness($context, allowUserRulesEdit: true)` beschreibbar.
Andere Dateien werden dadurch nicht freigegeben. Die Erkennung eines
Speicherauftrags ist eine Prompt-Anweisung, keine technische Intent-Pruefung.

## Seitenindex

`$site->getPageIndexCsv()` liefert die vorhandenen Markdown-Seiten und
Uebersetzungen mit Document-Root-relativen Pfaden, Titeln, URLs,
Veroeffentlichungsstatus und Sprache. Die URLs stammen vom Shiller-Adapter;
bei unveroeffentlichten Seiten bleiben sie leer. Die Methode startet keine
AI-Anfrage. Die Anwendung kann den Index bei Bedarf als TextPrompt mitgeben.
