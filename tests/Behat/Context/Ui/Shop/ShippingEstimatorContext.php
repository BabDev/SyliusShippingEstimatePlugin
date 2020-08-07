<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Behat\Context\Ui\Shop;

use Behat\Behat\Context\Context;
use Tests\BabDev\SyliusShippingEstimatePlugin\Behat\Page\Shop\SummaryPageInterface;
use Webmozart\Assert\Assert;

final class ShippingEstimatorContext implements Context
{
    /** @var SummaryPageInterface */
    private $summaryPage;

    public function __construct(SummaryPageInterface $summaryPage)
    {
        $this->summaryPage = $summaryPage;
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
}
