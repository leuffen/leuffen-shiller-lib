<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Closure;
use Leuffen\Shiller\Ai\ShillerAiContextFactory;
use Leuffen\Shiller\Codec;
use Leuffen\Shiller\Types\T_EditExecution;
use Leuffen\Shiller\Types\T_ExecutedFile;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\PromptType\FilePrompt;
use Phore\AiHarness\PromptType\TextPrompt;
use Phore\Cli\Input\In;
use Phore\FileSystem\PhoreDirectory;
use RuntimeException;
use Throwable;

/** Fuehrt bestehende Resolutions Task fuer Task aus und protokolliert Dateidiffs. */
final class ShillerExecuteAction
{
    private readonly PhoreDirectory $root;

    /**
     * Erstellt eine auf eine Projektwurzel beschraenkte Ausfuehrungs-Action.
     *
     * @param string $projectRoot Website-Projektwurzel.
     * @param Closure|null $apply Optionaler fn(?AiContext,array):T_EditExecution.
     * @param Closure|null $confirm Optionaler fn(string):bool fuer Tests.
     * @param AiContext|null $context Vorbereiteter AI-Kontext.
     * @param string $model Modell-Override.
     * @param string $skillsDir Theme-Skills-Override.
     * @see self::execute()
     *
     * Beispiel: (new ShillerExecuteAction('/srv/site'))->execute(yes: true);
     */
    public function __construct(
        string $projectRoot,
        private readonly ?Closure $apply = null,
        private readonly ?Closure $confirm = null,
        private readonly ?AiContext $context = null,
        private readonly string $model = '',
        private readonly string $skillsDir = '',
    ) {
        $path = (string) phore_uri($projectRoot)->abs();
        $this->root = phore_dir($path, ['rootDir' => $path])->assertDirectory()->assertReadable();
    }

