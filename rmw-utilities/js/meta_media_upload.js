jQuery(document).ready(function(){

    //----------------------- META UPLOAD IMAGE -----------------------------------------------------------------------
    // Instantiates the variable that holds the media library frame.
    var media_upload_buttons = jQuery( '.rmw_media_upload_button' );

    // Runs when the image button is clicked.
    media_upload_buttons.click(function(e){

        // Prevents the default action from occurring.
        e.preventDefault();

        var this_button = jQuery( this );
        var frame_title = this_button.attr( 'data-title' );
        var url_field   = this_button.attr( 'data-field' );

        // If the frame already exists, re-open it.
        if ( this_button['meta_image_frame'] ) {
            this_button['meta_image_frame'].open();
            return;
        }

        // Sets up the media library frame
        this_button['meta_image_frame'] = wp.media.frames.meta_image_frame = wp.media({
            title: frame_title,
            button: { text:  'Select Image' },
            library: { type: 'image' }
        });

        // Runs when an image is selected.
        this_button['meta_image_frame'].on('select', function(){

            // Grabs the attachment selection and creates a JSON representation of the model.
            var media_attachment = this_button['meta_image_frame'].state().get('selection').first().toJSON();

            // Sends the attachment URL to our custom image input field.
            jQuery('input[name=' + url_field + ']' ).val( media_attachment.url );
        });

        // Opens the media library frame.
        this_button['meta_image_frame'].open();
    });
});