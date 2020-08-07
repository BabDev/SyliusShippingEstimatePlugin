<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Behat\Page\Shop;

use Sylius\Behat\Page\Shop\Cart\SummaryPage as BaseSummaryPage;

class SummaryPage extends BaseSummaryPage implements SummaryPageInterface
{
    public function hasShippingEstimator(): bool
    {
        return $this->hasElement('shipping_estimator');
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'shipping_estimator' => '[data-test-cart-shipping-estimator]',
        ]);
    }
}
