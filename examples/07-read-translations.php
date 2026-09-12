<?php

declare(strict_types=1);

// ENTWURFSBEISPIEL: Schiller-API noch nicht implementiert. Siehe examples/README.md.
// Rückgaben sind erwartete Werte, keine gemessenen Ausgaben.

use Leuffen\Schiller\SchillerDir;
use Leuffen\Schiller\TranslationSet;
use Phore\FileSystem\PhoreDirectory;

return static function (PhoreDirectory $root): TranslationSet {
    $site = new SchillerDir($root);
    $translations = $site->page('leistungen/diagnostik.md')->translations();

    // pageId='diagnostics'
    // availableLanguages=['de','en'], missingLanguages=['fr']
    // items: list<TranslationInfo>:
    // de: state='existing', path='leistungen/diagnostik.md', sourceLanguage='de'
    // en: state='existing', path='en/diagnostics.md', sourceLanguage='en'
    // fr: state='fallback', path=null, sourceLanguage='de'
    // Für reader ist canCreate überall false.
    foreach ($translations->items as $translation) {
        if ($translation->path === null) {
            continue; // Fallback ist keine eigene bearbeitbare Sprachdatei.
        }
        $document = $site->page($translation->path)->read(); // PageDocument
        $translatedTitle = $document->header->string('title'); // ?string
        $translatedBody = $document->content;                 // string
        // en liefert 'Diagnostics' und "## Diagnostics\n".
    }

    // Verbotene Varianten werden nicht ausgegeben und nicht als fehlend angeboten.
    return $translations;
};
