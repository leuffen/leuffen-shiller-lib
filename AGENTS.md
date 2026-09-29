# Repository-Regeln

## Dateisystemzugriff

Für Datei- und Verzeichniszugriffe sowie sicherheitsrelevante Pfad-, Root-, Symlink-, Hardlink-, Existenz-, Lesbarkeits-, Schreibbarkeits- und Traversierungsprüfungen ist ausschließlich `phore/filesystem` zu verwenden. Restrictions werden am Phore-Einstieg gebunden und über die daraus abgeleiteten `PhoreUri`-, `PhoreFile`- und `PhoreDirectory`-Objekte weitergereicht; native PHP-Dateisystemprüfungen, eigene Walk- oder Pfadvalidierungs-Helper und parallele Catch-and-Re-throw-Logik dürfen dafür nicht ergänzt werden. Schiller-eigene fachliche Validierungen, etwa Tags, Referenzsyntax oder Kollisionen innerhalb eines Installationsplans, bleiben Aufgabe dieser Library.
