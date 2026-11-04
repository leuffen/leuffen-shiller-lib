<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use RuntimeException;

/**
 * Unaufgeloester Kunden-Aenderungsauftrag: Quellen, Textabschnitte und Tasks.
 *
 * Abschnitt-zu-Task und Task-zu-Abschnitt sind bewusst beidseitig abgebildet;
 * ein Abschnitt darf zu mehreren Tasks gehoeren und umgekehrt.
 */
final class ShillerChangeRequest
{
    private function __construct(
        private readonly array $sources,
        private readonly array $sections,
        private readonly array $tasks,
    ) {}

    /**
     * Erstellt einen unveraenderten Change Request aus extrahierten Task-Vorschlaegen.
     *
     * Die IDs werden hier vergeben und nicht von der AI uebernommen. Referenzen
     * auf unbekannte Textabschnitte oder Assets werden abgelehnt.
     *
     * @param list<array{id:string,path:string,kind:string}> $sources Quellen (text|asset).
     * @param list<array{id:string,source_id:string,line_start:int,line_end:int,text:string}> $sections Textabschnitte.
     * @param list<array{title:string,description:string,section_ids:list<string>,asset_paths:list<string>}> $proposals Task-Vorschlaege.
     * @return self Gepruefte, noch nicht aufgeloeste Change List.
     * @throws RuntimeException Bei fehlenden Tasks oder ungueltigen Referenzen.
     * @see ShillerEditAction::analyze()
     *
     * Beispiel: ShillerChangeRequest::fromAnalysis($sources, $sections, $proposals)->toArray().
     */
    public static function fromAnalysis(array $sources, array $sections, array $proposals): self
    {
        $sectionIds = [];
        foreach ($sections as $section) {
            $id = $section['id'];
            if (isset($sectionIds[$id])) {
                throw new RuntimeException('Duplicate section ID: ' . $id);
            }
            $sectionIds[$id] = true;
        }

        $assetPaths = [];
        foreach ($sources as $source) {
            if ($source['kind'] === 'asset') {
                $assetPaths[$source['path']] = true;
            }
        }

        $tasks = [];
        $assignments = [];
        foreach ($proposals as $proposal) {
            if (!is_array($proposal)) {
                throw new RuntimeException('Task proposals must be objects.');
            }

            $title = $proposal['title'] ?? null;
            $description = $proposal['description'] ?? null;
            if (!is_string($title) || trim($title) === ''
                || !is_string($description) || trim($description) === '') {
                throw new RuntimeException('Every task requires a title and a description.');
            }

            $references = $proposal['section_ids'] ?? null;
            $assets = $proposal['asset_paths'] ?? null;
            if (!is_array($references) || !array_is_list($references)
                || !is_array($assets) || !array_is_list($assets)) {
                throw new RuntimeException('Task references must be lists.');
            }

            $selectedSections = [];
            foreach ($references as $id) {
                if (!is_string($id) || !isset($sectionIds[$id])) {
                    throw new RuntimeException('Unknown section reference in task: ' . json_encode($id));
                }
                $selectedSections[$id] = true;
            }

            $selectedAssets = [];
            foreach ($assets as $path) {
                if (!is_string($path) || !isset($assetPaths[$path])) {
                    throw new RuntimeException('Unknown asset reference in task: ' . json_encode($path));
                }
                $selectedAssets[$path] = true;
            }

            if ($selectedSections === [] && $selectedAssets === []) {
                throw new RuntimeException('A task must reference at least one source section or asset.');
            }

            $taskId = sprintf('T-%04d', count($tasks) + 1);
            $tasks[] = [
                'id' => $taskId,
                'title' => trim($title),
                'description' => trim($description),
                'section_ids' => array_keys($selectedSections),
                'asset_paths' => array_keys($selectedAssets),
                'status' => 'open',
            ];
            foreach ($selectedSections as $id => $_) {
                $assignments[$id][] = $taskId;
            }
        }

        if ($tasks === []) {
            throw new RuntimeException('No actionable changes were extracted.');
        }

        foreach ($sections as &$section) {
            $section['task_ids'] = $assignments[$section['id']] ?? [];
        }
        unset($section);

        return new self($sources, $sections, $tasks);
    }

    /**
     * Liefert das serialisierbare YAML-Schema ohne Loesungen.
     *
     * @return array{schema_version:int,sources:array,sections:array,tasks:array} Change-Request-Daten.
     * @see ShillerResolutionPlan::toArray()
     *
     * Beispiel: $yaml = \Leuffen\Shiller\Codec::emitYaml($request->toArray());
     */
    public function toArray(): array
    {
        return [
            'schema_version' => 1,
            'sources' => $this->sources,
            'sections' => $this->sections,
            'tasks' => $this->tasks,
        ];
    }
}
