<?php

declare(strict_types=1);

namespace spec\BabDev\SyliusShippingEstimatePlugin\Estimator;

use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateOption;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateReasons;
use BabDev\SyliusShippingEstimatePlugin\Exception\InvalidArgumentException;
use PhpSpec\ObjectBehavior;

class ShippingEstimateSpec extends ObjectBehavior
{
    public function it_holds_the_options_it_was_given(): void
    {
        $ups = new ShippingEstimateOption('ups_ground', 'UPS Ground', 2500, 'USD');
        $dhl = new ShippingEstimateOption('dhl', 'DHL', 2000, 'USD');

        $this->beConstructedThrough('of', [$ups, $dhl]);

        $this->isSuccessful()->shouldBe(true);
        $this->options()->shouldReturn([$ups, $dhl]);
        $this->reason()->shouldBeNull();
        $this->detail()->shouldBeNull();
    }

    public function it_cannot_be_successful_with_nothing_in_it(): void
    {
        $this->beConstructedThrough('of', []);

        $this->shouldThrow(InvalidArgumentException::class)->duringInstantiation();
    }

    public function it_reports_the_reason_there_are_no_options(): void
    {
        $this->beConstructedThrough('unavailable', [ShippingEstimateReasons::NOT_AVAILABLE]);

        $this->isSuccessful()->shouldBe(false);
        $this->options()->shouldReturn([]);
        $this->reason()->shouldReturn(ShippingEstimateReasons::NOT_AVAILABLE);
        $this->detail()->shouldBeNull();
    }

    public function it_carries_the_detail_behind_a_reason(): void
    {
        $this->beConstructedThrough('unavailable', [ShippingEstimateReasons::CANCELLED, 'We do not ship there.']);

        $this->reason()->shouldReturn(ShippingEstimateReasons::CANCELLED);
        $this->detail()->shouldReturn('We do not ship there.');
    }

    public function it_accepts_a_reason_it_has_never_heard_of(): void
    {
        // Reasons are open: an estimator of an integrator's own reports whatever its carriers say.
        $this->beConstructedThrough('unavailable', ['package_overweight']);

        $this->reason()->shouldReturn('package_overweight');
    }

    public function it_has_no_metadata_by_default(): void
    {
        $this->beConstructedThrough('unavailable', [ShippingEstimateReasons::NOT_AVAILABLE]);

        $this->metadata()->shouldReturn([]);
    }

    public function it_adds_metadata_to_a_copy_rather_than_to_itself(): void
    {
        $option = new ShippingEstimateOption('ups_ground', 'UPS Ground', 2500, 'USD');

        $this->beConstructedThrough('of', [$option]);

        $withMetadata = $this->withMetadata('residential', true);

        $withMetadata->metadata()->shouldReturn(['residential' => true]);
        $withMetadata->options()->shouldReturn([$option]);
        $withMetadata->isSuccessful()->shouldBe(true);

        $this->metadata()->shouldReturn([]);
    }
}
