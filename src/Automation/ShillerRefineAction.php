<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Closure;
use Leuffen\Shiller\Ai\ShillerAiContextFactory;
use Leuffen\Shiller\Codec;
use Leuffen\Shiller\Types\T_Changes;
use Leuffen\Shiller\Types\T_QuoteFile;
use Leuffen\Shiller\Types\T_Resolution;
use Leuffen\Shiller\Types\T_Task;
use Phore\AiHarness\AiContext;
use Phore\AiHarness\PromptType\FilePrompt;
use Phore\AiHarness\PromptType\TextPrompt;
use Phore\Cli\Input\In;
use Phore\FileSystem\PhoreDirectory;
use RuntimeException;

/** Interaktive, nur auf das Edit-YAML wirkende Ueberarbeitung von Tasks. */
final class ShillerRefineAction
{
    private readonly PhoreDirectory $root;

    /**
     * Erstellt den interaktiven Editor fuer das Projekt.
     *
     * Die Callbacks erlauben Tests ohne Terminal und ohne AI-Zugriff.
     *
     * @param string $projectRoot Projektwurzel mit Edit-YAML und Website-Dateien.
     * @param Closure|null $readInput Optionaler fn(string):string fuer Terminal-Eingaben.
     * @param Closure|null $refine Optionaler fn(?AiContext,array):T_Changes.
     * @param AiContext|null $context Vorbereiteter Kontext.
     * @param string $model Modell-Override.
     * @param string $skillsDir Skills-Override.
     * @see self::run()
     *
     * Beispiel: (new ShillerRefineAction('/srv/site'))->run();
     */
    public function __construct(
        string $projectRoot,
        private readonly ?Closure $readInput = null,
        private readonly ?Closure $refine = null,
        private readonly ?AiContext $context = null,
        private readonly string $model = '',
        private readonly string $skillsDir = '',
    ) {
        $path = (string) phore_uri($projectRoot)->abs();
        $this->root = phore_dir($path, ['rootDir' => $path])->assertDirectory()->assertReadable();
    }

