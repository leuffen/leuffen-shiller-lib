<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Types;

/** Wurzel-DTO des Change Requests fuer AiContext::struct(). */
final class T_Changes
{
    /**
     * Quelldateien mit lueckenlosen Quotes, nach Dateiname sortiert.
     *
     * @var list<T_QuoteFile>
     */
    public array $quotes;

    /**
     * Aenderungswuensche ohne Loesung.
     *
     * @var list<T_Task>
     */
    public array $tasks;

    /**
     * Umsetzungsplaene aus shiller edit resolve; bei create leer.
     *
     * @var list<T_Resolution>
     */
    public array $resolutions;

    /**
     * @param list<T_QuoteFile> $quotes Vollstaendige Quelldateien.
     * @param list<T_Task> $tasks Identifizierte Aenderungswuensche.
     * @param list<T_Resolution> $resolutions Bei create []; resolve fuegt Plaene hinzu.
     * @see T_QuoteFile
     *
     * Beispiel: new T_Changes([new T_QuoteFile('feedback.txt', [new T_Quote('Text', ['T-0001'])])], [new T_Task('T-0001', 'Text bearbeiten')], []).
     */
    public function __construct(array $quotes, array $tasks, array $resolutions)
    {
        $this->quotes = $quotes;
        $this->tasks = $tasks;
        $this->resolutions = $resolutions;
    }

    /**
     * Serialisiert den unveraenderten DTO-Baum fuer YAML ohne ID-Umschreibung.
     *
     * @return array{quotes:list<array>,tasks:list<array>,resolutions:list<array>}
     * @see \Leuffen\Shiller\Codec::emitYaml()
     *
     * Beispiel: \Leuffen\Shiller\Codec::emitYaml($changes->toArray()).
     */
    public function toArray(): array
    {
        return [
            'quotes' => array_map(
                static fn(T_QuoteFile $file): array => [
                    'fileName' => $file->fileName,
                    'quotes' => array_map(
                        static fn(T_Quote $quote): array => get_object_vars($quote),
                        $file->quotes,
                    ),
                ],
                $this->quotes,
            ),
            'tasks' => array_map(static fn(T_Task $task): array => get_object_vars($task), $this->tasks),
            'resolutions' => array_map(
                static fn(T_Resolution $resolution): array => get_object_vars($resolution),
                $this->resolutions,
            ),
        ];
    }
}
