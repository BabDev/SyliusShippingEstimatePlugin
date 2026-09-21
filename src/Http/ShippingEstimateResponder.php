<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Http;

use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimate;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateOption;
use BabDev\SyliusShippingEstimatePlugin\Estimator\ShippingEstimateReasons;
use Sylius\Bundle\MoneyBundle\Formatter\MoneyFormatterInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends an estimate as the JSON the bundled cart widget reads.
 */
final class ShippingEstimateResponder implements ShippingEstimateResponderInterface
{
    /**
     * The statuses this plugin's own reasons are answered with.
     *
     * A reason not listed here is answered with a 200, including any an estimator of its own
     * provides: an estimate that ran and came back with nothing is an answer, not a failure, and the
     * widget reads those reasons from a successful response.
     */
    public const DEFAULT_STATUS_CODES = [
        ShippingEstimateReasons::CANCELLED => Response::HTTP_BAD_REQUEST,
        ShippingEstimateReasons::CALCULATOR_ERROR => Response::HTTP_INTERNAL_SERVER_ERROR,
    ];

    /**
     * @param array<string, int> $statusCodes Reason code to HTTP status map, for an integrator that answers a reason differently than this plugin does
     */
    public function __construct(
        private MoneyFormatterInterface $moneyFormatter,
        private array $statusCodes = self::DEFAULT_STATUS_CODES,
    ) {
    }

    public function respond(ShippingEstimate $estimate, Request $request): Response
    {
        return new JsonResponse($this->buildPayload($estimate), $this->statusCodeFor($estimate));
    }

    private function statusCodeFor(ShippingEstimate $estimate): int
    {
        $reason = $estimate->reason();

        if ($reason === null) {
            return Response::HTTP_OK;
        }

        return $this->statusCodes[$reason] ?? Response::HTTP_OK;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(ShippingEstimate $estimate): array
    {
        $payload = [
            'error' => !$estimate->isSuccessful(),
            'options' => array_map(
                fn (ShippingEstimateOption $option): array => $this->buildOption($option),
                $estimate->options(),
            ),
            'reason' => $estimate->reason(),
        ];

        $detail = $estimate->detail();

        if ($detail !== null) {
            $payload['custom_reason'] = $detail;
        }

        // The keys above are the endpoint's contract, so metadata adds to the payload rather than rewriting it.
        return array_merge($estimate->metadata(), $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOption(ShippingEstimateOption $option): array
    {
        return array_merge($option->metadata, [
            'name' => $option->methodName,
            'rate' => $this->moneyFormatter->format($option->amount, $option->currencyCode),
        ]);
    }
}
