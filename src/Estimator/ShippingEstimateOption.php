<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Estimator;

/**
 * One shipping method, priced for the address being estimated for.
 */
final class ShippingEstimateOption
{
    /**
     * @param array<string, mixed> $metadata Arbitrary extra data about this option
     */
    public function __construct(
        public readonly string $methodCode,
        public readonly string $methodName,
        public readonly int $amount,
        public readonly string $currencyCode,
        public readonly array $metadata = [],
    ) {
    }
}
