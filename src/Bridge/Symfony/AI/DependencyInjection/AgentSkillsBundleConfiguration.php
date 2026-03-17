<?php

declare(strict_types=1);

namespace AgentSkills\Bridge\Symfony\AI\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class AgentSkillsBundleConfiguration implements ConfigurationInterface
{
    /**
     * @return TreeBuilder<'array'>
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('agent_skills');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('skills')
                    ->addDefaultsIfNotSet()
                    ->treatFalseLike(['enabled' => false])
                    ->treatTrueLike(['enabled' => true])
                    ->treatNullLike(['enabled' => false])
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->arrayNode('agents')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->stringNode('loader')
                                        ->defaultValue('agent_skills.filesystem_loader')
                                        ->info('A service implementing "AgentSkills\SkillLoaderInterface", default to "agent_skills.filesystem_loader".')
                                    ->end()
                                    ->arrayNode('directories')
                                        ->defaultValue(['%kernel.share_dir%/skills'])
                                        ->acceptAndWrap(['string'])
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->arrayNode('github_repositories')
                                        ->info('GitHub repositories to load skills from. Each entry requires a "repository" key in "owner/repo" format.')
                                        ->arrayPrototype()
                                            ->children()
                                                ->stringNode('repository')
                                                    ->isRequired()
                                                    ->cannotBeEmpty()
                                                    ->info('GitHub repository in "owner/repo" format or full URL.')
                                                ->end()
                                                ->stringNode('path')
                                                    ->defaultValue('')
                                                    ->info('Subdirectory within the repository where skills are stored.')
                                                ->end()
                                                ->stringNode('branch')
                                                    ->defaultValue('main')
                                                    ->info('Branch to load skills from.')
                                                ->end()
                                                ->stringNode('token')
                                                    ->defaultNull()
                                                    ->info('GitHub personal access token for private repositories.')
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('active_skills')
                                        ->arrayPrototype()
                                            ->children()
                                                ->stringNode('name')->cannotBeEmpty()->end()
                                            ->end()
                                            ->beforeNormalization()
                                            ->ifString()
                                                ->then(static fn (string $v): array => ['name' => $v])
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->booleanNode('include_index')
                                        ->info('Whether to include a skill index in the system prompt.')
                                        ->defaultFalse()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('evaluation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->stringNode('workspace')
                            ->defaultValue('%kernel.project_dir%/var/skill-evals')
                            ->info('Directory to store evaluation results.')
                        ->end()
                        ->stringNode('grading_model')
                            ->defaultNull()
                            ->info('Model to use for LLM grading (e.g. "gpt-4o-mini").')
                        ->end()
                        ->stringNode('grading_platform')
                            ->defaultNull()
                            ->info('Platform service reference for grading.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
