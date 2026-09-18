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

    /**
     * Overridden because {@see SyliusPluginTrait} derives the expected extension alias from the plugin
     * name, which would underscore "BabDev" to "bab_dev" and not match this extension's alias.
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        // The trait types this property as `ExtensionInterface|bool`; only ever assigned one here.
        if (!$this->containerExtension instanceof ExtensionInterface) {
            $this->containerExtension = new BabDevSyliusShippingEstimateExtension();
        }

        return $this->containerExtension;
    }
}
