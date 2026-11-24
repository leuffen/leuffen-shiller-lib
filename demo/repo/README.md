# Demo Repository

Dieses Verzeichnis stellt ein eigenständiges Website-Repository dar. Die
`.shiller.yml` liegt in der Projektwurzel. Alle relativen Konfigurationspfade
beziehen sich auf dieses Verzeichnis, nicht auf `docs/`.

- `docs/`: Website-Seiten und YAML-Sidecars mit fiktiven Beispieldaten.
- `.shiller.d/static-context.md`: read-only Technik- und Bearbeitungsregeln.
- `.shiller.d/user-rules.md`: bestätigte individuelle Fakten und dauerhafte
  Benutzerregeln; Änderungen erst nach ausdrücklicher Zustimmung.
- `.shiller-context.d/`: Projektkontext und Rohdaten für `context build`.
- `node_modules/@leuffen/themejs2/`: optional bereitzustellendes Theme-Paket
  mit Vorlagen unter `_tpl/` und AI-Skills unter `skills/`.

Die Demo enthält keine eigenen Template-Dateien. Für Template- und
AI-Anpassungsworkflows muss das Theme-Paket im konfigurierten Pfad verfügbar
sein. Der normale Repository-Zugriff benötigt das Paket nicht.

## Ausführung

Installiere die Composer-Abhängigkeiten der Shiller-Library und führe die
folgenden Befehle aus `demo/repo/` aus. Der relative CLI-Aufrufpfad ist
kein Konfigurationspfad. Für AI-Aufrufe wird ein AI-Modell benötigt.

```sh
php ../../bin/shiller context build
php ../../bin/shiller adapt index.md _data/general.yml --debug
```

Die Konfiguration lädt den statischen Kontext aus `.shiller.d/static-context.md`
und den aufbereiteten Projektkontext aus `.shiller-context.d/project.md` nur
lesend. `.shiller.d/user-rules.md` ist separat editierbar. `project.rules.md` enthält zusätzliche Regeln
für den Context-Build; die Rohdaten liegen unter `raw/`.
