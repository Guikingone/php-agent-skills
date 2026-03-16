<?php

declare(strict_types=1);

use AgentSkills\GithubSkillLoader;
use AgentSkills\SkillInterface;
use Symfony\Component\HttpClient\HttpClient;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load skills from a public GitHub repository
$loader = new GithubSkillLoader(
    [['repository' => 'smnandre/symfony-ux-skills']],
    HttpClient::create(),
);

$skill = $loader->loadSkill('twig-component');

assert($skill instanceof SkillInterface);

echo $skill->getBody();
