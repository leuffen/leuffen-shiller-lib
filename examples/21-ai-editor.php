<?php

declare(strict_types=1);

use Leuffen\Shiller\Automation\DTO\InlineCompletionRequest;
use Leuffen\Shiller\Automation\DTO\InlineEditRequest;
use Leuffen\Shiller\Automation\ShillerAiEditorAction;

require dirname(__DIR__) . '/vendor/autoload.php';

// Benötigt den im Phore AI Harness konfigurierten open_ai-Key.
$ai = new ShillerAiEditorAction();

$completion = new InlineCompletionRequest();
$completion->language = 'markdown';
$completion->codeBefore = '## Unsere Leistungen';
$completion->codeAfter = "\n\n## Kontakt";
$completion->cursor = 19; // UTF-16-Offset, wie in CodeMirror.
$result = $ai->inlineCompletion($completion);
echo $result->text . PHP_EOL;
print_r($result->usage);
print_r($result->initializationUsage); // Nur beim erstmaligen Aufbau zusätzlich angefallen.

// Die ursprüngliche Response-ID wird für weitere Vorschläge wiederverwendet.
if ($result->responseId !== null) {
    $edit = new InlineEditRequest();
    $edit->previousResponseId = $result->responseId;
    $edit->language = 'markdown';
    $edit->codeBefore = "## Willkommen\n";
    $edit->selection = 'Wir sind eine gute Praxis.';
    $edit->codeAfter = "\n## Kontakt";
    $edit->from = 14;
    $edit->to = 39;
    $edit->prompt = 'Formuliere sachlicher, ohne neue Fakten zu erfinden.';
    $replacement = $ai->inlineEdit($edit);
    echo $replacement->text . PHP_EOL;
    print_r($replacement->usage); // cachedInputTokens zeigt tatsächliche Cache-Hits.
}
