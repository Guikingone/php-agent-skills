<?php

declare(strict_types=1);

use AgentSkills\ChainSkillLoader;
use AgentSkills\FilesystemSkillLoader;
use AgentSkills\GithubSkillLoader;
use AgentSkills\SkillInterface;
use Symfony\Component\HttpClient\HttpClient;

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Combine local and remote skill loaders
// Local skills take precedence over remote ones
$localLoader = new FilesystemSkillLoader([__DIR__ . '/.skills']);

$githubLoader = new GithubSkillLoader(
    [
        ['repository' => 'smnandre/symfony-ux-skills'],
        ['repository' => 'smnandre/symfony-ux-skills', 'token' => 'foo', 'branch' => 'main'],
    ],
    HttpClient::create(),
);

$chainLoader = new ChainSkillLoader([$localLoader, $githubLoader]);

$skill = $chainLoader->loadSkill('twig-component');

assert($skill instanceof SkillInterface);

echo $skill->getBody();
