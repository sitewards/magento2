define(
    [
        'jquery',
        'Heidelpay_Gateway/js/view/payment/method-renderer/hgw-abstract',
        'Heidelpay_Gateway/js/action/place-order',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($, Component, placeOrderAction, urlBuilder, storage, additionalValidators, customer, quote, fullScreenLoader) {
        'use strict';

        return Component.extend({

            /**
             * Property that indicates, if the payment method is storing
             * additional data.
             */
            savesAdditionalData: true,

            defaults: {
                template: 'Heidelpay_Gateway/payment/heidelpay-easycredit-form',
                hgwEasyCreditChecked: false,
                hgwEasyCreditInfo: {
                    agreement_text: '',
                    redirect_url: ''
                }
            },

            initialize: function () {
                this._super();
                this.getAdditionalPaymentInformation();

                return this;
            },

            initObservable: function() {
                this._super()
                    .observe([
                        'hgwEasyCreditChecked', 'hgwEasyCreditInfo'
                    ]);

                return this;
            },

            getAdditionalPaymentInformation: function() {
                var parent = this;
                var serviceUrl, payload;

                fullScreenLoader.startLoader();

                // first, set the payment method to easyCredit.
                if (!customer.isLoggedIn()) {
                    serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/set-payment-information', {
                        cartId:  quote.getQuoteId()
                    });
                    payload = {
                        cartId: quote.getQuoteId(),
                        email: quote.guestEmail,
                        paymentMethod: this.getData(),
                        billingAddress: quote.billingAddress()
                    };
                } else {
                    serviceUrl = urlBuilder.createUrl('/carts/mine/set-payment-information', {});
                    payload = {
                        cartId: quote.getQuoteId(),
                        paymentMethod: this.getData(),
                        billingAddress: quote.billingAddress()
                    };
                }

                storage.post(
                    serviceUrl, JSON.stringify(payload)
                ).done(
                    function () {
                        serviceUrl = urlBuilder.createUrl('/hgw/get-easycredit-info', {});
                        var hgwPayload = {
                            quoteId: quote.getQuoteId(),
                            email: quote.guestEmail
                        };

                        storage.post(
                            serviceUrl, JSON.stringify(hgwPayload)
                        ).done(
                            function(data) {
                                var info = JSON.parse(data);

                                // set the easyCredit information text, which comes from the payment
                                if( info !== null ) {
                                    parent.hgwEasyCreditInfo(info);
                                }

                                fullScreenLoader.stopLoader();
                            }
                        );
                    }
                );

                fullScreenLoader.stopLoader();
            },

            getCode: function () {
                return 'hgweasy';
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'hgw_easycredit_checked': this.hgwEasyCreditChecked()
                    }
                };
            },

            /**
             * Redirect to hgw controller
             * Override magento placepayment function
             */
            placeOrder: function (data, event) {
                var self = this,
                    placeOrder;

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() && additionalValidators.validate()) {
                    var test = '';
                    return true;
                }

                return false;
            },

            validate: function() {
                var form = $('#hgw-easycredit-form');

                return form.validation() && form.validation('isValid');
            }
        });
    }
);