    /**
     * Fuehrt einen mehrstufigen Dialog und speichert erst bei :save.
     *
     * Natuerliche Eingaben duerfen Tasks zusammenlegen, umformulieren und
     * Quotes, Questions oder Resolutions neu zuordnen. Originaltext bleibt.
     * :undo verwirft die letzte Runde; :quit schreibt nichts.
     *
     * @param string $input Projekt-relativer Pfad zur Edit-YAML.
     * @param string $documentRoot Website-Dokumentverzeichnis.
     * @return string Pfad nach :save oder leer bei :quit.
     * @throws RuntimeException Bei ungueltigen Modellantworten.
     * @see T_Changes::toArray()
     *
     * Beispiel: $action->run('.shiller-edit.d/shiller-edit.yml');
     */
    public function run(string $input = '.shiller-edit.d/shiller-edit.yml', string $documentRoot = 'docs'): string
    {
        $relative = $this->root->assertRelativePath($input);
        $file = $this->root->withSubPath($relative)->asFile()->assertFile()->assertReadable();
        $original = $file->get_yaml();
        if (!is_array($original) || !is_array($original['quotes'] ?? null)
            || !is_array($original['tasks'] ?? null) || !is_array($original['resolutions'] ?? null)) {
            throw new RuntimeException('Invalid Shiller Edit YAML: ' . $file);
        }
        // Nach bereits aufgezeichneten Schreibvorgaengen bleiben die alten
        // Task-IDs fuer die Diff-Historie unveraenderlich.
        $ledger = $this->root->withSubPath('.shiller-edit.d/shiller-edit-changes.yml')->asFile();
        if ($ledger->exists()) {
            $log = $ledger->assertReadable()->get_yaml();
            if (!is_array($log) || !is_array($log['changes'] ?? null)) {
                throw new RuntimeException('Invalid execution history: ' . $ledger);
            }
            if ($log['changes'] !== []) {
                throw new RuntimeException('Refine is locked after execution history was created.');
            }
        }

        $working = $original;
        $history = [];
        $context = $this->context;
        [$paths, $sitePrompts] = $this->sources($documentRoot);
        echo "Refine: Anweisungen eingeben (Enter = senden, Ctrl+J = neue Zeile).\n";
        echo "Befehle: :show, :undo, :save, :quit.\n";
        $this->show($working);

        while (true) {
            $command = trim(($this->readInput ?? In::AskMultiLine(...))('Refine'));
            if ($command === ':quit') {
                return '';
            }
            if ($command === ':show') {
                $this->show($working);
                continue;
            }
            if ($command === ':undo') {
                if ($history !== []) {
                    $working = array_pop($history);
                    $this->show($working);
                }
                continue;
            }
            if ($command === ':save') {
                $file->set_contents(Codec::emitYaml($working));
                return $relative;
            }
            if ($command === '') {
                continue;
            }

            // Der AI-Kontext wird erst gebraucht, wenn tatsaechlich eine
            // natuerlichsprachliche Aenderung angefordert wird.
            if ($context === null && $this->refine === null) {
                $context = (new ShillerAiContextFactory((string) $this->root))->create(
                    model: $this->model,
                    skillsDir: $this->skillsDir,
                );
            }
            $prompts = [
                new TextPrompt(
                    'Du bearbeitest ausschliesslich den strukturierten Change Request. '
                    . 'Gib den KOMPLETTEN aktualisierten T_Changes-Baum zurueck. '
                    . 'Du darfst Tasks zusammenlegen, splitten, umbenennen, typisieren '
                    . '(content/style), Fragen und Admin-Hinweise pflegen, Quotes neu '
                    . 'zuordnen und dazu passende Resolutions ueberarbeiten. '
                    . 'Original-Quote-Texte, Reihenfolge, Quelldateinamen und '
                    . 'alle Kundenaussagen muessen unveraendert erhalten bleiben. '
                    . 'Beim Zusammenlegen die Task-Referenzen in Quotes UND '
                    . 'Resolutions konsistent umstellen; keine verwaisten IDs. '
                    . 'Eine Resolution darf nur Tasks desselben Typs enthalten. '
                    . 'Nutze den vorhandenen Projektkontext und relevante Skills; '
                    . 'Website-Dateien NUR LESEN, NIEMALS bearbeiten. '
                    . 'Aendere ausschliesslich das Gewuenschte, keine Fakten erfinden.',
                    alias: 'refineRules',
                    allowInstructions: true,
                ),
                new TextPrompt(json_encode([
                    'request' => $command,
                    'changes' => $working,
                    'projectFiles' => $paths,
                ], JSON_THROW_ON_ERROR), alias: 'refineRequest'),
                ...$sitePrompts,
            ];
            $response = $this->refine !== null
                ? ($this->refine)($context, $prompts)
                : $context->struct($prompts, T_Changes::class);
            $updated = $this->validate($original, $response);
            $history[] = $working;
            $working = $updated;
            $this->show($working);
        }
    }

