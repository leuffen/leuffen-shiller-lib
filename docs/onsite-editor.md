# Shiller Onsite Editor

Der Onsite Editor nutzt ein bereits konfiguriertes ShillerRepository und
den Phore AI Harness. Er eignet sich für einen Webeditor, der Vorschläge
und Rückmeldungen anzeigt und erst nach einer Benutzerentscheidung speichert.
Text-Replacements und vollständige Neufassungen laufen über
EditableTextPrompt aus dem Harness; es gibt keine zweite Edit-Engine.

## Konfiguration

Die [kommentierte .shiller.yml](.shiller.yml) enthält die vollständige
Konfiguration einschließlich aller AI-Felder, Defaults und Voraussetzungen.
Der folgende Ausschnitt zeigt die spezifischen Onsite-Editor-Einstellungen.

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
bisherige ThemeJS2-Default. Die optionale Instruction-Datei wird relativ zur
Projektwurzel aufgelöst und als SystemPrompt gesendet. Projektkontext aus
`context_files` (Dateipfad oder geordnete Liste) und die vorhandenen
AI-Einstellungen bleiben aktiv. Ohne Feld wird nur eine vorhandene
`.shiller-context.d/project.md` geladen. Andere Dateien werden nicht
automatisch als Projektkontext eingebunden.
Webzugriff ist nur bei aktivierter Konfiguration und expliziter Nachfrage
vorgesehen. Das ist eine AI-Anweisung, keine technische Web-Sperre je Prompt.

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

Bei `editText()`, `editTexts()`, `editFile()` und `createFile()` ist die
konfigurierte Regeldatei über das bestehende Harness-Tool `filesystem_edit`
direkt bearbeitbar. Andere Dateien bleiben read-only. Die AI kann Fakten und Regeln
ergänzen, aktualisieren und entfernen, aber keine weiteren Dateien anlegen
oder löschen. Die Anwendung autorisiert den Benutzer vor diesen Aufrufen.
`createContext()` und Inline Edit geben die Regeln nur lesend frei;
`createContext(allowUserRulesEdit: true)` aktiviert die einzelne Schreibfreigabe.
Inline Completion bleibt ohne Projektkontext.

Im folgenden Beispiel wird eine Regel gespeichert, ohne die Website-Datei
zu überschreiben. Der Editor stammt aus dem nachfolgenden Grundbeispiel.

```php
$result = $editor->editFile(
    'index.md',
    'Merke dir dauerhaft: Die Einleitung dieser Seite soll kurz bleiben.'
);
echo $result->message;
var_dump($result->userRulesChanged); // Erwartet: true.
var_dump($result->hasChanges);       // Erwartet: false, kein Text-Edit.
```

Für eine globale Regel etwa "Merke dir für alle Seiten: keine Superlative"
anfordern. Bei `editText()` den exakten Seitenpfad als Alias übergeben, wenn
der Auftrag diese Seite betrifft. Die gespeicherten Regeln wirken auch in
späteren Aufrufen. `userRulesChanged` wird aus dem tatsächlichen Dateiinhalt
ermittelt; `hasChanges` und `changes` beziehen sich weiterhin nur auf
Textvorschläge. Regeländerungen werden sofort gespeichert und sind nicht
Teil eines Website-Text-Undo. Die Erkennung eines dauerhaften Regelwunsches
ist eine Prompt-Anweisung, keine unabhängige technische Intent-Prüfung.

## Datei bearbeiten und weiter chatten

```php
use Leuffen\Shiller\Access\ShillerRepository;
use Leuffen\Shiller\OnsiteEditor\ShillerOnsiteEditor;

$site = new ShillerRepository('/srv/site/docs', projectRoot: '/srv/site');
$editor = new ShillerOnsiteEditor($site);

$result = $editor->editFile('index.md', 'Überarbeite die Einleitung.');
echo $result->content;         // Vollständiger vorgeschlagener Dateiinhalt.
echo $result->message;         // Separate AI-Antwort.
echo $result->originalContent; // Ausgangsstand für Diff oder Undo.

$next = $editor->editFile(
    'index.md',
    'Kürze die neue Einleitung.',
    previousResponseId: $result->responseId,
    currentContent: $result->content,
);
```

