<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Event;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class BeforeEstimateShippingEvent extends Event
{
    private OrderInterface $cart;

    private AddressInterface $address;

    private ?string $cancelReason = null;

    public function __construct(OrderInterface $cart, AddressInterface $address)
    {
        $this->cart = $cart;
        $this->address = $address;
    }

    public function getCart(): OrderInterface
    {
        return $this->cart;
    }

    public function getAddress(): AddressInterface
    {
        return $this->address;
    }

    public function setAddress(AddressInterface $address): void
    {
        $this->address = $address;
    }

    public function cancelEstimate(string $reason): void
    {
        $this->cancelReason = $reason;

        $this->stopPropagation();
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }
}
