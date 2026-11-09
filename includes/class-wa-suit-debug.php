<?php
/**
 * Private fatal-error logging for WA Suit.
 *
 * @package WASuit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures fatal PHP errors in a protected WA Suit log.
 */
class WA_Suit_Debug {

	/**
	 * Developer settings option.
	 */
	const OPTION_NAME = 'wa_suit_developer_settings';

	/**
	 * Registers the fatal-error handler independently of optional file logging.
	 *
	 * @return void
	 */
	public static function init() {
		register_shutdown_function( array( __CLASS__, 'capture_fatal_error' ) );
		add_filter( 'wp_php_error_args', array( __CLASS__, 'capture_wordpress_fatal_error' ), 1, 2 );
	}

	/**
	 * Captures a fatal error before WordPress renders its recovery screen.
	 *
	 * @param array    $args  Arguments passed to the fatal-error screen.
	 * @param WP_Error $error WordPress fatal error.
	 * @return array
	 */
	public static function capture_wordpress_fatal_error( $args, $error ) {
		unset( $error );
		self::capture_fatal_error();
		return $args;
	}

	/**
	 * Writes the last fatal PHP error to the private WA Suit log.
	 *
	 * @return void
	 */
	public static function capture_fatal_error() {
		if ( ! empty( $GLOBALS['wa_suit_fatal_error_handled'] ) ) {
			return;
		}

		$error = error_get_last();
		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

		if ( ! is_array( $error ) || ! isset( $error['type'], $error['message'], $error['file'], $error['line'] ) || ! in_array( $error['type'], $fatal_types, true ) ) {
			return;
		}
		$GLOBALS['wa_suit_fatal_error_handled'] = true;

		$snippet_context = isset( $GLOBALS['wa_suit_current_php_snippet'] ) && is_array( $GLOBALS['wa_suit_current_php_snippet'] )
			? $GLOBALS['wa_suit_current_php_snippet']
			: array();
		$snippet_id = isset( $snippet_context['id'] ) && is_string( $snippet_context['id'] )
			? sanitize_key( $snippet_context['id'] )
			: '';
		$snippet_title = isset( $snippet_context['title'] ) && is_string( $snippet_context['title'] )
			? sanitize_text_field( $snippet_context['title'] )
			: '';
		$snippet_disabled = '' !== $snippet_id
			? self::disable_failed_snippet( $snippet_id, $snippet_title, $error['message'] )
			: false;

		$entry = sprintf(
			"[%s UTC] PHP fatal error (%d): %s in %s:%d\n",
			gmdate( 'Y-m-d H:i:s' ),
			$error['type'],
			$error['message'],
			$error['file'],
			$error['line']
		);

		$settings = get_option( self::OPTION_NAME, array() );
		if ( is_array( $settings ) && ! empty( $settings['debug_enabled'] ) && ! self::append_log( $entry ) ) {
			error_log( 'WA Suit debug logger could not write its private fatal-error log. ' . trim( $entry ) );
		}

		self::send_fatal_error_email( $error, $snippet_disabled ? $snippet_title : '' );
	}

	/**
	 * Disables a failing PHP snippet and queues an administrator notice.
	 *
	 * @param string $snippet_id    Snippet identifier.
	 * @param string $snippet_title Snippet title.
	 * @param string $message       Failure message.
	 * @return void
	 */
	public static function handle_snippet_failure( $snippet_id, $snippet_title, $message ) {
		$snippet_id = sanitize_key( $snippet_id );
		if ( '' === $snippet_id ) {
			return;
		}

		$snippet_disabled = self::disable_failed_snippet( $snippet_id, $snippet_title, $message );
		self::send_fatal_error_email(
			array(
				'type'    => E_ERROR,
				'message' => $message,
				'file'    => '',
				'line'    => 0,
			),
			$snippet_disabled ? $snippet_title : ''
		);
	}

	/**
	 * Disables the saved snippet associated with a PHP failure.
	 *
	 * @param string $snippet_id    Snippet identifier.
	 * @param string $snippet_title Snippet title.
	 * @param string $message       Failure message.
	 * @return bool Whether the saved snippet is confirmed disabled.
	 */
	private static function disable_failed_snippet( $snippet_id, $snippet_title, $message ) {
		$option_name = 'wa_suit_mod_code-snippets';
		$settings    = get_option( $option_name, array() );
		if ( ! is_array( $settings ) || ! isset( $settings['snippets'] ) || ! is_array( $settings['snippets'] ) || ! isset( $settings['snippets'][ $snippet_id ] ) || ! is_array( $settings['snippets'][ $snippet_id ] ) ) {
			return false;
		}

		$settings['snippets'][ $snippet_id ]['enabled'] = '0';
		update_option( $option_name, $settings );
		$verified_settings = get_option( $option_name, array() );
		$disabled = is_array( $verified_settings ) &&
			isset( $verified_settings['snippets'][ $snippet_id ]['enabled'] ) &&
			'1' !== (string) $verified_settings['snippets'][ $snippet_id ]['enabled'];
		if ( ! $disabled ) {
			error_log( 'WA Suit could not confirm that the failing PHP snippet was disabled: ' . $snippet_id );
			return false;
		}

		self::queue_snippet_failure_notice( $snippet_title, $message );
		return true;
	}

