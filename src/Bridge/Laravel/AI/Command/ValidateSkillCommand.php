<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Laravel\AI\Command;

use AgentSkills\SkillInterface;
use AgentSkills\SkillLoaderInterface;
use AgentSkills\Validation\SkillValidatorInterface;
use Illuminate\Console\Command;

use function count;
use function is_string;
use function sprintf;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ValidateSkillCommand extends Command
{
    /** @var string */
    protected $signature = 'ai:agent:validate-skills {--skill= : The name of a specific skill to validate}';

    /** @var string */
    protected $description = 'Validate Agent Skills against the specification';

    public function __construct(
        private readonly SkillLoaderInterface $skillLoader,
        private readonly SkillValidatorInterface $skillValidator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $skillName = $this->option('skill');

        if (is_string($skillName) && '' !== $skillName) {
            return $this->validateSingleSkill($skillName);
        }

        return $this->validateAllSkills();
    }

    private function validateSingleSkill(string $skillName): int
    {
        $skill = $this->skillLoader->loadSkill($skillName);

        if (!$skill instanceof SkillInterface) {
            $this->error(sprintf('Skill "%s" not found.', $skillName));

            return self::FAILURE;
        }

        $result = $this->skillValidator->validate($skill);

        $this->table(
            ['Skill', 'Status', 'Errors', 'Warnings'],
            [[$skill->getName(), $result->isValid() ? 'valid' : 'invalid', count($result->getErrors()), count($result->getWarnings())]],
        );

        if ($result->hasWarnings()) {
            $this->newLine();
            $this->warn('Warnings:');

            foreach ($result->getWarnings() as $warning) {
                $this->line(sprintf(' * %s', $warning));
            }
        }

        if (!$result->isValid()) {
            $this->newLine();
            $this->error('Errors:');

            foreach ($result->getErrors() as $error) {
                $this->line(sprintf(' * %s', $error));
            }

            return self::FAILURE;
        }

        $this->info(sprintf('The skill "%s" is valid.', $skill->getName()));

        return self::SUCCESS;
    }

    private function validateAllSkills(): int
    {
        $skills = $this->skillLoader->loadSkills();

        if ([] === $skills) {
            $this->warn('No skills found.');

            return self::SUCCESS;
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
                    $this->line(sprintf(' * [%s] error: %s', $skill->getName(), $error));
                }
            }

            if ($result->hasWarnings()) {
                foreach ($result->getWarnings() as $warning) {
                    $this->line(sprintf(' * [%s] warning: %s', $skill->getName(), $warning));
                }
            }
        }

        $this->table(['Skill', 'Status', 'Errors', 'Warnings'], $rows);

        $this->newLine();
        $this->info('Summary');
        $this->line(sprintf('Total: %d', count($skills)));
        $this->line(sprintf('Valid: %d', $totalValid));
        $this->line(sprintf('Invalid: %d', $totalInvalid));
        $this->line(sprintf('Warnings: %d', $totalWarnings));

        if ($hasErrors) {
            return self::FAILURE;
        }

        $this->info('All skills are valid!');

        return self::SUCCESS;
    }
}
