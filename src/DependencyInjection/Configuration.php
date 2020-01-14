<?php

declare(strict_types=1);

namespace Roukmoute\DoctrinePrefixBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('roukmoute_doctrine_prefix');

        $rootNode
            ->children()
                ->scalarNode('prefix')
                    ->defaultValue('sf')
                    ->info('will be prepended to table and sequence names')
                ->end()
                ->arrayNode('bundles')
                    ->info('if set, the prefix will be applied to specified bundles only')
                    ->prototype('scalar')
                    ->end()
                ->end()
                ->scalarNode('encoding')
                    ->defaultValue('UTF-8')
                    ->info('the encoding to convert the prefix to')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
