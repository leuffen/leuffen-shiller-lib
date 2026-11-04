<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use RuntimeException;

/**
 * Separates Schema fuer die spaetere Solve-Phase; edit schreibt es nicht.
 *
 * Eine Resolution kann mehrere Tasks gemeinsam bearbeiten. Jede geplante
 * Dateiaenderung benennt die Datei sowie das Was und Wie, aber fuehrt nichts aus.
 */
final class ShillerResolutionPlan
{
    /**
     * @param string $changeRequest Pfad zur zugehoerigen Change-Request-YAML.
     * @param list<array{id:string,task_ids:list<string>,summary:string,changes:list<array{path:string,description:string,method:string}>}> $resolutions Gruppierte Loesungsplaene.
     * @throws RuntimeException Bei fehlenden Referenzen oder ungueltigen Schritten.
     * @see ShillerChangeRequest::toArray()
     *
     * Beispiel: new ShillerResolutionPlan('.shiller-edit/change-request.yml', []);
     */
    public function __construct(
        private readonly string $changeRequest,
        private readonly array $resolutions = [],
    ) {
        if (trim($changeRequest) === '') {
            throw new RuntimeException('Resolution plan requires a change-request path.');
        }

        $seen = [];
        foreach ($resolutions as $resolution) {
            if (!is_array($resolution)
                || !is_string($resolution['id'] ?? null)
                || preg_match('/^R-[0-9]+$/', $resolution['id']) !== 1
                || isset($seen[$resolution['id']])) {
                throw new RuntimeException('Invalid or duplicate resolution ID.');
            }
            $seen[$resolution['id']] = true;

            if (!is_array($resolution['task_ids'] ?? null) || $resolution['task_ids'] === []) {
                throw new RuntimeException('A resolution must reference tasks.');
            }
            foreach ($resolution['task_ids'] as $taskId) {
                if (!is_string($taskId) || preg_match('/^T-[0-9]+$/', $taskId) !== 1) {
                    throw new RuntimeException('Invalid resolution task reference.');
                }
            }

            if (!is_string($resolution['summary'] ?? null) || trim($resolution['summary']) === ''
                || !is_array($resolution['changes'] ?? null)) {
                throw new RuntimeException('A resolution requires a summary and changes.');
            }
            foreach ($resolution['changes'] as $change) {
                if (!is_array($change)
                    || !is_string($change['path'] ?? null) || trim($change['path']) === ''
                    || !is_string($change['description'] ?? null) || trim($change['description']) === ''
                    || !is_string($change['method'] ?? null) || trim($change['method']) === '') {
                    throw new RuntimeException('Resolution changes require path, description and method.');
                }
            }
        }
    }

    /**
     * Gibt die separate, spaeter zu persistierende Resolution-Datenstruktur aus.
     *
     * @return array{schema_version:int,change_request:string,resolutions:array} Resolution-Plan.
     * @see ShillerChangeRequest::toArray()
     *
     * Beispiel: (new ShillerResolutionPlan('changes.yml'))->toArray().
     */
    public function toArray(): array
    {
        return [
            'schema_version' => 1,
            'change_request' => $this->changeRequest,
            'resolutions' => $this->resolutions,
        ];
    }
}
