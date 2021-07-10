(function ($) {
    'use strict';

    $.fn.extend({
        shippingEstimator: function () {
            var form = $('#sylius-shipping-estimator');
            var enterAddressMessage = $('#sylius-shipping-estimator-enter-address');
            var noOptionsMessage = $('#sylius-shipping-estimator-no-shipping-options');
            var optionsTable = $('#sylius-shipping-estimator-shipping-options');

            form.on('submit', function (event) {
                event.preventDefault();

                var target = $(event.currentTarget);

                // Make sure fields are filled in before submitting
                var countrySelect = $(event.currentTarget).find('select[name$="[country]"]');
                var postcodeInput = $(event.currentTarget).find('input[name$="[postcode]"]');

                if (countrySelect.val() === '' || postcodeInput.val() === '') {
                    target.removeClass('warning');

                    enterAddressMessage.removeClass('hidden');
                    noOptionsMessage.addClass('hidden');
                    optionsTable.hide();

                    return;
                }

                var data = {
                    country: countrySelect.val(),
                    postcode: postcodeInput.val()
                }

                var errorContainer = $('#sylius-shipping-estimator-error');

                errorContainer
                    .addClass('hidden')
                    .removeClass('visible')
                    .text('')
                ;

                $.ajax({
                    url: target.attr('data-url'),
                    type: 'GET',
                    data: data,
                    beforeSend: function () {
                        target.addClass('loading');
                    },
                    success: function (response) {
                        if (response.error) {
                            if (response.reason === 'shipping_not_available') {
                                target.addClass('warning');

                                enterAddressMessage.addClass('hidden');
                                noOptionsMessage.removeClass('hidden');
                                optionsTable.hide();
                            }
                        } else {
                            var options = '';

                            $(response.options).each(function (key, value) {
                                options += '<tr><td>' + value.name + '</td><td>' + value.rate + '</td></tr>';
                            });

                            target.removeClass('warning');

                            enterAddressMessage.addClass('hidden');
                            noOptionsMessage.addClass('hidden');
                            optionsTable.find('tbody').html(options);
                            optionsTable.show();
                        }
                    },
                    error: function (response) {
                        if (response.responseJSON.hasOwnProperty('reason')) {
                            switch (response.responseJSON.reason) {
                                case 'shipping_calculator_error':
                                    errorContainer
                                        .text("We're sorry, there was a temporary error calculating the shipping for your order. Please try again.")
                                        .removeClass('hidden')
                                    ;

                                    target.addClass('warning');

                                    enterAddressMessage.removeClass('hidden');
                                    noOptionsMessage.addClass('hidden');
                                    optionsTable.hide();

                                    break;

                                case 'shipping_estimate_cancelled':
                                    errorContainer
                                        .text(response.responseJSON.hasOwnProperty('custom_reason') ? response.responseJSON.custom_reason : 'The shipping estimate was cancelled.')
                                        .removeClass('hidden')
                                    ;

                                    target.addClass('warning');

                                    enterAddressMessage.removeClass('hidden');
                                    noOptionsMessage.addClass('hidden');
                                    optionsTable.hide();

                                    break;

                                case 'shipping_not_supported':
                                    target.addClass('warning');

                                    enterAddressMessage.addClass('hidden');
                                    noOptionsMessage.removeClass('hidden');
                                    optionsTable.hide();

                                    break;

                                default:
                                    errorContainer
                                        .text('Error getting shipping estimates, please try again.')
                                        .show()
                                    ;

                                    target.removeClass('warning');

                                    enterAddressMessage.removeClass('hidden');
                                    noOptionsMessage.addClass('hidden');
                                    optionsTable.hide();

                                    break;
                            }
                        } else {
                            errorContainer
                                .text('Error getting shipping estimates, please try again.')
                                .show()
                            ;

                            target.removeClass('warning');

                            enterAddressMessage.removeClass('hidden');
                            noOptionsMessage.addClass('hidden');
                            optionsTable.hide();
                        }
                    },
                    complete: function () {
                        target.removeClass('loading');
                    }
                });
            });
        }
    });

    $(document).ready(function () {
        $(document).shippingEstimator();
    });
})(jQuery);
