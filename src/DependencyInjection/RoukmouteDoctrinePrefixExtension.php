<?php

declare(strict_types=1);

namespace Roukmoute\DoctrinePrefixBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class RoukmouteDoctrinePrefixExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('roukmoute_doctrineprefixbundle.prefix', $config['prefix']);
        $container->setParameter('roukmoute_doctrineprefixbundle.bundles', $config['bundles']);
        $container->setParameter('roukmoute_doctrineprefixbundle.encoding', $config['encoding']);
    }
}
