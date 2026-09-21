<?php

declare(strict_types=1);

namespace spec\BabDev\SyliusShippingEstimatePlugin\Estimator;

use PhpSpec\ObjectBehavior;

class ShippingEstimateOptionSpec extends ObjectBehavior
{
    public function let(): void
    {
        $this->beConstructedWith('ups_ground', 'UPS Ground', 2500, 'USD');
    }

    public function it_describes_the_method_it_priced(): void
    {
        $this->methodCode->shouldReturn('ups_ground');
        $this->methodName->shouldReturn('UPS Ground');
    }

    public function it_carries_the_rate_in_minor_units(): void
    {
        $this->amount->shouldReturn(2500);
        $this->currencyCode->shouldReturn('USD');
    }

    public function it_has_no_metadata_by_default(): void
    {
        $this->metadata->shouldReturn([]);
    }

    public function it_carries_metadata_when_given_some(): void
    {
        $this->beConstructedWith('ups_ground', 'UPS Ground', 2500, 'USD', ['residential' => true]);

        $this->metadata->shouldReturn(['residential' => true]);
    }
}
