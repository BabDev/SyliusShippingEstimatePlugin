# Changelog

## 0.3.0 (????-??-??)

- Drop support for PHP 8.0
- Use the `Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface` service in the shipping estimator controller instead of directly using the shipping calculator registry
- Fix the AJAX shipping estimate endpoint erroring on every request
- Report shipping as unavailable instead of raising a server error when the cart has no shipment
- Fix each shipping option being priced with the cart's existing shipping method instead of its own
- Revert the estimate address on the cart once the estimate is built, so requesting an estimate no longer changes the customer's cart
- Make the messages shown by the JavaScript translatable, passing them to the script as `data-message-*` attributes on the estimator form
- Render shipping method names as text rather than markup in the estimate table
- Handle error responses that carry no JSON body instead of failing silently
- Answer `shipping_not_supported` with a 200 response, matching `shipping_not_available`; both mean the estimate ran and found no rates
- Decouple the controller from `Symfony\Bundle\FrameworkBundle\Controller\AbstractController`

## 0.2.0 (2022-07-15)

- Add support for Sylius 1.11
- Drop support for PHP 7.4
- Drop support for Symfony <5.4
- Drop support for Sylius <1.11
