<?php
/**
 * Installs standalone WordPress fatal and database error drop-ins.
 *
 * @package WASuit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages branded maintenance screens that work without the plugin bootstrap.
 */
class WA_Suit_Error_Screen {

	const SIGNATURE        = 'WA-SUIT-ERROR-SCREEN';
	const VERSION          = '1.0.0';
	const VERSION_OPTION   = 'wa_suit_error_screen_version';
	const HASH_OPTION      = 'wa_suit_error_screen_hash';
	const CONFLICT_OPTION  = 'wa_suit_error_screen_conflict';
	const SETTINGS_OPTION  = 'wa_suit_error_screen_settings';
	const MAINTENANCE_OPTION = 'wa_suit_error_screen_maintenance';

	/**
	 * Registers installation, settings, and notice hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_install' ) );
		add_action( 'admin_post_wa_suit_save_error_screen_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_wa_suit_toggle_error_screen_maintenance', array( __CLASS__, 'toggle_maintenance' ) );
		add_action( 'admin_post_wa_suit_preview_error_screen', array( __CLASS__, 'preview' ) );
		add_action( 'admin_notices', array( __CLASS__, 'conflict_notice' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_maintenance_screen' ), 0 );
	}

	/**
	 * Installs drop-ins after activation and retries unresolved file conflicts.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$settings_hash = self::get_settings_hash();
		$conflicts     = get_option( self::CONFLICT_OPTION, array() );
		if (
			self::VERSION === get_option( self::VERSION_OPTION )
			&& $settings_hash === get_option( self::HASH_OPTION )
			&& empty( $conflicts )
		) {
			return;
		}

		self::install();
	}

	/**
	 * Creates or updates WordPress drop-ins without replacing files owned by others.
	 *
	 * @return bool
	 */
	public static function install() {
		$source = __DIR__ . '/templates/error-screen.php';
		if ( ! is_readable( $source ) || ! is_dir( WP_CONTENT_DIR ) || ! is_writable( WP_CONTENT_DIR ) ) {
			update_option( self::CONFLICT_OPTION, array( 'php-error.php', 'db-error.php' ), false );
			return false;
		}

		$template = file_get_contents( $source );
		if ( false === $template ) {
			update_option( self::CONFLICT_OPTION, array( 'php-error.php', 'db-error.php' ), false );
			return false;
		}

		$brand      = self::get_settings();
		$config     = base64_encode( wp_json_encode( $brand ) );
		$contents   = str_replace( '@@WA_SUIT_CONFIG@@', $config, $template );
		$conflicts  = array();
		$write_error = false;

		foreach ( array( 'php-error.php', 'db-error.php' ) as $filename ) {
			$target = trailingslashit( WP_CONTENT_DIR ) . $filename;
			if ( file_exists( $target ) && ! self::is_ours( $target ) ) {
				$conflicts[] = $filename;
				continue;
			}

			if ( ! self::write_dropin( $target, $contents ) ) {
				$write_error = true;
			}
		}

		update_option( self::CONFLICT_OPTION, $conflicts, false );
		if ( ! $write_error ) {
			update_option( self::VERSION_OPTION, self::VERSION, false );
			update_option( self::HASH_OPTION, self::get_settings_hash(), false );
		}

		return ! $write_error && empty( $conflicts );
	}

	/**
	 * Removes only drop-ins bearing this plugin's signature.
	 *
	 * @return void
	 */
	public static function uninstall() {
		foreach ( array( 'php-error.php', 'db-error.php' ) as $filename ) {
			$target = trailingslashit( WP_CONTENT_DIR ) . $filename;
			if ( is_file( $target ) && self::is_ours( $target ) ) {
				unlink( $target );
			}
		}

		delete_option( self::VERSION_OPTION );
		delete_option( self::HASH_OPTION );
		delete_option( self::CONFLICT_OPTION );
	}

