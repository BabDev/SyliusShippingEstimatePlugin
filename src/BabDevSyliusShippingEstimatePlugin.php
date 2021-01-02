<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin;

use BabDev\SyliusShippingEstimatePlugin\DependencyInjection\BabDevSyliusShippingEstimateExtension;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class BabDevSyliusShippingEstimatePlugin extends Bundle
{
    use SyliusPluginTrait;

    protected function getBundlePrefix(): string
    {
        return 'babdev_sylius_shipping_estimate';
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->containerExtension) {
            $this->containerExtension = new BabDevSyliusShippingEstimateExtension();
        }

        return $this->containerExtension ?? null;
    }
}
