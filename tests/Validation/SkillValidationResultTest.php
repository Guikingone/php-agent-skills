<?php

declare(strict_types=1);

namespace AgentSkills\Tests\Validation;

use AgentSkills\Skill;
use AgentSkills\SkillMetadata;
use AgentSkills\Validation\SkillValidationResult;
use PHPUnit\Framework\TestCase;

final class SkillValidationResultTest extends TestCase
{
    public function testIsValidWithNoErrors()
    {
        $metadata = new SkillMetadata('my-skill', 'A skill');
        $skill = new Skill('This is the instruction body.', $metadata);

        $result = new SkillValidationResult($skill);

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
        $this->assertSame([], $result->getWarnings());
        $this->assertFalse($result->hasWarnings());
    }

    public function testIsNotValidWithErrors()
    {
        $metadata = new SkillMetadata('my-skill', 'A skill');
        $skill = new Skill('This is the instruction body.', $metadata);

        $result = new SkillValidationResult($skill, ['Missing name field.']);

        $this->assertFalse($result->isValid());
        $this->assertSame(['Missing name field.'], $result->getErrors());
    }

    public function testIsValidWithWarningsOnly()
    {
        $metadata = new SkillMetadata('my-skill', 'A skill');
        $skill = new Skill('This is the instruction body.', $metadata);

        $result = new SkillValidationResult($skill, [], ['Description is short.']);

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertSame(['Description is short.'], $result->getWarnings());
    }

    public function testMultipleErrorsAndWarnings()
    {
        $metadata = new SkillMetadata('my-skill', 'A skill');
        $skill = new Skill('This is the instruction body.', $metadata);

        $result = new SkillValidationResult($skill, ['err1', 'err2'], ['warn1']);

        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->getErrors());
        $this->assertCount(1, $result->getWarnings());
    }

    public function testGetSkillName()
    {
        $metadata = new SkillMetadata('my-skill', 'A skill');
        $skill = new Skill('This is the instruction body.', $metadata);

        $result = new SkillValidationResult($skill);

        $this->assertSame('my-skill', $result->getSkillName());
    }
}
