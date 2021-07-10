<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Behat\Page\Shop;

use Sylius\Behat\Page\Shop\Cart\SummaryPageInterface as BaseSummaryPageInterface;

interface SummaryPageInterface extends BaseSummaryPageInterface
{
    public function hasShippingEstimator(): bool;

    public function doesNotHaveAddressMessage(): bool;

    public function hasAddressMessage(): bool;

    public function doesNotHaveNoShippingOptionsMessage(): bool;

    public function hasNoShippingOptionsMessage(): bool;

    public function seeShippingOptions(int $count): bool;

    public function selectCountry(string $value): void;

    public function specifyPostcode(string $value): void;

    public function clickEstimateShippingButton(): void;
}
