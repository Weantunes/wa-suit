<?php
/**
 * Administration menu and dashboard.
 *
 * @package WASuit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the plugin administration interface and module switching.
 */
class WA_Suit_Admin {

	/**
	 * Settings instance.
	 *
	 * @var WA_Suit_Settings
	 */
	private $settings;

	/**
	 * Module loader instance.
	 *
	 * @var WA_Suit_Module_Loader
	 */
	private $module_loader;

	/**
	 * Sets up the administration controller.
	 *
	 * @param WA_Suit_Settings      $settings      Global settings.
	 * @param WA_Suit_Module_Loader $module_loader Module loader.
	 */
	public function __construct( $settings, $module_loader ) {
		$this->settings      = $settings;
		$this->module_loader = $module_loader;
	}

	/**
	 * Registers administration hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_head', array( $this, 'custom_admin_menu_icon_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'show_duplicate_notice' ) );
		add_action( 'wp_ajax_wa_suit_toggle_module', array( $this, 'ajax_toggle_module' ) );
		add_action( 'admin_post_wa_suit_save_general_settings', array( $this, 'save_general_settings' ) );
		add_action( 'admin_post_wa_suit_save_elementor_form_settings', array( $this, 'save_elementor_form_settings' ) );
		add_action( 'admin_post_wa_suit_save_developer_settings', array( $this, 'save_developer_settings' ) );
		add_action( 'admin_post_wa_suit_clear_debug_log', array( $this, 'clear_debug_log' ) );
		add_action( 'admin_post_wa_suit_create_child_theme', array( $this, 'create_child_theme' ) );
		add_action( 'admin_action_wa_suit_duplicate_post', array( $this, 'duplicate_post' ) );
		add_filter( 'post_row_actions', array( $this, 'add_duplicate_post_action' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_duplicate_post_action' ), 10, 2 );
	}

/**
 * Personaliza o ícone do WA Suit no menu administrativo.
 *
 * @return void
 */
public function custom_admin_menu_icon_css() {
    ?>
<style>
#adminmenu .toplevel_page_wa-suit .wp-menu-image {
    display: flex;
    align-items: center;
    justify-content: center;
}

#adminmenu .toplevel_page_wa-suit .wp-menu-image img {
    width: 22px;
    height: auto;
    margin: -2px 0 0 0;
    padding: 0;
    display: inline-block;
}
</style>
<?php
}

	/**
	 * Adds the WA Web Design menu and its module and settings entries.
	 *
	 * @return void
	 */


public function add_menu() {
	add_menu_page(
		__( 'WA Suit', 'wa-suit' ),
		__( 'WA Suit', 'wa-suit' ),
		'manage_options',
		'wa-suit',
		array( $this, 'render_dashboard_menu_page' ),
		WA_SUIT_URL . 'assets/images/logo-wa-branco.svg',
		3
	);

	global $submenu;

	if ( isset( $submenu['wa-suit'][0] ) ) {
		$submenu['wa-suit'][0][0] = __( 'Painel', 'wa-suit' );
	}

	add_submenu_page(
		'wa-suit',
		__( 'Módulo', 'wa-suit' ),
		__( 'Módulo', 'wa-suit' ),
		'manage_options',
		'wa-suit-modules',
		array( $this, 'render_modules_menu_page' )
	);

	add_submenu_page(
		'wa-suit',
		__( 'Configuração', 'wa-suit' ),
		__( 'Configuração', 'wa-suit' ),
		'manage_options',
		'wa-suit-settings',
		array( $this, 'render_settings_menu_page' )
	);

	add_submenu_page(
		'wa-suit',
		__( 'Suporte', 'wa-suit' ),
		__( 'Suporte', 'wa-suit' ),
		'manage_options',
		'wa-suit-support',
		array( $this, 'render_support_menu_page' )
	);
}


/**
 * Renderiza a página Painel.
 *
 * @return void
 */
public function render_dashboard_menu_page() {
	$this->render_page();
}

/**
 * Renderiza a página Módulos.
 *
 * @return void
 */
public function render_modules_menu_page() {
	$this->render_page( 'modules' );
}

/**
 * Renderiza a página Configuração.
 *
 * @return void
 */
public function render_settings_menu_page() {
	$this->render_page( 'settings' );
}

/**
 * Renders the WA Suit support page.
 *
 * @return void
 */
