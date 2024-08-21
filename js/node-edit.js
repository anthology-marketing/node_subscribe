(function ($, Drupal, drupalSettings) {
    Drupal.behaviors.notifyFollowersOption = {
      attach: function (context, drupalSettings) {
            var content_moderation_dropdown = $( '[data-drupal-selector="edit-moderation-state-0-state"]' );
            if(content_moderation_dropdown.length){
                var value = $( '[data-drupal-selector="edit-moderation-state-0-state"]' ).val()
                if( value != 'published' ){
                    $( '.form-item-send-node-subscribe-emails' ).hide();
                    $( '.form-item-send-node-subscribe-emails-message' ).hide();
                }else{
                    $( '.form-item-send-node-subscribe-emails' ).show();
                    $( '.form-item-send-node-subscribe-emails-message' ).show();
                }

                $( '[data-drupal-selector="edit-moderation-state-0-state"]' ).change(function() {
                    // alert( "Handler for .change() called." + $( this ).val() );
                    var value = $( this ).val()
                    if( value != 'published' ){
                        $( '.form-item-send-node-subscribe-emails' ).hide();
                        $( '.form-item-send-node-subscribe-emails-message' ).hide();
                    }else{
                        $( '.form-item-send-node-subscribe-emails' ).show();
                        $( '.form-item-send-node-subscribe-emails-message' ).show();
                    }
                });
            }
        }
    }
  }(jQuery, Drupal, drupalSettings));
