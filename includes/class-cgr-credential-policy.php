<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CGR_Credential_Policy {
	/** This identity controls protection settings, independently of notification recipients. */
	const OWNER_EMAIL = 'peteaugusta@gmail.com';

	public static function can_manage() {
		$user = wp_get_current_user();
		return current_user_can( 'manage_options' ) && $user->exists() && 0 === strcasecmp( trim( $user->user_email ), self::OWNER_EMAIL );
	}

	private static function authorize_owner() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Only the designated protection owner with administrator access can manage credential protection.', 'calico-google-reviews' ), '', array( 'response' => 403 ) );
		}
	}
	public static function settings() {
		return wp_parse_args( get_option( 'cgr_credential_policy', array() ), array( 'enabled' => false, 'days' => 90, 'expires' => 0, 'email' => '', 'paused' => false, 'per_pull' => 100, 'monthly_pulls' => 5, 'monthly_reviews' => 500, 'notice' => '' ) );
	}
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_cgr_save_policy', array( $this, 'save' ) );
		add_action( 'cgr_location_sync', array( __CLASS__, 'check' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'check' ) );
	}
	public function menu() {
		if ( self::can_manage() ) { add_submenu_page( 'cgr-reviews', 'Credential Protection', 'Credential Protection', 'manage_options', 'cgr-protection', array( $this, 'page' ) ); }
	}
	public function save() {
		self::authorize_owner();
		check_admin_referer( 'cgr_save_policy' );
		self::check();
		$old = self::settings();
		$s = $old;
		$s['enabled'] = isset( $_POST['enabled'] );
		$s['paused'] = isset( $_POST['paused'] );
		foreach ( array( 'days' => 3650, 'per_pull' => 10000, 'monthly_pulls' => 10000, 'monthly_reviews' => 1000000 ) as $field => $maximum ) {
			$s[ $field ] = max( 1, min( $maximum, isset( $_POST[ $field ] ) ? absint( $_POST[ $field ] ) : $old[ $field ] ) );
		}
		$email = isset( $_POST['email'] ) ? trim( wp_unslash( $_POST['email'] ) ) : '';
		if ( $email && ! is_email( $email ) ) { wp_die( 'Enter a valid notification email address.' ); }
		$s['email'] = sanitize_email( $email );
		if ( $s['enabled'] && ( ! $old['enabled'] || isset( $_POST['renew'] ) ) ) { $s['expires'] = time() + $s['days'] * DAY_IN_SECONDS; $s['notice'] = ''; }
		update_option( 'cgr_credential_policy', $s, false );
		wp_safe_redirect( admin_url( 'admin.php?page=cgr-protection' ) );
		exit;
	}
	public static function check() {
		$s = self::settings();
		if ( ! $s['enabled'] || ! $s['expires'] ) { return; }
		$expired = time() >= $s['expires'];
		if ( $expired ) {
			$api = new CGR_Google_API();
			foreach ( $api->get_locations() as $id => $location ) {
				if ( $location['outscraper_api_key_enc'] ) { $api->update_location( $id, array( 'outscraper_api_key_enc' => '', 'last_error' => 'Outscraper credential expired. Renew access and re-enter the API key.' ) ); }
			}
			// Remove the legacy copy as well as every location credential.
			$legacy = get_option( 'cgr_settings', array() );
			if ( is_array( $legacy ) ) { $legacy['outscraper_api_key_enc'] = ''; update_option( 'cgr_settings', $legacy, false ); }
		}
		$type = $expired ? 'expired' : ( time() >= $s['expires'] - 30 * DAY_IN_SECONDS ? 'warning' : '' );
		$marker = $type . ':' . $s['expires'];
		if ( $type && $marker !== $s['notice'] && add_option( 'cgr_policy_mail_lock', time(), '', false ) ) {
			$s['notice'] = $marker;
			update_option( 'cgr_credential_policy', $s, false );
			$recipient = $s['email'] ? $s['email'] : get_option( 'admin_email' );
			$sent = wp_mail( $recipient, 'Calico Google Reviews: Outscraper access ' . ( $expired ? 'expired' : 'expires soon' ), home_url() . "\nOutscraper credential expiration: " . gmdate( 'Y-m-d H:i', $s['expires'] ) . " UTC.\n" . ( $expired ? 'Saved Outscraper keys have been erased. Renew access and re-enter the key to resume pulls.' : 'An administrator can renew access in Google Reviews > Credential Protection.' ) . "\n" . admin_url( 'admin.php?page=cgr-protection' ) );
			if ( ! $sent ) { $s['notice'] = ''; update_option( 'cgr_credential_policy', $s, false ); }
			delete_option( 'cgr_policy_mail_lock' );
		}
	}
	public static function reserve( $requested ) {
		self::check();
		$s = self::settings();
		if ( $s['enabled'] && ( ! $s['expires'] || time() >= $s['expires'] ) ) { return new WP_Error( 'cgr_expired', 'Outscraper access expired. Renew access and re-enter the API key.' ); }
		if ( $s['paused'] ) { return new WP_Error( 'cgr_paused', 'Outscraper requests are paused by an administrator.' ); }
		if ( ! $s['enabled'] ) { return $requested; }
		// Serialize budget reservations across manual and scheduled requests.
		if ( ! add_option( 'cgr_policy_budget_lock', time(), '', false ) ) {
			if ( (int) get_option( 'cgr_policy_budget_lock' ) < time() - 60 ) { delete_option( 'cgr_policy_budget_lock' ); }
			return new WP_Error( 'cgr_busy', 'Another request is reserving usage. Try again shortly.' );
		}
		try {
			$usage = self::usage();
			$remaining = $s['monthly_reviews'] - $usage['reviews'];
			if ( $usage['pulls'] >= $s['monthly_pulls'] || $remaining < 1 ) { return new WP_Error( 'cgr_usage_limit', 'Monthly Outscraper usage limit reached. Requests resume next UTC calendar month.' ); }
			$limit = min( $requested ? $requested : $s['per_pull'], $s['per_pull'], $remaining );
			$usage['pulls']++;
			$usage['reviews'] += $limit;
			$usage['last_request'] = time();
			update_option( 'cgr_credential_usage', $usage, false );
			return $limit;
		} finally { delete_option( 'cgr_policy_budget_lock' ); }
	}
	public static function usage() {
		$usage = get_option( 'cgr_credential_usage', array() );
		return isset( $usage['month'] ) && gmdate( 'Y-m' ) === $usage['month'] ? $usage : array( 'month' => gmdate( 'Y-m' ), 'pulls' => 0, 'reviews' => 0, 'last_request' => 0 );
	}
	public function page() {
		self::authorize_owner();
		$s = self::settings(); $usage = self::usage();
		?>
		<div class="wrap"><h1>Outscraper Credential Protection</h1>
		<p>Applies to all locations and both scheduled and manual pulls. Expiration erases saved Outscraper credentials and stops pulls; existing reviews remain available. Renewing after expiration also requires entering the key again on Sources.</p>
		<p>Only the WordPress administrator account with email <?php echo esc_html( self::OWNER_EMAIL ); ?> can change or renew this policy. Notification recipients do not receive management access. People with access to change account emails, plugin files, or the database can still bypass local restrictions. Backups may retain older keys.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'cgr_save_policy' ); ?><input type="hidden" name="action" value="cgr_save_policy">
		<table class="form-table"><tr><th>Enable expiration and usage limits</th><td><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>></td></tr>
		<tr><th>Pause all Outscraper requests</th><td><input type="checkbox" name="paused" value="1" <?php checked( $s['paused'] ); ?>></td></tr>
		<?php foreach ( array( 'days' => 'Days per renewal', 'per_pull' => 'Maximum reviews requested per pull', 'monthly_pulls' => 'Maximum pulls per month', 'monthly_reviews' => 'Maximum reviews requested per month' ) as $field => $label ) : ?>
		<tr><th><label for="cgr-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th><td><input id="cgr-<?php echo esc_attr( $field ); ?>" type="number" min="1" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $s[ $field ] ); ?>"></td></tr>
		<?php endforeach; ?>
		<tr><th>Current expiration</th><td><?php echo esc_html( $s['expires'] ? wp_date( 'Y-m-d H:i T', $s['expires'] ) : 'Not enabled yet' ); ?><p><label><input type="checkbox" name="renew" value="1"> Refresh access for the selected number of days from today</label></p><p>Saving other changes does not extend access.</p></td></tr>
		<tr><th><label for="cgr-policy-email">Notification email</label></th><td><input id="cgr-policy-email" type="email" name="email" class="regular-text" value="<?php echo esc_attr( $s['email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"><p>Leave blank to use WordPress General Settings → Administration Email Address automatically. One warning is sent within 30 days of expiration and another on expiration.</p></td></tr></table>
		<?php submit_button( 'Save protection settings' ); ?></form>
		<h2>Usage this month (UTC)</h2><p><?php echo esc_html( $usage['pulls'] . ' pulls; ' . $usage['reviews'] . ' reviews requested/reserved.' ); ?></p>
		<p>Usage is reserved before each request, including failures and timeouts. Limits cover this installation only and are not an Outscraper billing guarantee. With protection enabled, a zero/unlimited source review limit is capped by this policy. Expiration is enforced before every API request and during hourly WordPress scheduled checks; email and background erasure depend on WordPress cron running.</p></div>
		<?php
	}
}