    /** @return array<string,mixed> */
    private function validate(array $original, mixed $result): array
    {
        if (!$result instanceof T_Changes || !array_is_list($result->quotes)
            || !array_is_list($result->tasks) || !array_is_list($result->resolutions)
            || count($result->quotes) !== count($original['quotes'])) {
            throw new RuntimeException('Refine must return complete T_Changes.');
        }

        $ids = [];
        foreach ($result->tasks as $task) {
            if (!$task instanceof T_Task || preg_match('/^T-[0-9]{4,}$/', $task->id) !== 1
                || trim($task->summary) === '' || isset($ids[$task->id])
                || !array_is_list($task->questions)) {
                throw new RuntimeException('Invalid refined task.');
            }
            $ids[$task->id] = $task->type;
        }
        if ($ids === []) {
            throw new RuntimeException('Refine must retain at least one task.');
        }

        foreach ($result->quotes as $i => $source) {
            $before = $original['quotes'][$i];
            if (!$source instanceof T_QuoteFile || $source->fileName !== ($before['fileName'] ?? null)
                || !array_is_list($source->quotes)
                || count($source->quotes) !== count($before['quotes'] ?? [])) {
                throw new RuntimeException('Refine cannot change original quote sources.');
            }
            foreach ($source->quotes as $j => $quote) {
                $old = $before['quotes'][$j];
                if ($quote->text !== ($old['text'] ?? null) || !array_is_list($quote->taskIds)
                    || (($old['taskIds'] ?? []) !== [] && $quote->taskIds === [])) {
                    throw new RuntimeException('Refine cannot discard customer evidence.');
                }
                foreach ($quote->taskIds as $taskId) {
                    if (!isset($ids[$taskId])) {
                        throw new RuntimeException('Quote references unknown task: ' . $taskId);
                    }
                }
            }
        }

        $resolutionIds = [];
        $assigned = [];
        foreach ($result->resolutions as $resolution) {
            if (!$resolution instanceof T_Resolution
                || preg_match('/^R-[0-9]{4,}$/', $resolution->id) !== 1
                || isset($resolutionIds[$resolution->id])
                || trim($resolution->description) === '' || trim($resolution->defect) === ''
                || !array_is_list($resolution->taskIds) || $resolution->taskIds === []
                || !array_is_list($resolution->files) || $resolution->files === []) {
                throw new RuntimeException('Invalid refined resolution.');
            }
            $resolutionIds[$resolution->id] = true;
            $types = [];
            foreach ($resolution->taskIds as $taskId) {
                if (!isset($ids[$taskId]) || isset($assigned[$taskId])) {
                    throw new RuntimeException('Duplicate or unknown resolution task.');
                }
                $assigned[$taskId] = true;
                $types[$ids[$taskId]] = true;
            }
            if (count($types) !== 1) {
                throw new RuntimeException('Resolution must not mix task types.');
            }
            foreach ($resolution->files as $path) {
                if (!is_string($path) || $path === '') {
                    throw new RuntimeException('Invalid refined file path.');
                }
                $this->root->assertRelativePath($path);
            }
        }
        return $result->toArray();
    }

    /** @param array<string,mixed> $data */
    private function show(array $data): void
    {
        echo 'Tasks: ' . count($data['tasks']) . ', Resolutions: ' . count($data['resolutions']) . "\n";
        foreach ($data['tasks'] as $task) {
            echo $task['id'] . ' (' . ($task['type'] ?? 'content') . ') ' . $task['summary'] . "\n";
        }
    }

    /** @return array{0:list<string>,1:list<FilePrompt>} */
    private function sources(string $documentRoot): array
    {
        $relative = $this->root->assertRelativePath($documentRoot);
        $dir = $this->root->withSubPath($relative);
        if (!$dir->exists()) {
            return [[], []];
        }
        $paths = [];
        $prompts = [];
        foreach ($dir->assertDirectory()->assertReadable()->listFiles(recursive: true, sort: 'path') as $file) {
            $name = str_replace('\\', '/', (string) $file->getRelPath($this->root));
            if (count($paths) >= 500 || str_contains($name, '/.')) {
                continue;
            }
            $paths[] = $name;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['md', 'txt', 'html', 'css', 'js', 'json', 'yml', 'yaml'], true)
                || count($prompts) >= 30) {
                continue;
            }
            $content = $file->assertFile()->assertReadable()->get_contents();
            if (strlen($content) > 15000 || preg_match('//u', $content) !== 1) {
                continue;
            }
            $prompts[] = new FilePrompt(
                $name,
                $content,
                'text/plain',
                instructions: 'Projektdatei ausschliesslich als lesbare Referenz.',
            );
        }
        return [$paths, $prompts];
    }
}
