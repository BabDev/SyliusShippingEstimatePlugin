<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Estimator;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;

/**
 * Prices the shipping methods available to a cart for an address the customer has not committed to.
 */
interface ShippingEstimatorInterface
{
    /**
     * Estimates the cost of shipping the cart to the given address.
     *
     * Implementations must leave the cart exactly as they found it.
     */
    public function estimate(OrderInterface $cart, AddressInterface $address): ShippingEstimate;
}
