<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class BabDevSyliusShippingEstimateExtension extends Extension
{
    public function getAlias(): string
    {
        return 'babdev_sylius_shipping_estimate';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
    }
}
