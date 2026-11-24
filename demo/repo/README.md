# Demo Repository

Dieses Verzeichnis stellt ein eigenständiges Website-Repository dar. Die
`.shiller.yml` liegt direkt in der Projektwurzel; alle relativen Pfade in
dieser Konfiguration beziehen sich auf dieses Verzeichnis, nicht auf `docs/`.

- `docs/`: installierte Website mit Markdown-Seiten und YAML-Sidecars.
- `_tpl/`: lokale Mock-Vorlagen. `_root/` enthält die Startstruktur für
  `init`, `pages/` enthält tag-basierte Seitenvorlagen.
- `.shiller-context.d/`: Projektkontext und Rohdaten für `context build`.
- `node_modules/@leuffen/themejs2/skills/`: bei AI-Anpassungen benötigte
  ThemeJS2-Skills (separat bereitzustellen).

## Ausführung

Installiere die Composer-Abhängigkeiten der Shiller-Library und führe die
Befehle aus `demo/repo/` aus. Die Website ist bereits initialisiert.
Die CLI kann aus einer lokalen Installation oder über den folgenden Pfad
zur Library aufgerufen werden; dieser Aufrufpfad ist kein Konfigurationspfad.

```sh
php ../../bin/shiller install --tags demo
php ../../bin/shiller revert index.md
```

Zum erneuten Initialisieren einer Kopie des Demo-Projekts:

```sh
php ../../bin/shiller init --template-dir _tpl --tags demo
```

Für AI-Aufrufe müssen die ThemeJS2-Skills im konfigurierten lokalen Pfad
bereitstehen und ein AI-Modell verfügbar sein:

```sh
php ../../bin/shiller context build
php ../../bin/shiller adapt index.md _data/general.yml --debug
```

Die Konfiguration lädt ausschließlich den angegebenen Projektkontext
`.shiller-context.d/project.md`. `project.rules.md` enthält zusätzliche
Regeln für den Context-Build; die Rohdaten liegen unter `raw/`.
Die Daten und Texte in den Vorlagen sind Mock-Daten.
