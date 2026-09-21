<?php

declare(strict_types=1);

namespace spec\BabDev\SyliusShippingEstimatePlugin\Estimator;

use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimate;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateOption;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateReasons;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimatorInterface;
use BabDev\SyliusShippingEstimatePlugin\Event\BeforeEstimateShippingEvent;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EventDispatchingShippingEstimatorSpec extends ObjectBehavior
{
    public function let(ShippingEstimatorInterface $estimator, EventDispatcherInterface $eventDispatcher): void
    {
        $this->beConstructedWith($estimator, $eventDispatcher);
    }

    public function it_is_a_shipping_estimator(): void
    {
        $this->shouldImplement(ShippingEstimatorInterface::class);
    }

    public function it_gives_listeners_the_cart_and_the_address_being_estimated_for(
        ShippingEstimatorInterface $estimator,
        EventDispatcherInterface $eventDispatcher,
        OrderInterface $cart,
        AddressInterface $address,
    ): void {
        $estimate = $this->anEstimate();

        $unwrappedCart = $cart->getWrappedObject();
        $unwrappedAddress = $address->getWrappedObject();

        $eventDispatcher
            ->dispatch(Argument::that(static fn ($event): bool => $event instanceof BeforeEstimateShippingEvent &&
                $event->getCart() === $unwrappedCart &&
                $event->getAddress() === $unwrappedAddress))
            ->willReturnArgument(0)
        ;

        $estimator->estimate($cart, $address)->willReturn($estimate);

        $this->estimate($cart, $address)->shouldBeLike($estimate);
    }

    public function it_estimates_for_an_address_a_listener_replaced(
        ShippingEstimatorInterface $estimator,
        EventDispatcherInterface $eventDispatcher,
        OrderInterface $cart,
        AddressInterface $address,
        AddressInterface $replacement,
    ): void {
        $estimate = $this->anEstimate();

        $unwrappedReplacement = $replacement->getWrappedObject();

        $eventDispatcher
            ->dispatch(Argument::type(BeforeEstimateShippingEvent::class))
            ->will(static function (array $args) use ($unwrappedReplacement): object {
                $args[0]->setAddress($unwrappedReplacement);

                return $args[0];
            })
        ;

        // A listener may swap the address outright rather than editing the one it was handed.
        $estimator->estimate($cart, $replacement)->willReturn($estimate)->shouldBeCalled();
        $estimator->estimate($cart, $address)->shouldNotBeCalled();

        $this->estimate($cart, $address)->shouldBeLike($estimate);
    }

    public function it_reports_a_cancelled_estimate_without_making_one(
        ShippingEstimatorInterface $estimator,
        EventDispatcherInterface $eventDispatcher,
        OrderInterface $cart,
        AddressInterface $address,
    ): void {
        $eventDispatcher
            ->dispatch(Argument::type(BeforeEstimateShippingEvent::class))
            ->will(static function (array $args): object {
                $args[0]->cancelEstimate('We do not ship there.');

                return $args[0];
            })
        ;

        $estimator->estimate(Argument::cetera())->shouldNotBeCalled();

        $this->estimate($cart, $address)->shouldBeLike(ShippingEstimate::unavailable(
            ShippingEstimateReasons::CANCELLED,
            'We do not ship there.',
        ));
    }

    private function anEstimate(): ShippingEstimate
    {
        return ShippingEstimate::of(new ShippingEstimateOption('ups_ground', 'UPS Ground', 2500, 'USD'));
    }
}
