<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\DependencyInjection;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class BabDevSyliusShippingEstimateExtension extends Extension implements PrependExtensionInterface
{
    /**
     * The name of the rate limiter this plugin registers when it is not given one to use.
     */
    private const LIMITER_NAME = 'babdev_sylius_shipping_estimate';

    public function getAlias(): string
    {
        return 'babdev_sylius_shipping_estimate';
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->resolveConfig($container);

        if (!$this->shouldRegisterLimiter($config)) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'rate_limiter' => [
                'limiters' => [
                    self::LIMITER_NAME => [
                        'policy' => $config['rate_limiter']['policy'],
                        'limit' => $config['rate_limiter']['limit'],
                        'interval' => $config['rate_limiter']['interval'],
                        'cache_pool' => $config['rate_limiter']['cache_pool'],
                        'lock_factory' => $config['rate_limiter']['lock_factory'],
                    ],
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{rate_limiter: array{enabled: bool, service: string|null}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        if (!$this->isConfigEnabled($container, $config['rate_limiter'])) {
            return;
        }

        $limiter = $config['rate_limiter']['service'] ?? 'limiter.' . self::LIMITER_NAME;

        $container
            ->getDefinition('babdev_sylius_shipping_estimate.shop.controller.shipping_estimator')
            ->setArgument('$rateLimiterFactory', new Reference($limiter))
        ;
    }

    /**
     * @return array{rate_limiter: array{enabled: bool, service: string|null, policy: string, limit: int, interval: string, cache_pool: string, lock_factory: string|null}}
     */
    private function resolveConfig(ContainerBuilder $container): array
    {
        /** @var array{rate_limiter: array{enabled: bool, service: string|null, policy: string, limit: int, interval: string, cache_pool: string, lock_factory: string|null}} $config */
        $config = $this->processConfiguration(
            new Configuration(),
            $container->getExtensionConfig($this->getAlias()),
        );

        return $config;
    }

    /**
     * @param array{rate_limiter: array{enabled: bool, service: string|null}} $config
     */
    private function shouldRegisterLimiter(array $config): bool
    {
        // Nothing to register when limiting is off, or when the application brought its own limiter.
        if (!$config['rate_limiter']['enabled'] || null !== $config['rate_limiter']['service']) {
            return false;
        }

        // The framework extension owns the `rate_limiter` config key, so it has to be there to prepend to.
        return class_exists(FrameworkExtension::class);
    }
}
