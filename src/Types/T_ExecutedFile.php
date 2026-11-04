<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Types;

/** Vorgeschlagene vollstaendige Textdatei mit verpflichtendem Ausgangs-Hash. */
final class T_ExecutedFile
{
    /**
     * @param string $path Projekt-relativer Dateipfad aus der Resolution.
     * @param string $beforeSha256 SHA-256 des unveraenderten Eingabeinhalts.
     * @param string $content Vollstaendiger Zielinhalt, nicht nur ein Ausschnitt.
     * @see T_EditExecution
     *
     * Beispiel: new T_ExecutedFile('docs/index.md', hash('sha256', 'Alt'), 'Neu').
     */
    public function __construct(
        public string $path,
        public string $beforeSha256,
        public string $content,
    ) {
    }
}
