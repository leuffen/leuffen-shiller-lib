# Repository-Regeln

## Dateisystemzugriff

Für Datei- und Verzeichniszugriffe ist grundsätzlich `phore/filesystem` mit `phore_file()`, `phore_dir()` und `phore_uri()` zu verwenden. Native PHP-Dateisystemfunktionen dürfen nur verwendet werden, wenn `phore/filesystem` für die konkret benötigte Operation keine entsprechende API bereitstellt, insbesondere für reine Symlink-Prüfungen; parallele eigene Datei-Lese-, Schreib- oder Verzeichnislogik neben `phore/filesystem` ist zu vermeiden.
