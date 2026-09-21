<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Estimator;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

/**
 * Estimates shipping with the shipping methods and calculators the shop is already configured with.
 */
final class ShippingEstimator implements ShippingEstimatorInterface
{
    public function __construct(
        private readonly ShippingMethodsResolverInterface $shippingMethodsResolver,
        private readonly DelegatingCalculatorInterface $shippingCalculator,
    ) {
    }

    public function estimate(OrderInterface $cart, AddressInterface $address): ShippingEstimate
    {
        $originalShippingAddress = $cart->getShippingAddress();

        // The shipping method resolver reads the address from the shipment's order, so the estimate address has to be put on the cart.
        $cart->setShippingAddress($address);

        try {
            return $this->buildEstimate($cart);
        } finally {
            $cart->setShippingAddress($originalShippingAddress);
        }
    }

    /**
     * Resolves the shipping methods available to the cart and prices each of them.
     *
     * Expects the address being estimated for to already be set on the cart.
     */
    private function buildEstimate(OrderInterface $cart): ShippingEstimate
    {
        $shipments = $cart->getShipments();

        if ($shipments->count() === 0) {
            return ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_AVAILABLE);
        }

        /** @var ShipmentInterface $shipment */
        $shipment = $shipments->first();
        $shipment->setOrder($cart);

        if (!$this->shippingMethodsResolver->supports($shipment)) {
            return ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_SUPPORTED);
        }

        $options = [];
        $hadError = false;

        $originalShippingMethod = $shipment->getMethod();

        try {
            foreach ($this->shippingMethodsResolver->getSupportedMethods($shipment) as $shippingMethod) {
                try {
                    $shipment->setMethod($shippingMethod);

                    $options[] = new ShippingEstimateOption(
                        (string) $shippingMethod->getCode(),
                        (string) $shippingMethod->getName(),
                        $this->shippingCalculator->calculate($shipment),
                        (string) $cart->getCurrencyCode(),
                    );
                } catch (\Exception) {
                    /*
                     * Errored out getting a rate for this calculator, just skip it; we can report the
                     * calculator error if the options list is totally empty.
                     */
                    $hadError = true;
                }
            }
        } finally {
            $shipment->setMethod($originalShippingMethod);
        }

        if ($options === []) {
            if ($hadError) {
                return ShippingEstimate::unavailable(ShippingEstimateReasons::CALCULATOR_ERROR);
            }

            return ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_AVAILABLE);
        }

        return ShippingEstimate::of(...$options);
    }
}
