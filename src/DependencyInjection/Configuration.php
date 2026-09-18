<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('babdev_sylius_shipping_estimate');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rateLimiter = $rootNode->children()->arrayNode('rate_limiter');

        $rateLimiter->info('Enables the RateLimiter component when available for the shipping estimate endpoint.');

        if (ContainerBuilder::willBeAvailable('symfony/rate-limiter', RateLimiterFactory::class, ['babdev/sylius-shipping-estimate-plugin'])) {
            $rateLimiter->canBeDisabled();
        } else {
            $rateLimiter->canBeEnabled();
        }

        $children = $rateLimiter->children();

        $children->scalarNode('service')
            ->info('The service ID of a rate limiter factory to use. When set, every other option is ignored.')
            ->defaultNull()
        ;

        $children->enumNode('policy')
            ->info('The algorithm used by the limiter this plugin registers.')
            ->values(['fixed_window', 'token_bucket', 'sliding_window', 'no_limit'])
            ->defaultValue('sliding_window')
        ;

        $children->integerNode('limit')
            ->info('The maximum number of estimates a single client may request in an interval.')
            ->min(1)
            ->defaultValue(30)
        ;

        $children->scalarNode('interval')
            ->info('The interval the limit applies over, as a number followed by a unit such as "1 minute".')
            ->defaultValue('1 minute')
        ;

        $children->scalarNode('cache_pool')
            ->info('The cache pool holding the limiter state.')
            ->defaultValue('cache.rate_limiter')
        ;

        $children->scalarNode('lock_factory')
            ->info('The lock factory used to make limiter updates atomic, used when the Lock component is installed and enabled.')
            ->defaultNull()
        ;

        return $treeBuilder;
    }
}
