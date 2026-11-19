# Shiller Demo

Die Demo trennt bewusst das wiederverwendbare Template vom nutzenden Webseitenprojekt:

- `template/` repraesentiert ein Template-Projekt mit `_tpl/_root` und tag-basierten Seitenvorlagen.
- `project/` repraesentiert das Webseitenprojekt, in dem Shiller ausgefuehrt wird.

Die Anpassung verwendet die ueber `skills_dir` konfigurierten ThemeJS2-Skills
anstelle kopierter `_rules.d`-Dateien. Projektfakten liegen unter
`project/.shiller-context.d/`, Sidecars unter `project/docs/_data/`.

Ausfuehrung aus `demo/project/` nach Installation der Composer-Abhaengigkeiten
im Repository und Bereitstellung von `@leuffen/themejs2` im Projekt:

```sh
php ../../bin/shiller init --template-dir ../template/_tpl --tags demo
php ../../bin/shiller adapt index.md --debug
```

Die `.shiller.yml` liegt in der Projektwurzel und wird von `init`
aus `template/_tpl/_root/` uebernommen. Das `adapt`-Beispiel benoetigt
Netzwerk-/Modellzugriff und ist kein Offline-Smoke-Test.
