jQuery(document).ready(function(){

    var lookup_button    = jQuery( '#rmw_product_lookup' );
    var asin_field       = jQuery( 'input[name=asin]' );
    var post_title       = jQuery( 'input[name=post_title]' );
    var post_body        = jQuery( 'textarea[name=content]' );
    var post_id_field    = jQuery( 'input[name=post_ID]' );
    var thumb_id_field   = jQuery( 'input[name=_thumbnail_id]');
    var last_fetch_field = jQuery( 'input[name=last_fetch]');

    function update_meta_field( field_name, value ) {
        var meta_field = jQuery( 'input[name=' + field_name + ']' );

        meta_field.val( value );

        var meta_display = jQuery( '#' + field_name + '_display' );
        meta_display.html( value );
    }

    /**
     * Ajax call to process that copies image from Amazon to our media library and returns the media ID
     *
     * @param image_url
     * @param title
     */
    function ajax_set_product_image( image_url, title ) {

        // Call the image copy ajax function
        var post_id = post_id_field.val( );
        jQuery.post(
            ajaxurl,
            {
                'action':    'rmw_wpaz_copy_image',
                'image_url': image_url,
                'title':     title,
                'id':        post_id
            },
            function(response){

                // Responding to the image handler...
                var img_data = JSON.parse( response );
                if ( img_data.code != 0 ) {

                    // Report the error
                    alert( 'Something went wrong copying the image. The server responded: ' + img_data.message + " (" + img_data.code + ")" );

                } else {
                    // No error. Add image ID to featured image spot
                    thumb_id_field.val( img_data.image_id );
                }
            }
        );
    }


    /**
     * Ajax fetch product info from Amazon
     * @param asin
     */
    function ajax_fetch_product( asin ) {
        // Call Amazon services on this ASIN
        jQuery.post(
            ajaxurl,
            {
                'action': 'rmw_wpaz_find_product',
                'asin':   asin
            },
            function(response){

                // We got a response. Parse out the JSON return
                var data = JSON.parse( response );

                // Was there an error?
                if ( data.code != 0 ) {

                    // Report the error
                    alert( 'Something went wrong. The server responded: ' + data.message + " (" + data.code + ")" );

                } else {

                    // No error. Capture the product info from the response
                    var product_info = data.product;

                    // Copy the image from Amazon to our system
                    ajax_set_product_image( product_info.image, product_info.title );

                    // Copy the title the post title
                    post_title.val( product_info.title );

                    // Copy the product description to the post body
                    if( post_body.is(':visible') ) {
                        post_body.val( product_info.description );
                    } else {
                        var editor = tinyMCE.get("content");
                        editor.setContent( product_info.description );
                    }

                    // Copy all the remaining fields
                    update_meta_field( 'author',                  product_info.author );
                    update_meta_field( 'list_price',              product_info.list_price );
                    update_meta_field( 'publisher',               product_info.publisher );
                    update_meta_field( 'url',                     product_info.url );
                    update_meta_field( 'sales_rank',              product_info.sales_rank );
                    update_meta_field( 'is_ranked',               product_info.is_ranked );
                    update_meta_field( 'amazon_price',            product_info.amazon_price );
                    update_meta_field( 'amazon_discount',         product_info.amazon_discount );
                    update_meta_field( 'amazon_discount_percent', product_info.amazon_discount_percent );
                    update_meta_field( 'availability',            product_info.availability );
                    update_meta_field( 'last_fetch',              product_info.last_fetch );
                }
            }
        );
    }

    /**
     * Click the product lookup button
     */
    lookup_button.click( function( e ) {

        e.preventDefault();

        // Only do a fetch if we've never fetched before...
        var last_fetch_time = last_fetch_field.val( );
        if ( last_fetch_time.length == 0 ) {

            // get the amazon ID from the field
            var asin = jQuery.trim( asin_field.val() );
            asin_field.val( asin );

            if ( asin.length == 0 ) {
                alert( 'Please enter an Amazon product ID # before clicking LOOKUP' );
            } else {
                ajax_fetch_product( asin );
            }
        }
    });
});