<?php

declare(strict_types=1);

namespace Leuffen\Shiller\Automation;

use Phore\Cli\Annotation\CliCommand;
use Phore\Cli\Annotation\CliParameter;
use Phore\Cli\Annotation\CliScope;
use Phore\Log\Driver\PhoreConsoleLoggerDriver;
use Phore\Log\PhoreLogger;

/** CLI command for the first project initialization step. */
#[CliScope('shiller')]
final class ShillerInitCli
{
    /**
     * Initialisiert ein Projekt aus _root und installiert optional ausgewaehlte Vorlagentags.
     *
     * @param string $tags Kommagetrennte Tags, etwa base,theme:osman.
     * @param string $templateDir _tpl-Pfad; leer liest template_dir aus der .shiller.yml im Projekt-Root.
     * @param string $root Document Root, standardmaessig docs im aktuellen Projekt.
     * @throws RuntimeException Bei ungueltiger Konfiguration oder Installationsfehlern.
     * @see ShillerAutomationFactory::create()
     * @example shiller init --template-dir ./node_modules/@leuffen/themejs2/_tpl --tags raven
     */
    #[CliCommand('init', 'Initialisiert ein Projekt aus dem Template.', 'Kopiert die _root-Basis in das Projekt und installiert optional ausgewaehlte Vorlagentags. Verwende init fuer die erstmalige Grundinitialisierung eines Projekts.')]
    public function init(
        #[CliParameter('tags', 'Kommagetrennte Vorlagentags')]
        string $tags = '',
        #[CliParameter('template-dir', 'Pfad zum _tpl-Verzeichnis')]
        string $templateDir = '',
        #[CliParameter('root', 'Document Root')]
        string $root = 'docs',
    ): void {
        $startDirectory = getcwd();
        if ($startDirectory === false) {
            throw new RuntimeException('Cannot determine current directory.');
        }

        $hooks = $this->loadInitHooks($startDirectory);
        $this->runInitHooks($hooks['before'], $startDirectory);

        $written = (new ShillerAutomationFactory($startDirectory))
            ->create($root, $templateDir, $this->logger())
            ->init($this->csv($tags));

        $this->runInitHooks($hooks['after'], $startDirectory);

        echo implode("\n", $written) . "\n";
    }

    /**
     * Laedt die konfigurierten Lifecycle-Kommandos fuer shiller init.
     *
     * @return array{before: list<string>, after: list<string>}
     */
    private function loadInitHooks(string $startDirectory): array
    {
        $projectDirectory = phore_dir($startDirectory, ['rootDir' => $startDirectory])
            ->assertDirectory()
            ->assertReadable();
        $configFile = $projectDirectory->withSubPath('.shiller.yml')->asFile();

        if (!$configFile->exists()) {
            return ['before' => [], 'after' => []];
        }

        $config = $configFile->get_yaml();
        if (!is_array($config)) {
            throw new RuntimeException("Invalid Shiller config: $configFile");
        }

        $hooks = [];
        foreach (['before', 'after'] as $phase) {
            $commands = $config['hooks']['init'][$phase] ?? [];
            if (!is_array($commands)) {
                throw new RuntimeException("hooks.init.$phase must be a list in $configFile");
            }

            $hooks[$phase] = [];
            foreach ($commands as $command) {
                if (!is_string($command) || trim($command) === '') {
                    throw new RuntimeException(
                        "hooks.init.$phase entries must be non-empty strings in $configFile",
                    );
                }
                $hooks[$phase][] = $command;
            }
        }

        return $hooks;
    }

    /**
     * @param list<string> $commands
     */
    private function runInitHooks(array $commands, string $startDirectory): void
    {
        foreach ($commands as $command) {
            $process = proc_open(
                $command,
                [0 => STDIN, 1 => STDOUT, 2 => STDERR],
                $pipes,
                $startDirectory,
            );
            if (!is_resource($process)) {
                throw new RuntimeException("Cannot start init hook: $command");
            }

            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                throw new RuntimeException("Init hook failed with exit code $exitCode: $command");
            }
        }
    }

    private function logger(): PhoreLogger
    {
        return new PhoreLogger(new PhoreConsoleLoggerDriver());
    }

    /**
     * @return list<string>
     */
    private function csv(string $values): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $values)),
            static fn(string $value): bool => $value !== '',
        ));
    }
}
