define([
    'jquery',
    'Magento_Customer/js/customer-data'
], function ($, customerData) {
    'use strict';
    const defaults = {
        cartSubscription: null,
        productFormSelector: '#product_addtocart_form',
    }

    return function (api) {
        return new Promise((resolve, reject) => {
            let $form = $(defaults.productFormSelector);

            if (!defaults.cartSubscription) {
                // Attach cart subscription to listen for the successful add to cart.
                const cart = customerData.get('cart');

                $form.trigger('submit');

                if ($form.validation('isValid')) {
                    $('body').trigger('processStart');

                    defaults.cartSubscription = cart.subscribe((cartData) => {
                        // If we no longer have cart items then reset.
                        if (!cartData.items.length)  {
                            defaults.cartSubscription.dispose();
                            defaults.cartSubscription = null;
                        }

                        api.setQuoteId(cartData.braintree_masked_id);
                        $('body').trigger('processStop');
                        resolve();
                    });

                    return;
                }

                reject();
                return;
            }

            resolve();
            return;
        });
    }
});
