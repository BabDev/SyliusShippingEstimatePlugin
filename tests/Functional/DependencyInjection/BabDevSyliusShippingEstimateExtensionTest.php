<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Functional\DependencyInjection;

use BabDev\SyliusShippingEstimatePlugin\Controller\ShippingEstimatorController;
use BabDev\SyliusShippingEstimatePlugin\DependencyInjection\BabDevSyliusShippingEstimateExtension;
use BabDev\SyliusShippingEstimatePlugin\Form\Type\ShippingEstimatorType;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

final class BabDevSyliusShippingEstimateExtensionTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function the_container_is_loaded_with_the_plugin_services(): void
    {
        $this->load();

        $this->assertContainerBuilderHasService('babdev_sylius_shipping_estimate.shop.controller.shipping_estimator', ShippingEstimatorController::class);
        $this->assertContainerBuilderHasService('babdev_sylius_shipping_estimate.shop.form.type.shipping_estimator', ShippingEstimatorType::class);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new BabDevSyliusShippingEstimateExtension(),
        ];
    }
}
