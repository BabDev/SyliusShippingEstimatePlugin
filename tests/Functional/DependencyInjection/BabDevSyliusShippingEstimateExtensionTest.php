<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Functional\DependencyInjection;

use BabDev\SyliusShippingEstimatePlugin\Controller\ShippingEstimatorController;
use BabDev\SyliusShippingEstimatePlugin\DependencyInjection\BabDevSyliusShippingEstimateExtension;
use BabDev\SyliusShippingEstimatePlugin\Estimator\EventDispatchingShippingEstimator;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimator;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimatorInterface;
use BabDev\SyliusShippingEstimatePlugin\Form\Type\ShippingEstimatorType;
use BabDev\SyliusShippingEstimatePlugin\Http\ShippingEstimateResponder;
use BabDev\SyliusShippingEstimatePlugin\Http\ShippingEstimateResponderInterface;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

final class BabDevSyliusShippingEstimateExtensionTest extends AbstractExtensionTestCase
{
    private const CONTROLLER_ID = 'babdev_sylius_shipping_estimate.shop.controller.shipping_estimator';

    private const ESTIMATOR_ID = 'babdev_sylius_shipping_estimate.estimator';

    private const DEFAULT_ESTIMATOR_ID = 'babdev_sylius_shipping_estimate.estimator.default';

    private const RESPONDER_ID = 'babdev_sylius_shipping_estimate.shop.estimate_responder';

    /**
     * @test
     */
    public function the_container_is_loaded_with_the_plugin_services(): void
    {
        $this->load();

        $this->assertContainerBuilderHasService(self::CONTROLLER_ID, ShippingEstimatorController::class);
        $this->assertContainerBuilderHasService('babdev_sylius_shipping_estimate.shop.form.type.shipping_estimator', ShippingEstimatorType::class);
        $this->assertContainerBuilderHasService(self::DEFAULT_ESTIMATOR_ID, ShippingEstimator::class);
        $this->assertContainerBuilderHasService(self::ESTIMATOR_ID, EventDispatchingShippingEstimator::class);
        $this->assertContainerBuilderHasService(self::RESPONDER_ID, ShippingEstimateResponder::class);
    }

    /**
     * @test
     */
    public function the_event_seam_wraps_the_estimator_that_prices_the_methods(): void
    {
        $this->load();

        // The dispatch is the outer service, so replacing the inner one keeps the event.
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            self::ESTIMATOR_ID,
            0,
            new Reference(self::DEFAULT_ESTIMATOR_ID),
        );

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            self::CONTROLLER_ID,
            6,
            new Reference(self::ESTIMATOR_ID),
        );
    }

    /**
     * @test
     */
    public function the_seams_are_aliased_by_their_interfaces(): void
    {
        $this->load();

        // What an integrator overrides or decorates to replace either half of the estimate.
        $this->assertContainerBuilderHasAlias(ShippingEstimatorInterface::class, self::ESTIMATOR_ID);
        $this->assertContainerBuilderHasAlias(ShippingEstimateResponderInterface::class, self::RESPONDER_ID);
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

        $this->assertArrayNotHasKey('$rateLimiterFactory', $this->container->getDefinition(self::CONTROLLER_ID)->getArguments());
    }

    /**
     * @test
     */
    public function it_registers_a_framework_limiter_from_the_plugin_configuration(): void
    {
        $container = $this->prependWith(['rate_limiter' => ['limit' => 5, 'interval' => '30 seconds']]);

        $this->assertSame(
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

        $this->assertSame([], $container->getExtensionConfig('framework'));
    }

    /**
     * @test
     */
    public function it_registers_no_framework_limiter_when_rate_limiting_is_disabled(): void
    {
        $container = $this->prependWith(['rate_limiter' => ['enabled' => false]]);

        $this->assertSame([], $container->getExtensionConfig('framework'));
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
