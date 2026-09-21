<?php

declare(strict_types=1);

namespace spec\BabDev\SyliusShippingEstimatePlugin\Estimator;

use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimate;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateOption;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateReasons;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimatorInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Shipment;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Shipping\Calculator\DelegatingCalculatorInterface;
use Sylius\Component\Shipping\Model\ShippingMethodInterface;
use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;

class ShippingEstimatorSpec extends ObjectBehavior
{
    public function let(
        ShippingMethodsResolverInterface $shippingMethodsResolver,
        DelegatingCalculatorInterface $shippingCalculator,
    ): void {
        $this->beConstructedWith($shippingMethodsResolver, $shippingCalculator);
    }

    public function it_is_a_shipping_estimator(): void
    {
        $this->shouldImplement(ShippingEstimatorInterface::class);
    }

    public function it_reports_shipping_as_unavailable_for_a_cart_without_a_shipment(): void
    {
        $this->estimate($this->createCart(), $this->createEstimateAddress())
            ->shouldBeLike(ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_AVAILABLE))
        ;
    }

    public function it_reports_shipping_as_unsupported_when_the_methods_cannot_be_resolved(
        ShippingMethodsResolverInterface $shippingMethodsResolver,
    ): void {
        $shippingMethodsResolver->supports(Argument::any())->willReturn(false);

        $this->estimate($this->createCart(new Shipment()), $this->createEstimateAddress())
            ->shouldBeLike(ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_SUPPORTED))
        ;
    }

    public function it_prices_every_method_the_shipment_supports(
        ShippingMethodsResolverInterface $shippingMethodsResolver,
        DelegatingCalculatorInterface $shippingCalculator,
        ShippingMethodInterface $dhl,
        ShippingMethodInterface $ups,
    ): void {
        $this->describeMethod($dhl, 'dhl', 'DHL');
        $this->describeMethod($ups, 'ups', 'UPS');

        $shipment = new Shipment();
        $cart = $this->createCart($shipment);

        $shippingMethodsResolver->supports($shipment)->willReturn(true);
        $shippingMethodsResolver->getSupportedMethods($shipment)->willReturn([
            $dhl->getWrappedObject(),
            $ups->getWrappedObject(),
        ]);

        // Keyed by method code, so a rate can only be right if that method was put on the shipment first.
        $rates = ['dhl' => 2000, 'ups' => 2500];

        $shippingCalculator->calculate(Argument::any())->will(static function (array $args) use ($rates): int {
            return $rates[(string) $args[0]->getMethod()->getCode()];
        });

        $this->estimate($cart, $this->createEstimateAddress())->shouldBeLike(ShippingEstimate::of(
            new ShippingEstimateOption('dhl', 'DHL', 2000, 'USD'),
            new ShippingEstimateOption('ups', 'UPS', 2500, 'USD'),
        ));
    }

    public function it_skips_a_method_whose_calculator_errors_out(
        ShippingMethodsResolverInterface $shippingMethodsResolver,
        DelegatingCalculatorInterface $shippingCalculator,
        ShippingMethodInterface $dhl,
        ShippingMethodInterface $ups,
    ): void {
        $this->describeMethod($dhl, 'dhl', 'DHL');
        $this->describeMethod($ups, 'ups', 'UPS');

        $shipment = new Shipment();
        $cart = $this->createCart($shipment);

        $shippingMethodsResolver->supports($shipment)->willReturn(true);
        $shippingMethodsResolver->getSupportedMethods($shipment)->willReturn([
            $dhl->getWrappedObject(),
            $ups->getWrappedObject(),
        ]);

        $shippingCalculator->calculate(Argument::any())->will(static function (array $args): int {
            if ($args[0]->getMethod()->getCode() === 'dhl') {
                // Calculators come from the shop and its carriers, so this stands in for whatever one
                // of them throws rather than for anything the plugin defines.
                throw new \RuntimeException('The carrier did not answer.');
            }

            return 2500;
        });

        // One carrier failing does not cost the customer the rates the others quoted.
        $this->estimate($cart, $this->createEstimateAddress())->shouldBeLike(ShippingEstimate::of(
            new ShippingEstimateOption('ups', 'UPS', 2500, 'USD'),
        ));
    }

    public function it_reports_a_calculator_error_when_no_method_could_be_priced(
        ShippingMethodsResolverInterface $shippingMethodsResolver,
        DelegatingCalculatorInterface $shippingCalculator,
        ShippingMethodInterface $dhl,
    ): void {
        $this->describeMethod($dhl, 'dhl', 'DHL');

        $shipment = new Shipment();
        $cart = $this->createCart($shipment);

        $shippingMethodsResolver->supports($shipment)->willReturn(true);
        $shippingMethodsResolver->getSupportedMethods($shipment)->willReturn([$dhl->getWrappedObject()]);

        $shippingCalculator->calculate(Argument::any())->willThrow(new \RuntimeException('The carrier did not answer.'));

        $this->estimate($cart, $this->createEstimateAddress())
            ->shouldBeLike(ShippingEstimate::unavailable(ShippingEstimateReasons::CALCULATOR_ERROR))
        ;
    }

    public function it_reports_shipping_as_unavailable_when_no_method_is_supported(
        ShippingMethodsResolverInterface $shippingMethodsResolver,
    ): void {
        $shipment = new Shipment();
        $cart = $this->createCart($shipment);

        $shippingMethodsResolver->supports($shipment)->willReturn(true);
        $shippingMethodsResolver->getSupportedMethods($shipment)->willReturn([]);

        $this->estimate($cart, $this->createEstimateAddress())
            ->shouldBeLike(ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_AVAILABLE))
        ;
    }

    public function it_estimates_for_the_address_it_was_given(
        OrderInterface $cart,
        AddressInterface $estimateAddress,
    ): void {
        $cart->getShippingAddress()->willReturn(null);
        $cart->getShipments()->willReturn(new ArrayCollection());

        // Sylius resolves shipping methods from the address on the shipment's order, so the
        // hypothetical being estimated for has to go onto the cart for the resolution to see it.
        $cart->setShippingAddress($estimateAddress)->shouldBeCalled();
        $cart->setShippingAddress(null)->shouldBeCalled();

        $this->estimate($cart, $estimateAddress);
    }

    public function it_puts_the_carts_own_shipping_address_back(
        OrderInterface $cart,
        AddressInterface $existingAddress,
        AddressInterface $estimateAddress,
    ): void {
        $cart->getShippingAddress()->willReturn($existingAddress);
        $cart->getShipments()->willReturn(new ArrayCollection());

        $cart->setShippingAddress($estimateAddress)->shouldBeCalled();

        // The estimate address was a hypothetical. Leaving it behind would hand whatever flushes the
        // cart next an address the customer never chose.
        $cart->setShippingAddress($existingAddress)->shouldBeCalled();

        $this->estimate($cart, $estimateAddress);
    }

    public function it_puts_the_shipments_own_method_back(
        ShippingMethodsResolverInterface $shippingMethodsResolver,
        DelegatingCalculatorInterface $shippingCalculator,
        OrderInterface $cart,
        ShipmentInterface $shipment,
        ShippingMethodInterface $originalMethod,
        ShippingMethodInterface $dhl,
        AddressInterface $estimateAddress,
    ): void {
        $this->describeMethod($dhl, 'dhl', 'DHL');

        $cart->getShippingAddress()->willReturn(null);
        $cart->getCurrencyCode()->willReturn('USD');
        $cart->getShipments()->willReturn(new ArrayCollection([$shipment->getWrappedObject()]));

        $cart->setShippingAddress(Argument::any())->shouldBeCalled();

        $shipment->getMethod()->willReturn($originalMethod);
        $shipment->setOrder($cart)->shouldBeCalled();
        $shipment->setMethod($dhl)->shouldBeCalled();

        // The shipment is a managed entity; the method swapped onto it to price a row is not a change
        // to the customer's cart either.
        $shipment->setMethod($originalMethod)->shouldBeCalled();

        $shippingMethodsResolver->supports($shipment)->willReturn(true);
        $shippingMethodsResolver->getSupportedMethods($shipment)->willReturn([$dhl->getWrappedObject()]);
        $shippingCalculator->calculate(Argument::any())->willReturn(2000);

        $this->estimate($cart, $estimateAddress);
    }

    private function createCart(Shipment ...$shipments): Order
    {
        $cart = new Order();
        $cart->setCurrencyCode('USD');

        foreach ($shipments as $shipment) {
            $cart->addShipment($shipment);
        }

        return $cart;
    }

    private function createEstimateAddress(): Address
    {
        $address = new Address();
        $address->setCountryCode('US');
        $address->setPostcode('90802');

        return $address;
    }

    /**
     * @param ShippingMethodInterface&\Prophecy\Prophecy\ObjectProphecy $method
     */
    private function describeMethod($method, string $code, string $name): void
    {
        $method->getCode()->willReturn($code);
        $method->getName()->willReturn($name);
    }
}
