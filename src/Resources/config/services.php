<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BabDev\SyliusShippingEstimatePlugin\Controller\ShippingEstimatorController;
use BabDev\SyliusShippingEstimatePlugin\Form\Type\ShippingEstimatorType;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('babdev_sylius_shipping_estimate.shop.controller.shipping_estimator', ShippingEstimatorController::class)
        ->args([
            expr('service("sylius.resource_registry").get("sylius.order")'),
            service('sylius.resource_controller.request_configuration_factory'),
            service('sylius.context.cart'),
            service('sylius.resource_controller.view_handler'),
            service('sylius.factory.address'),
            service('sylius.factory.adjustment'),
            service('sylius.shipping_methods_resolver'),
            service('sylius.registry.shipping_calculator'),
            service('sylius.money_formatter'),
            service('event_dispatcher'),
        ])
        ->call('setContainer', [service('service_container')])
        ->tag('controller.service_arguments')
    ;

    $services->set('babdev_sylius_shipping_estimate.shop.form.type.shipping_estimator', ShippingEstimatorType::class)
        ->tag('form.type')
    ;
};
