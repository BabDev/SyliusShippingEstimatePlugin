<?php

declare(strict_types=1);

namespace spec\BabDev\SyliusShippingEstimatePlugin\Exception;

use BabDev\SyliusShippingEstimatePlugin\Exception\ExceptionInterface;
use PhpSpec\ObjectBehavior;

class InvalidArgumentExceptionSpec extends ObjectBehavior
{
    public function let(): void
    {
        $this->beConstructedWith('Something was wrong with the arguments.');
    }

    public function it_is_a_plugin_exception(): void
    {
        $this->shouldImplement(ExceptionInterface::class);
    }

    public function it_is_still_the_spl_exception_it_replaces(): void
    {
        // Anything already catching the SPL exception keeps catching this one.
        $this->shouldHaveType(\InvalidArgumentException::class);
    }
}
