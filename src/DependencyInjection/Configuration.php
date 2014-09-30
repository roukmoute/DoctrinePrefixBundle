<?php

namespace Roukmoute\DoctrinePrefixBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('mathias_strasser_doctrine_prefix');

        $rootNode
            ->children()
                ->scalarNode('prefix')
                    ->defaultValue('sf')
                ->end()
                ->arrayNode('bundles')
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->scalarNode('encoding')
                    ->defaultValue('UTF-8')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
