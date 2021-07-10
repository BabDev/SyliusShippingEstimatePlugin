# Hooking the Estimator

When a user submits the form to request a shipping estimate, the controller will dispatch an event that allows developers to manipulate the address that will be used for the estimate or cancel the request.

The `BabDev\SyliusShippingEstimatePlugin\Event\BeforeEstimateShippingEvent` event is dispatched, which contains the current cart and the address data from the estimate form.

## Examples

### Adding Data To The Address

A dynamic shipping calculator may require more data than just the country and postal code, so an event listener may be used to add extra data to the address.

```php
<?php

namespace App\EventListener;

use App\Service\ShippingApisService;
use BabDev\SyliusShippingEstimatePlugin\Event\BeforeEstimateShippingEvent;

final class AddProvinceToShippingEstimateAddressListener
{
    private ShippingApisService $shippingApisService;

    public function __construct(ShippingApisService $shippingApisService)
    {
        $this->shippingApisService = $shippingApisService;
    }

    public function __invoke(BeforeEstimateShippingEvent $event): void
    {
        // Only applies to US customers
        if ($event->getAddress()->getCountryCode() !== 'US') {
            return;
        }

        // Uses the shippingapis.com API to get the state for the given postal code, stripping off the 4-digit suffix
        $state = $this->shippingApisService->getState(substr(trim($event->getAddress()->getPostcode()), 0, 5));

        $event->getAddress()->setProvinceCode('US-' . $state);
    }
}
```

### Cancelling The Request

A shipping estimate can be cancelled by calling the plugin's `cancelEstimate` method, providing a reason for the cancellation. Note, the reason will be displayed on the site frontend with the default JavaScript integration.

```php
<?php

namespace App\EventListener;

use BabDev\SyliusShippingEstimatePlugin\Event\BeforeEstimateShippingEvent;

final class CancelShippingEstimatesForUnitedStatesCustomersListener
{
    public function __invoke(BeforeEstimateShippingEvent $event): void
    {
        // Only applies to US customers
        if ($event->getAddress()->getCountryCode() !== 'US') {
            return;
        }

        $event->cancelEstimate('Sorry, we do not provide shipping estimates for US customers.');
    }
}
```
