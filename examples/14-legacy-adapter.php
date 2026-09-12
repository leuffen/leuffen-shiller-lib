<?php

declare(strict_types=1);

// AUSFÜHRBARER ENTWURF der reinen Zuordnung, keine vollständige Adapterimplementierung.
// Kein Filesystem-Zugriff: Der Aufrufer liefert bereits rootgeprüfte Pfade und Header.
// Der gemeinsame Builder prüft Existenz/Rechte und erzeugt TreeNode/FileEntry.
// PHPDoc-Rückgabe: array{nodePath:string, groupPath:string, filePath:string,
// language:string, isRootDocument:bool, candidates:array<string,string>}

// Beispiel:
// $map = require __FILE__;
// $result = $map('leistungen/diagnostik.de.md',
//     ['pid'=>'leistungen/diagnostik', 'lang'=>'de', 'title'=>'Diagnostik'],
//     ['de','en','fr'], 'de');
// nodePath/groupPath='leistungen/diagnostik.md', filePath='leistungen/diagnostik.de.md'
// candidates['fr']='leistungen/diagnostik.fr.md'; Existenz erst durch Storage prüfen.
// 'leistungen/index.de.md' mit pid='leistungen/index' -> nodePath='leistungen'.
// Eine _section.yml ohne Index erzeugt der Builder separat mit file=null.

return static function (
    string $path,
    array $header,
    array $languages,
    string $defaultLanguage,
): array {
    if (!in_array($defaultLanguage, $languages, true)) {
        throw new InvalidArgumentException('Standardsprache nicht konfiguriert.');
    }
    if (!preg_match('~^(.+)\.([^.\/]+)\.(md|html)$~D', $path, $match)) {
        throw new InvalidArgumentException('Erwartet: <pid>.<lang>.md oder .html');
    }
    [, $pid, $language, $extension] = $match;
    if (!in_array($language, $languages, true)) {
        throw new InvalidArgumentException('Unbekannte Sprache.');
    }
    if (($header['pid'] ?? null) !== $pid || ($header['lang'] ?? null) !== $language) {
        throw new InvalidArgumentException('PID oder Sprache stimmt nicht mit dem Dateipfad überein.');
    }

    $groupPath = $pid . '.' . $extension;
    $parent = dirname($pid);
    $nodePath = basename($pid) === 'index'
        ? ($parent === '.' ? '' : $parent)
        : $groupPath;
    $candidates = [];
    foreach ($languages as $code) {
        $candidates[$code] = $pid . '.' . $code . '.' . $extension;
    }

    // PID/lang bleiben Teil des Legacy-Headers; hier wird nichts umgeschrieben.
    // md/html-Mischgruppen erfordern zusätzlich den gemeinsamen Bestandsindex.
    return [
        'nodePath' => $nodePath,
        'groupPath' => $groupPath,
        'filePath' => $path,
        'language' => $language,
        'isRootDocument' => $language === $defaultLanguage,
        'candidates' => $candidates,
    ];
};
