# Changelog

## 0.3.0 (????-??-??)

- Drop support for PHP 8.0
- Use the `Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface` service in the shipping estimator controller instead of directly using the shipping calculator registry
- Fix the AJAX shipping estimate endpoint erroring on every request
- Report shipping as unavailable instead of raising a server error when the cart has no shipment

## 0.2.0 (2022-07-15)

- Add support for Sylius 1.11
- Drop support for PHP 7.4
- Drop support for Symfony <5.4
- Drop support for Sylius <1.11
