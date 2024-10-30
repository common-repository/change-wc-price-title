// JavaScript File
jQuery(function($){
    var price 			= cwpt_settings_params.product_price,
        currency 		= cwpt_settings_params.wc_currency,
        product_type 	= cwpt_settings_params.product_type,
        multiplier      = cwpt_settings_params.multiplier;

        if( multiplier ){
            $( '[name=quantity]').change( function(){
                if ( !( this.value < 1 ) ) {

                    switch( product_type ){
                        case 'simple':
                            var product_total = parseFloat( price * this.value );
                            $('.woocommerce-Price-amount').html( "( " + currency + price + ' x ' + this.value + " qty = " + currency + product_total.toFixed(2) + " ) " );
                        break;
                        case 'variable':
                            variation_id    = document.getElementsByName( "variation_id" )[0].value;
                            product_total   = parseFloat( price[variation_id] * this.value );
                            
                            $('.woocommerce-variation-price').html( '<span class="price"><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">'+currency+'</span>'+product_total.toFixed( 2 )+'</span></span>' );
                        break;
                    }
                }
            });        
        }
    
});