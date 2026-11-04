<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Types;

/**
 * Ergebnis der Analyse eines einzelnen Tasks, ohne Website-Dateien zu aendern.
 *
 * Ein Plan ist nur bei canResolve=true gueltig. Sonst muessen konkrete
 * questions den Informationsbedarf oder das Hindernis benennen.
 */
final class T_ResolveAssessment
{
    public string $taskId;

    public bool $canResolve;

    public string $description;

    /** @var list<string> Betroffene oder neu anzulegende Projektdateien. */
    public array $files;

    /** @var list<string> Konkrete, noch nicht ausgefuehrte Aenderungsschritte. */
    public array $changes;

    /** @var list<string> Noch offene Fragen, falls kein belastbarer Plan moeglich ist. */
    public array $questions;

    /**
     * @param string $taskId Original-ID des analysierten Tasks.
     * @param bool $canResolve Gibt an, ob ein belastbarer Plan moeglich ist.
     * @param string $description Zusammenfassung des geplanten Vorgehens.
     * @param list<string> $files Projekt-relative Zieldateien.
     * @param list<string> $changes Geplante Schritte ohne Ausfuehrung.
     * @param list<string> $questions Konkret benoetigte Zusatzinformationen.
     * @see T_Resolution
     *
     * Beispiel: new T_ResolveAssessment('T-0001', false, '', [], [], ['Welches Logo?']).
     */
    public function __construct(
        string $taskId,
        bool $canResolve,
        string $description,
        array $files,
        array $changes,
        array $questions,
    ) {
        $this->taskId = $taskId;
        $this->canResolve = $canResolve;
        $this->description = $description;
        $this->files = $files;
        $this->changes = $changes;
        $this->questions = $questions;
    }
}
