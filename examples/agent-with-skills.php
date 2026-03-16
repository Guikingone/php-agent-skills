<?php

declare(strict_types=1);

use AgentSkills\FilesystemSkillLoader;
use AgentSkills\SkillInterface;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$loader = new FilesystemSkillLoader([
    __DIR__ . '/.skills',
]);

$skill = $loader->loadSkill('twig-component');

assert($skill instanceof SkillInterface);

echo $skill->getBody();
