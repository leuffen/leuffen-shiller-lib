# Shiller Demo

Die Demo trennt bewusst das wiederverwendbare Template vom nutzenden Webseitenprojekt:

- `template/` repraesentiert ein Template-Projekt mit `_tpl/_root` und tag-basierten Seitenvorlagen.
- `project/` repraesentiert das Webseitenprojekt, in dem Shiller ausgefuehrt wird.

Die Anpassung verwendet die ueber `skills_dir` konfigurierten ThemeJS2-Skills
anstelle kopierter `_rules.d`-Dateien. Projektfakten liegen unter
`project/.shiller-context.d/`, Sidecars unter `project/docs/_data/`.
