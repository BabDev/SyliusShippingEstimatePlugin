<?php

declare(strict_types=1);

namespace spec\BabDev\SyliusShippingEstimatePlugin\Event;

use PhpSpec\ObjectBehavior;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Contracts\EventDispatcher\Event;

class BeforeEstimateShippingEventSpec extends ObjectBehavior
{
    public function let(OrderInterface $cart, AddressInterface $address): void
    {
        $this->beConstructedWith($cart, $address);
    }

    public function it_is_an_event(): void
    {
        $this->beAnInstanceOf(Event::class);
    }

    public function it_provides_the_current_cart(OrderInterface $cart): void
    {
        $this->getCart()->shouldReturn($cart);
    }

    public function it_provides_the_address(AddressInterface $address): void
    {
        $this->getAddress()->shouldReturn($address);
    }

    public function it_allows_the_address_to_be_changed(AddressInterface $address): void
    {
        $this->setAddress($address);
        $this->getAddress()->shouldReturn($address);
    }

    public function it_can_be_cancelled(): void
    {
        $reason = 'Testing cancellation';

        $this->cancelEstimate($reason);
        $this->isPropagationStopped()->shouldBe(true);
        $this->getCancelReason()->shouldReturn($reason);
    }
}