    /**
     * Fuehrt jeden noch nicht protokollierten Task mit Resolution einzeln aus.
     *
     * Schreibt nur zuvor geplante bestehende Textdateien. Fuer jede tatsaechliche
     * Dateiaenderung werden Task-ID, Resolution-ID, Hashes und zeilengenaue
     * Unified-Diff-Hunks in einer separaten YAML-Historie gespeichert.
     *
     * @param string $input Projekt-relativer Pfad zur Edit-YAML.
     * @param string $changesFile Projekt-relativer Pfad zum Diff-Protokoll.
     * @param string $taskId Optional nur einen Task ausfuehren.
     * @param bool $yes Vor jeder Task-Aenderung keine Rueckfrage stellen.
     * @param bool $dryRun Ausschliesslich Diffs berechnen und anzeigen.
     * @return string Relativer Pfad zur Historie, leer bei Dry-Run.
     * @throws RuntimeException Bei ungueltigem Plan oder unerwartetem Dateistand.
     * @see ShillerEditDiff::between()
     *
     * Beispiel: $action->execute(taskId: 'T-0001', yes: true);
     */
    public function execute(
        string $input = '.shiller-edit.d/shiller-edit.yml',
        string $changesFile = '.shiller-edit.d/shiller-edit-changes.yml',
        string $taskId = '',
        bool $yes = false,
        bool $dryRun = false,
    ): string {
        $inputPath = $this->root->assertRelativePath($input);
        $logPath = $this->root->assertRelativePath($changesFile);
        if ($inputPath === $logPath) {
            throw new RuntimeException('Execution history must differ from input YAML.');
        }
        $source = $this->root->withSubPath($inputPath)->asFile()->assertFile()->assertReadable();
        $data = $source->get_yaml();
        if (!is_array($data) || !is_array($data['tasks'] ?? null)
            || !is_array($data['resolutions'] ?? null)
            || !array_is_list($data['tasks']) || !array_is_list($data['resolutions'])
            || !is_array($data['quotes'] ?? null)) {
            throw new RuntimeException('Invalid Shiller Edit YAML: ' . $source);
        }

        $ledgerFile = $this->root->withSubPath($logPath)->asFile();
        $ledger = $ledgerFile->exists()
            ? $ledgerFile->assertFile()->assertReadable()->get_yaml()
            : ['version' => 1, 'changes' => []];
        if (!is_array($ledger) || ($ledger['version'] ?? null) !== 1
            || !is_array($ledger['changes'] ?? null)
            || !array_is_list($ledger['changes'])) {
            throw new RuntimeException('Invalid edit changes YAML: ' . $ledgerFile);
        }
        $completed = [];
        foreach ($ledger['changes'] as $entry) {
            if (!is_array($entry) || !is_string($entry['taskId'] ?? null)) {
                throw new RuntimeException('Invalid execution history entry.');
            }
            $completed[$entry['taskId']] = true;
        }

        $tasks = [];
        foreach ($data['tasks'] as $task) {
            if (!is_array($task) || !is_string($task['id'] ?? null)
                || isset($tasks[$task['id']]) || !is_string($task['summary'] ?? null)
                || !in_array($task['type'] ?? null, ['content', 'style'], true)) {
                throw new RuntimeException('Invalid task in ' . $source);
            }
            $tasks[$task['id']] = $task;
        }
        if ($taskId !== '' && !isset($tasks[$taskId])) {
            throw new RuntimeException('Unknown task: ' . $taskId);
        }
        $planned = [];
        foreach ($data['resolutions'] as $resolution) {
            if (!is_array($resolution) || !is_string($resolution['id'] ?? null)
                || !array_is_list($resolution['taskIds'] ?? null)
                || !array_is_list($resolution['files'] ?? null)
                || !is_string($resolution['description'] ?? null)
                || !is_string($resolution['defect'] ?? null)) {
                throw new RuntimeException('Invalid resolution in ' . $source);
            }
            foreach ($resolution['taskIds'] as $id) {
                if (!is_string($id) || !isset($tasks[$id]) || isset($planned[$id])) {
                    throw new RuntimeException('Duplicate or unknown task reference in resolution.');
                }
                $planned[$id] = $resolution;
            }
        }

        $context = $this->context;
        $processed = 0;
        foreach ($tasks as $id => $task) {
            if (($taskId !== '' && $id !== $taskId) || isset($completed[$id])) {
                continue;
            }
            if (!isset($planned[$id])) {
                if ($taskId === $id) {
                    throw new RuntimeException('Task has no resolution: ' . $id);
                }
                continue;
            }
            if (($task['questions'] ?? []) !== []) {
                throw new RuntimeException('Cannot execute task with open questions: ' . $id);
            }

            $resolution = $planned[$id];
            $originals = $this->readPlannedFiles($resolution['files'], $inputPath, $logPath);
            if ($context === null && $this->apply === null) {
                $context = (new ShillerAiContextFactory((string) $this->root))->create(
                    model: $this->model,
                    skillsDir: $this->skillsDir,
                );
            }

            // Original-Quotes und Nachbar-Tasks bleiben Kontext. Die AI darf
            // nur die fuer diesen Task wirklich notwendigen Dateien aendern.
            $evidence = [];
            foreach ($data['quotes'] as $quoteFile) {
                foreach (($quoteFile['quotes'] ?? []) as $quote) {
                    if (in_array($id, $quote['taskIds'] ?? [], true)) {
                        $evidence[] = $quote['text'] ?? '';
                    }
                }
            }
            $prompts = [
                new TextPrompt(
                    'Setze ausschliesslich den EINEN angegebenen Task fachlich '
                    . 'korrekt um, passend zum Resolution-Plan und zu den Edit-Skills. '
                    . 'Lade bei Bedarf relevante Theme-Edit-Skills und References. '
                    . 'Bei gemeinsamer Resolution mit weiteren Tasks nur den '
                    . 'genannten Task bearbeiten, nichts vorwegnehmen. Gib '
                    . 'T_EditExecution zurueck: taskId muss exakt stimmen, '
                    . 'files enthaelt nur geaenderte geplante Dateien. Fuer '
                    . 'jede Datei path, beforeSha256 und den VOLLSTAENDIGEN '
                    . 'neuen Textinhalt content ausgeben. Unverwandte Dateien '
                    . 'nicht bearbeiten. Nicht selbst Dateien schreiben, keine '
                    . 'Tools fuer Schreibvorgaenge verwenden und keine Fakten erfinden.',
                    alias: 'executeTask',
                    allowInstructions: true,
                ),
                new TextPrompt(json_encode([
                    'task' => $task,
                    'resolution' => $resolution,
                    'evidence' => $evidence,
                    'allowedFilesWithSha256' => array_map(
                        static fn(string $value): string => hash('sha256', $value),
                        $originals,
                    ),
                ], JSON_THROW_ON_ERROR), alias: 'executePlan'),
            ];
            foreach ($originals as $name => $body) {
                $prompts[] = new FilePrompt(
                    $name,
                    $body,
                    'text/plain',
                    instructions: 'Originaldatei; nur vollstaendige Zielversion fuer diesen Task erzeugen.',
                );
            }
            $response = $this->apply !== null
                ? ($this->apply)($context, $prompts)
                : $context->struct($prompts, T_EditExecution::class);
            $edits = $this->validateResponse($id, $originals, $response);

            $entries = [];
            foreach ($edits as $name => $content) {
                $hunks = ShillerEditDiff::between($originals[$name], $content);
                if ($hunks === []) {
                    continue;
                }
                $entries[] = [
                    'taskId' => $id,
                    'resolutionId' => $resolution['id'],
                    'file' => $name,
                    'beforeSha256' => hash('sha256', $originals[$name]),
                    'afterSha256' => hash('sha256', $content),
                    'hunks' => $hunks,
                ];
                echo "\n" . $id . ' | ' . $name . "\n";
                foreach ($hunks as $hunk) {
                    echo $hunk['diff'];
                }
            }
            if ($entries === []) {
                throw new RuntimeException('Task produced no changes: ' . $id);
            }
            if ($dryRun) {
                $processed++;
                continue;
            }
            if (!$yes && !($this->confirm ?? In::AskBool(...))('Task ' . $id . ' anwenden?', false)) {
                echo 'Task nicht angewendet: ' . $id . "\n";
                continue;
            }

            // Vor dem ersten Write alle Zielinhalte nochmals pruefen. Der
            // Originalzustand wird bei einem Fehler innerhalb des Tasks
            // wiederhergestellt; zuvor abgeschlossene Tasks bleiben bestehen.
            foreach ($edits as $name => $content) {
                $current = $this->root->withSubPath($name)->asFile()->assertFile()->assertReadable();
                if ($current->get_contents() !== $originals[$name]) {
                    throw new RuntimeException('File changed during execution: ' . $name);
                }
            }
            $written = [];
            try {
                foreach ($edits as $name => $content) {
                    $this->root->withSubPath($name)->asFile()->set_contents($content);
                    $written[] = $name;
                }
                $ledger['changes'] = [...$ledger['changes'], ...$entries];
                $ledgerFile->set_contents(Codec::emitYaml($ledger));
            } catch (Throwable $error) {
                foreach ($written as $name) {
                    $this->root->withSubPath($name)->asFile()->set_contents($originals[$name]);
                }
                throw $error;
            }
            $completed[$id] = true;
            $processed++;
        }
        if ($processed === 0) {
            echo "Keine offenen, ausfuehrbaren Tasks.\n";
        }
        return $dryRun ? '' : $logPath;
    }

