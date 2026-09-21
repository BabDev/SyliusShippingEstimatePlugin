<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BabDev\SyliusShippingEstimatePlugin\Controller\ShippingEstimatorController;
use BabDev\SyliusShippingEstimatePlugin\Estimator\EventDispatchingShippingEstimator;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimator;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimatorInterface;
use BabDev\SyliusShippingEstimatePlugin\Form\Type\ShippingEstimatorType;
use BabDev\SyliusShippingEstimatePlugin\Http\ShippingEstimateResponder;
use BabDev\SyliusShippingEstimatePlugin\Http\ShippingEstimateResponderInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('babdev_sylius_shipping_estimate.estimator.default', ShippingEstimator::class)
        ->args([
            service('sylius.shipping_methods_resolver'),
            service('sylius.shipping_calculator'),
        ])
    ;

    $services->set('babdev_sylius_shipping_estimate.estimator', EventDispatchingShippingEstimator::class)
        ->args([
            service('babdev_sylius_shipping_estimate.estimator.default'),
            service('event_dispatcher'),
        ])
    ;

    $services->alias(ShippingEstimatorInterface::class, 'babdev_sylius_shipping_estimate.estimator');

    $services->set('babdev_sylius_shipping_estimate.shop.estimate_responder', ShippingEstimateResponder::class)
        ->args([
            service('sylius.money_formatter'),
        ])
    ;

    $services->alias(ShippingEstimateResponderInterface::class, 'babdev_sylius_shipping_estimate.shop.estimate_responder');

    $services->set('babdev_sylius_shipping_estimate.shop.controller.shipping_estimator', ShippingEstimatorController::class)
        ->args([
            expr('service("sylius.resource_registry").get("sylius.order")'),
            service('sylius.resource_controller.request_configuration_factory'),
            service('sylius.context.cart'),
            service('form.factory'),
            service('twig'),
            service('sylius.factory.address'),
            service('babdev_sylius_shipping_estimate.estimator'),
            service('babdev_sylius_shipping_estimate.shop.estimate_responder'),
        ])
        ->tag('controller.service_arguments')
    ;

    $services->set('babdev_sylius_shipping_estimate.shop.form.type.shipping_estimator', ShippingEstimatorType::class)
        ->tag('form.type')
    ;
};
