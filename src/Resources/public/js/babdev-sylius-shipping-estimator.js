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

                    enterAddressMessage.show();
                    noOptionsMessage.hide();
                    optionsTable.hide();

                    return;
                }

                var data = {
                    countryCode: countrySelect.val(),
                    postcode: postcodeInput.val()
                }

                var errorContainer = $('#sylius-shipping-estimator-error');

                errorContainer
                    .hide()
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
                            target.addClass('warning');

                            enterAddressMessage.hide();
                            noOptionsMessage.show();
                            optionsTable.hide();
                        } else {
                            var options = '';

                            $(response.options).each(function (key, value) {
                                options += '<tr><td>' + value.name + '</td><td>' + value.rate + '</td></tr>';
                            });

                            target.removeClass('warning');

                            optionsTable.find('tbody').html(options);
                            enterAddressMessage.hide();
                            noOptionsMessage.hide();
                            optionsTable.show();
                        }
                    },
                    error: function () {
                        errorContainer
                            .text('Error getting shipping estimates, please try again.')
                            .show()
                        ;

                        target.removeClass('warning');

                        enterAddressMessage.show();
                        noOptionsMessage.hide();
                        optionsTable.hide();
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
