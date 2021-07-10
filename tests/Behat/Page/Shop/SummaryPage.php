<?php

declare(strict_types=1);

namespace Tests\BabDev\SyliusShippingEstimatePlugin\Behat\Page\Shop;

use Sylius\Behat\Page\Shop\Cart\SummaryPage as BaseSummaryPage;
use Sylius\Behat\Service\JQueryHelper;

class SummaryPage extends BaseSummaryPage implements SummaryPageInterface
{
    public function hasShippingEstimator(): bool
    {
        return $this->hasElement('shipping_estimator');
    }

    public function doesNotHaveAddressMessage(): bool
    {
        $message = $this->getElement('enter_address_message');

        return $message->hasClass('hidden');
    }

    public function hasAddressMessage(): bool
    {
        $message = $this->getElement('enter_address_message');

        return !$message->hasClass('hidden');
    }

    public function doesNotHaveNoShippingOptionsMessage(): bool
    {
        $message = $this->getElement('no_shipping_options_message');

        return $message->hasClass('hidden');
    }

    public function hasNoShippingOptionsMessage(): bool
    {
        $message = $this->getElement('no_shipping_options_message');

        return !$message->hasClass('hidden');
    }

    public function seeShippingOptions(int $count): bool
    {
        $table = $this->getElement('shipping_options_table');
        $options = $table->findAll('css', 'tbody tr');

        return count($options) === $count;
    }

    public function selectCountry(string $value): void
    {
        $country = $this->getElement('shipping_estimator_country');
        $country->selectOption($value);
    }

    public function specifyPostcode(string $value): void
    {
        $postcode = $this->getElement('shipping_estimator_postcode');
        $postcode->setValue($value);
    }

    public function clickEstimateShippingButton(): void
    {
        $button = $this->getElement('estimate_shipping_button');
        $button->click();

        JQueryHelper::waitForFormToStopLoading($this->getDocument());
    }

    protected function getDefinedElements(): array
    {
        return array_merge(parent::getDefinedElements(), [
            'shipping_estimator' => '[data-test-cart-shipping-estimator]',
            'shipping_estimator_form' => '#sylius-shipping-estimator',
            'shipping_estimator_country' => '[data-test-shipping-estimatecountry]',
            'shipping_estimator_postcode' => '#babdev_sylius_shipping_estimator_postcode',
            'estimate_shipping_button' => '[data-test-estimate-shipping]',
            'enter_address_message' => '[data-test-enter-address-message]',
            'no_shipping_options_message' => '[data-test-no-shipping-options-message]',
            'shipping_options_table' => '[data-test-shipping-options]',
            'shipping_estimator_error' => '[data-test-shipping-estimator-error]',
        ]);
    }
}
