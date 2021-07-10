# Installation & Setup

To install this plugin, run the following [Composer](https://getcomposer.org/) command:

```bash
composer require babdev/sylius-shipping-estimate-plugin
```

## Register The Plugin

For an application using Symfony Flex the plugin should be automatically registered, but if not you will need to add it to your `config/bundles.php` file.

```php
<?php

return [
    // ...

    BabDev\SyliusShippingEstimatePlugin\BabDevSyliusShippingEstimatePlugin::class => ['all' => true],
];
```

## Import the Configuration

In your `config/packages/_sylius.yaml` file, import the plugin's configuration. This is used to register the plugin with the `SyliusUiBundle` template events to automatically add the widget to your cart page.

```yaml
imports:
    - { resource: "@BabDevSyliusShippingEstimatePlugin/Resources/config/app/config.yml" }
```

## Import the Routes

In your `config/routes.yaml` file, import the plugin's routing configuration. The below example uses the default locale-aware frontend routing for Sylius, which you can customize if desired.

```yaml
babdev_sylius_shipping_estimate_shop:
    resource: "@BabDevSyliusShippingEstimatePlugin/Resources/config/routing/shop.yml"
    prefix: /{_locale}
    requirements:
        _locale: ^[A-Za-z]{2,4}(_([A-Za-z]{4}|[0-9]{3}))?(_([A-Za-z]{2}|[0-9]{3}))?$
```
