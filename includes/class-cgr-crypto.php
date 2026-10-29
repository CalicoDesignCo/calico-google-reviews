<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CGR_Crypto {

	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;
		if ( '' === $plaintext ) {
			return '';
		}

		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new WP_Error( 'cgr_missing_openssl', __( 'OpenSSL is required to store Google credentials securely.', 'calico-google-reviews' ) );
		}

		$iv         = random_bytes( 16 );
		$ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return new WP_Error( 'cgr_encrypt_failed', __( 'The Google credential could not be encrypted.', 'calico-google-reviews' ) );
		}

		$mac = hash_hmac( 'sha256', $iv . $ciphertext, self::key(), true );
		return base64_encode( $iv . $mac . $ciphertext );
	}

	public static function decrypt( $encoded ) {
		$encoded = (string) $encoded;
		if ( '' === $encoded ) {
			return '';
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}

		$payload = base64_decode( $encoded, true );
		if ( false === $payload || strlen( $payload ) < 49 ) {
			return '';
		}

		$iv         = substr( $payload, 0, 16 );
		$mac        = substr( $payload, 16, 32 );
		$ciphertext = substr( $payload, 48 );
		$expected   = hash_hmac( 'sha256', $iv . $ciphertext, self::key(), true );
		if ( ! hash_equals( $expected, $mac ) ) {
			return '';
		}

		$plaintext = openssl_decrypt( $ciphertext, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
		return false === $plaintext ? '' : $plaintext;
	}

	private static function key() {
		$material = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		$material .= defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : wp_salt( 'secure_auth' );
		return hash( 'sha256', $material, true );
	}
}
