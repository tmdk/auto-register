<?php
/**
 * Plugin Name:       Auto Register
 * Description:       Automatically registers users using a magic link
 * Version:           1.0.1
 * Author:            tmdk
 * Text Domain:       auto-register
 * GitHub Plugin URI: https://github.com/tmdk/auto-register
 */

define( 'AR_QUERY_ARG', 'arotp' );

$ar_admin_page = null;

/**
 * @param $key
 *
 * @return bool
 */
function ar_check_otp_key( $key ) {
	if ( $key === get_option( 'ar_otp_key' ) ) {
		return true;
	}

	return false;
}

function ar_register_user() {
	try {
		$user_id = wp_insert_user( [
			'user_pass'            => bin2hex( random_bytes( 8 ) ),
			'user_login'           => base_convert( random_int( 0, PHP_INT_MAX ), 10, 36 ),
			'show_admin_bar_front' => 'false',
			'role'                 => get_option( 'ar_user_role', 'subscriber' ),
			'locale'               => get_option( 'ar_user_locale', 'en_US' ),
		] );
	} catch ( Exception $e ) {
		return false;
	}

	if ( is_wp_error( $user_id ) ) {
		return false;
	}

	return $user_id;
}

function ar_redirect() {
	$redirect_enabled = get_option( 'ar_redirect_enabled', false );

	if ( $redirect_enabled ) {
		$redirect_url = get_option( 'ar_redirect_url', '' );
	}

	if ( empty( $redirect_url ) ) {
		wp_safe_redirect( remove_query_arg( AR_QUERY_ARG ) );
		exit();
	}

	wp_safe_redirect( $redirect_url );
	exit();
}

add_action( 'template_redirect', function () {
	if ( ! isset( $_REQUEST[ AR_QUERY_ARG ] ) ) {
		return;
	}

	$otp_key = $_REQUEST[ AR_QUERY_ARG ];

	$user_id   = false;
	$logged_in = false;

	if ( ar_check_otp_key( $otp_key ) ) {
		$user_id = ar_register_user();
	}

	if ( $user_id ) {
		$logged_in = ar_login( $user_id );
	}

	if ( $logged_in ) {
		ar_redirect();
	}

	wp_safe_redirect( remove_query_arg( AR_QUERY_ARG ) );
	exit();
} );

function ar_login( $user_id ) {
	wp_destroy_current_session();
	wp_clear_auth_cookie();
	wp_set_current_user( 0 );

	$user = new WP_User( $user_id );

	if ( $user->ID === 0 ) {
		return false;
	}

	wp_set_auth_cookie( $user->ID, false );
	do_action( 'wp_login', $user->user_login, $user );

	wp_set_current_user( $user->ID );

	return true;
}

function ar_add_admin_page() {
	global $ar_admin_page;
	$ar_admin_page = add_menu_page(
		__( 'Auto Register', 'auto-register' ), // title
		__( 'Auto Register', 'auto-register' ), // menu title
		'manage_options', // capability
		'auto-register', // menu slug
		'ar_admin_page' // callable
	);
}

function ar_register_settings() {
	$defaults = [
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	];

	register_setting( 'auto-register', 'ar_otp_key', $defaults );
	register_setting( 'auto-register', 'ar_redirect_enabled', $defaults );
	register_setting( 'auto-register', 'ar_redirect_url', $defaults );
	register_setting( 'auto-register', 'ar_user_locale', $defaults );
	register_setting( 'auto-register', 'ar_user_role', $defaults );

	add_settings_field( 'ar_otp_key', __( 'Magic Key', 'auto-register' ), '__return_empty_string', 'auto-register' );
	add_settings_field( 'ar_redirect_enabled', __( 'Redirect', 'auto-register' ), '__return_empty_string',
		'auto-register' );
	add_settings_field( 'ar_redirect_url', __( 'Redirect To', 'auto-register' ), '__return_empty_string',
		'auto-register' );
	add_settings_field( 'ar_user_locale', __( 'User Locale', 'auto-register' ), '__return_empty_string',
		'auto-register' );
	add_settings_field( 'ar_user_role', __( 'User Role', 'auto-register' ), '__return_empty_string',
		'auto-register' );
}

add_action( 'admin_init', 'ar_register_settings' );

function ar_admin_page() { ?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Auto Register', 'auto-register' ) ?></h1>
		<form method="POST" action="options.php">
			<?php settings_fields( 'auto-register' ); ?>
			<table class="form-table" role="presentation">
				<tbody>
				<tr>
					<th scope="row"><label for="ar_otp_key"><?php esc_html_e( 'Magic Key', 'auto-register' ) ?></label>
					</th>
					<td>
						<input name="ar_otp_key" id="ar_otp_key" type="text"
							   value="<?php echo esc_attr( get_option( 'ar_otp_key', '' ) ) ?>" class="regular-text">
						<p class="description">
							Example URL:<br>
							<input id="ar_otp_url_example" type="text" readonly
								   value="<?php echo esc_attr( add_query_arg( AR_QUERY_ARG,
								       get_option( 'ar_otp_key', '' ), home_url() ) ) ?>" class="regular-text">
							<button class="ar-copy-btn button button-secondary" type="button"
									data-clipboard-target="#ar_otp_url_example">
								Copy
							</button>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Redirect', 'auto-register' ) ?>
					</th>
					<td>
						<fieldset>
							<legend class="screen-reader-text">
								<span><?php esc_html_e( 'Redirect', 'auto-register' ) ?></span>
							</legend>
							<label for="ar_redirect_enabled">
								<input name="ar_redirect_enabled" type="checkbox" id="ar_redirect_enabled"
									   value="1" <?php checked( get_option( 'ar_redirect_enabled', false ) ) ?>>
								<?php esc_html_e( 'Enabled' ) ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ar_redirect_url"><?php esc_html_e( 'Redirect To', 'auto-register' ) ?></label>
					</th>
					<td>
						<input name="ar_redirect_url" id="ar_redirect_url" type="text"
							   value="<?php echo esc_attr( get_option( 'ar_redirect_url', '' ) ) ?>"
							   class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ar_user_locale"><?php esc_html_e( 'User Locale', 'auto-register' ) ?></label>
					</th>
					<td>
						<?php wp_dropdown_languages( [
							'id'       => 'ar_user_locale',
							'name'     => 'ar_user_locale',
							'selected' => get_option( 'ar_user_locale', 'en_US' ),
						] ) ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="ar_user_role"><?php esc_html_e( 'User Role', 'auto-register' ) ?></label>
					</th>
					<td>
						<select id="ar_user_role" name="ar_user_role">
							<?php wp_dropdown_roles( get_option( 'ar_user_role', 'subscriber' ) ) ?>
						</select>
					</td>
				</tr>
				</tbody>
			</table>
			<?php submit_button() ?>
		</form>
	</div>
<?php }

add_action( 'admin_menu', 'ar_add_admin_page' );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	global $ar_admin_page;

	if ( $hook === $ar_admin_page ) {
		wp_enqueue_script( 'clipboard' );
		wp_add_inline_script( 'clipboard',
			"(function () { if (typeof ClipboardJS === 'function') new ClipboardJS('.ar-copy-btn') })()" );
	}
} );
