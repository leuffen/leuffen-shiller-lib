<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Types;

/** Strukturierte AI-Antwort fuer die Ausfuehrung genau eines Tasks. */
final class T_EditExecution
{
    /**
     * @param string $taskId Stabile ID des aktuell ausgefuehrten Tasks.
     * @param list<T_ExecutedFile> $files Vollstaendige neue Datei-Inhalte.
     * @see T_ExecutedFile
     *
     * Beispiel: new T_EditExecution('T-0001', [new T_ExecutedFile('docs/index.md', hash('sha256', 'Alt'), 'Neu')]).
     */
    public function __construct(
        public string $taskId,
        public array $files,
    ) {
    }
}
