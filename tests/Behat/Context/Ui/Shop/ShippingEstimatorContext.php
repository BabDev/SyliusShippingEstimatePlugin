<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
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
     * @When I see the shipping estimator error :error
     */
    public function iSeeTheShippingEstimatorError(string $error): void
    {
        Assert::same($this->summaryPage->getShippingEstimatorError(), $error);
    }

    /**
     * @When I see the following shipping options:
     */
    public function iSeeTheFollowingShippingOptions(TableNode $expectedOptions): void
    {
        $expected = [];

        foreach ($expectedOptions->getHash() as $row) {
            $expected[$row['method']] = $row['cost'];
        }

        $actual = $this->summaryPage->getShippingOptions();

        // Compared loosely so the assertion covers the rates without pinning the row order.
        Assert::eq($actual, $expected, sprintf(
            'Expected shipping options [%s] but got [%s].',
            self::describeOptions($expected),
            self::describeOptions($actual),
        ));
    }

    /**
     * @When I see :count shipping options available
     */
    public function iSeeShippingOptions(int $count): void
    {
        Assert::same($this->summaryPage->countShippingOptions(), $count);
    }

    /**
     * @param array<string, string> $options
     */
    private static function describeOptions(array $options): string
    {
        $described = [];

        foreach ($options as $method => $cost) {
            $described[] = $method . ' ' . $cost;
        }

        // Escaped because the caller's message is run through sprintf() by the assertion library.
        return str_replace('%', '%%', implode(', ', $described));
    }
}