	/**
	 * Queues an administrator notice for a snippet that was automatically disabled.
	 *
	 * @param string $snippet_title Snippet title.
	 * @param string $message       Failure message.
	 * @return void
	 */
	public static function queue_snippet_failure_notice( $snippet_title, $message ) {
		$notices = get_option( 'wa_suit_php_snippet_failure_notices', array() );
		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$notices[] = array(
			'title'   => is_string( $snippet_title ) && '' !== $snippet_title ? sanitize_text_field( $snippet_title ) : __( 'Código PHP', 'wa-suit' ),
			'message' => substr( sanitize_text_field( $message ), 0, 500 ),
			'time'    => function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
		);
		update_option( 'wa_suit_php_snippet_failure_notices', array_slice( $notices, -10 ), false );
	}

	/**
	 * Sends fatal error details to WA Web Design support.
	 *
	 * @param array  $error         PHP error information.
	 * @param string $snippet_title Optional snippet title.
	 * @return void
	 */
	private static function send_fatal_error_email( $error, $snippet_title = '' ) {
		if ( ! function_exists( 'wp_mail' ) ) {
			error_log( 'WA Suit could not send a fatal-error notification because wp_mail is unavailable.' );
			return;
		}
		if ( ! empty( $GLOBALS['wa_suit_sending_fatal_error_email'] ) ) {
			return;
		}

		$GLOBALS['wa_suit_sending_fatal_error_email'] = true;
		$site_url = function_exists( 'home_url' ) ? home_url( '/' ) : ( isset( $_SERVER['HTTP_HOST'] ) ? 'https://' . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : 'URL indisponível' );
		$site_name = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : 'Site WordPress';
		$timestamp = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$error_key = 'wa_suit_fatal_' . md5(
			(string) $site_url . '|' .
			( isset( $error['message'] ) ? (string) $error['message'] : '' ) . '|' .
			( isset( $error['file'] ) ? (string) $error['file'] : '' ) . '|' .
			( isset( $error['line'] ) ? (string) $error['line'] : '' )
		);
		if ( function_exists( 'get_transient' ) && get_transient( $error_key ) ) {
			return;
		}
		$subject   = sprintf( '[WA Suit] Erro crítico no site %s', $site_name );
		$body      = array(
			'Um erro PHP foi detectado pelo WA Suit.',
			'',
			'Site: ' . $site_url,
			'Nome: ' . $site_name,
			'Horário: ' . $timestamp,
			'Tipo do erro: ' . ( isset( $error['type'] ) ? (string) $error['type'] : 'desconhecido' ),
			'Mensagem: ' . ( isset( $error['message'] ) ? (string) $error['message'] : 'Mensagem indisponível' ),
			'Arquivo: ' . ( isset( $error['file'] ) ? (string) $error['file'] : 'indisponível' ),
			'Linha: ' . ( isset( $error['line'] ) ? (string) $error['line'] : 'indisponível' ),
		);
		if ( '' !== $snippet_title ) {
			$body[] = 'Código WA Suit: ' . $snippet_title;
			$body[] = 'O código foi desativado automaticamente.';
		}

		try {
			$sent = wp_mail( 'suporte@wawebdesign.com.br', $subject, implode( "\n", $body ) );
			if ( ! $sent ) {
				error_log( 'WA Suit could not send the fatal-error notification email to support.' );
			} elseif ( function_exists( 'set_transient' ) ) {
				set_transient( $error_key, 1, 10 * MINUTE_IN_SECONDS );
			}
		} catch ( Throwable $mail_error ) {
			error_log( 'WA Suit could not send the fatal-error notification email: ' . $mail_error->getMessage() );
		}
	}

