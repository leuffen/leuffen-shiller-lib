<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Types;

/** Wurzel-DTO des Change Requests fuer AiContext::struct(). */
final class T_Changes
{
    /** Textbloecke und Assetnamen mit Task-Referenzen.
     * @var list<T_Quote>
     */
    public array $quotes;

    /** Noch nicht geloeste Aenderungswuensche.
     * @var list<T_Task>
     */
    public array $tasks;

    /** In Phase 1 leer, spaeter gruppierte Loesungsplaene.
     * @var list<T_Resolution>
     */
    public array $resolutions;

    /**
     * @param list<T_Quote> $quotes Originaltext und Task-Referenzen.
     * @param list<T_Task> $tasks Aenderungswuensche.
     * @param list<T_Resolution> $resolutions Im Edit-Intake zwingend leer.
     * @see T_Resolution
     *
     * Beispiel: new T_Changes([new T_Quote('Text', ['T-0001'])], [new T_Task('T-0001', 'Text bearbeiten')], []).
     */
    public function __construct(array $quotes, array $tasks, array $resolutions)
    {
        $this->quotes = $quotes;
        $this->tasks = $tasks;
        $this->resolutions = $resolutions;
    }

    /**
     * Nur YAML-Serialisierung; keine IDs oder Inhalte werden nachbearbeitet.
     *
     * @return array{quotes:list<array>,tasks:list<array>,resolutions:list<array>}
     * @see \Leuffen\Shiller\Codec::emitYaml()
     *
     * Beispiel: \Leuffen\Shiller\Codec::emitYaml($changes->toArray()).
     */
    public function toArray(): array
    {
        return [
            'quotes' => array_map(static fn(T_Quote $quote): array => get_object_vars($quote), $this->quotes),
            'tasks' => array_map(static fn(T_Task $task): array => get_object_vars($task), $this->tasks),
            'resolutions' => array_map(
                static fn(T_Resolution $resolution): array => get_object_vars($resolution),
                $this->resolutions,
            ),
        ];
    }
}
