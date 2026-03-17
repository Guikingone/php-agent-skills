<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\Command;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use AgentSkills\Validation\SkillValidatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function is_string;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
#[AsCommand(
    name: 'ai:agent:validate-skills',
    description: 'Validate Agent Skills against the specification',
)]
final class ValidateSkillCommand extends Command
{
    public function __construct(
        private readonly SkillLoaderInterface $skillLoader,
        private readonly SkillValidatorInterface $skillValidator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('skill', null, InputOption::VALUE_REQUIRED, 'The name of a specific skill to validate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $skillName = $input->getOption('skill');

        if (is_string($skillName) && '' !== $skillName) {
            return $this->validateSingleSkill($io, $skillName);
        }

        return $this->validateAllSkills($io);
    }

    private function validateSingleSkill(SymfonyStyle $io, string $skillName): int
    {
        $skill = $this->skillLoader->loadSkill($skillName);

        if (!$skill instanceof SkillInterface) {
            $io->error(sprintf('Skill "%s" not found.', $skillName));

            return Command::FAILURE;
        }

        $result = $this->skillValidator->validate($skill);

        $io->table(
            ['Skill', 'Status', 'Errors', 'Warnings'],
            [[$skill->getName(), $result->isValid() ? 'valid' : 'invalid', count($result->getErrors()), count($result->getWarnings())]],
        );

        if ($result->hasWarnings()) {
            $io->section('Warnings');

            foreach ($result->getWarnings() as $warning) {
                $io->writeln(sprintf(' * %s', $warning));
            }
        }

        if (!$result->isValid()) {
            $io->section('Errors');

            foreach ($result->getErrors() as $error) {
                $io->writeln(sprintf(' * %s', $error));
            }

            return Command::FAILURE;
        }

        $io->success(sprintf('The skill "%s" is valid.', $skill->getName()));

        return Command::SUCCESS;
    }

    private function validateAllSkills(SymfonyStyle $io): int
    {
        $skills = $this->skillLoader->loadSkills();

        if ([] === $skills) {
            $io->warning('No skills found.');

            return Command::SUCCESS;
        }

        $rows = [];
        $totalValid = 0;
        $totalInvalid = 0;
        $totalWarnings = 0;
        $hasErrors = false;

        /** @var SkillInterface $skill */
        foreach ($skills as $skill) {
            $result = $this->skillValidator->validate($skill);
            $warningCount = count($result->getWarnings());
            $errorCount = count($result->getErrors());

            if ($result->isValid()) {
                ++$totalValid;
            } else {
                ++$totalInvalid;
                $hasErrors = true;
            }

            $totalWarnings += $warningCount;

            $rows[] = [
                $skill->getName(),
                $result->isValid() ? 'valid' : 'invalid',
                $errorCount,
                $warningCount,
            ];

            if (!$result->isValid()) {
                foreach ($result->getErrors() as $error) {
                    $io->writeln(sprintf(' * [%s] error: %s', $skill->getName(), $error));
                }
            }

            if ($result->hasWarnings()) {
                foreach ($result->getWarnings() as $warning) {
                    $io->writeln(sprintf(' * [%s] warning: %s', $skill->getName(), $warning));
                }
            }
        }

        $io->table(['Skill', 'Status', 'Errors', 'Warnings'], $rows);

        $io->section('Summary');
        $io->writeln(sprintf('Total: %d', count($skills)));
        $io->writeln(sprintf('Valid: %d', $totalValid));
        $io->writeln(sprintf('Invalid: %d', $totalInvalid));
        $io->writeln(sprintf('Warnings: %d', $totalWarnings));

        if ($hasErrors) {
            return Command::FAILURE;
        }

        $io->success('All skills are valid!');

        return Command::SUCCESS;
    }
}
