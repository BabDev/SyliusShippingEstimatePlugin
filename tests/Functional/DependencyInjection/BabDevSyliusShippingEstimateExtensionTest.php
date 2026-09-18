<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Functional\DependencyInjection;

use BabDev\SyliusShippingEstimatePlugin\Controller\ShippingEstimatorController;
use BabDev\SyliusShippingEstimatePlugin\DependencyInjection\BabDevSyliusShippingEstimateExtension;
use BabDev\SyliusShippingEstimatePlugin\Form\Type\ShippingEstimatorType;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

final class BabDevSyliusShippingEstimateExtensionTest extends AbstractExtensionTestCase
{
    private const CONTROLLER_ID = 'babdev_sylius_shipping_estimate.shop.controller.shipping_estimator';

    /**
     * @test
     */
    public function the_container_is_loaded_with_the_plugin_services(): void
    {
        $this->load();

        $this->assertContainerBuilderHasService(self::CONTROLLER_ID, ShippingEstimatorController::class);
        $this->assertContainerBuilderHasService('babdev_sylius_shipping_estimate.shop.form.type.shipping_estimator', ShippingEstimatorType::class);
    }

    /**
     * @test
     */
    public function the_controller_is_given_the_plugins_own_limiter_by_default(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            self::CONTROLLER_ID,
            '$rateLimiterFactory',
            new Reference('limiter.babdev_sylius_shipping_estimate'),
        );
    }

    /**
     * @test
     */
    public function the_controller_is_given_a_configured_limiter_service(): void
    {
        $this->load(['rate_limiter' => ['service' => 'app.limiter.shipping']]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            self::CONTROLLER_ID,
            '$rateLimiterFactory',
            new Reference('app.limiter.shipping'),
        );
    }

    /**
     * @test
     */
    public function the_controller_is_given_no_limiter_when_rate_limiting_is_disabled(): void
    {
        $this->load(['rate_limiter' => ['enabled' => false]]);

        self::assertArrayNotHasKey(
            '$rateLimiterFactory',
            $this->container->getDefinition(self::CONTROLLER_ID)->getArguments(),
        );
    }

    /**
     * @test
     */
    public function it_registers_a_framework_limiter_from_the_plugin_configuration(): void
    {
        $container = $this->prependWith(['rate_limiter' => ['limit' => 5, 'interval' => '30 seconds']]);

        self::assertSame(
            [
                [
                    'rate_limiter' => [
                        'limiters' => [
                            'babdev_sylius_shipping_estimate' => [
                                'policy' => 'sliding_window',
                                'limit' => 5,
                                'interval' => '30 seconds',
                                'cache_pool' => 'cache.rate_limiter',
                                'lock_factory' => null,
                            ],
                        ],
                    ],
                ],
            ],
            $container->getExtensionConfig('framework'),
        );
    }

    /**
     * @test
     */
    public function it_registers_no_framework_limiter_when_given_one_to_use(): void
    {
        $container = $this->prependWith(['rate_limiter' => ['service' => 'app.limiter.shipping']]);

        self::assertSame([], $container->getExtensionConfig('framework'));
    }

    /**
     * @test
     */
    public function it_registers_no_framework_limiter_when_rate_limiting_is_disabled(): void
    {
        $container = $this->prependWith(['rate_limiter' => ['enabled' => false]]);

        self::assertSame([], $container->getExtensionConfig('framework'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function prependWith(array $config): ContainerBuilder
    {
        $extension = new BabDevSyliusShippingEstimateExtension();

        $container = new ContainerBuilder();
        $container->registerExtension($extension);
        $container->loadFromExtension($extension->getAlias(), $config);

        $extension->prepend($container);

        return $container;
    }

    /**
     * @return ExtensionInterface[]
     */
    protected function getContainerExtensions(): array
    {
        return [
            new BabDevSyliusShippingEstimateExtension(),
        ];
    }
}
