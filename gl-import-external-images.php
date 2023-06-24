<?php
/**
 * Plugin Name:       GL Import External Images
 * Plugin URI:        https://greenlifeit.com/plugins
 * Description:       Download and Insert images to WP Media Library from External URLs.
 * Version:           1.0
 * Author:            Asiqur Rahman
 * Author URI:        https://asique.net
 * License:           GPLv2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gliei
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

function gliei_upload_form() {
	?>
	<div class="gliei-wrap" style="text-align: center;">
		<div class="gliei-input">
			<div style="margin-bottom: 15px;">or</div>
			<input name="url" type="url" id="gliei-url" placeholder="<?php _e( 'Image URL...', 'gliei' ); ?>" style="min-width: 300px;" autocomplete="off">
		</div>
		<div class="gliei-submit" style="margin-top: 15px;">
			<input type="hidden" name="gliei_nonce" id="gliei_nonce" value="<?php echo wp_create_nonce( 'gliei' ); ?>">
			<button type="button" id="gliei-submit-btn" class="button-primary">
				<?php _e( 'Add to Media Library', 'gliei' ); ?>
			</button>
			<div id="gliei-message" style="max-width: 300px; margin: 15px auto 0;"></div>
		</div>
	</div>
	<script>
		document.getElementById( 'gliei-submit-btn' ).addEventListener( 'click', function ( event ) {
			event.preventDefault();
			
			var urlInput = document.getElementById( 'gliei-url' );
			if ( urlInput.value.length == 0 ) {
				alert( '<?php _e( 'Please add a URL', 'gliei' ); ?>' );
				return false;
			}
			
			var message = document.getElementById( 'gliei-message' );
			message.innerHTML = '<img src="<?php echo includes_url( 'images/spinner.gif' ); ?>" alt="Importing...">';
			message.classList = 'downloading';
			
			var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
			var xhttp = new XMLHttpRequest();
			
			var formData = new FormData();
			formData.append( 'action', 'gliei_action' );
			formData.append( 'url', urlInput.value );
			formData.append( 'gliei_nonce', document.getElementById( 'gliei_nonce' ).value );
			
			xhttp.open( "POST", ajaxurl );
			xhttp.send( formData );
			
			xhttp.onreadystatechange = function () {
				if ( this.readyState === 4 && this.status === 200 ) {
					var data = JSON.parse( this.responseText );
					if ( data.success === true ) {
						message.classList.add( 'updated' );
						message.innerHTML = data.data;
						urlInput.value = '';
						
						// refresh media content
						if ( wp.media.frame.content.get() !== null ) {
							wp.media.frame.content.mode( 'browse' ).get().collection.props.set( {
								ignore: (
									+ new Date()
								)
							} );
							wp.media.frame.state().get( 'selection' ).add( wp.media.attachment( data.image ) );
						} else {
							wp.media.frame.library.props.set( {
								ignore: (
									+ new Date()
								)
							} );
						}
					} else {
						message.classList.add( 'error' );
						message.innerHTML = data.data;
					}
				}
			};
		} );
	</script>
	<?php
}

add_action( 'post-upload-ui', 'gliei_upload_form' );

function gliei_import_image() {
	if ( ! wp_verify_nonce( $_POST['gliei_nonce'], 'gliei' ) ) {
		wp_send_json_error( __( 'You don\'t have permission to upload image', 'gliei' ) );
		wp_die();
	}
	
	if ( isset( $_POST['url'] ) ) {
		$image = media_sideload_image( esc_url_raw( $_POST['url'] ), null, null, 'id' );
		if ( is_wp_error( $image ) ) {
			wp_send_json_error( $image->get_error_message() );
		} else {
			wp_send_json( array( 'success' => true, 'data' => __( 'Import Successful.', 'gliei' ), 'image' => $image ) );
		}
	} else {
		wp_send_json_error( __( 'No URL found!', 'gliei' ) );
	}
	
	wp_die();
}

add_action( 'wp_ajax_gliei_action', 'gliei_import_image' );
add_action( 'wp_ajax_nopriv_gliei_action', '__return_zero' );