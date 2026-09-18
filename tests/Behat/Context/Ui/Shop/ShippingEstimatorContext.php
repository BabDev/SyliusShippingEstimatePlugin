<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Tests\BabDev\SyliusShippingEstimatePlugin\Behat\Page\Shop\SummaryPageInterface;
use Webmozart\Assert\Assert;

final class ShippingEstimatorContext implements Context
{
    public function __construct(private SummaryPageInterface $summaryPage)
    {
    }

    /**
     * @When I do not see the shipping estimator
     */
    public function customerDoesNotSeeTheShippingEstimator(): void
    {
        Assert::false($this->summaryPage->hasShippingEstimator());
    }

    /**
     * @When I see the shipping estimator
     */
    public function customerSeesTheShippingEstimator(): void
    {
        Assert::true($this->summaryPage->hasShippingEstimator());
    }

    /**
     * @When I choose :countryName as my country
     */
    public function iChooseAsMyCountry(string $countryName): void
    {
        $this->summaryPage->selectCountry($countryName);
    }

    /**
     * @When I specify :postcode as my postcode
     */
    public function iSpecifyAsMyPostcode(string $postcode): void
    {
        $this->summaryPage->specifyPostcode($postcode);
    }

    /**
     * @When I click the estimate shipping button
     */
    public function iClickTheEstimateShippingButton(): void
    {
        $this->summaryPage->clickEstimateShippingButton();
    }

    /**
     * @When the enter address message is visible
     */
    public function theEnterAddressMessageIsVisible(): void
    {
        Assert::true($this->summaryPage->hasAddressMessage(), 'The enter address message is not visible.');
    }

    /**
     * @When the enter address message is not visible
     */
    public function theEnterAddressMessageIsNotVisible(): void
    {
        Assert::true($this->summaryPage->doesNotHaveAddressMessage(), 'The enter address message is visible.');
    }

    /**
     * @When the no shipping options message is visible
     */
    public function theNoShippingOptionsMessageIsVisible(): void
    {
        Assert::true($this->summaryPage->hasNoShippingOptionsMessage(), 'The no shipping options message is not visible.');
    }

    /**
     * @When the no shipping options message is not visible
     */
    public function theNoShippingOptionsMessageIsNotVisible(): void
    {
        Assert::true($this->summaryPage->doesNotHaveNoShippingOptionsMessage(), 'The no shipping options message is visible.');
    }

    /**
     * @When I see :count shipping options available
     */
    public function iSeeShippingOptions(int $count): void
    {
        Assert::same($this->summaryPage->countShippingOptions(), $count);
    }
}
