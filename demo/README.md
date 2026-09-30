# Shiller Demo

Die Demo trennt bewusst das wiederverwendbare Template vom nutzenden Webseitenprojekt:

- `template/` repraesentiert ein Template-Projekt mit `_tpl/_root` und tag-basierten Seitenvorlagen.
- `project/` repraesentiert das Webseitenprojekt, in dem Shiller ausgefuehrt wird.

`template/_tpl/_root/docs/_rules.d/` liefert initiale Rule-Sets aus. Im installierten Projekt liegen sie unter `project/docs/_rules.d/` und werden dort vom `ShillerRuleSetManager` gegen den aktuellen Content-Bestand ausgewertet.
