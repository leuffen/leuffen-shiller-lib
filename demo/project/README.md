# Demo Project

Dieses Verzeichnis repraesentiert das einzelne Webseitenprojekt, das
`../template` nutzt. Shiller wird von hier ausgefuehrt; der Document Root ist
`docs/`.

Beispiele:

```sh
php ../../bin/shiller context build
php ../../bin/shiller context build .shiller-context.d/raw --focus "Kontaktdaten"
php ../../bin/shiller ai --debug adjust index.md _data/general.yml
php ../../bin/shiller ai adjust index.md
php ../../bin/shiller revert index.md _data/general.yml
```

`.shiller-context.d/project.md` ist der zentrale Projektkontext. Weitere
Markdown-Dateien direkt in `.shiller-context.d/` werden ebenfalls immer als\nKontext geladen, außer Dateien mit dem Suffix `.rules.md`.\n`project.rules.md` enthält nur zusätzliche Regeln für `context build` und wird\nnicht als normaler Kontext geladen. `.shiller-context.d/raw/` enthaelt Rohdaten fuer
`context build` und wird bei `ai adjust` nicht direkt eingebunden.

`docs/.shiller.yml` konfiguriert `skills_dir` fuer die ThemeJS2-Skills.
Shiller analysiert den Projektkontext einmal und nutzt einen Checkpoint
fuer die getrennte Anpassung jeder ausgewählten Datei. `--debug` zeigt
Skill- und Dateischritte. `docs/_data/general.yml.d.json` beschreibt
die strukturierten Felder.
