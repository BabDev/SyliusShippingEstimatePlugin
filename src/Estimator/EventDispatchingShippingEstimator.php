<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Estimator;

use BabDev\SyliusShippingEstimatePlugin\Event\BeforeEstimateShippingEvent;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Gives listeners their say on an estimate before it is made, then hands it to the estimator behind this one.
 */
final class EventDispatchingShippingEstimator implements ShippingEstimatorInterface
{
    public function __construct(
        private ShippingEstimatorInterface $estimator,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function estimate(OrderInterface $cart, AddressInterface $address): ShippingEstimate
    {
        $event = new BeforeEstimateShippingEvent($cart, $address);

        $this->eventDispatcher->dispatch($event);

        if ($event->isPropagationStopped()) {
            return ShippingEstimate::unavailable(
                ShippingEstimateReasons::CANCELLED,
                $event->getCancelReason(),
            );
        }

        return $this->estimator->estimate($cart, $event->getAddress());
    }
}
