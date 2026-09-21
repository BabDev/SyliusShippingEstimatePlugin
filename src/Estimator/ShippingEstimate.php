<?php

declare(strict_types=1);

namespace BabDev\SyliusShippingEstimatePlugin\Estimator;

use BabDev\SyliusShippingEstimatePlugin\Exception\InvalidArgumentException;

/**
 * The answer to one estimate: either the priced options, or the reason there are none.
 */
final class ShippingEstimate
{
    /**
     * @param list<ShippingEstimateOption> $options
     * @param non-empty-string|null        $reason
     * @param array<string, mixed>         $metadata
     */
    private function __construct(
        private readonly array $options,
        private readonly ?string $reason,
        private readonly ?string $detail,
        private readonly array $metadata,
    ) {
    }

    /**
     * Creates a successful estimate.
     *
     * @throws InvalidArgumentException if given no options
     */
    public static function of(ShippingEstimateOption ...$options): self
    {
        if ($options === []) {
            throw new InvalidArgumentException(sprintf(
                'A successful %s requires at least one option; use %s::unavailable() to report an estimate with no rates in it.',
                self::class,
                self::class,
            ));
        }

        return new self(array_values($options), null, null, []);
    }

    /**
     * Creates an estimate with no options in it.
     *
     * @param non-empty-string $reason A reason code identifying why the shipping estimate is not available.
     */
    public static function unavailable(string $reason, ?string $detail = null): self
    {
        return new self([], $reason, $detail, []);
    }

    public function isSuccessful(): bool
    {
        return $this->reason === null;
    }

    /**
     * @return list<ShippingEstimateOption>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @return non-empty-string|null
     */
    public function reason(): ?string
    {
        return $this->reason;
    }

    public function detail(): ?string
    {
        return $this->detail;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Returns a copy of this estimate carrying additional metadata.
     */
    public function withMetadata(string $key, mixed $value): self
    {
        return new self(
            $this->options,
            $this->reason,
            $this->detail,
            array_merge($this->metadata, [$key => $value]),
        );
    }
}