    /**
     * @param list<string> $paths
     * @return array<string,string> Vollstaendige Originaldateien.
     */
    private function readPlannedFiles(array $paths, string $input, string $log): array
    {
        if ($paths === []) {
            throw new RuntimeException('Resolution has no target files.');
        }
        $files = [];
        foreach ($paths as $name) {
            if (!is_string($name) || trim($name) === '') {
                throw new RuntimeException('Invalid resolution file.');
            }
            $name = $this->root->assertRelativePath($name);
            if (isset($files[$name]) || $name === $input || $name === $log
                || str_starts_with($name, '.shiller-')
                || str_starts_with($name, 'vendor/') || str_starts_with($name, 'node_modules/')
                || str_starts_with($name, '.git/')) {
                throw new RuntimeException('Unsafe resolution file: ' . $name);
            }
            $body = $this->root->withSubPath($name)->asFile()->assertFile()->assertReadable()->get_contents();
            if (strlen($body) > 80000 || str_contains($body, "\0")
                || preg_match('//u', $body) !== 1) {
                throw new RuntimeException('Only UTF-8 text files up to 80KB can be edited: ' . $name);
            }
            $files[$name] = $body;
        }
        return $files;
    }

    /**
     * @param array<string,string> $originals
     * @return array<string,string>
     */
    private function validateResponse(string $taskId, array $originals, mixed $response): array
    {
        if (!$response instanceof T_EditExecution || $response->taskId !== $taskId
            || !array_is_list($response->files) || $response->files === []) {
            throw new RuntimeException('Invalid execution result for ' . $taskId);
        }
        $edits = [];
        foreach ($response->files as $change) {
            if (!$change instanceof T_ExecutedFile
                || !array_key_exists($change->path, $originals) || isset($edits[$change->path])
                || !hash_equals(hash('sha256', $originals[$change->path]), $change->beforeSha256)
                || strlen($change->content) > 80000 || str_contains($change->content, "\0")
                || preg_match('//u', $change->content) !== 1
                || $change->content === $originals[$change->path]) {
                throw new RuntimeException('Invalid or stale execution file for ' . $taskId);
            }
            $edits[$change->path] = $change->content;
        }
        return $edits;
    }
}