	/**
	 * Renders the settings form for the standalone maintenance screen.
	 *
	 * @return void
	 */
	public static function render_settings() {
		$settings = self::get_settings();
		$maintenance_enabled = (bool) get_option( self::MAINTENANCE_OPTION, false );
		?>
		<?php if ( isset( $_GET['maintenance-changed'] ) && is_string( $_GET['maintenance-changed'] ) ) : ?>
			<div class="notice notice-success inline"><p><?php echo '1' === sanitize_text_field( wp_unslash( $_GET['maintenance-changed'] ) ) ? esc_html__( 'Modo de manutenção ativado para visitantes.', 'wa-suit' ) : esc_html__( 'Modo de manutenção desativado.', 'wa-suit' ); ?></p></div>
		<?php elseif ( isset( $_GET['error-screen-error'] ) ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html__( 'Não foi possível ativar a manutenção porque a tela independente não pôde ser instalada. Verifique se já existe um php-error.php ou db-error.php de outro plugin e se wp-content permite gravação.', 'wa-suit' ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wa-general-settings__form">
			<input type="hidden" name="action" value="wa_suit_save_error_screen_settings">
			<?php wp_nonce_field( 'wa_suit_save_error_screen_settings' ); ?>
			<section class="wa-general-settings__card">
				<div class="wa-general-settings__card-heading">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<div>
						<h3><?php echo esc_html__( 'Tela de erro e manutenção', 'wa-suit' ); ?></h3>
						<p><?php echo esc_html__( 'O WA Suit instala telas independentes para erros críticos do PHP e falhas no banco de dados. Elas continuam disponíveis mesmo quando o WordPress ou um plugin não consegue carregar.', 'wa-suit' ); ?></p>
					</div>
				</div>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="wa-suit-error-screen-name"><?php echo esc_html__( 'Nome da empresa', 'wa-suit' ); ?></label></th>
							<td><input class="regular-text" type="text" id="wa-suit-error-screen-name" name="settings[name]" value="<?php echo esc_attr( $settings['name'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="wa-suit-error-screen-logo"><?php echo esc_html__( 'URL do logo', 'wa-suit' ); ?></label></th>
							<td>
								<input class="regular-text" type="url" id="wa-suit-error-screen-logo" name="settings[logo]" value="<?php echo esc_attr( $settings['logo'] ); ?>" placeholder="https://exemplo.com/logo.png">
								<p class="description"><?php echo esc_html__( 'Use uma imagem pública em PNG, JPG, SVG ou WebP. Se deixar vazio, será usado automaticamente o Logo do site definido em Aparência → Personalizar → Identidade do site.', 'wa-suit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="wa-suit-error-screen-color"><?php echo esc_html__( 'Cor principal', 'wa-suit' ); ?></label></th>
							<td><input type="color" id="wa-suit-error-screen-color" name="settings[color]" value="<?php echo esc_attr( $settings['color'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="wa-suit-error-screen-whatsapp"><?php echo esc_html__( 'WhatsApp para suporte', 'wa-suit' ); ?></label></th>
							<td>
								<input class="regular-text" type="tel" id="wa-suit-error-screen-whatsapp" name="settings[whatsapp]" value="<?php echo esc_attr( $settings['whatsapp'] ); ?>" placeholder="5511999999999">
								<p class="description"><?php echo esc_html__( 'Informe o número com código do país, somente números. O botão de contato aparece quando esse campo é preenchido.', 'wa-suit' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Crédito', 'wa-suit' ); ?></th>
							<td>
								<label for="wa-suit-error-screen-credit">
									<input type="checkbox" id="wa-suit-error-screen-credit" name="settings[credit]" value="1" <?php checked( $settings['credit'] ); ?>>
									<?php echo esc_html__( 'Exibir discretamente “Solução por WA Web Design” no rodapé', 'wa-suit' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="description"><?php echo esc_html__( 'Os arquivos php-error.php e db-error.php são criados em wp-content. Arquivos de outros plugins ou do servidor nunca são substituídos; em caso de conflito, o WA Suit informa aqui no painel.', 'wa-suit' ); ?></p>
			</section>
			<div class="wa-general-settings__actions">
				<button type="submit" class="wa-admin-btn wa-admin-btn--primary"><?php echo esc_html__( 'Salvar configurações', 'wa-suit' ); ?></button>
			</div>
		</form>
		<section class="wa-general-settings__card">
			<div class="wa-general-settings__card-heading">
				<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
				<div>
					<h3><?php echo esc_html__( 'Modo de manutenção', 'wa-suit' ); ?></h3>
					<p><?php echo esc_html__( 'Visitantes verão a tela de manutenção. Administradores e usuários conectados continuam acessando o site e o WordPress normalmente.', 'wa-suit' ); ?></p>
				</div>
			</div>
			<p>
				<strong><?php echo esc_html__( 'Status:', 'wa-suit' ); ?></strong>
				<?php echo $maintenance_enabled ? esc_html__( 'Ativado', 'wa-suit' ) : esc_html__( 'Desativado', 'wa-suit' ); ?>
			</p>
			<div class="wa-general-settings__actions">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wa_suit_toggle_error_screen_maintenance">
					<input type="hidden" name="enabled" value="<?php echo $maintenance_enabled ? '0' : '1'; ?>">
					<?php wp_nonce_field( 'wa_suit_toggle_error_screen_maintenance' ); ?>
					<button type="submit" class="wa-admin-btn <?php echo $maintenance_enabled ? 'wa-admin-btn--secondary' : 'wa-admin-btn--primary'; ?>">
						<?php echo $maintenance_enabled ? esc_html__( 'Desativar manutenção', 'wa-suit' ) : esc_html__( 'Ativar manutenção', 'wa-suit' ); ?>
					</button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank">
					<input type="hidden" name="action" value="wa_suit_preview_error_screen">
					<?php wp_nonce_field( 'wa_suit_preview_error_screen' ); ?>
					<button type="submit" class="wa-admin-btn wa-admin-btn--secondary"><?php echo esc_html__( 'Pré-visualizar tela', 'wa-suit' ); ?></button>
				</form>
			</div>
			<p class="description"><?php echo esc_html__( 'A tela de erro crítico é exibida automaticamente em falhas fatais. O modo de manutenção é controlado separadamente por este botão.', 'wa-suit' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Enables or disables public maintenance mode.
	 *
	 * @return void
	 */
	public static function toggle_maintenance() {
		if ( ! WA_Suit_Security::can_manage_settings() ) {
			wp_die( esc_html__( 'Você não tem permissão para alterar o modo de manutenção.', 'wa-suit' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'wa_suit_toggle_error_screen_maintenance' );
		$enabled = isset( $_POST['enabled'] ) && is_string( $_POST['enabled'] ) && '1' === wp_unslash( $_POST['enabled'] );
		if ( $enabled && ! self::install() ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'             => 'wa-suit',
						'tab'              => 'settings',
						'settings_section' => 'error-screen',
						'error-screen-error' => 'install',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		update_option( self::MAINTENANCE_OPTION, $enabled, false );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => 'wa-suit',
					'tab'                => 'settings',
					'settings_section'   => 'error-screen',
					'maintenance-changed' => $enabled ? '1' : '0',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Shows a capability- and nonce-protected preview of the standalone screen.
	 *
	 * @return void
	 */
	public static function preview() {
		if ( ! WA_Suit_Security::can_manage_settings() ) {
			wp_die( esc_html__( 'Você não tem permissão para visualizar esta tela.', 'wa-suit' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'wa_suit_preview_error_screen' );
		if ( ! self::install() ) {
			wp_die( esc_html__( 'Não foi possível gerar a pré-visualização. Verifique os avisos de instalação no painel.', 'wa-suit' ), '', array( 'response' => 500 ) );
		}

		$path = trailingslashit( WP_CONTENT_DIR ) . 'php-error.php';
		if ( ! self::is_ours( $path ) ) {
			wp_die( esc_html__( 'O arquivo da tela de erro não pertence ao WA Suit.', 'wa-suit' ), '', array( 'response' => 409 ) );
		}

		$GLOBALS['wa_suit_error_screen_preview'] = true;
		include $path;
		exit;
	}

	/**
	 * Serves the maintenance screen to public visitors while leaving WordPress access intact.
	 *
	 * @return void
	 */
	public static function maybe_render_maintenance_screen() {
		if (
			! get_option( self::MAINTENANCE_OPTION, false )
			|| is_admin()
			|| is_user_logged_in()
			|| ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
			|| ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		) {
			return;
		}

		$path = trailingslashit( WP_CONTENT_DIR ) . 'php-error.php';
		if ( ! self::is_ours( $path ) ) {
			return;
		}

		$GLOBALS['wa_suit_error_screen_maintenance'] = true;
		include $path;
		exit;
	}

	/**
	 * Validates and saves the screen branding settings.
	 *
	 * @return void
	 */
	public static function save_settings() {
		if ( ! WA_Suit_Security::can_manage_settings() ) {
			wp_die( esc_html__( 'Você não tem permissão para alterar estas configurações.', 'wa-suit' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'wa_suit_save_error_screen_settings' );
		$input    = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$defaults = self::get_settings();
		$name     = isset( $input['name'] ) && is_string( $input['name'] ) ? sanitize_text_field( $input['name'] ) : $defaults['name'];
		$logo     = isset( $input['logo'] ) && is_string( $input['logo'] ) ? esc_url_raw( trim( $input['logo'] ), array( 'http', 'https' ) ) : '';
		$color    = isset( $input['color'] ) && is_string( $input['color'] ) ? sanitize_hex_color( $input['color'] ) : '';
		$whatsapp = isset( $input['whatsapp'] ) && is_string( $input['whatsapp'] ) ? preg_replace( '/\D+/', '', $input['whatsapp'] ) : '';

		$settings = array(
			'name'     => '' !== $name ? $name : $defaults['name'],
			'logo'     => is_string( $logo ) ? $logo : '',
			'color'    => $color ? $color : '#1f2937',
			'whatsapp' => is_string( $whatsapp ) && strlen( $whatsapp ) >= 8 && strlen( $whatsapp ) <= 15 ? $whatsapp : '',
			'credit'   => isset( $input['credit'] ) && '1' === (string) $input['credit'],
		);
		update_option( self::SETTINGS_OPTION, $settings, false );
		self::install();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'wa-suit',
					'tab'              => 'settings',
					'settings_section' => 'error-screen',
					'settings-saved'   => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Displays file conflicts or filesystem errors to administrators.
	 *
	 * @return void
	 */
	public static function conflict_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$conflicts = get_option( self::CONFLICT_OPTION, array() );
		if ( ! is_array( $conflicts ) || empty( $conflicts ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'WA Suit — tela de erro:', 'wa-suit' ),
			esc_html(
				sprintf(
					__( 'não foi possível instalar ou atualizar %1$s em wp-content. Arquivos existentes de terceiros não foram substituídos. Verifique as permissões ou resolva o conflito e recarregue o painel.', 'wa-suit' ),
					implode( ', ', array_map( 'sanitize_file_name', $conflicts ) )
				)
			)
		);
	}

	/**
	 * Returns validated screen settings.
	 *
	 * @return array
	 */
	private static function get_settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$name = isset( $saved['name'] ) && is_string( $saved['name'] ) ? sanitize_text_field( $saved['name'] ) : get_bloginfo( 'name' );
		$logo = isset( $saved['logo'] ) && is_string( $saved['logo'] ) ? esc_url_raw( $saved['logo'], array( 'http', 'https' ) ) : '';
		if ( '' === $logo && function_exists( 'get_theme_mod' ) && function_exists( 'wp_get_attachment_image_src' ) ) {
			$custom_logo_id = absint( get_theme_mod( 'custom_logo' ) );
			if ( $custom_logo_id ) {
				$custom_logo = wp_get_attachment_image_src( $custom_logo_id, 'full' );
				if ( is_array( $custom_logo ) && isset( $custom_logo[0] ) && is_string( $custom_logo[0] ) ) {
					$logo = esc_url_raw( $custom_logo[0], array( 'http', 'https' ) );
				}
			}
		}
		$color    = isset( $saved['color'] ) && is_string( $saved['color'] ) ? sanitize_hex_color( $saved['color'] ) : '';
		$whatsapp = isset( $saved['whatsapp'] ) && is_string( $saved['whatsapp'] ) ? preg_replace( '/\D+/', '', $saved['whatsapp'] ) : '';

		return array(
			'name'     => '' !== $name ? $name : get_bloginfo( 'name' ),
			'logo'     => is_string( $logo ) ? $logo : '',
			'color'    => $color ? $color : '#1f2937',
			'whatsapp' => is_string( $whatsapp ) ? $whatsapp : '',
			'credit'   => ! empty( $saved['credit'] ),
		);
	}

	/**
	 * Returns a stable hash of the current branding settings.
	 *
	 * @return string
	 */
	private static function get_settings_hash() {
		return hash( 'sha256', wp_json_encode( self::get_settings() ) );
	}

	/**
	 * Writes a generated drop-in while refusing to replace unowned files.
	 *
	 * @param string $target   Target drop-in path.
	 * @param string $contents Generated PHP.
	 * @return bool
	 */
	private static function write_dropin( $target, $contents ) {
		if ( file_exists( $target ) ) {
			if ( ! self::is_ours( $target ) ) {
				return false;
			}

			$current = file_get_contents( $target );
			if ( is_string( $current ) && hash_equals( hash( 'sha256', $contents ), hash( 'sha256', $current ) ) ) {
				return true;
			}

			$temp = tempnam( WP_CONTENT_DIR, '.wa-suit-error-' );
			if ( false === $temp ) {
				return false;
			}
			$written = false !== file_put_contents( $temp, $contents, LOCK_EX );
			if ( ! $written || ! chmod( $temp, 0644 ) ) {
				unlink( $temp );
				return false;
			}
			$renamed = self::is_ours( $target ) && rename( $temp, $target );
			if ( file_exists( $temp ) ) {
				unlink( $temp );
			}
			return $renamed;
		}

		$temp = tempnam( WP_CONTENT_DIR, '.wa-suit-error-' );
		if ( false === $temp ) {
			return false;
		}
		$written = false !== file_put_contents( $temp, $contents, LOCK_EX );
		if ( ! $written || ! chmod( $temp, 0644 ) ) {
			unlink( $temp );
			return false;
		}
		$created = @link( $temp, $target );
		if ( ! $created ) {
			$destination = @fopen( $target, 'xb' );
			$source      = @fopen( $temp, 'rb' );
			if ( false !== $destination && false !== $source ) {
				$created = false !== stream_copy_to_stream( $source, $destination );
				fflush( $destination );
			}
			if ( false !== $source ) {
				fclose( $source );
			}
			if ( false !== $destination ) {
				fclose( $destination );
				chmod( $target, 0644 );
			}
			if ( ! $created && self::is_ours( $target ) ) {
				unlink( $target );
			}
		}
		if ( file_exists( $temp ) ) {
			unlink( $temp );
		}

		return $created;
	}

	/**
	 * Checks the ownership marker without loading a potentially large file.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	private static function is_ours( $path ) {
		if ( is_link( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return false;
		}

		$file = fopen( $path, 'rb' );
		if ( false === $file ) {
			return false;
		}
		$header = fread( $file, 512 );
		fclose( $file );

		return is_string( $header ) && false !== strpos( $header, self::SIGNATURE );
	}
}