currentContent ist der aktuelle, ggf. noch nicht gespeicherte Editorinhalt.
Ohne diesen Parameter wird die vorhandene Datei neu gelesen. Die Response-ID
ist die letzte Antwort der Conversation und muss durch die Anwendung
dem Benutzer, Projekt und zulässigen Bearbeitungsziel zugeordnet werden.
Sie ist kein Autorisierungsmerkmal. Speichere den aktuellen Text separat.
editText() eignet sich auch für beliebige Strings oder unsaved Editor-Inputs;
createFile() liefert nur einen Vorschlag für eine noch nicht vorhandene Datei.

## Mehrere Texte in einem Aufruf

```php
use Phore\AiHarness\PromptType\EditableTextPrompt;

$intro = new EditableTextPrompt('Willkommen.', alias: 'intro');
$footer = new EditableTextPrompt('Kontakt.', alias: 'footer');

$result = $editor->editTexts([$intro, $footer], 'Formuliere beide freundlicher.');
echo $intro->getContent();
echo $footer->getContent();
echo $result->message;
foreach ($result->pendingTasks as $task) {
    echo $task->description . ': ' . $task->reason;
}
```

changes enthält ausschließlich tatsächlich geänderte Textpuffer (mit
Ausgangs- und aktuellem Text), keine bereits gespeicherten Dateien.
Bei einem einzelnen Text stehen originalContent und content zusätzlich
direkt im Ergebnis. status ist completed (Änderungen ohne offene Aufgaben),
partial (Änderungen und offene Aufgaben), blocked (offene Aufgaben ohne
Änderungen) oder answered (keine Änderungen und keine offenen Aufgaben).
pendingTasks enthält description, files und reason als strukturierte
AI-Rückmeldung. Die Anwendung kann offene Aufgaben an andere Prozesse
weitergeben. Technische Fehler werden als Exceptions weitergegeben.
Bei editTexts bleiben bereits geänderte Input-Objekte auch bei späterem
Fehler verändert; die Anwendung entscheidet, ob sie diese übernimmt.

## Kontext und Inline-Editor

Das konfigurierte `template_dir` wird als separates Read-only-Filesystem
`templates` eingebunden. Die AI kann Vorlagen auflisten, lesen und durchsuchen.
Sie dienen als Referenz für Zielstruktur, Layout, Komponenten und Datenformat.
Beispieltexte und Mock-Daten sind durch tatsächliche Projektinhalte und
verifizierte Fakten zu ersetzen; fehlende Angaben dürfen nicht erfunden werden.
Vorlagen selbst werden niemals geändert. Ohne `template_dir` entfällt dieses
Filesystem; ein explizit konfigurierter Pfad muss lesbar sein.

getFileIndex() liefert einen CSV-Index vorhandener Markdown-Seiten aus
dem bestehenden Adapter-Seitenbaum mit Dateipfad, Titel, URL,
Veröffentlichungsstatus und Sprache. Nicht veröffentlichte Seiten erhalten
keine URL. Die Adapter bestimmen die tatsächlichen Übersetzungen und Links;
es werden keine URLs geraten. Der Index ist automatisch Teil des AI-Kontexts.
Der Harness kann UTF-8-Dateien im Document Root auflisten, durchsuchen
(Literalsuche) und lesen, auch in anderen Sprachen. Direkte Website-Dateimutationen sind deaktiviert; nur die explizit
konfigurierte User-Rules-Datei kann separat freigegeben werden. getAvailableSkills() und result.availableSkills
liefern entdeckte Skill-Namen; dies ist kein Nachweis geladener Skills.

```php
$context = $editor->createContext();
echo $context->text('Welche Seiten benötigen eine verständlichere Einleitung?');
```

inlineCompletion() und inlineEdit() verwenden die vorhandenen
Automation-DTOs und geben weiterhin ausschließlich die Einfügung oder den
Replacement-Text zurück. Completion verzichtet auf den vollständigen
Projektkontext; Inline Edit nutzt ihn lesend. Die vorhandene
ShillerAiEditorAction bleibt separat nutzbar.

## Grenzen

Diese Version erlaubt keine projektweiten direkten AI-Schreibzugriffe.
Die konfigurierte User-Rules-Datei ist die einzige Ausnahme.
Die Anwendung speichert vorgeschlagene Inhalte selbst nach ihren
Berechtigungs- und Revisionsprüfungen. Für einen Agenten mit
Dateiendungs-Allowlist muss der Harness Schreibrechte auch am tatsächlich
aufgelösten Ziel durchsetzen. Die vorhandene Literalsuche ist keine
Regex-Suche. Diese Funktionen sind nicht durch eine zweite
Shiller-Implementierung nachgebildet.
