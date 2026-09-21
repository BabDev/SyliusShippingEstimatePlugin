# Replacing the Estimator

The estimate is made by two services, and either can be replaced on its own.

| Service ID                                                | Interface                            | Responsibility                                                        |
|-----------------------------------------------------------|--------------------------------------|-----------------------------------------------------------------------|
| `babdev_sylius_shipping_estimate.estimator.default`       | `ShippingEstimatorInterface`         | Works out what the cart costs to ship to an address                   |
| `babdev_sylius_shipping_estimate.estimator`               | `ShippingEstimatorInterface`         | Dispatches `BeforeEstimateShippingEvent`, then delegates to the above |
| `babdev_sylius_shipping_estimate.shop.estimate_responder` | `ShippingEstimateResponderInterface` | Turns the estimate into the JSON the widget reads                     |

Both interfaces are aliased to the service the plugin registers, so `decorates:` and autowiring work without naming the concrete classes.

## Replacing How Rates Are Worked Out

`BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimatorInterface` takes the cart and the address being estimated for, and answers with a `ShippingEstimate`:

```php
<?php

namespace App\Shipping;

use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimate;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateOption;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateReasons;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimatorInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;

final class CarrierApiShippingEstimator implements ShippingEstimatorInterface
{
    public function __construct(private CarrierApi $carrierApi)
    {
    }

    public function estimate(OrderInterface $cart, AddressInterface $address): ShippingEstimate
    {
        $quotes = $this->carrierApi->quote($cart, $address);

        if ($quotes->refusedForWeight()) {
            return ShippingEstimate::unavailable('package_overweight');
        }

        if ($quotes->isEmpty()) {
            return ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_AVAILABLE);
        }

        return ShippingEstimate::of(...array_map(
            static fn (Quote $quote): ShippingEstimateOption => new ShippingEstimateOption(
                $quote->methodCode,
                $quote->methodName,
                $quote->amountInCents,
                (string) $cart->getCurrencyCode(),
            ),
            $quotes->all(),
        ));
    }
}
```

Register it as the **inner** service, so listeners on `BeforeEstimateShippingEvent` still run:

```yaml
services:
    App\Shipping\CarrierApiShippingEstimator: ~

    babdev_sylius_shipping_estimate.estimator.default:
        alias: App\Shipping\CarrierApiShippingEstimator
```

Replacing `babdev_sylius_shipping_estimate.estimator` instead takes over the event dispatch as well, which means listeners stop being called. Prefer the inner service unless that is what you want.

### Leave The Cart As You Found It

Sylius resolves shipping methods from the address on the shipment's order, so an estimate against the shop's own methods has to put the address being estimated for onto the cart. That address is a hypothetical the customer has not chosen, and leaving it behind hands whatever flushes the cart next an address they never asked for.

An implementation that puts anything on the cart or its shipments must put the original back, including when the estimate fails part way through. The plugin's own estimator does this in a `finally` block. The same applies to the shipment's shipping method if you swap it to price a row.

### Amounts Are Integers

`ShippingEstimateOption` carries the rate as an integer in the currency's minor units, which is what every shipping calculator reports and what a machine consumer needs. Formatting it for a person to read is the responder's job.

## Reasons

An estimate with no options in it always carries a reason. The plugin's own are constants on `BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateReasons`:

| Constant           | Value                               | Meaning                                                     |
|--------------------|-------------------------------------|-------------------------------------------------------------|
| `NOT_AVAILABLE`    | `shipping_not_available`            | The estimate ran and found no rates                         |
| `NOT_SUPPORTED`    | `shipping_not_supported`            | Shipping methods could not be resolved for the cart         |
| `CALCULATOR_ERROR` | `shipping_calculator_error`         | Every shipping method errored out while being priced        |
| `CANCELLED`        | `shipping_estimate_cancelled`       | A listener stopped the estimate                             |
| `INVALID_REQUEST`  | `shipping_estimate_invalid_request` | The request did not describe an address, so no estimate ran |

Your estimator may report reasons of its own, including what your carriers actually refuse for, and nothing downstream assumes a reason came from that list. To word one for the customer, add a `data-message-{reason}` attribute to the widget's form; see [Customize the Output](/open-source/packages/shipping-estimate-plugin/docs/1.x/customize-the-output).

## Adding To The Response

`ShippingEstimate::withMetadata()` adds keys to the JSON payload without your having to replace the responder:

```php
return ShippingEstimate::of(...$options)->withMetadata('quoted_as_residential', true);
```

`ShippingEstimateOption` takes per-option metadata the same way, as its last constructor argument, which lands on that option's row.

Metadata is merged **under** the keys the endpoint already sends, so `error`, `options`, `reason`, and `custom_reason` cannot be overwritten by it.

## Replacing The Response

Replace `BabDev\SyliusShippingEstimatePlugin\Http\ShippingEstimateResponderInterface` to change the payload itself, or the status a reason is answered with.

The plugin's responder answers a canceled estimate and an invalid request with a `400`, and a calculator error with a `500`; every other reason, including any of your own, gets a `200`, on the grounds that an estimate which ran and came back with nothing is an answer rather than a failure. That mapping is its second constructor argument:

```yaml
services:
    babdev_sylius_shipping_estimate.shop.estimate_responder:
        class: BabDev\SyliusShippingEstimatePlugin\Http\ShippingEstimateResponder
        arguments:
            - '@sylius.money_formatter'
            - shipping_estimate_cancelled: 400
              shipping_estimate_invalid_request: 400
              shipping_calculator_error: 500
              package_overweight: 400
```
