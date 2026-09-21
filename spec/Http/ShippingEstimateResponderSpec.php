<?php

declare(strict_types=1);

namespace spec\BabDev\SyliusShippingEstimatePlugin\Http;

use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimate;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateOption;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateReasons;
use BabDev\SyliusShippingEstimatePlugin\Http\ShippingEstimateResponderInterface;
use PhpSpec\ObjectBehavior;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ShippingEstimateResponderSpec extends ObjectBehavior
{
    public function let(MoneyFormatterInterface $moneyFormatter): void
    {
        $this->beConstructedWith($moneyFormatter);
    }

    public function it_is_a_shipping_estimate_responder(): void
    {
        $this->shouldImplement(ShippingEstimateResponderInterface::class);
    }

    public function it_sends_the_priced_options_with_their_rates_formatted(
        MoneyFormatterInterface $moneyFormatter,
    ): void {
        $moneyFormatter->format(2000, 'USD')->willReturn('$20.00');
        $moneyFormatter->format(2500, 'USD')->willReturn('$25.00');

        $estimate = ShippingEstimate::of(
            new ShippingEstimateOption('dhl', 'DHL', 2000, 'USD'),
            new ShippingEstimateOption('ups', 'UPS', 2500, 'USD'),
        );

        $response = $this->respond($estimate, $this->createRequest());

        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
        $response->getContent()->shouldReturn(
            '{"error":false,"options":[{"name":"DHL","rate":"$20.00"},{"name":"UPS","rate":"$25.00"}],"reason":null}',
        );
    }

    public function it_answers_an_estimate_that_found_nothing_with_a_success(): void
    {
        $response = $this->respond(
            ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_AVAILABLE),
            $this->createRequest(),
        );

        // The estimate ran and came back empty. That is an answer, and the widget reads the reason
        // from a successful response.
        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
        $response->getContent()->shouldReturn('{"error":true,"options":[],"reason":"shipping_not_available"}');
    }

    public function it_answers_an_unsupported_estimate_with_a_success(): void
    {
        $response = $this->respond(
            ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_SUPPORTED),
            $this->createRequest(),
        );

        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
        $response->getContent()->shouldReturn('{"error":true,"options":[],"reason":"shipping_not_supported"}');
    }

    public function it_sends_the_detail_behind_a_cancelled_estimate(): void
    {
        $response = $this->respond(
            ShippingEstimate::unavailable(ShippingEstimateReasons::CANCELLED, 'We do not ship there.'),
            $this->createRequest(),
        );

        $response->getStatusCode()->shouldReturn(Response::HTTP_BAD_REQUEST);
        $response->getContent()->shouldReturn(
            '{"error":true,"options":[],"reason":"shipping_estimate_cancelled","custom_reason":"We do not ship there."}',
        );
    }

    public function it_reports_a_request_without_an_address_as_a_bad_request(): void
    {
        $response = $this->respond(
            ShippingEstimate::unavailable(ShippingEstimateReasons::INVALID_REQUEST),
            $this->createRequest(),
        );

        $response->getStatusCode()->shouldReturn(Response::HTTP_BAD_REQUEST);
        $response->getContent()->shouldReturn(
            '{"error":true,"options":[],"reason":"shipping_estimate_invalid_request"}',
        );
    }

    public function it_reports_a_calculator_error_as_a_server_error(): void
    {
        $response = $this->respond(
            ShippingEstimate::unavailable(ShippingEstimateReasons::CALCULATOR_ERROR),
            $this->createRequest(),
        );

        $response->getStatusCode()->shouldReturn(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function it_answers_a_reason_it_does_not_know_with_a_success(): void
    {
        $response = $this->respond(ShippingEstimate::unavailable('package_overweight'), $this->createRequest());

        // An estimator of an integrator's own reports whatever its carriers say, and an unmapped
        // reason is a reason rather than a failure.
        $response->getStatusCode()->shouldReturn(Response::HTTP_OK);
        $response->getContent()->shouldReturn('{"error":true,"options":[],"reason":"package_overweight"}');
    }

    public function it_answers_a_reason_with_the_status_it_was_configured_with(
        MoneyFormatterInterface $moneyFormatter,
    ): void {
        $this->beConstructedWith($moneyFormatter, ['package_overweight' => Response::HTTP_BAD_REQUEST]);

        $response = $this->respond(ShippingEstimate::unavailable('package_overweight'), $this->createRequest());

        $response->getStatusCode()->shouldReturn(Response::HTTP_BAD_REQUEST);
    }

    public function it_adds_estimate_metadata_to_the_payload(): void
    {
        $estimate = ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_AVAILABLE)
            ->withMetadata('residential', true)
        ;

        $response = $this->respond($estimate, $this->createRequest());

        $response->getContent()->shouldReturn(
            '{"residential":true,"error":true,"options":[],"reason":"shipping_not_available"}',
        );
    }

    public function it_does_not_let_metadata_rewrite_the_endpoints_contract(): void
    {
        $estimate = ShippingEstimate::unavailable(ShippingEstimateReasons::NOT_AVAILABLE)
            ->withMetadata('error', 'not a boolean')
            ->withMetadata('reason', 'something else')
        ;

        $response = $this->respond($estimate, $this->createRequest());

        // Metadata adds to the payload; the keys the widget depends on are not up for grabs.
        $response->getContent()->shouldReturn('{"error":true,"reason":"shipping_not_available","options":[]}');
    }

    public function it_adds_option_metadata_to_the_row_it_belongs_to(
        MoneyFormatterInterface $moneyFormatter,
    ): void {
        $moneyFormatter->format(2500, 'USD')->willReturn('$25.00');

        $estimate = ShippingEstimate::of(
            new ShippingEstimateOption('ups', 'UPS', 2500, 'USD', ['residential' => true]),
        );

        $response = $this->respond($estimate, $this->createRequest());

        $response->getContent()->shouldReturn(
            '{"error":false,"options":[{"residential":true,"name":"UPS","rate":"$25.00"}],"reason":null}',
        );
    }

    private function createRequest(): Request
    {
        $request = Request::create('/ajax/estimate-shipping', Request::METHOD_GET);
        $request->setRequestFormat('json');

        return $request;
    }
}
