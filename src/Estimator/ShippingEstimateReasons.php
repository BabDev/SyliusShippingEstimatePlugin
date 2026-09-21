<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Estimator;

final class ShippingEstimateReasons
{
    /**
     * The estimate ran and found no rates. Not considered an error.
     */
    public const NOT_AVAILABLE = 'shipping_not_available';

    /**
     * Nothing could resolve shipping methods for the cart, usually because the address does not match a shipping zone. Not considered an error.
     */
    public const NOT_SUPPORTED = 'shipping_not_supported';

    /**
     * Every shipping method errored out while being priced.
     */
    public const CALCULATOR_ERROR = 'shipping_calculator_error';

    /**
     * A listener on the before-estimate event stopped the estimate.
     */
    public const CANCELLED = 'shipping_estimate_cancelled';

    /**
     * The request did not describe an address to estimate for, so no estimate was attempted.
     */
    public const INVALID_REQUEST = 'shipping_estimate_invalid_request';
}