	/**
	 * Returns the protected log file path.
	 *
	 * @return string
	 */
	public static function get_log_path() {
		$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : false;
		$base_directory = realpath( WP_CONTENT_DIR );

		if ( false === $document_root || self::is_path_inside( $base_directory, $document_root ) ) {
			$base_directory = realpath( sys_get_temp_dir() );

			if ( false !== $document_root && self::is_path_inside( $base_directory, $document_root ) ) {
				$base_directory = realpath( dirname( untrailingslashit( ABSPATH ) ) );
			}

			if ( false === $base_directory || ( false !== $document_root && self::is_path_inside( $base_directory, $document_root ) ) ) {
				return '';
			}
		}

		if ( false === $base_directory ) {
			return '';
		}

		return trailingslashit( $base_directory ) . 'wa-suit-' . substr( hash( 'sha256', ABSPATH ), 0, 16 ) . '/wa-suit-debug.log';
	}

	/**
	 * Returns the tail of the private log file.
	 *
	 * @param int $max_bytes Maximum bytes to return.
	 * @return string|false
	 */
	public static function get_log_contents( $max_bytes = 65536 ) {
		$path = self::get_log_path();

		if ( '' === $path ) {
			return false;
		}

		if ( ! file_exists( $path ) ) {
			return '';
		}

		if ( ! is_readable( $path ) ) {
			return false;
		}

		$file = fopen( $path, 'rb' );
		if ( false === $file ) {
			return false;
		}

		$file_size = filesize( $path );
		if ( false === $file_size ) {
			fclose( $file );
			return false;
		}

		$start = max( 0, $file_size - absint( $max_bytes ) );
		if ( 0 !== $start && -1 === fseek( $file, $start ) ) {
			fclose( $file );
			return false;
		}

		$contents = stream_get_contents( $file );
		fclose( $file );

		if ( false === $contents ) {
			return false;
		}

		if ( 0 !== $start ) {
			$first_newline = strpos( $contents, "\n" );
			$contents      = false === $first_newline ? '' : substr( $contents, $first_newline + 1 );
		}

		return $contents;
	}

	/**
	 * Clears the private log.
	 *
	 * @return bool
	 */
	public static function clear_log() {
		$path = self::get_log_path();

		if ( '' === $path ) {
			return false;
		}

		if ( ! file_exists( $path ) ) {
			return true;
		}

		$cleared = false !== file_put_contents( $path, '', LOCK_EX );
		$rotated_path = $path . '.1';

		if ( file_exists( $rotated_path ) ) {
			$cleared = $cleared && unlink( $rotated_path );
		}

		return $cleared;
	}

	/**
	 * Appends a log entry and ensures the directory cannot be browsed.
	 *
	 * @param string $entry Log entry.
	 * @return bool
	 */
	private static function append_log( $entry ) {
		$log_path = self::get_log_path();
		if ( '' === $log_path ) {
			return false;
		}

		$directory = dirname( $log_path );
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return false;
		}
		if ( DIRECTORY_SEPARATOR === '/' && ! chmod( $directory, 0700 ) ) {
			return false;
		}

		$index_file = $directory . '/index.php';
		if ( ! file_exists( $index_file ) && false === file_put_contents( $index_file, "<?php\n// Silence is golden.\n", LOCK_EX ) ) {
			return false;
		}

		$htaccess_file = $directory . '/.htaccess';
		$htaccess_rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
		if ( ! file_exists( $htaccess_file ) && false === file_put_contents( $htaccess_file, $htaccess_rules, LOCK_EX ) ) {
			return false;
		}

		$web_config_file = $directory . '/web.config';
		$web_config_rules = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n";
		if ( ! file_exists( $web_config_file ) && false === file_put_contents( $web_config_file, $web_config_rules, LOCK_EX ) ) {
			return false;
		}

		if ( file_exists( $log_path ) && filesize( $log_path ) > 1048576 ) {
			if ( false === file_put_contents( $log_path, '', LOCK_EX ) ) {
				return false;
			}
		}

		$written = file_put_contents( $log_path, $entry, FILE_APPEND | LOCK_EX );
		if ( false !== $written && DIRECTORY_SEPARATOR === '/' && ! chmod( $log_path, 0600 ) ) {
			return false;
		}

		return false !== $written;
	}

	/**
	 * Checks whether a path is equal to or nested beneath another path.
	 *
	 * @param string|false $path        Path to check.
	 * @param string|false $parent_path Parent path.
	 * @return bool
	 */
	private static function is_path_inside( $path, $parent_path ) {
		if ( false === $path || false === $parent_path ) {
			return false;
		}

		$path        = untrailingslashit( wp_normalize_path( $path ) );
		$parent_path = untrailingslashit( wp_normalize_path( $parent_path ) );

		if ( '\\' === DIRECTORY_SEPARATOR ) {
			$path        = strtolower( $path );
			$parent_path = strtolower( $parent_path );
		}

		return $path === $parent_path || 0 === strpos( $path, trailingslashit( $parent_path ) );
	}
}
