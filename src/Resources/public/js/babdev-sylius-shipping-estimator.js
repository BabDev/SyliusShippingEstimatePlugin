(function ($) {
    'use strict';

    // Fallbacks used when the widget markup does not carry the matching `data-message-*` attribute.
    var MESSAGE_FALLBACKS = {
        incompleteForm: 'Please fill in all fields to estimate your shipping.',
        calculatorError: "We're sorry, there was a temporary error calculating the shipping for your order. Please try again.",
        estimateCancelled: 'The shipping estimate was cancelled.',
        genericError: 'Error getting shipping estimates, please try again.',
        rateLimited: 'You have requested too many shipping estimates. Please wait a moment and try again.'
    };

    $.fn.extend({
        shippingEstimator: function () {
            var form = $('#sylius-shipping-estimator');
            var enterAddressMessage = $('#sylius-shipping-estimator-enter-address');
            var noOptionsMessage = $('#sylius-shipping-estimator-no-shipping-options');
            var optionsTable = $('#sylius-shipping-estimator-shipping-options');
            var errorContainer = $('#sylius-shipping-estimator-error');

            var message = function (key) {
                var provided = form.data('message' + key.charAt(0).toUpperCase() + key.slice(1));

                return typeof provided === 'string' && provided !== '' ? provided : MESSAGE_FALLBACKS[key];
            };

            var showEnterAddress = function () {
                enterAddressMessage.removeClass('hidden');
                noOptionsMessage.addClass('hidden');
                optionsTable.hide();
            };

            var showNoOptions = function () {
                enterAddressMessage.addClass('hidden');
                noOptionsMessage.removeClass('hidden');
                optionsTable.hide();
            };

            var showOptions = function (options) {
                var body = optionsTable.find('tbody').empty();

                $.each(options, function (index, option) {
                    $('<tr />')
                        .append($('<td />').text(option.name))
                        .append($('<td />').text(option.rate))
                        .appendTo(body)
                    ;
                });

                enterAddressMessage.addClass('hidden');
                noOptionsMessage.addClass('hidden');
                optionsTable.show();
            };

            var showError = function (text) {
                errorContainer.text(text).removeClass('hidden');
            };

            var clearError = function () {
                errorContainer.addClass('hidden').text('');
            };

            form.on('submit', function (event) {
                event.preventDefault();

                form.removeClass('warning');
                clearError();

                // Make sure fields are filled in before submitting
                var countrySelect = form.find('select[name$="[country]"]');
                var postcodeInput = form.find('input[name$="[postcode]"]');

                countrySelect.parent().removeClass('error');
                postcodeInput.parent().removeClass('error');

                if (countrySelect.val() === '' || postcodeInput.val() === '') {
                    form.addClass('warning');

                    if (countrySelect.val() === '') {
                        countrySelect.parent().addClass('error');
                    }

                    if (postcodeInput.val() === '') {
                        postcodeInput.parent().addClass('error');
                    }

                    showError(message('incompleteForm'));
                    showEnterAddress();

                    return;
                }

                $.ajax({
                    url: form.attr('data-url'),
                    type: 'GET',
                    data: {
                        country: countrySelect.val(),
                        postcode: postcodeInput.val()
                    },
                    beforeSend: function () {
                        form.addClass('loading');
                    },
                    success: function (response) {
                        if (!response || !response.error) {
                            form.removeClass('warning');
                            showOptions(response && response.options ? response.options : []);

                            return;
                        }

                        form.addClass('warning');

                        if (response.reason === 'shipping_not_available' || response.reason === 'shipping_not_supported') {
                            showNoOptions();

                            return;
                        }

                        showError(message('genericError'));
                        showEnterAddress();
                    },
                    error: function (jqXHR) {
                        // `responseJSON` is undefined whenever the response was not JSON at all, such as
                        // an HTML error page, a failed connection or an aborted request.
                        var payload = jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : {};

                        form.addClass('warning');

                        switch (payload.reason) {
                            case 'shipping_estimate_rate_limited':
                                showError(message('rateLimited'));
                                showEnterAddress();

                                break;

                            case 'shipping_calculator_error':
                                showError(message('calculatorError'));
                                showEnterAddress();

                                break;

                            case 'shipping_estimate_cancelled':
                                // The cancel reason is optional, so fall back when it is absent or empty.
                                showError(
                                    typeof payload.custom_reason === 'string' && payload.custom_reason !== ''
                                        ? payload.custom_reason
                                        : message('estimateCancelled')
                                );
                                showEnterAddress();

                                break;

                            default:
                                showError(message('genericError'));
                                showEnterAddress();

                                break;
                        }
                    },
                    complete: function () {
                        form.removeClass('loading');
                    }
                });
            });
        }
    });

    $(document).ready(function () {
        $(document).shippingEstimator();
    });
})(jQuery);