public function render_support_menu_page() {
	$plugin_data = get_file_data( WA_SUIT_FILE, array( 'Version' => 'Version' ) );
	$plugin_version = isset( $plugin_data['Version'] ) && '' !== $plugin_data['Version']
		? $plugin_data['Version']
		: WA_SUIT_VERSION;
	?>
	<div class="wrap wa-suit-support">
		<h1><?php echo esc_html__( 'Suporte WA Suit', 'wa-suit' ); ?></h1>
		<p><?php echo esc_html__( 'Encontre ajuda para configurar os recursos do plugin ou relatar um problema.', 'wa-suit' ); ?></p>
		<div class="card">
			<h2><?php echo esc_html__( 'Atendimento', 'wa-suit' ); ?></h2>
			<p><?php echo esc_html__( 'Acesse o site oficial da WA Web Design para solicitar suporte. Ao relatar um problema, informe a versão do WA Suit, do WordPress e do PHP e descreva os passos para reproduzi-lo.', 'wa-suit' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( 'https://wawebdesign.com.br/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Acessar WA Web Design', 'wa-suit' ); ?></a></p>
		</div>
		<div class="card">
			<h2><?php echo esc_html__( 'Informações do ambiente', 'wa-suit' ); ?></h2>
			<p><?php echo esc_html( sprintf( __( 'WA Suit %1$s · WordPress %2$s · PHP %3$s', 'wa-suit' ), $plugin_version, get_bloginfo( 'version' ), PHP_VERSION ) ); ?></p>
		</div>
		<?php $this->render_plugin_footer(); ?>
	</div>
	<?php
}

/**
 * Renders shared support and copyright links on WA Suit pages.
 *
 * @return void
 */
private function render_plugin_footer() {
	$whatsapp_url = 'https://api.whatsapp.com/send?phone=5511999077418&text=' . rawurlencode( 'Olá! Gostaria de obter suporte para o plugin WA Suit.' );
	?>
	<footer class="wa-admin-footer wa-suit-plugin-footer">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wa-suit-support' ) ); ?>"><?php echo esc_html__( 'Ajuda', 'wa-suit' ); ?></a>
		<p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> <a href="<?php echo esc_url( 'https://wawebdesign.com.br/' ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'WA Web Design. Todos os direitos reservados.', 'wa-suit' ); ?></a></p>
		<a class="wa-suit-plugin-footer__contact" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Fale com um especialista', 'wa-suit' ); ?></a>
	</footer>
	<?php
}

	/**
	 * Enqueues assets only on plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		$allowed_pages = array( 'wa-suit', 'wa-suit-modules', 'wa-suit-settings', 'wa-suit-support' );
		$current_page  = isset( $_GET['page'] ) && is_string( $_GET['page'] )
			? sanitize_key( wp_unslash( $_GET['page'] ) )
			: '';

		if ( ! in_array( $current_page, $allowed_pages, true ) ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'wa-suit-admin',
			WA_SUIT_URL . 'assets/css/admin.css',
			array( 'dashicons' ),
			filemtime( WA_SUIT_PATH . 'assets/css/admin.css' )
		);

		wp_enqueue_script(
			'wa-suit-admin',
			WA_SUIT_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			filemtime( WA_SUIT_PATH . 'assets/js/admin.js' ),
			true
		);

		$current_tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] )
			? sanitize_key( wp_unslash( $_GET['tab'] ) )
			: 'modules';
		if ( 'dashboard' === $current_tab ) {
			$current_tab = 'modules';
		}

		wp_localize_script(
			'wa-suit-admin',
			'waSuitAdminData',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => WA_Suit_Security::create_nonce(),
				'adminUrl'   => admin_url( 'admin.php?page=wa-suit' ),
				'currentTab' => $current_tab,
				'strings'    => array(
					'copied'     => __( 'Copiado!', 'wa-suit' ),
					'copy'       => __( 'Copiar', 'wa-suit' ),
					'confirmDel' => __( 'Tem certeza que deseja excluir este link?', 'wa-suit' ),
					'saving'     => __( 'Salvando...', 'wa-suit' ),
					'save'       => __( 'Salvar Link', 'wa-suit' ),
					'active'     => __( 'Ativo', 'wa-suit' ),
					'inactive'   => __( 'Inativo', 'wa-suit' ),
				),
			)
		);
	}

	/**
	 * Handles AJAX module toggle.
	 *
	 * @return void
	 */
	public function ajax_toggle_module() {
		if ( ! WA_Suit_Security::can_manage_settings() ) {
			wp_send_json_error( array( 'message' => __( 'Permissão negada.', 'wa-suit' ) ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! WA_Suit_Security::verify_nonce( $nonce ) ) {
			wp_send_json_error( array( 'message' => __( 'Sessão expirada. Recarregue a página.', 'wa-suit' ) ) );
		}

		$module_id = isset( $_POST['module_id'] ) ? sanitize_key( $_POST['module_id'] ) : '';
		$state     = isset( $_POST['state'] ) && '1' === (string) $_POST['state'];

		$module = $this->module_loader->get_registry()->get_module( $module_id );
		if ( ! $module ) {
			wp_send_json_error( array( 'message' => __( 'Módulo não encontrado.', 'wa-suit' ) ) );
		}

		$was_active = $module->is_active();
		if ( $state ) {
			$this->settings->enable_module( $module_id );
		} else {
			$this->settings->disable_module( $module_id );
		}

		if ( 'hide-login' === $module_id && $was_active !== $state && is_callable( array( $module, 'refresh_rewrite_rules' ) ) ) {
			$module->refresh_rewrite_rules( $state );
		}

		$active_count = count( $this->module_loader->get_registry()->get_active() );

		wp_send_json_success(
			array(
				'module_id'    => $module_id,
				'state'        => $state,
				'active_count' => $active_count,
				'title'        => $module->get_title(),
				'tab_url'      => admin_url( 'admin.php?page=wa-suit&tab=' . rawurlencode( $module_id ) ),
				'message'      => $state
					? sprintf( __( '"%s" foi ativado.', 'wa-suit' ), $module->get_title() )
					: sprintf( __( '"%s" foi desativado.', 'wa-suit' ), $module->get_title() ),
			)
		);
	}

	/**
	 * Saves the general WA Suit options.
	 *
	 * @return void
	 */
	public function save_general_settings() {
		if ( ! WA_Suit_Security::can_manage_settings() ) {
			wp_die( esc_html__( 'Você não tem permissão para alterar estas configurações.', 'wa-suit' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'wa_suit_save_general_settings' );
		$input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			? wp_unslash( $_POST['settings'] )
			: array();
		$this->settings->save_general_settings( $input );

		$settings_section = isset( $_POST['settings_section'] ) && is_string( $_POST['settings_section'] )
			? sanitize_key( wp_unslash( $_POST['settings_section'] ) )
			: 'general';
		$settings_section = in_array( $settings_section, array( 'general', 'developer' ), true )
			? $settings_section
			: 'general';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'wa-suit',
					'tab'             => 'settings',
					'settings_section' => $settings_section,
					'settings-saved'  => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Saves Elementor Pro form settings.
	 *
	 * @return void
	 */
	public function save_elementor_form_settings() {
		if ( ! WA_Suit_Security::can_manage_settings() ) {
			wp_die( esc_html__( 'Você não tem permissão para alterar estas configurações.', 'wa-suit' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'wa_suit_save_elementor_form_settings' );

		$input = isset( $_POST['settings'] ) && is_array( $_POST['settings'] )
			? wp_unslash( $_POST['settings'] )
			: array();
		WA_Suit_Elementor_Form_Masks::save_settings( $input );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => 'wa-suit',
					'tab'              => 'settings',
					'settings_section' => 'elementor-pro',
					'settings-saved'   => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Adds a duplicate link to supported content rows.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    Current post.
	 * @return array
	 */
	public function add_duplicate_post_action( $actions, $post ) {
		$settings = $this->settings->get_general_settings();
		if ( empty( $settings['duplicate_enabled'] ) || ! in_array( $post->post_type, $settings['duplicate_post_types'], true ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$duplicate_token = str_replace( '-', '', wp_generate_uuid4() );
		$url = add_query_arg(
			array(
				'action'          => 'wa_suit_duplicate_post',
				'post'            => $post->ID,
				'duplicate_token' => $duplicate_token,
			),
			admin_url( 'admin.php' )
		);
		$url = wp_nonce_url( $url, 'wa_suit_duplicate_post_' . $post->ID . '_' . $duplicate_token );
		$actions['wa_suit_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicar', 'wa-suit' ) . '</a>';

		return $actions;
	}

	/**
	 * Creates a draft copy of a post, page, or configured public post type.
	 *
	 * @return void
	 */
	public function duplicate_post() {
		$post_id        = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$duplicate_token = isset( $_GET['duplicate_token'] ) && is_string( $_GET['duplicate_token'] )
			? sanitize_text_field( wp_unslash( $_GET['duplicate_token'] ) )
			: '';
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $duplicate_token ) ) {
			wp_die( esc_html__( 'O link de duplicação é inválido. Atualize a lista e tente novamente.', 'wa-suit' ), '', array( 'response' => 400 ) );
		}
		check_admin_referer( 'wa_suit_duplicate_post_' . $post_id . '_' . $duplicate_token );

		$source   = get_post( $post_id );
		$settings = $this->settings->get_general_settings();
		if ( ! $source || empty( $settings['duplicate_enabled'] ) || ! in_array( $source->post_type, $settings['duplicate_post_types'], true ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Este conteúdo não pode ser duplicado com suas permissões atuais.', 'wa-suit' ), '', array( 'response' => 403 ) );
		}

		$previous_copy = get_posts(
			array(
				'post_type'              => $source->post_type,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'meta_key'               => '_wa_suit_duplicate_request',
				'meta_value'             => $duplicate_token,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! empty( $previous_copy[0] ) && (int) get_post_meta( $previous_copy[0], '_wa_suit_duplicated_from', true ) === $post_id ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . absint( $previous_copy[0] ) . '&action=edit' ) );
			exit;
		}

		$duplicate_lock = 'wa_suit_duplicate_lock_' . hash( 'sha256', $duplicate_token );
		$lock_data      = array(
			'post_id'    => $post_id,
			'user_id'    => get_current_user_id(),
			'created_at' => time(),
		);
		if ( ! add_option( $duplicate_lock, $lock_data, '', false ) ) {
			$existing_lock = get_option( $duplicate_lock );
			if ( is_array( $existing_lock ) && isset( $existing_lock['created_at'] ) && time() - absint( $existing_lock['created_at'] ) > 600 ) {
				delete_option( $duplicate_lock );
			}

			if ( ! add_option( $duplicate_lock, $lock_data, '', false ) ) {
				$previous_copy = get_posts(
					array(
						'post_type'              => $source->post_type,
						'post_status'            => 'any',
						'posts_per_page'         => 1,
						'fields'                 => 'ids',
						'meta_key'               => '_wa_suit_duplicate_request',
						'meta_value'             => $duplicate_token,
						'suppress_filters'       => true,
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					)
				);
				if ( ! empty( $previous_copy[0] ) && (int) get_post_meta( $previous_copy[0], '_wa_suit_duplicated_from', true ) === $post_id ) {
					wp_safe_redirect( admin_url( 'post.php?post=' . absint( $previous_copy[0] ) . '&action=edit' ) );
					exit;
				}

				wp_die( esc_html__( 'Esta duplicação já está sendo processada. Aguarde alguns instantes e tente novamente.', 'wa-suit' ), '', array( 'response' => 409 ) );
			}
		}

		$fail_duplicate = function ( $message, $response = 500, $failed_duplicate_id = 0 ) use ( $duplicate_lock ) {
			if ( $failed_duplicate_id ) {
				wp_delete_post( $failed_duplicate_id, true );
			}
			delete_option( $duplicate_lock );
			wp_die( esc_html( $message ), '', array( 'response' => $response ) );
		};

		$existing_duplicates = get_posts(
			array(
				'post_type'              => $source->post_type,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'meta_key'               => '_wa_suit_duplicated_from',
				'meta_value'             => $post_id,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$copy_number = count( $existing_duplicates ) + 1;
		$copy_title  = sprintf( __( '%1$s (cópia %2$d)', 'wa-suit' ), $source->post_title, $copy_number );
		$copy_slug   = sanitize_title( $source->post_name . '-copia-' . $copy_number );
		$duplicate_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'      => $source->post_type,
					'post_status'    => 'draft',
					'post_title'     => $copy_title,
					'post_name'      => $copy_slug,
					'post_content'   => $source->post_content,
					'post_excerpt'   => $source->post_excerpt,
					'post_author'    => get_current_user_id(),
					'post_parent'    => $source->post_parent,
					'menu_order'     => $source->menu_order,
					'comment_status' => $source->comment_status,
					'ping_status'    => $source->ping_status,
				)
			),
			true
		);

		if ( is_wp_error( $duplicate_id ) || ! $duplicate_id ) {
			$error_message = is_wp_error( $duplicate_id ) ? $duplicate_id->get_error_message() : __( 'Não foi possível criar a cópia do conteúdo.', 'wa-suit' );
			$fail_duplicate( $error_message );
		}

		update_post_meta( $duplicate_id, '_wa_suit_duplicate_request', $duplicate_token );
		if ( $duplicate_token !== get_post_meta( $duplicate_id, '_wa_suit_duplicate_request', true ) ) {
			$fail_duplicate( __( 'Não foi possível proteger a solicitação de duplicação contra cliques repetidos.', 'wa-suit' ), 500, $duplicate_id );
		}

		foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
			$term_ids = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $term_ids ) ) {
				$fail_duplicate( $term_ids->get_error_message(), 500, $duplicate_id );
			}

			$assigned = wp_set_object_terms( $duplicate_id, $term_ids, $taxonomy );
			if ( is_wp_error( $assigned ) ) {
				$fail_duplicate( $assigned->get_error_message(), 500, $duplicate_id );
			}
		}

		$elementor_db = null;
		if (
			class_exists( '\Elementor\Plugin' )
			&& isset( \Elementor\Plugin::$instance->db )
			&& method_exists( \Elementor\Plugin::$instance->db, 'copy_elementor_meta' )
		) {
			$elementor_db = \Elementor\Plugin::$instance->db;
		}

		$meta = get_post_meta( $post_id );
		foreach ( $meta as $meta_key => $values ) {
			$is_elementor_meta = 0 === strpos( $meta_key, '_elementor' );
			if (
				in_array( $meta_key, array( '_edit_lock', '_edit_last', '_elementor_css', '_wa_suit_duplicated_by', '_wa_suit_duplicated_from', '_wa_suit_duplicate_number', '_wa_suit_duplicate_request' ), true )
				|| ( $elementor_db && ( $is_elementor_meta || in_array( $meta_key, array( '_thumbnail_id', '_wp_page_template' ), true ) ) )
			) {
				continue;
			}

			foreach ( $values as $value ) {
				if ( '_elementor_page_settings' === $meta_key && ! is_array( $value ) ) {
					$value = array();
				}

				if ( ! add_post_meta( $duplicate_id, $meta_key, wp_slash( $value ) ) ) {
					$fail_duplicate( __( 'Não foi possível copiar os metadados do conteúdo.', 'wa-suit' ), 500, $duplicate_id );
				}
			}
		}

		if ( $elementor_db ) {
			try {
				$elementor_db->copy_elementor_meta( $post_id, $duplicate_id );
			} catch ( \Throwable $error ) {
				error_log(
					sprintf(
						'WA Suit: Elementor metadata copy failed for duplicate post %d: %s',
						$duplicate_id,
						$error->getMessage()
					)
				);
				$fail_duplicate( __( 'Não foi possível copiar os dados do Elementor. Consulte o log de erros do servidor.', 'wa-suit' ), 500, $duplicate_id );
			}

			foreach ( array( '_elementor_data', '_elementor_page_settings', '_thumbnail_id', '_wp_page_template' ) as $meta_key ) {
				if ( ! isset( $meta[ $meta_key ][0] ) ) {
					continue;
				}

				$source_value = maybe_unserialize( $meta[ $meta_key ][0] );
				$copied_value = get_post_meta( $duplicate_id, $meta_key, true );
				if ( '_elementor_data' === $meta_key && is_string( $source_value ) && is_string( $copied_value ) ) {
					$source_json = json_decode( $source_value, true );
					$source_json_valid = JSON_ERROR_NONE === json_last_error() && is_array( $source_json );
					$copied_json = json_decode( $copied_value, true );
					$copied_json_valid = JSON_ERROR_NONE === json_last_error() && is_array( $copied_json );
					if ( $source_json_valid ) {
						$source_value = $source_json;
					}
					if ( $copied_json_valid ) {
						$copied_value = $copied_json;
					}
				}

				if ( ! metadata_exists( 'post', $duplicate_id, $meta_key ) || ! $this->duplicate_meta_values_match( $source_value, $copied_value ) ) {
					error_log(
						sprintf(
							'WA Suit: Elementor metadata verification failed for duplicate post %d (meta key %s).',
							$duplicate_id,
							$meta_key
						)
					);
					$fail_duplicate( __( 'Os dados do Elementor ou as imagens não foram copiados corretamente. A cópia incompleta foi removida; consulte o log de erros do servidor.', 'wa-suit' ), 500, $duplicate_id );
				}
			}
		}

		update_post_meta( $duplicate_id, '_wa_suit_duplicated_by', get_current_user_id() );
		update_post_meta( $duplicate_id, '_wa_suit_duplicated_from', $post_id );
		update_post_meta( $duplicate_id, '_wa_suit_duplicate_number', $copy_number );
		if (
			(int) get_post_meta( $duplicate_id, '_wa_suit_duplicated_by', true ) !== get_current_user_id()
			|| (int) get_post_meta( $duplicate_id, '_wa_suit_duplicated_from', true ) !== $post_id
			|| (int) get_post_meta( $duplicate_id, '_wa_suit_duplicate_number', true ) !== $copy_number
		) {
			$fail_duplicate( __( 'Não foi possível registrar quem criou a cópia.', 'wa-suit' ), 500, $duplicate_id );
		}

		$elementor_css_error = false;
		if (
			metadata_exists( 'post', $duplicate_id, '_elementor_data' )
			&& class_exists( '\Elementor\Core\Files\CSS\Post' )
			&& method_exists( '\Elementor\Core\Files\CSS\Post', 'update' )
		) {
			try {
				$elementor_css = new \Elementor\Core\Files\CSS\Post( $duplicate_id );
				$elementor_css->update();
			} catch ( \Throwable $error ) {
				error_log(
					sprintf(
						'WA Suit: Elementor CSS regeneration failed for duplicate post %d: %s',
						$duplicate_id,
						$error->getMessage()
					)
				);
				$elementor_css_error = true;
			}
		}

		$redirect_url = admin_url( 'post.php?post=' . absint( $duplicate_id ) . '&action=edit' );
		if ( $elementor_css_error ) {
			$redirect_url = add_query_arg( 'wa_suit_elementor_css_error', '1', $redirect_url );
		}

		delete_option( $duplicate_lock );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Compares post meta values while ignoring associative-array key order.
	 *
	 * @param mixed $source_value Original value.
	 * @param mixed $copied_value Copied value.
	 * @return bool
	 */
	private function duplicate_meta_values_match( $source_value, $copied_value ) {
		if ( is_array( $source_value ) && is_array( $copied_value ) ) {
			if ( array_keys( $source_value ) !== array_keys( $copied_value ) ) {
				ksort( $source_value );
				ksort( $copied_value );
			}

			if ( array_keys( $source_value ) !== array_keys( $copied_value ) ) {
				return false;
			}

			foreach ( $source_value as $key => $value ) {
				if ( ! $this->duplicate_meta_values_match( $value, $copied_value[ $key ] ) ) {
					return false;
				}
			}

			return true;
		}

		if ( is_numeric( $source_value ) && is_numeric( $copied_value ) ) {
			return (string) $source_value === (string) $copied_value;
		}

		return $source_value === $copied_value;
	}

	/**
	 * Displays a warning when Elementor CSS could not be regenerated for a copy.
	 *
	 * @return void
	 */
	public function show_duplicate_notice() {
		$post_id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$has_error = isset( $_GET['wa_suit_elementor_css_error'] )
			&& is_string( $_GET['wa_suit_elementor_css_error'] )
			&& '1' === wp_unslash( $_GET['wa_suit_elementor_css_error'] );

		if ( ! $has_error || ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php echo esc_html__( 'A cópia foi criada, mas o Elementor não conseguiu regenerar o CSS. Abra a página no Elementor e salve para atualizar os estilos. Consulte o log de erros do servidor para ver o detalhe técnico.', 'wa-suit' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders main administrative interface.
	 *
	 * @return void
	 */
		public function render_page( $forced_tab = null ) {
			if ( ! WA_Suit_Security::can_manage_settings() ) {
				wp_die(
					esc_html__( 'Você não tem permissão para acessar esta página.', 'wa-suit' )
				);
			}

			$registry    = $this->module_loader->get_registry();
			$all_modules = $registry->get_all();

			if ( null !== $forced_tab ) {
				$current_tab = sanitize_key( $forced_tab );
			} else {
				$current_tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] )
					? sanitize_key( wp_unslash( $_GET['tab'] ) )
					: 'modules';
			}

			if ( 'dashboard' === $current_tab ) {
				$current_tab = 'modules';
			}

			$module = null;
			if ( 'settings' !== $current_tab && 'modules' !== $current_tab ) {
				$module = $registry->get_module( $current_tab );
			}
			$is_module_list = isset( $_GET['page'] ) && is_string( $_GET['page'] ) && 'wa-suit-modules' === sanitize_key( wp_unslash( $_GET['page'] ) );
			?>
<div class="wrap wa-admin-wrap wa-suit-admin-page">
    <div class="wa-admin-header">
        <div class="wa-admin-header__title">
            <img class="wa-admin-header__logo"
                src="<?php echo esc_url( WA_SUIT_URL . 'assets/images/logo-wa-preto.svg' ); ?>" alt="WA WEB DESIGN">
        </div>
        <nav class="wa-admin-tabs" aria-label="<?php esc_attr_e( 'Navegação do plugin', 'wa-suit' ); ?>">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wa-suit' ) ); ?>"
                <?php echo ( 'modules' === $current_tab && ! $is_module_list ) ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html__( 'Painel', 'wa-suit' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wa-suit-modules' ) ); ?>"
                <?php echo ( null !== $module || $is_module_list ) ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html__( 'Módulo', 'wa-suit' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wa-suit-settings' ) ); ?>"
                <?php echo 'settings' === $current_tab ? 'aria-current="page"' : ''; ?>>
                <?php echo esc_html__( 'Configuração', 'wa-suit' ); ?>
            </a>
        </nav>
    </div>
    <?php
				if ( $module ) {
					$this->render_module_tab( $module );
				} elseif ( 'settings' === $current_tab ) {
					$this->render_general_settings_tab();
				} else {
					$this->render_dashboard_tab( $all_modules );
				}
				?>
    <?php $this->render_plugin_footer(); ?>
</div>
<?php
		}

		/**
		 * Renders the modules dashboard.
		 *
		 * @param array $all_modules List of registered modules.
		 * @return void
		 */
		private function render_dashboard_tab( $all_modules ) {
			?>
<div class="wa-admin-dashboard-wrap">
    <div class="wa-admin-modules-grid">
        <?php foreach ( $all_modules as $mod ) :
						$is_active = $mod->is_active();
						$mod_id    = $mod->get_id();
						?>
        <div class="wa-admin-box wa-admin-module-box <?php echo $is_active ? 'is-active' : 'is-inactive'; ?>"
            data-module-card="<?php echo esc_attr( $mod_id ); ?>">
            <header class="wa-admin-box-header">
                <div class="wa-admin-box-title-wrap">
                    <span class="dashicons <?php echo esc_attr( $mod->get_icon() ); ?> wa-admin-module-icon"></span>
                    <h3 class="wa-admin-box-title"><?php echo esc_html( $mod->get_title() ); ?></h3>
                </div>
                <span class="wa-admin-badge-tag"><?php echo esc_html( $mod->get_category() ); ?></span>
            </header>

            <div class="wa-admin-box-content">
                <p><?php echo esc_html( $mod->get_description() ); ?></p>
            </div>

            <footer class="wa-admin-box-footer">
                <div class="wa-admin-box-status">
                    <?php if ( $mod->has_settings_tab() ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=wa-suit&tab=' . rawurlencode( $mod_id ) ) ); ?>"
                        class="wa-admin-settings-link" data-action-area="<?php echo esc_attr( $mod_id ); ?>"
                        style="<?php echo $is_active ? '' : 'display: none;'; ?>">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php echo esc_html__( 'Configurações', 'wa-suit' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                    <?php endif; ?>
                </div>

                <div class="wa-admin-box-actions">
                    <label class="wa-admin-switch"
                        title="<?php esc_attr_e( 'Ativar ou desativar módulo', 'wa-suit' ); ?>">
                        <input type="checkbox" class="wa-admin-module-toggle"
                            data-module-id="<?php echo esc_attr( $mod_id ); ?>" <?php checked( $is_active ); ?>>
                        <span class="wa-admin-slider"></span>
                    </label>
                </div>
            </footer>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
		}

		/**
		 * Renders a module settings view.
		 *
		 * @param WA_Suit_Module_Base $module Module object.
		 * @return void
		 */
		private function render_module_tab( $module ) {
			$is_active = $module->is_active();
			?>
<div class="wa-admin-module-view">
    <div class="wa-admin-module-view-header">
        <div class="wa-admin-module-view-nav">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wa-suit&tab=modules' ) ); ?>"
                class="wa-admin-back-link">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php echo esc_html__( 'Módulos', 'wa-suit' ); ?>
            </a>
            <span class="wa-admin-sep">/</span>
            <span class="wa-admin-current"><?php echo esc_html( $module->get_title() ); ?></span>
        </div>

        <div class="wa-admin-module-view-actions">
            <label class="wa-admin-switch">
                <input type="checkbox" class="wa-admin-module-toggle"
                    data-module-id="<?php echo esc_attr( $module->get_id() ); ?>" <?php checked( $is_active ); ?>>
                <span class="wa-admin-slider"></span>
            </label>
            <span class="wa-admin-toggle-status" data-status-label="<?php echo esc_attr( $module->get_id() ); ?>">
                <?php echo $is_active ? esc_html__( 'Ativo', 'wa-suit' ) : esc_html__( 'Inativo', 'wa-suit' ); ?>
            </span>
        </div>
    </div>

    <div class="wa-admin-module-view-body">
        <?php $module->render_tab(); ?>
    </div>
</div>
<?php
		}

		/**
		 * Renders plugin-wide settings.
		 *
		 * @return void
		 */
		private function render_general_settings_tab() {
			$settings_section = isset( $_GET['settings_section'] ) && is_string( $_GET['settings_section'] )
				? sanitize_key( wp_unslash( $_GET['settings_section'] ) )
				: 'general';
			if ( ! in_array( $settings_section, array( 'general', 'developer', 'elementor-pro' ), true ) ) {
				$settings_section = 'general';
			}
			?>
<div class="wa-general-settings">
    <header class="wa-general-settings__header">
        <div>
            <p class="wa-general-settings__eyebrow"><?php echo esc_html__( 'PREFERÊNCIAS DO PLUGIN', 'wa-suit' ); ?></p>
            <h2><?php echo esc_html__( 'Configuração', 'wa-suit' ); ?></h2>
            <p><?php echo esc_html__( 'Gerencie ferramentas e avisos do WA Suit em um só lugar.', 'wa-suit' ); ?></p>
        </div>
    </header>

	<nav class="wa-config-tabs" aria-label="<?php esc_attr_e( 'Abas de configuração', 'wa-suit' ); ?>">
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wa-suit', 'tab' => 'settings', 'settings_section' => 'general' ), admin_url( 'admin.php' ) ) ); ?>" <?php echo 'general' === $settings_section ? 'aria-current="page"' : ''; ?>>
			<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
			<?php echo esc_html__( 'Geral', 'wa-suit' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wa-suit', 'tab' => 'settings', 'settings_section' => 'developer' ), admin_url( 'admin.php' ) ) ); ?>" <?php echo 'developer' === $settings_section ? 'aria-current="page"' : ''; ?>>
			<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
			<?php echo esc_html__( 'Desenvolvedor', 'wa-suit' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wa-suit', 'tab' => 'settings', 'settings_section' => 'elementor-pro' ), admin_url( 'admin.php' ) ) ); ?>" <?php echo 'elementor-pro' === $settings_section ? 'aria-current="page"' : ''; ?>>
			<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span>
			<?php echo esc_html__( 'Elementor Pro', 'wa-suit' ); ?>
		</a>
	</nav>

	<?php if ( isset( $_GET['settings-saved'] ) && is_string( $_GET['settings-saved'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['settings-saved'] ) ) ) : ?>
		<div class="notice notice-success inline"><p><?php echo esc_html__( 'Configurações salvas.', 'wa-suit' ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'developer' === $settings_section ) : ?>
		<?php $this->render_developer_settings_tab(); ?>
	</div>
	<?php return; ?>
	<?php endif; ?>

	<?php if ( 'elementor-pro' === $settings_section ) : ?>
		<?php $this->render_elementor_pro_settings_tab(); ?>
	</div>
	<?php return; ?>
	<?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
        class="wa-general-settings__form">
        <input type="hidden" name="action" value="wa_suit_save_general_settings">
		<input type="hidden" name="settings_section" value="general">
        <?php wp_nonce_field( 'wa_suit_save_general_settings' ); ?>

        <?php $this->settings->render_general_settings(); ?>

        <div class="wa-general-settings__actions">
            <button type="submit"
                class="wa-admin-btn wa-admin-btn--primary"><?php echo esc_html__( 'Salvar configurações', 'wa-suit' ); ?></button>
        </div>
		<br>
    </form>
	 <?php $this->render_child_theme_settings(); ?>
</div>
<?php
		}

		/**
		 * Renders Elementor Pro form mask settings.
		 *
		 * @return void
		 */
		private function render_elementor_pro_settings_tab() {
			$settings = WA_Suit_Elementor_Form_Masks::get_settings();
			$countries = WA_Suit_Elementor_Form_Masks::get_phone_countries();
			?>
			<section class="wa-general-settings__card">
				<div class="wa-general-settings__card-heading">
					<span class="dashicons dashicons-feedback" aria-hidden="true"></span>
					<div>
						<h3><?php echo esc_html__( 'Máscaras de campos dos formulários', 'wa-suit' ); ?></h3>
						<p><?php echo esc_html__( 'Adicione máscaras personalizadas e, opcionalmente, um seletor de país com formatação internacional para telefones.', 'wa-suit' ); ?></p>
					</div>
				</div>

				<?php if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) : ?>
					<div class="notice notice-info inline">
						<p><?php echo esc_html__( 'O Elementor Pro precisa estar ativo para exibir as opções de máscara nos campos do widget Formulário.', 'wa-suit' ); ?></p>
					</div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wa-general-settings__form">
					<input type="hidden" name="action" value="wa_suit_save_elementor_form_settings">
					<?php wp_nonce_field( 'wa_suit_save_elementor_form_settings' ); ?>

					<label class="wa-general-settings__toggle">
						<input type="checkbox" name="settings[phone_country_selector]" value="1" <?php checked( $settings['phone_country_selector'] ); ?>>
						<span>
							<strong><?php echo esc_html__( 'Ativar telefone internacional e seleção de país', 'wa-suit' ); ?></strong>
							<small><?php echo esc_html__( 'Libera a opção Telefone internacional no campo do formulário. Em Sem máscara, o campo fica sem seletor de país e sem formatação.', 'wa-suit' ); ?></small>
						</span>
					</label>

					<div class="wa-general-settings__form-row">
						<label for="wa-suit-phone-default-country"><strong><?php echo esc_html__( 'País inicial do telefone', 'wa-suit' ); ?></strong></label>
						<select id="wa-suit-phone-default-country" name="settings[phone_default_country]" data-wa-phone-default-country>
							<?php foreach ( $countries as $country_code ) : ?>
								<?php
								$country_name = class_exists( '\Locale' )
									? \Locale::getDisplayRegion( 'und_' . strtoupper( $country_code ), 'pt_BR' )
									: '';
								$country_name = $country_name ? $country_name : strtoupper( $country_code );
								?>
								<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( $settings['phone_default_country'], $country_code ); ?>>
									<?php echo esc_html( $country_name . ' (' . strtoupper( $country_code ) . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="wa-general-settings__form-row">
						<label for="wa-suit-phone-country-mode"><strong><?php echo esc_html__( 'Países disponíveis no seletor', 'wa-suit' ); ?></strong></label>
						<select id="wa-suit-phone-country-mode" name="settings[phone_country_mode]">
							<option value="exclude" <?php selected( $settings['phone_country_mode'], 'exclude' ); ?>>
								<?php echo esc_html__( 'Todos, exceto os países selecionados', 'wa-suit' ); ?>
							</option>
							<option value="only" <?php selected( $settings['phone_country_mode'], 'only' ); ?>>
								<?php echo esc_html__( 'Somente os países selecionados', 'wa-suit' ); ?>
							</option>
						</select>
					</div>

					<div class="wa-general-settings__form-row">
						<label for="wa-suit-phone-countries-search"><strong><?php echo esc_html__( 'Pesquisar países', 'wa-suit' ); ?></strong></label>
						<input id="wa-suit-phone-countries-search" type="search" placeholder="<?php echo esc_attr__( 'Digite o nome de um país', 'wa-suit' ); ?>" data-wa-phone-countries-search>
						<select id="wa-suit-phone-countries" name="settings[phone_countries][]" multiple size="8" data-wa-phone-countries>
							<?php foreach ( $countries as $country_code ) : ?>
								<?php
								$country_name = class_exists( '\Locale' )
									? \Locale::getDisplayRegion( 'und_' . strtoupper( $country_code ), 'pt_BR' )
									: '';
								$country_name = $country_name ? $country_name : strtoupper( $country_code );
								?>
								<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( in_array( $country_code, $settings['phone_countries'], true ), true ); ?>>
									<?php echo esc_html( $country_name . ' (' . strtoupper( $country_code ) . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<small><?php echo esc_html__( 'Se o modo for "Somente os países selecionados", deixe a lista vazia para permitir todos.', 'wa-suit' ); ?></small>
					</div>

					<div class="wa-general-settings__card-heading">
						<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html__( 'Widgets', 'wa-suit' ); ?></h3>
							<p><?php echo esc_html__( 'Ative ou desative os widgets do WA Suit disponíveis no Elementor.', 'wa-suit' ); ?></p>
						</div>
					</div>

					<label class="wa-general-settings__toggle">
						<input type="hidden" name="settings[widgets][popup]" value="0">
						<input type="checkbox" name="settings[widgets][popup]" value="1" <?php checked( WA_Suit_Elementor_Form_Masks::is_widget_enabled( 'popup' ) ); ?>>
						<span>
							<strong><?php echo esc_html__( 'Widget Popup', 'wa-suit' ); ?></strong>
							<small><?php echo esc_html__( 'Exibe ou oculta o widget Popup do WA Suit na lista de widgets do Elementor. Ativado por padrão.', 'wa-suit' ); ?></small>
						</span>
					</label>

					<div class="wa-general-settings__actions">
						<button type="submit" class="wa-admin-btn wa-admin-btn--primary"><?php echo esc_html__( 'Salvar configurações', 'wa-suit' ); ?></button>
					</div>
				</form>
			</section>
			<script>
				( function () {
					var countrySelect = document.querySelector( '[data-wa-phone-countries]' );
					var defaultSelect = document.querySelector( '[data-wa-phone-default-country]' );
					var searchInput = document.querySelector( '[data-wa-phone-countries-search]' );
					var displayNames = typeof Intl !== 'undefined' && Intl.DisplayNames
						? new Intl.DisplayNames( [ document.documentElement.lang || 'pt-BR' ], { type: 'region' } )
						: null;

					[ countrySelect, defaultSelect ].forEach( function ( select ) {
						if ( ! select ) {
							return;
						}

						Array.prototype.forEach.call( select.options, function ( option ) {
							var code = option.value.toUpperCase();
							var name = displayNames ? displayNames.of( code ) : code;
							name = name || code;
							option.textContent = name + ' (' + code + ')';
							option.dataset.countryName = ( name + ' ' + code ).toLocaleLowerCase();
						} );
					} );

					if ( searchInput && countrySelect ) {
						searchInput.addEventListener( 'input', function () {
							var query = searchInput.value.trim().toLocaleLowerCase();
							Array.prototype.forEach.call( countrySelect.options, function ( option ) {
								option.hidden = query && option.dataset.countryName.indexOf( query ) === -1;
							} );
						} );
					}
				} )();
			</script>
			<?php
		}

		/**
		 * Renders developer diagnostics and the private fatal-error log.
		 *
		 * @return void
		 */
		private function render_developer_settings_tab() {
			$settings = get_option( WA_Suit_Debug::OPTION_NAME, array() );
			$enabled  = is_array( $settings ) && ! empty( $settings['debug_enabled'] );
			$log       = WA_Suit_Debug::get_log_contents();
			$log_error = false === $log;
			$wp_debug  = defined( 'WP_DEBUG' ) && WP_DEBUG;
			$wp_log    = defined( 'WP_DEBUG_LOG' ) && ( true === WP_DEBUG_LOG || ( is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) );
			$theme     = wp_get_theme();
			$result    = isset( $_GET['developer-result'] ) && is_string( $_GET['developer-result'] )
				? sanitize_key( wp_unslash( $_GET['developer-result'] ) )
				: '';
			?>
			<div class="wa-developer-settings">
				<header class="wa-developer-settings__intro">
					<span class="dashicons dashicons-shield" aria-hidden="true"></span>
					<div>
						<h3><?php echo esc_html__( 'Ferramentas do desenvolvedor', 'wa-suit' ); ?></h3>
						<p><?php echo esc_html__( 'Diagnostique erros fatais sem exibir detalhes técnicos para os visitantes.', 'wa-suit' ); ?></p>
					</div>
				</header>

				<?php if ( 'saved' === $result ) : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html__( 'Configuração de depuração salva.', 'wa-suit' ); ?></p></div>
				<?php elseif ( 'cleared' === $result ) : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html__( 'Log apagado.', 'wa-suit' ); ?></p></div>
				<?php elseif ( 'log-error' === $result ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html__( 'Não foi possível apagar o log. Verifique as permissões de escrita no diretório privado de logs.', 'wa-suit' ); ?></p></div>
				<?php endif; ?>

				<section class="wa-general-settings__card">
					<div class="wa-general-settings__card-heading">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html__( 'Depuração de erros fatais', 'wa-suit' ); ?></h3>
							<p><?php echo esc_html__( 'Quando ligada, registra erros fatais de PHP em um arquivo privado do WA Suit.', 'wa-suit' ); ?></p>
						</div>
					</div>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wa_suit_save_developer_settings">
						<?php wp_nonce_field( 'wa_suit_save_developer_settings' ); ?>
						<label class="wa-general-settings__toggle">
							<input type="checkbox" name="debug_enabled" value="1" <?php checked( $enabled ); ?>>
							<span>
								<strong><?php echo esc_html__( 'Ativar log de depuração do WA Suit', 'wa-suit' ); ?></strong>
								<small><?php echo esc_html__( 'Registra erros fatais de PHP no próximo carregamento de página. Os detalhes não são mostrados no site público.', 'wa-suit' ); ?></small>
							</span>
						</label>
						<p class="description"><?php echo esc_html__( 'Esta opção não altera WP_DEBUG nem o arquivo wp-config.php. O modo de depuração nativo do WordPress é configurado separadamente no servidor.', 'wa-suit' ); ?></p>
						<p><button type="submit" class="wa-admin-btn wa-admin-btn--primary"><?php echo esc_html__( 'Salvar depuração', 'wa-suit' ); ?></button></p>
					</form>
				</section>

				<section class="wa-general-settings__card">
					<div class="wa-general-settings__card-heading">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html__( 'Estado do ambiente', 'wa-suit' ); ?></h3>
							<p><?php echo esc_html__( 'Informações úteis para suporte técnico.', 'wa-suit' ); ?></p>
						</div>
					</div>
					<dl class="wa-developer-settings__status">
						<div><dt><?php echo esc_html__( 'WordPress', 'wa-suit' ); ?></dt><dd><?php echo esc_html( get_bloginfo( 'version' ) ); ?></dd></div>
						<div><dt><?php echo esc_html__( 'PHP', 'wa-suit' ); ?></dt><dd><?php echo esc_html( PHP_VERSION ); ?></dd></div>
						<div><dt><?php echo esc_html__( 'Tema ativo', 'wa-suit' ); ?></dt><dd><?php echo esc_html( $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) ); ?></dd></div>
						<div><dt>WP_DEBUG</dt><dd><?php echo $wp_debug ? esc_html__( 'Ativado', 'wa-suit' ) : esc_html__( 'Desativado', 'wa-suit' ); ?></dd></div>
						<div><dt>WP_DEBUG_LOG</dt><dd><?php echo $wp_log ? esc_html__( 'Ativado', 'wa-suit' ) : esc_html__( 'Desativado', 'wa-suit' ); ?></dd></div>
					</dl>
				</section>

				<section class="wa-general-settings__card">
					<div class="wa-general-settings__card-heading">
						<span class="dashicons dashicons-text-page" aria-hidden="true"></span>
						<div>
							<h3><?php echo esc_html__( 'Log do WA Suit', 'wa-suit' ); ?></h3>
							<p><?php echo esc_html__( 'Exibe os erros fatais capturados enquanto a depuração estiver ligada. O arquivo é mantido fora da raiz pública sempre que o servidor permite.', 'wa-suit' ); ?></p>
						</div>
					</div>

					<?php if ( $log_error ) : ?>
						<div class="notice notice-error inline"><p><?php echo esc_html__( 'O log não pode ser lido ou o diretório privado não está disponível. Verifique as permissões de arquivo e diretório.', 'wa-suit' ); ?></p></div>
					<?php elseif ( '' === $log ) : ?>
						<p class="description"><?php echo esc_html__( 'Nenhum erro fatal foi registrado.', 'wa-suit' ); ?></p>
					<?php else : ?>
						<pre class="wa-developer-settings__log"><?php echo esc_html( $log ); ?></pre>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wa_suit_clear_debug_log">
						<?php wp_nonce_field( 'wa_suit_clear_debug_log' ); ?>
						<button type="submit" class="wa-admin-btn"><?php echo esc_html__( 'Limpar log', 'wa-suit' ); ?></button>
					</form>
				</section>
			</div>
			<?php
		}

		/**
		 * Saves the WA Suit fatal-error logging preference.
		 *
		 * @return void
		 */
		public function save_developer_settings() {
			if ( ! WA_Suit_Security::can_manage_settings() ) {
				wp_die( esc_html__( 'Você não tem permissão para alterar estas configurações.', 'wa-suit' ), '', array( 'response' => 403 ) );
			}

			check_admin_referer( 'wa_suit_save_developer_settings' );
			$enabled = isset( $_POST['debug_enabled'] ) && is_scalar( $_POST['debug_enabled'] ) && '1' === (string) wp_unslash( $_POST['debug_enabled'] );
			update_option(
				WA_Suit_Debug::OPTION_NAME,
				array( 'debug_enabled' => $enabled )
			);

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'             => 'wa-suit',
						'tab'              => 'settings',
						'settings_section' => 'developer',
						'developer-result' => 'saved',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		/**
		 * Clears the private WA Suit fatal-error log.
		 *
		 * @return void
		 */
		public function clear_debug_log() {
			if ( ! WA_Suit_Security::can_manage_settings() ) {
				wp_die( esc_html__( 'Você não tem permissão para limpar este log.', 'wa-suit' ), '', array( 'response' => 403 ) );
			}

			check_admin_referer( 'wa_suit_clear_debug_log' );
			$cleared = WA_Suit_Debug::clear_log();

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'             => 'wa-suit',
						'tab'              => 'settings',
						'settings_section' => 'developer',
						'developer-result' => $cleared ? 'cleared' : 'log-error',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		/**
		 * Renders the child theme creation settings card.
		 *
		 * @return void
		 */
		private function render_child_theme_settings() {
			$current_theme   = wp_get_theme();
			$available_themes = wp_get_themes();
			$parent_themes   = array_filter(
				$available_themes,
				function ( $theme ) {
					return ! $theme->errors() && $theme->get_stylesheet() === $theme->get_template();
				}
			);
			$current_parent  = $current_theme->get_template();
			$default_name    = isset( $parent_themes[ $current_parent ] )
				? $parent_themes[ $current_parent ]->get( 'Name' ) . ' Child'
				: $current_theme->get( 'Name' ) . ' Child';
			$result          = isset( $_GET['child-theme-result'] ) && is_string( $_GET['child-theme-result'] )
				? sanitize_key( wp_unslash( $_GET['child-theme-result'] ) )
				: '';
			?>
			<section class="wa-general-settings__card">
				<div class="wa-general-settings__card-heading">
					<span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
					<div>
						<h3><?php echo esc_html__( 'Criar tema filho', 'wa-suit' ); ?></h3>
						<p><?php echo esc_html__( 'Escolha um tema instalado para criar um tema filho sem editar os arquivos originais.', 'wa-suit' ); ?></p>
					</div>
				</div>
				<p class="description"><?php echo esc_html__( 'A ativação do tema filho pode alterar a aparência do site. Faça um backup antes de prosseguir.', 'wa-suit' ); ?></p>

				<?php if ( 'created' === $result ) : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html__( 'Tema filho criado com sucesso.', 'wa-suit' ); ?></p></div>
				<?php elseif ( 'created-activated' === $result ) : ?>
					<div class="notice notice-success inline"><p><?php echo esc_html__( 'Tema filho criado e ativado com sucesso.', 'wa-suit' ); ?></p></div>
				<?php elseif ( '' !== $result ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( $this->get_child_theme_error_message( $result ) ); ?></p></div>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wa_suit_create_child_theme">
					<?php wp_nonce_field( 'wa_suit_create_child_theme' ); ?>
					<p>
						<label for="wa-suit-child-theme-parent">
							<strong><?php echo esc_html__( 'Tema que será o pai', 'wa-suit' ); ?></strong>
						</label><br>
						<select id="wa-suit-child-theme-parent" name="parent_theme" class="regular-text" required>
							<option value=""><?php echo esc_html__( 'Selecione um tema instalado', 'wa-suit' ); ?></option>
							<?php foreach ( $parent_themes as $theme_slug => $theme ) : ?>
								<option value="<?php echo esc_attr( $theme_slug ); ?>" <?php selected( $current_parent, $theme_slug ); ?>>
									<?php echo esc_html( $theme->get( 'Name' ) ); ?>
									<?php if ( $current_parent === $theme_slug ) : ?>
										— <?php echo esc_html__( 'base do tema ativo', 'wa-suit' ); ?>
									<?php endif; ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>
					<p>
						<label for="wa-suit-child-theme-name">
							<strong><?php echo esc_html__( 'Nome do tema filho', 'wa-suit' ); ?></strong>
						</label><br>
						<input
							type="text"
							id="wa-suit-child-theme-name"
							name="child_theme_name"
							class="regular-text"
							value="<?php echo esc_attr( $default_name ); ?>"
							required
						>
					</p>
					<?php if ( current_user_can( 'switch_themes' ) ) : ?>
						<label class="wa-general-settings__toggle">
							<input type="checkbox" name="activate_child_theme" value="1" checked>
							<span>
								<strong><?php echo esc_html__( 'Criar e ativar o tema filho', 'wa-suit' ); ?></strong>
								<small><?php echo esc_html__( 'Marcado por padrão. Desmarque para apenas criar e deixar o tema atual ativo.', 'wa-suit' ); ?></small>
							</span>
						</label>
					<?php else : ?>
						<p class="description"><?php echo esc_html__( 'Sua conta pode criar o tema, mas não tem permissão para ativá-lo.', 'wa-suit' ); ?></p>
					<?php endif; ?>
					<?php if ( current_user_can( 'install_themes' ) ) : ?>
						<br>
						<button type="submit" class="wa-admin-btn wa-admin-btn--primary">
							<?php echo esc_html__( 'Criar tema filho', 'wa-suit' ); ?>
						</button>
					<?php endif; ?>
				</form>
			</section>
			<?php
		}

		/**
		 * Creates a child theme for an installed parent theme.
		 *
		 * @return void
		 */
		public function create_child_theme() {
			if ( ! WA_Suit_Security::can_manage_settings() || ! current_user_can( 'install_themes' ) ) {
				wp_die( esc_html__( 'Você não tem permissão para criar temas.', 'wa-suit' ), '', array( 'response' => 403 ) );
			}

			check_admin_referer( 'wa_suit_create_child_theme' );

			$theme_name = isset( $_POST['child_theme_name'] )
				? sanitize_text_field( wp_unslash( $_POST['child_theme_name'] ) )
				: '';
			$theme_slug = sanitize_title( $theme_name );
			$parent_slug = isset( $_POST['parent_theme'] ) && is_string( $_POST['parent_theme'] )
				? sanitize_text_field( wp_unslash( $_POST['parent_theme'] ) )
				: '';
			$activate_theme = ! empty( $_POST['activate_child_theme'] );

			if ( '' === $theme_name || '' === $theme_slug || ! preg_match( '/^[a-z0-9-]+$/', $theme_slug ) ) {
				$this->redirect_child_theme_result( 'invalid-name' );
			}

			if ( $activate_theme && ! current_user_can( 'switch_themes' ) ) {
				wp_die( esc_html__( 'Você não tem permissão para ativar temas.', 'wa-suit' ), '', array( 'response' => 403 ) );
			}

			$installed_themes = wp_get_themes();
			$parent_theme     = isset( $installed_themes[ $parent_slug ] ) ? $installed_themes[ $parent_slug ] : null;
			if ( ! $parent_theme || $parent_theme->errors() || $parent_theme->get_stylesheet() !== $parent_theme->get_template() ) {
				$this->redirect_child_theme_result( 'invalid-parent' );
			}

			$theme_root = get_theme_root( $parent_slug );
			if ( ! is_string( $theme_root ) || ! is_dir( $theme_root ) || ! is_writable( $theme_root ) ) {
				$this->redirect_child_theme_result( 'not-writable' );
			}

			$theme_directory = trailingslashit( $theme_root ) . $theme_slug;
			if ( file_exists( $theme_directory ) ) {
				$this->redirect_child_theme_result( 'already-exists' );
			}

			if ( ! wp_mkdir_p( $theme_directory ) || ! is_dir( $theme_directory ) || ! is_writable( $theme_directory ) ) {
				$this->redirect_child_theme_result( 'create-failed' );
			}

			$theme_name  = str_replace( array( "\r", "\n", '*/' ), array( '', '', '* /' ), $theme_name );
			$function_name = 'wa_suit_' . str_replace( '-', '_', $theme_slug ) . '_enqueue_parent_styles';
			$style_css   = "/*\nTheme Name: " . $theme_name . "\n";
			$style_css  .= "Template: " . $parent_slug . "\n";
			$style_css  .= "Version: 1.0.0\n";
			$style_css  .= "Text Domain: " . $theme_slug . "\n";
			$style_css  .= "*/\n";
			$functions   = "<?php\n/**\n * Enqueues the parent and child theme stylesheets.\n */\n";
			$functions  .= "function " . $function_name . "() {\n";
			$functions  .= "\twp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );\n";
			$functions  .= "\twp_enqueue_style( 'child-style', get_stylesheet_uri(), array( 'parent-style' ), wp_get_theme()->get( 'Version' ) );\n";
			$functions  .= "}\n";
			$functions  .= "add_action( 'wp_enqueue_scripts', '" . $function_name . "' );\n";

			$style_written = strlen( $style_css ) === file_put_contents( trailingslashit( $theme_directory ) . 'style.css', $style_css );
			$functions_written = strlen( $functions ) === file_put_contents( trailingslashit( $theme_directory ) . 'functions.php', $functions );

			if ( ! $style_written || ! $functions_written ) {
				if ( file_exists( trailingslashit( $theme_directory ) . 'style.css' ) ) {
					wp_delete_file( trailingslashit( $theme_directory ) . 'style.css' );
				}
				if ( file_exists( trailingslashit( $theme_directory ) . 'functions.php' ) ) {
					wp_delete_file( trailingslashit( $theme_directory ) . 'functions.php' );
				}
				rmdir( $theme_directory );
				$this->redirect_child_theme_result( 'write-failed' );
			}

			wp_clean_themes_cache();
			if ( $activate_theme ) {
				switch_theme( $theme_slug );
				$this->redirect_child_theme_result( 'created-activated' );
			}

			$this->redirect_child_theme_result( 'created' );
		}

		/**
		 * Redirects back to settings with the child-theme operation result.
		 *
		 * @param string $result Result code.
		 * @return void
		 */
		private function redirect_child_theme_result( $result ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'                => 'wa-suit',
						'tab'                 => 'settings',
						'child-theme-result' => sanitize_key( $result ),
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		/**
		 * Returns a localized message for child-theme creation errors.
		 *
		 * @param string $result Result code.
		 * @return string
		 */
		private function get_child_theme_error_message( $result ) {
			$messages = array(
				'invalid-name'    => __( 'Digite um nome válido para o tema filho.', 'wa-suit' ),
				'invalid-parent'  => __( 'Selecione um tema pai instalado e válido.', 'wa-suit' ),
				'not-writable'    => __( 'A pasta de temas não está disponível para gravação. Verifique as permissões do servidor.', 'wa-suit' ),
				'already-exists'  => __( 'Já existe uma pasta de tema com esse nome. Escolha outro nome.', 'wa-suit' ),
				'create-failed'   => __( 'Não foi possível criar a pasta do tema filho. Verifique as permissões do servidor.', 'wa-suit' ),
				'write-failed'    => __( 'Não foi possível gravar os arquivos do tema filho. Verifique as permissões do servidor.', 'wa-suit' ),
			);

			return isset( $messages[ $result ] ) ? $messages[ $result ] : __( 'Não foi possível criar o tema filho.', 'wa-suit' );
		}

		/**
		 * Renders Help tab.
		 *
		 * @return void
		 */
		private function render_help_tab() {
			?>
<div class="wa-admin-box wa-admin-help-box">
    <header class="wa-admin-box-header">
        <h3 class="wa-admin-box-title">
            <span class="dashicons dashicons-welcome-learn-more"></span>
            <?php echo esc_html__( 'Guia de Uso: Links Dinâmicos no Elementor', 'wa-suit' ); ?>
        </h3>
    </header>

    <div class="wa-admin-box-content wa-admin-help-content">
        <p>
            <?php echo esc_html__( 'O módulo de Links Dinâmicos permite que você cadastre links estratégicos (como WhatsApp, Checkout, Agendamento) em um único lugar e utilize shortcodes em todos os botões do Elementor.', 'wa-suit' ); ?>
        </p>

        <div class="wa-admin-help-grid">
            <div class="wa-admin-help-col">
                <h4>1. <?php echo esc_html__( 'Cadastrar o Link', 'wa-suit' ); ?></h4>
                <p><?php echo esc_html__( 'Na aba "Links Dinâmicos", clique em "+ Novo Link", defina um nome (ex: WhatsApp Geral) e a URL completa.', 'wa-suit' ); ?>
                </p>
            </div>
            <div class="wa-admin-help-col">
                <h4>2. <?php echo esc_html__( 'Copiar o Shortcode', 'wa-suit' ); ?></h4>
                <p><?php echo esc_html__( 'Clique no botão "Copiar" ao lado do link para copiar [wa_link id="slug"].', 'wa-suit' ); ?>
                </p>
            </div>
            <div class="wa-admin-help-col">
                <h4>3. <?php echo esc_html__( 'Usar no Elementor', 'wa-suit' ); ?></h4>
                <p><?php echo esc_html__( 'No Elementor, cole o shortcode diretamente no campo de Link do botão ou selecione a Tag Dinâmica > Shortcode.', 'wa-suit' ); ?>
                </p>
            </div>
            <div class="wa-admin-help-col">
                <h4>4. <?php echo esc_html__( 'Alteração Global Instantânea', 'wa-suit' ); ?></h4>
                <p><?php echo esc_html__( 'Quando o número ou a URL mudar, edite uma única vez no painel. Todos os botões do site serão atualizados na hora!', 'wa-suit' ); ?>
                </p>
            </div>
        </div>
    </div>
</div>
<?php
		}
	}