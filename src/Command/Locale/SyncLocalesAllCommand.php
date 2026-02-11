<?php

declare(strict_types=1);

namespace App\Command\Locale;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:locale:sync:all',
    description: 'Run all locale sync commands in a safe, dependency-aware order.'
)]
final class SyncLocalesAllCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument(
                'steps',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Optional list of steps to run (e.g. countries currencies). If omitted, runs all.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $consoleStyle = new SymfonyStyle($input, $output);

        $requestedStepNames = $this->normalizeRequestedSteps((array) $input->getArgument('steps'));

        $stepDefinitions = $this->getStepDefinitions();

        $stepsToRun = $this->filterStepsToRun($stepDefinitions, $requestedStepNames);

        if (count($stepsToRun) === 0) {
            $consoleStyle->error('No steps selected. Valid steps: ' . implode(', ', array_keys($stepDefinitions)));
            return Command::INVALID;
        }

        $consoleStyle->title('Locale sync: all');
        $consoleStyle->writeln('Will run: ' . implode(' -> ', array_map(static fn(array $step): string => $step['label'], $stepsToRun)));
        $consoleStyle->newLine();

        $application = $this->getApplication();
        if ($application === null) {
            $consoleStyle->error('Console application is not available.');
            return Command::FAILURE;
        }

        $overallStartTimestamp = microtime(true);

        foreach ($stepsToRun as $stepDefinition) {
            $commandName = $stepDefinition['command'];
            $stepLabel = $stepDefinition['label'];

            $consoleStyle->section($stepLabel);

            $command = $application->find($commandName);

            $stepStartTimestamp = microtime(true);

            $arrayInput = new ArrayInput([
                'command' => $commandName,
            ]);

            // Make sure nested commands don't attempt to read from STDIN.
            $arrayInput->setInteractive(false);

            $exitCode = $command->run($arrayInput, $output);

            $durationSeconds = microtime(true) - $stepStartTimestamp;

            if ($exitCode !== Command::SUCCESS) {
                $consoleStyle->error(sprintf(
                    '%s failed (command: %s, exit code: %d, duration: %.2fs). Stopping.',
                    $stepLabel,
                    $commandName,
                    $exitCode,
                    $durationSeconds
                ));

                return $exitCode;
            }

            $consoleStyle->success(sprintf('%s OK (%.2fs)', $stepLabel, $durationSeconds));
        }

        $overallDurationSeconds = microtime(true) - $overallStartTimestamp;

        $consoleStyle->success(sprintf('All locale sync steps finished OK (%.2fs).', $overallDurationSeconds));

        return Command::SUCCESS;
    }

    /**
     * Dependency-aware order:
     * 1) Countries
     * 2) Currencies
     * 3) Country -> Currency mapping
     * 4) Languages
     * 5) Country -> Language hints
     */
    private function getStepDefinitions(): array
    {
        return [
            'countries' => [
                'label' => 'Sync countries',
                'command' => 'app:locale:sync:countries',
            ],
            'currencies' => [
                'label' => 'Sync currencies',
                'command' => 'app:locale:sync:currencies',
            ],
            'country_currencies' => [
                'label' => 'Sync country currencies',
                'command' => 'app:locale:sync:country-currencies',
            ],
            'languages' => [
                'label' => 'Sync languages',
                'command' => 'app:locale:sync:languages',
            ],
            'country_language_hints' => [
                'label' => 'Sync country language hints',
                'command' => 'app:locale:sync:country-language-hints',
            ],
        ];
    }

    private function normalizeRequestedSteps(array $requestedStepNames): array
    {
        $normalizedStepNames = [];

        foreach ($requestedStepNames as $requestedStepName) {
            $requestedStepName = strtolower(trim((string) $requestedStepName));
            if ($requestedStepName === '') {
                continue;
            }

            $normalizedStepNames[] = $requestedStepName;
        }

        return array_values(array_unique($normalizedStepNames));
    }

    private function filterStepsToRun(array $stepDefinitions, array $requestedStepNames): array
    {
        if (count($requestedStepNames) === 0) {
            return array_values($stepDefinitions);
        }

        $stepsToRun = [];

        foreach ($requestedStepNames as $requestedStepName) {
            if (!isset($stepDefinitions[$requestedStepName])) {
                continue;
            }

            $stepsToRun[] = $stepDefinitions[$requestedStepName];
        }

        return $stepsToRun;
    }
}
