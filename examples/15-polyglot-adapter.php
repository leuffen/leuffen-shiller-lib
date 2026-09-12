<?php

declare(strict_types=1);

// AUSFÜHRBARER ENTWURF der reinen Zuordnung, keine vollständige Adapterimplementierung.
// Kein Filesystem-Zugriff: Der Aufrufer liefert bereits rootgeprüfte Pfade und Header.
// Der gemeinsame Builder prüft Existenz/Rechte und erzeugt TreeNode/FileEntry.
// PHPDoc-Rückgabe: array{nodePath:string, groupPath:string, filePath:string,
// language:string, isRootDocument:bool, candidates:array<string,string>}

// Beispiel:
// $map = require __FILE__;
// $result = $map('en/leistungen/diagnostik.md', ['title'=>'Diagnostics'],
//     ['de','en','fr'], 'de');
// nodePath/groupPath='leistungen/diagnostik.md', filePath='en/leistungen/diagnostik.md'
// language='en', isRootDocument=false, candidates['fr']='fr/leistungen/diagnostik.md'
// 'leistungen/index.md' -> nodePath='leistungen', language='de'.
// Ein Ordner ohne Index erzeugt der Builder separat mit file=null.

return static function (
    string $path,
    array $header,
    array $languages,
    string $defaultLanguage,
): array {
    if (!in_array($defaultLanguage, $languages, true)) {
        throw new InvalidArgumentException('Standardsprache nicht konfiguriert.');
    }
    foreach (['pid', 'page_id', 'lang'] as $reserved) {
        if (array_key_exists($reserved, $header)) {
            throw new InvalidArgumentException('Sprach-/ID-Felder gehören nicht in Polyglot-Dateiheader.');
        }
    }

    $parts = explode('/', $path);
    $language = $defaultLanguage;
    if (in_array($parts[0], $languages, true)) {
        $language = array_shift($parts);
        if ($language === $defaultLanguage) {
            throw new InvalidArgumentException('Die Standardsprache liegt direkt im Root.');
        }
    }
    $groupPath = implode('/', $parts);
    if (!preg_match('~^(.+)\.(md|html)$~D', $groupPath, $match)) {
        throw new InvalidArgumentException('Erwartet: Markdown- oder HTML-Seite.');
    }
    foreach (explode('/', $match[1]) as $segment) {
        if (in_array($segment, $languages, true)) {
            throw new InvalidArgumentException('Reservierter Sprachcode im neutralen Seitenpfad.');
        }
    }

    $parent = dirname($groupPath);
    $nodePath = basename($match[1]) === 'index'
        ? ($parent === '.' ? '' : $parent)
        : $groupPath;
    $candidates = [];
    foreach ($languages as $code) {
        $candidates[$code] = $code === $defaultLanguage
            ? $groupPath
            : $code . '/' . $groupPath;
    }

    return [
        'nodePath' => $nodePath,
        'groupPath' => $groupPath,
        'filePath' => $path,
        'language' => $language,
        'isRootDocument' => $language === $defaultLanguage,
        'candidates' => $candidates,
    ];
};
