<?php

namespace Arcadia\Bundle\RunnerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('arcadia_runner');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()

                ->arrayNode('runners')
                    ->arrayPrototype()
                        ->children()

                            ->integerNode('tasks_in_parallel')
                                ->min(1)
                                ->defaultValue(1)
                            ->end()

                            ->arrayNode('runner')
                                ->children()
                                    ->scalarNode('class')
                                        ->defaultValue('Arcadia\\Bundle\\RunnerBundle\\Entity\\DefaultRunner')
                                    ->end()
                                    ->scalarNode('doctrine_manager')
                                        ->defaultValue('default')
                                    ->end()
                                    ->integerNode('idle')
                                        ->min(0)
                                        ->defaultValue(2)
                                    ->end()
                                    ->booleanNode('refresh')
                                        ->defaultFalse()
                                    ->end()
                                    ->integerNode('ttl')
                                        ->min(0)
                                        ->defaultValue(1000)
                                    ->end()
                                ->end()
                            ->end()

                            ->arrayNode('task')
                                ->children()
                                    ->scalarNode('class')
                                        ->defaultValue('Arcadia\\Bundle\\RunnerBundle\\Entity\\DefaultTask')
                                    ->end()
                                    ->scalarNode('doctrine_manager')
                                        ->defaultValue('default')
                                    ->end()
                                    ->scalarNode('handler')
                                    ->end()
                                ->end()
                            ->end()

                        ->end()
                    ->end()
                ->end()

            ->end()
        ;

        return $treeBuilder;
    }
}