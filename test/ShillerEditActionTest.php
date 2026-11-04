<?php

declare(strict_types=1);

use Leuffen\Shiller\Automation\ShillerChangeRequest;
use Leuffen\Shiller\Automation\ShillerEditAction;
use Leuffen\Shiller\Automation\ShillerResolutionPlan;
use PHPUnit\Framework\TestCase;

final class ShillerEditActionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/shiller-edit-' . bin2hex(random_bytes(6));
        phore_dir($this->dir . '/feedback/nested')->mkdir();
        phore_file($this->dir . '/feedback/request.txt')->set_contents(
            "Logo austauschen und Adresse korrigieren.\n\n"
            . "Auch auf der Startseite die Adresse korrigieren.\n",
        );
        phore_file($this->dir . '/feedback/nested/logo.png')->set_contents("\x89PNG\0");
    }

    protected function tearDown(): void
    {
        phore_dir($this->dir)->rmDir(true);
    }

    public function testDirectoryAnalysisProducesBidirectionalTaskLinksAndAssets(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static function (array $sections, array $assets): array {
                self::assertCount(2, $sections);
                self::assertSame(['feedback/nested/logo.png'], $assets);

                return [
                    [
                        'title' => 'Adresse korrigieren',
                        'description' => 'Adresse in beiden betroffenen Stellen anpassen.',
                        'section_ids' => ['S-0001', 'S-0002'],
                        'asset_paths' => [],
                    ],
                    [
                        'title' => 'Logo tauschen',
                        'description' => 'Kundenlogo ersetzen.',
                        'section_ids' => ['S-0001'],
                        'asset_paths' => ['feedback/nested/logo.png'],
                    ],
                ];
            },
        );

        $path = $action->analyze(['feedback']);
        self::assertSame('.shiller-edit/change-request.yml', $path);
        $data = phore_file($this->dir . '/' . $path)->get_yaml();

        self::assertSame(1, $data['schema_version']);
        self::assertSame(['T-0001', 'T-0002'], $data['sections'][0]['task_ids']);
        self::assertSame(['T-0001'], $data['sections'][1]['task_ids']);
        self::assertSame(['S-0001', 'S-0002'], $data['tasks'][0]['section_ids']);
        self::assertSame(['feedback/nested/logo.png'], $data['tasks'][1]['asset_paths']);
        self::assertSame('open', $data['tasks'][1]['status']);
        self::assertSame(1, $data['sections'][0]['line_start']);
        self::assertSame(3, $data['sections'][1]['line_start']);
        self::assertCount(2, $data['sources']);
        self::assertArrayNotHasKey('resolutions', $data);
    }

    public function testStdinParagraphsAndOutputOverride(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static fn(array $sections, array $assets): array => [[
                'title' => 'Kontakt',
                'description' => 'Kontaktdaten aktualisieren.',
                'section_ids' => ['S-0001', 'S-0002'],
                'asset_paths' => [],
            ]],
        );

        $path = $action->analyze(
            stdinText: "Telefon aendern.\n\nE-Mail aendern.",
            output: 'requests/kunde.yml',
        );
        $data = phore_file($this->dir . '/' . $path)->get_yaml();
        self::assertSame('stdin', $data['sources'][0]['path']);
        self::assertSame('S-0002', $data['sections'][1]['id']);
        self::assertSame(['T-0001'], $data['sections'][1]['task_ids']);
    }

    public function testRejectsUnknownSectionReferences(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown section reference');

        ShillerChangeRequest::fromAnalysis(
            [['id' => 'F-0001', 'path' => 'stdin', 'kind' => 'text']],
            [['id' => 'S-0001', 'source_id' => 'F-0001', 'line_start' => 1, 'line_end' => 1, 'text' => 'Text']],
            [['title' => 'Task', 'description' => 'Details', 'section_ids' => ['S-9999'], 'asset_paths' => []]],
        );
    }

    public function testRefusesToOverwriteExistingYaml(): void
    {
        $action = new ShillerEditAction(
            $this->dir,
            static fn(array $sections, array $assets): array => [[
                'title' => 'Kontakt',
                'description' => 'Kontaktdaten aktualisieren.',
                'section_ids' => ['S-0001'],
                'asset_paths' => [],
            ]],
        );
        $action->analyze(stdinText: 'Telefon aendern.');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $action->analyze(stdinText: 'E-Mail aendern.');
    }

    public function testResolutionPlanStaysSeparateFromChangeRequest(): void
    {
        $plan = new ShillerResolutionPlan('requests/kunde.yml', [[
            'id' => 'R-0001',
            'task_ids' => ['T-0001', 'T-0002'],
            'summary' => 'Kontaktangaben gemeinsam anpassen.',
            'changes' => [
                ['path' => 'docs/index.md', 'description' => 'Adresse aktualisieren', 'method' => 'Text ersetzen'],
            ],
        ]]);
        self::assertSame(['T-0001', 'T-0002'], $plan->toArray()['resolutions'][0]['task_ids']);
        self::assertSame('requests/kunde.yml', $plan->toArray()['change_request']);
    }
}
