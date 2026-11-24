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
context_files: .shiller-context.d/project.md
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

getFileIndex() liefert einen CSV-Index vorhandener Markdown-Seiten aus
dem bestehenden Adapter-Seitenbaum mit Dateipfad, Titel, URL,
Veröffentlichungsstatus und Sprache. Nicht veröffentlichte Seiten erhalten
keine URL. Die Adapter bestimmen die tatsächlichen Übersetzungen und Links;
es werden keine URLs geraten. Der Index ist automatisch Teil des AI-Kontexts.
Der Harness kann UTF-8-Dateien im Document Root auflisten, durchsuchen
(Literalsuche) und lesen, auch in anderen Sprachen. Alle direkten
Dateimutationen sind deaktiviert. getAvailableSkills() und result.availableSkills
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
Die Anwendung speichert vorgeschlagene Inhalte selbst nach ihren
Berechtigungs- und Revisionsprüfungen. Für einen Agenten mit
Dateiendungs-Allowlist muss der Harness Schreibrechte auch am tatsächlich
aufgelösten Ziel durchsetzen. Die vorhandene Literalsuche ist keine
Regex-Suche. Diese Funktionen sind nicht durch eine zweite
Shiller-Implementierung nachgebildet.
