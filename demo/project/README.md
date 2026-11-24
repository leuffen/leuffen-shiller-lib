# Demo Project

Dieses Verzeichnis repraesentiert das einzelne Webseitenprojekt, das
`../template` nutzt. Shiller wird von hier ausgefuehrt; der Document Root ist
`docs/`.

Beispiele:

```sh
php ../../bin/shiller context build
php ../../bin/shiller context build .shiller-context.d/raw --focus "Kontaktdaten"
php ../../bin/shiller adapt index.md _data/general.yml --debug
php ../../bin/shiller adapt index.md
php ../../bin/shiller revert index.md _data/general.yml
```

`.shiller-context.d/project.md` ist der zentrale Projektkontext. Weitere
Markdown-Dateien direkt in `.shiller-context.d/` werden ebenfalls als
Kontext geladen, außer Dateien mit dem Suffix `.rules.md`.
`project.rules.md` enthält nur zusätzliche Regeln für `context build` und wird\nnicht als normaler Kontext geladen. `.shiller-context.d/raw/` enthaelt Rohdaten fuer
`context build` und wird bei `adapt` nicht direkt eingebunden.

`.shiller.yml` in der Projektwurzel legt die Konfigurationsversion `1.0`,
den Adapter `jekyll-polyglot:1.0`, `template_dir` und `skills_dir` fest. Das ThemeJS2-Paket muss vorher unter
`node_modules/@leuffen/themejs2/` bereitgestellt werden.
Shiller analysiert den Projektkontext einmal und nutzt einen Checkpoint
fuer die getrennte Anpassung jeder ausgewählten Datei. `--debug` zeigt
Skill- und Dateischritte. `docs/_data/general.yml.d.json` beschreibt
die strukturierten Felder.
