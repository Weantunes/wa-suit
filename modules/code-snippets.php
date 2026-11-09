<?php

/**
 * Code snippets module.
 *
 * @package WASuit
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Stores and runs code snippets on selected front-end locations.
 */
class WA_Suit_Module_Code_Snippets extends WA_Suit_Module_Base
{
	/**
	 * Captured direct output from PHP snippets, indexed by snippet ID.
	 *
	 * @var array<string, string>
	 */
	private $php_output = array();

	/**
	 * Results of PHP snippets already evaluated during this request.
	 *
	 * @var array<string, string>
	 */
	private $php_execution_results = array();

	/**
	 * Available snippet languages.
	 *
	 * @var array<string, string>
	 */
	private $languages = array(
		'html' => 'HTML',
		'css'  => 'CSS',
		'js'   => 'JavaScript',
		'php'  => 'PHP',
		'shortcode' => 'Shortcode',
	);

	/**
	 * Available front-end output locations.
	 *
	 * @var array<string, string>
	 */
	private $locations = array(
		'head'      => 'Cabeçalho — antes de fechar o head',
		'body_open' => 'Início do body — após abrir a página',
		'footer'    => 'Rodapé — antes de fechar o body',
	);

	/**
	 * Available display conditions.
	 *
	 * @var array<string, string>
	 */
	private $targets = array(
		'all'          => 'Site inteiro',
		'posts'        => 'Posts',
		'pages'        => 'Páginas',
		'category'     => 'Páginas de categoria',
		'search'       => 'Páginas de pesquisa',
		'tag_archive'  => 'Páginas de tag/arquivo',
		'url_contains' => 'URL contém',
	);

	/**
	 * Available device groups.
	 *
	 * @var array<string, string>
	 */
	private $devices = array(
		'all'     => 'Todos',
		'desktop' => 'Desktop',
		'tablet'  => 'Tablet',
		'mobile'  => 'Celular',
	);

	/**
	 * Registers settings handlers even when front-end snippet execution is disabled.
	 */
	public function __construct()
	{
		add_action('admin_post_wa_suit_save_code_snippets', array($this, 'save_snippets'));
		add_action('admin_post_wa_suit_duplicate_code_snippet', array($this, 'duplicate_snippet'));
		add_action('admin_post_wa_suit_manage_code_snippets', array($this, 'manage_snippets'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		add_action('wp_ajax_wa_suit_toggle_code_snippet', array($this, 'ajax_toggle_snippet'));
	}

	public function get_id()
	{
		return 'code-snippets';
	}

	public function get_title()
	{
		return __('Códigos Personalizados', 'wa-suit');
	}

	public function get_description()
	{
		return __('Adicione HTML, CSS, JavaScript, PHP ou shortcodes e escolha onde e em quais páginas executar.', 'wa-suit');
	}

	public function get_icon()
	{
		return 'dashicons-editor-code';
	}

	public function get_category()
	{
		return __('Desenvolvimento', 'wa-suit');
	}

	public function get_order()
	{
		return 20;
	}

	/**
	 * Registers snippet management and front-end output hooks.
	 *
	 * @return void
	 */
	public function init()
	{
		add_action('wp', array($this, 'execute_frontend_php_snippets'), 0);
		add_action('admin_init', array($this, 'execute_ajax_php_snippets'), 0);
		add_action('admin_notices', array($this, 'render_php_conflict_notices'));
		add_action('wp_head', array($this, 'render_head_snippets'), 99);
		add_action('wp_body_open', array($this, 'render_body_snippets'), 1);
		add_action('wp_footer', array($this, 'render_footer_snippets'), 99);
		add_shortcode('wa_suit_snippet', array($this, 'render_snippet_shortcode'));
	}

	/**
	 * Loads matching PHP snippets before the front-end template is rendered.
	 *
	 * @return void
	 */
	public function execute_frontend_php_snippets()
	{
		if (! is_admin()) {
			$this->execute_matching_php_snippets();
		}
	}

	/**
	 * Loads snippets for AJAX requests initiated from a public site page.
	 *
	 * @return void
	 */
	public function execute_ajax_php_snippets()
	{
		if ($this->is_frontend_ajax_request()) {
			$this->execute_matching_php_snippets();
		}
	}

	/**
	 * Enqueues module controls on its settings tab.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets($hook_suffix)
	{
		if (
			! isset($_GET['page'], $_GET['tab']) ||
			! is_string($_GET['page']) ||
			! is_string($_GET['tab']) ||
			'wa-suit' !== sanitize_key(wp_unslash($_GET['page'])) ||
			$this->get_id() !== sanitize_key(wp_unslash($_GET['tab']))
		) {
			return;
		}

		wp_enqueue_script(
			'wa-suit-code-snippets-admin',
			WA_SUIT_URL . 'assets/js/code-snippets-admin.js',
			array(),
			filemtime(WA_SUIT_PATH . 'assets/js/code-snippets-admin.js'),
			true
		);
		wp_enqueue_style(
			'wa-suit-code-snippets-admin',
			WA_SUIT_URL . 'assets/css/code-snippets-admin.css',
			array(),
			filemtime(WA_SUIT_PATH . 'assets/css/code-snippets-admin.css')
		);
		wp_localize_script(
			'wa-suit-code-snippets-admin',
			'waSuitCodeSnippets',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => WA_Suit_Security::create_nonce(),
				'strings' => array(
					'saving'         => __('Salvando...', 'wa-suit'),
					'error'          => __('Não foi possível atualizar o código.', 'wa-suit'),
					'urlPlaceholder' => __('Ex.: campanha ou /promocao/', 'wa-suit'),
					'urlMatch'       => __('Texto que a URL deve conter', 'wa-suit'),
					'removeUrl'      => __('Remover condição de URL', 'wa-suit'),
					'addUrl'         => __('Adicionar condição', 'wa-suit'),
					'urlRequired'    => __('Adicione pelo menos um trecho de URL para usar esta regra.', 'wa-suit'),
					'posts'          => __('Posts', 'wa-suit'),
					'products'       => __('Produtos', 'wa-suit'),
					'content'        => class_exists('WooCommerce') && post_type_exists('product')
						? __('Páginas, posts ou produtos', 'wa-suit')
						: __('Páginas ou posts', 'wa-suit'),
					'requiredFields' => __('Preencha o nome e o código antes de salvar.', 'wa-suit'),
				),
			)
		);
	}

	/**
	 * Toggles one snippet without requiring the editor form to be opened or saved.
	 *
	 * @return void
	 */
	public function ajax_toggle_snippet()
	{
		if (! WA_Suit_Security::can_manage_settings()) {
			wp_send_json_error(array('message' => __('Você não tem permissão para alterar este código.', 'wa-suit')), 403);
		}

		$nonce = isset($_POST['nonce']) && is_string($_POST['nonce'])
			? sanitize_text_field(wp_unslash($_POST['nonce']))
			: '';
		if (! WA_Suit_Security::verify_nonce($nonce)) {
			wp_send_json_error(array('message' => __('Sessão expirada. Atualize a página e tente novamente.', 'wa-suit')), 403);
		}

		$id = isset($_POST['snippet_id']) && is_string($_POST['snippet_id'])
			? sanitize_text_field(wp_unslash($_POST['snippet_id']))
			: '';
		$enabled = isset($_POST['enabled']) && is_string($_POST['enabled']) && '1' === wp_unslash($_POST['enabled']);
		$settings = $this->get_settings();
		$snippets = isset($settings['snippets']) && is_array($settings['snippets']) ? $settings['snippets'] : array();

		if ('' === $id || ! isset($snippets[$id]) || ! is_array($snippets[$id]) || ! empty($snippets[$id]['trashed'])) {
			wp_send_json_error(array('message' => __('Código não encontrado.', 'wa-suit')), 404);
		}

		$snippets[$id]['enabled'] = $enabled ? '1' : '0';
		$this->update_settings(array('snippets' => $snippets));

		wp_send_json_success(
			array(
				'enabled' => $enabled,
				'message' => $enabled ? __('Código ativado.', 'wa-suit') : __('Código desativado.', 'wa-suit'),
			)
		);
	}

	/**
	 * Saves all submitted snippets after checking authorization and nonce.
	 *
	 * @return void
	 */
	public function save_snippets()
	{
		if (! WA_Suit_Security::can_manage_settings()) {
			wp_die(esc_html__('Você não tem permissão para editar estes códigos.', 'wa-suit'));
		}

		check_admin_referer('wa_suit_save_code_snippets');

		$submitted = isset($_POST['snippets']) && is_array($_POST['snippets'])
			? wp_unslash($_POST['snippets'])
			: array();
		$existing_settings = $this->get_settings();
		$existing_snippets = isset($existing_settings['snippets']) && is_array($existing_settings['snippets'])
			? $existing_settings['snippets']
			: array();
		$snippets       = array();
		$syntax_notices = array();

		foreach ($submitted as $key => $input) {
			if (! is_array($input)) {
				continue;
			}

			if (empty($input['title']) && empty($input['code'])) {
				continue;
			}

			$snippet = $this->sanitize_snippet($input);
			if (null === $snippet) {
				wp_die(esc_html__('Um ou mais códigos estão incompletos ou têm opções inválidas. Revise nome, código, linguagem e regra de exibição.', 'wa-suit'));
			}

			$id = sanitize_key((string) $key);
			if ('' === $id) {
				$id = wp_generate_uuid4();
			}
			$snippet['trashed'] = isset($existing_snippets[$id]['trashed']) && ! empty($existing_snippets[$id]['trashed']) ? '1' : '0';
			$existing_snippet = isset($existing_snippets[$id]) && is_array($existing_snippets[$id])
				? $existing_snippets[$id]
				: array();
			if (isset($existing_snippet['created_at']) && is_string($existing_snippet['created_at']) && '' !== $existing_snippet['created_at']) {
				$snippet['created_at'] = $existing_snippet['created_at'];
				$snippet['created_by_id'] = isset($existing_snippet['created_by_id']) ? absint($existing_snippet['created_by_id']) : 0;
				$snippet['created_by_name'] = isset($existing_snippet['created_by_name']) && is_string($existing_snippet['created_by_name'])
					? $existing_snippet['created_by_name']
					: '';
			} else {
				$current_user = wp_get_current_user();
				$snippet['created_at'] = current_time('mysql');
				$snippet['created_by_id'] = get_current_user_id();
				$snippet['created_by_name'] = $current_user instanceof WP_User ? $current_user->display_name : '';
			}

			if ('php' === $snippet['language'] && '1' === $snippet['enabled']) {
				$syntax_error = $this->get_php_syntax_error($snippet['code']);
				if ('' !== $syntax_error) {
					$snippet['enabled'] = '0';
					$syntax_notices[] = array(
						'title'   => $snippet['title'],
						'message' => $syntax_error,
					);
				}
			}

			$snippets[$id] = $snippet;
		}

		$this->update_settings(array('snippets' => $snippets));
		foreach ($syntax_notices as $notice) {
			WA_Suit_Debug::queue_snippet_failure_notice($notice['title'], $notice['message']);
		}

		$redirect_url = add_query_arg(
			array(
				'page'           => 'wa-suit',
				'tab'            => $this->get_id(),
				'snippets-saved' => '1',
			),
			admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}

	/**
	 * Moves, restores, toggles, or permanently deletes selected snippets.
	 *
	 * @return void
	 */
	public function manage_snippets()
	{
		if (! WA_Suit_Security::can_manage_settings()) {
			wp_die(esc_html__('Você não tem permissão para gerenciar estes códigos.', 'wa-suit'), '', array('response' => 403));
		}

		$is_bulk = isset($_POST['bulk_action']) && is_string($_POST['bulk_action']);
		$operation = $is_bulk
			? sanitize_key(wp_unslash($_POST['bulk_action']))
			: (isset($_GET['operation']) && is_string($_GET['operation']) ? sanitize_key(wp_unslash($_GET['operation'])) : '');
		$settings = $this->get_settings();
		$snippets = isset($settings['snippets']) && is_array($settings['snippets']) ? $settings['snippets'] : array();

		if ($is_bulk) {
			check_admin_referer('wa_suit_manage_code_snippets');
			$raw_selected_ids = isset($_POST['snippet_ids']) && is_array($_POST['snippet_ids'])
				? wp_unslash($_POST['snippet_ids'])
				: array();
			$selected_ids = array_map('sanitize_key', array_filter($raw_selected_ids, 'is_string'));
		} else {
			$snippet_id = isset($_GET['snippet_id']) && is_string($_GET['snippet_id'])
				? sanitize_key(wp_unslash($_GET['snippet_id']))
				: '';
			check_admin_referer('wa_suit_manage_code_snippet_' . $snippet_id . '_' . $operation);
			$selected_ids = array($snippet_id);
		}

		$allowed_operations = array('trash', 'restore', 'delete', 'activate', 'deactivate');
		if (! in_array($operation, $allowed_operations, true)) {
			wp_die(esc_html__('Ação inválida para os códigos.', 'wa-suit'), '', array('response' => 400));
		}

		$selected_ids = array_values(array_unique(array_filter($selected_ids)));
		if (empty($selected_ids)) {
			wp_die(esc_html__('Selecione ao menos um código para continuar.', 'wa-suit'), '', array('response' => 400));
		}

		foreach ($selected_ids as $id) {
			if (! isset($snippets[$id]) || ! is_array($snippets[$id])) {
				wp_die(esc_html__('Um dos códigos selecionados não foi encontrado.', 'wa-suit'), '', array('response' => 404));
			}
			$is_trashed = ! empty($snippets[$id]['trashed']);
			if (
				('trash' === $operation && $is_trashed) ||
				(in_array($operation, array('restore', 'delete'), true) && ! $is_trashed) ||
				(in_array($operation, array('activate', 'deactivate'), true) && $is_trashed)
			) {
				wp_die(esc_html__('A ação não corresponde ao status atual de um dos códigos selecionados. Atualize a página e tente novamente.', 'wa-suit'), '', array('response' => 400));
			}
		}

		foreach ($selected_ids as $id) {
			switch ($operation) {
				case 'trash':
					$snippets[$id]['trashed'] = '1';
					$snippets[$id]['enabled'] = '0';
					break;
				case 'restore':
					$snippets[$id]['trashed'] = '0';
					$snippets[$id]['enabled'] = '0';
					break;
				case 'delete':
					unset($snippets[$id]);
					break;
				case 'activate':
					if (empty($snippets[$id]['trashed'])) {
						$snippets[$id]['enabled'] = '1';
					}
					break;
				case 'deactivate':
					if (empty($snippets[$id]['trashed'])) {
						$snippets[$id]['enabled'] = '0';
					}
					break;
			}
		}

		$this->update_settings(array('snippets' => $snippets));
		$redirect_url = add_query_arg(
			array(
				'page'            => 'wa-suit',
				'tab'             => $this->get_id(),
				'snippets-action' => $operation,
			),
			admin_url('admin.php')
		);
		wp_safe_redirect($redirect_url);
		exit;
	}

	/**
	 * Creates a disabled copy of a saved snippet.
	 *
	 * @return void
	 */
	public function duplicate_snippet()
	{
		if (! WA_Suit_Security::can_manage_settings()) {
			wp_die(esc_html__('Você não tem permissão para duplicar estes códigos.', 'wa-suit'), '', array('response' => 403));
		}

		$snippet_id = isset($_GET['snippet_id']) && is_string($_GET['snippet_id'])
			? sanitize_key(wp_unslash($_GET['snippet_id']))
			: '';
		check_admin_referer('wa_suit_duplicate_code_snippet_' . $snippet_id);

		$settings = $this->get_settings();
		$snippets = isset($settings['snippets']) && is_array($settings['snippets']) ? $settings['snippets'] : array();
		if ('' === $snippet_id || ! isset($snippets[$snippet_id]) || ! is_array($snippets[$snippet_id]) || ! empty($snippets[$snippet_id]['trashed'])) {
			wp_die(esc_html__('O código selecionado não foi encontrado.', 'wa-suit'), '', array('response' => 404));
		}

		$source      = $snippets[$snippet_id];
		$source_title = isset($source['title']) && is_string($source['title']) ? $source['title'] : '';
		$base_title  = preg_replace('/\s*\(c[oó]pia(?:\s+\d+)?\)$/iu', '', $source_title);
		$base_title  = is_string($base_title) && '' !== trim($base_title) ? trim($base_title) : $source_title;
		$copy_number = 0;

		foreach ($snippets as $existing_snippet) {
			if (! is_array($existing_snippet) || empty($existing_snippet['title']) || ! is_string($existing_snippet['title'])) {
				continue;
			}

			$existing_title = $existing_snippet['title'];
			if (! preg_match('/^(.*?)\s*\(c[oó]pia(?:\s+(\d+))?\)$/iu', $existing_title, $matches)) {
				continue;
			}

			if (sanitize_title($matches[1]) !== sanitize_title($base_title)) {
				continue;
			}

			$copy_number = max($copy_number, ! empty($matches[2]) ? absint($matches[2]) : 1);
		}

		$copy_number++;
		$copy = $source;
		$copy['title']   = sprintf(__('%1$s (cópia %2$d)', 'wa-suit'), $base_title, $copy_number);
		$copy['enabled'] = '0';
		$copy['trashed'] = '0';
		$copy['created_at'] = current_time('mysql');
		$current_user = wp_get_current_user();
		$copy['created_by_id'] = get_current_user_id();
		$copy['created_by_name'] = $current_user instanceof WP_User ? $current_user->display_name : '';
		$new_id = wp_generate_uuid4();

		$snippets[$new_id] = $copy;
		$this->update_settings(array('snippets' => $snippets));
		$saved_settings = $this->get_settings();
		$saved_snippets = isset($saved_settings['snippets']) && is_array($saved_settings['snippets']) ? $saved_settings['snippets'] : array();
		if (! isset($saved_snippets[$new_id]['enabled']) || '0' !== (string) $saved_snippets[$new_id]['enabled']) {
			wp_die(esc_html__('Não foi possível salvar a cópia do código.', 'wa-suit'), '', array('response' => 500));
		}

		$redirect_url = add_query_arg(
			array(
				'page'              => 'wa-suit',
				'tab'               => $this->get_id(),
				'snippet-duplicated' => '1',
			),
			admin_url('admin.php')
		);
		wp_safe_redirect($redirect_url);
		exit;
	}

	/**
	 * Renders the snippets editor.
	 *
	 * @return void
	 */
	public function render_tab()
	{
		$snippets = $this->get_setting('snippets', array());
		if (! is_array($snippets)) {
			$snippets = array();
		}

		$targets = $this->get_available_targets();
		$counts  = array(
			'all'       => 0,
			'active'    => 0,
			'inactive'  => 0,
			'trash'     => 0,
			'html'      => 0,
			'css'       => 0,
			'js'        => 0,
			'php'       => 0,
			'shortcode' => 0,
		);
		foreach ($snippets as $snippet) {
			if (! is_array($snippet)) {
				continue;
			}
			if (! empty($snippet['trashed'])) {
				$counts['trash']++;
				continue;
			}
			$counts['all']++;
			$counts[! empty($snippet['enabled']) ? 'active' : 'inactive']++;
			$language = isset($snippet['language'], $this->languages[$snippet['language']]) ? $snippet['language'] : '';
			if ('' !== $language) {
				$counts[$language]++;
			}
		}
?>
		<div class="wa-code-snippets">
			<div class="wa-code-snippets__header">
				<div>
					<h2><?php echo esc_html__('Códigos Personalizados', 'wa-suit'); ?></h2>
					<p><?php echo esc_html__('Pesquise, organize e gerencie seus códigos por linguagem e status.', 'wa-suit'); ?></p>
				</div>
				<div class="wa-code-snippets__header-actions">
					<button type="submit" class="wa-admin-btn wa-admin-btn--primary wa-code-snippets__save" form="wa-suit-snippet-form" title="<?php echo esc_attr__('Salvar alterações', 'wa-suit'); ?>" aria-label="<?php echo esc_attr__('Salvar alterações', 'wa-suit'); ?>">
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
						<?php echo esc_html__('Salvar alterações', 'wa-suit'); ?>
					</button>
				</div>
			</div>

			<?php $this->render_snippet_admin_notice(); ?>

			<div class="wa-code-snippets__toolbar">
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wa-suit-snippets-bulk-form" class="wa-code-snippets__bulk-form">
					<input type="hidden" name="action" value="wa_suit_manage_code_snippets">
					<input type="hidden" name="bulk_action" value="">
					<?php wp_nonce_field('wa_suit_manage_code_snippets'); ?>
				</form>
				<div class="wa-code-snippets__toolbar-top">
					<div class="wa-code-snippets__selection-tools">
						<button type="button" class="wa-code-snippets__select-mode" aria-pressed="false" data-wa-selection-toggle title="<?php echo esc_attr__('Selecionar códigos', 'wa-suit'); ?>">
							<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
							<span data-wa-selection-label><?php echo esc_html__('Selecionar', 'wa-suit'); ?></span>
						</button>
						<span class="wa-code-snippets__selected-count" data-wa-selected-count hidden></span>
						<div class="wa-code-snippets__bulk-actions" data-wa-bulk-actions hidden>
							<button type="button" data-wa-bulk-action="trash" title="<?php echo esc_attr__('Mover para a lixeira', 'wa-suit'); ?>" aria-label="<?php echo esc_attr__('Mover selecionados para a lixeira', 'wa-suit'); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><?php echo esc_html__('Lixeira', 'wa-suit'); ?></button>
							<button type="button" data-wa-bulk-action="activate" title="<?php echo esc_attr__('Ativar selecionados', 'wa-suit'); ?>" aria-label="<?php echo esc_attr__('Ativar selecionados', 'wa-suit'); ?>"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span><?php echo esc_html__('Ativar', 'wa-suit'); ?></button>
							<button type="button" data-wa-bulk-action="deactivate" title="<?php echo esc_attr__('Desativar selecionados', 'wa-suit'); ?>" aria-label="<?php echo esc_attr__('Desativar selecionados', 'wa-suit'); ?>"><span class="dashicons dashicons-controls-pause" aria-hidden="true"></span><?php echo esc_html__('Desativar', 'wa-suit'); ?></button>
							<button type="button" data-wa-bulk-action="restore" title="<?php echo esc_attr__('Restaurar selecionados', 'wa-suit'); ?>" aria-label="<?php echo esc_attr__('Restaurar selecionados', 'wa-suit'); ?>"><span class="dashicons dashicons-undo" aria-hidden="true"></span><?php echo esc_html__('Restaurar', 'wa-suit'); ?></button>
							<button type="button" data-wa-bulk-action="delete" title="<?php echo esc_attr__('Excluir permanentemente', 'wa-suit'); ?>" aria-label="<?php echo esc_attr__('Excluir permanentemente', 'wa-suit'); ?>"><span class="dashicons dashicons-no" aria-hidden="true"></span><?php echo esc_html__('Excluir', 'wa-suit'); ?></button>
						</div>
					</div>
					<button type="button" class="wa-admin-btn wa-admin-btn--primary wa-code-snippets__add" data-wa-snippet-add title="<?php echo esc_attr__('Adicionar código', 'wa-suit'); ?>" aria-label="<?php echo esc_attr__('Adicionar código', 'wa-suit'); ?>">
						<span class="dashicons dashicons-plus" aria-hidden="true"></span>
						<?php echo esc_html__('Adicionar código', 'wa-suit'); ?>
					</button>
				</div>
				<div class="wa-code-snippets__toolbar-filters">
					<div class="wa-code-snippets__tabs" role="group" aria-label="<?php echo esc_attr__('Filtrar por linguagem', 'wa-suit'); ?>">
						<?php
						$language_tabs = array_merge(array('all' => __('Todos', 'wa-suit')), $this->languages);
						foreach ($language_tabs as $language => $label) :
							$count = 'all' === $language ? $counts['all'] : $counts[$language];
						?>
							<button type="button" aria-pressed="<?php echo 'all' === $language ? 'true' : 'false'; ?>" data-wa-language-filter="<?php echo esc_attr($language); ?>">
								<?php echo esc_html($label); ?> <span data-wa-language-count="<?php echo esc_attr($language); ?>"><?php echo esc_html(number_format_i18n($count)); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
					<div class="wa-code-snippets__status-tabs" role="group" aria-label="<?php echo esc_attr__('Filtrar por status', 'wa-suit'); ?>">
						<button type="button" aria-pressed="false" data-wa-status-filter="active" title="<?php echo esc_attr__('Ativos', 'wa-suit'); ?>">ON <span data-wa-status-count="active"><?php echo esc_html(number_format_i18n($counts['active'])); ?></span></button>
						<button type="button" aria-pressed="false" data-wa-status-filter="inactive" title="<?php echo esc_attr__('Desativados', 'wa-suit'); ?>">OFF <span data-wa-status-count="inactive"><?php echo esc_html(number_format_i18n($counts['inactive'])); ?></span></button>
						<button type="button" aria-pressed="false" data-wa-status-filter="trash" title="<?php echo esc_attr__('Lixeira', 'wa-suit'); ?>"><?php echo esc_html__('Lixeira', 'wa-suit'); ?> <span data-wa-status-count="trash"><?php echo esc_html(number_format_i18n($counts['trash'])); ?></span></button>
					</div>
				</div>
				<div class="wa-code-snippets__toolbar-search">
					<label class="wa-code-snippets__location-filter">
						<span class="screen-reader-text"><?php echo esc_html__('Filtrar por localização', 'wa-suit'); ?></span>
						<select data-wa-snippet-location-filter>
							<option value="all"><?php echo esc_html__('Todos os locais', 'wa-suit'); ?></option>
							<?php foreach ($this->locations as $location => $label) : ?>
								<option value="<?php echo esc_attr($location); ?>"><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label class="wa-code-snippets__search">
						<span class="screen-reader-text"><?php echo esc_html__('Pesquisar códigos', 'wa-suit'); ?></span>
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<input type="search" data-wa-snippet-search placeholder="<?php echo esc_attr__('Pesquisar códigos...', 'wa-suit'); ?>">
					</label>
				</div>
			</div>
			<div class="wa-code-snippets__confirm" data-wa-bulk-dialog role="dialog" aria-modal="true" aria-labelledby="wa-code-snippets-confirm-title" hidden>
				<button type="button" class="wa-code-snippets__confirm-backdrop" data-wa-bulk-dialog-cancel aria-label="<?php echo esc_attr__('Fechar confirmação', 'wa-suit'); ?>"></button>
				<div class="wa-code-snippets__confirm-content">
					<h2 id="wa-code-snippets-confirm-title" data-wa-bulk-dialog-title></h2>
					<p data-wa-bulk-dialog-message></p>
					<div class="wa-code-snippets__confirm-actions">
						<button type="button" class="button" data-wa-bulk-dialog-cancel><?php echo esc_html__('Cancelar', 'wa-suit'); ?></button>
						<button type="button" class="button button-primary" data-wa-bulk-dialog-confirm><?php echo esc_html__('Confirmar', 'wa-suit'); ?></button>
					</div>
				</div>
			</div>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="wa-suit-snippet-form" data-wa-snippet-form>
				<input type="hidden" name="action" value="wa_suit_save_code_snippets">
				<?php wp_nonce_field('wa_suit_save_code_snippets'); ?>

				<div class="wa-code-snippets__list" data-wa-snippet-list>
					<?php foreach ($snippets as $id => $snippet) : ?>
						<?php
						if (is_array($snippet) && is_scalar($id)) {
							$this->render_snippet_fields(sanitize_key((string) $id), $snippet);
						}
						?>
					<?php endforeach; ?>
				</div>

				<p class="wa-code-snippets__empty" data-wa-snippet-empty hidden><?php echo esc_html__('Nenhum código encontrado.', 'wa-suit'); ?></p>
				<div class="wa-code-snippets__pagination" data-wa-snippet-pagination>
					<label class="wa-code-snippets__per-page">
						<span><?php echo esc_html__('Itens por página', 'wa-suit'); ?></span>
						<select data-wa-snippet-page-size>
							<option value="10">10</option>
							<option value="20">20</option>
							<option value="50">50</option>
							<option value="100" selected>100</option>
						</select>
					</label>
					<span class="wa-code-snippets__page-count" data-wa-snippet-page-label></span>
					<nav class="wa-code-snippets__page-numbers" data-wa-snippet-page-numbers aria-label="<?php echo esc_attr__('Paginação dos códigos', 'wa-suit'); ?>"></nav>
				</div>

				<template data-wa-snippet-template>
					<?php $this->render_snippet_fields('__SNIPPET_KEY__', array()); ?>
				</template>

			</form>

			<div class="wa-code-snippets__help">
				<p><?php echo esc_html__('HTML, CSS, JavaScript e o resultado de shortcodes são enviados ao navegador. Selecione PHP para códigos PHP: eles são executados antes do template e também em requisições AJAX iniciadas no site público, para que filtros e validações funcionem. A saída direta do PHP aparece na posição escolhida. Se uma função global já existir, o código será desativado por segurança e um aviso será exibido no painel. Tags PHP e cercas Markdown de código são aceitas. PHP executa no servidor para todos os visitantes que correspondam à regra escolhida, com acesso às funções do WordPress, e não pode ser limitado por dispositivo. Use apenas código confiável e mantenha um backup.', 'wa-suit'); ?></p>
				<p><?php echo esc_html__('Quando mais de uma regra corresponder, a regra de URL mais específica tem prioridade; sem correspondência de URL, o tipo de página selecionado tem prioridade sobre “Site inteiro”. Regras igualmente específicas podem ser exibidas juntas. Códigos marcados como somente shortcode só aparecem onde o shortcode gerado for usado.', 'wa-suit'); ?></p>
			</div>

		</div>
	<?php
	}

	/**
	 * Displays a result notice after a snippet action.
	 *
	 * @return void
	 */
	private function render_snippet_admin_notice()
	{
		$messages = array(
			'saved'      => __('Códigos salvos.', 'wa-suit'),
			'duplicated' => __('Cópia criada desativada. Revise o código e ative quando estiver pronto.', 'wa-suit'),
			'trash'      => __('Códigos movidos para a lixeira.', 'wa-suit'),
			'restore'    => __('Códigos restaurados e mantidos desativados.', 'wa-suit'),
			'delete'     => __('Códigos excluídos permanentemente.', 'wa-suit'),
			'activate'   => __('Códigos ativados.', 'wa-suit'),
			'deactivate' => __('Códigos desativados.', 'wa-suit'),
		);
		$key = '';
		if (isset($_GET['snippets-saved']) && is_string($_GET['snippets-saved']) && '1' === sanitize_text_field(wp_unslash($_GET['snippets-saved']))) {
			$key = 'saved';
		} elseif (isset($_GET['snippet-duplicated']) && is_string($_GET['snippet-duplicated']) && '1' === sanitize_text_field(wp_unslash($_GET['snippet-duplicated']))) {
			$key = 'duplicated';
		} elseif (isset($_GET['snippets-action']) && is_string($_GET['snippets-action'])) {
			$key = sanitize_key(wp_unslash($_GET['snippets-action']));
		}

		if (isset($messages[$key])) {
			printf('<div class="notice notice-success inline"><p>%s</p></div>', esc_html($messages[$key]));
		}
	}

	/**
	 * Outputs snippets registered for the document head.
	 *
	 * @return void
	 */
	public function render_head_snippets()
	{
		$this->render_location('head');
	}

	/**
	 * Outputs snippets registered for the beginning of the document body.
	 *
	 * @return void
	 */
	public function render_body_snippets()
	{
		$this->render_location('body_open');
	}

	/**
	 * Outputs snippets registered for the end of the document body.
	 *
	 * @return void
	 */
	public function render_footer_snippets()
	{
		$this->render_location('footer');
	}

	/**
	 * Renders snippets at a location when their display condition matches.
	 *
	 * @param string $location Current document location.
	 * @return void
	 */
	private function render_location($location)
	{
		if (is_admin()) {
			return;
		}

		$snippets = $this->get_setting('snippets', array());
		if (! is_array($snippets)) {
			return;
		}

		$matched_snippets = array();
		$sequence         = 0;

		foreach ($snippets as $id => $snippet) {
			if (! is_array($snippet) || empty($snippet['enabled']) || ! empty($snippet['trashed']) || ! empty($snippet['shortcode_only']) || ! isset($snippet['location'], $snippet['language']) || $location !== $snippet['location'] || ! isset($this->languages[$snippet['language']]) || ! $this->matches_target($snippet)) {
				continue;
			}

			$display_on = $this->get_snippet_display_targets($snippet);
			$url_length = in_array('url_contains', $display_on, true) ? $this->get_matching_url_specificity($snippet) : 0;
			$target_rank = $url_length > 0
				? 2
				: (in_array('all', $display_on, true) ? 0 : 1);
			$devices     = $this->get_snippet_devices($snippet);
			$device_rank = in_array('all', $devices, true) ? 0 : 1;

			$matched_snippets[] = array(
				'id'          => $id,
				'snippet'     => $snippet,
				'target_rank' => $target_rank,
				'url_length'  => $url_length,
				'device_rank' => $device_rank,
				'sequence'    => $sequence++,
			);
		}

		if (! empty($matched_snippets)) {
			$most_specific_url = max(array_column($matched_snippets, 'url_length'));
			$highest_target_rank = max(array_column($matched_snippets, 'target_rank'));

			$matched_snippets = array_values(
				array_filter(
					$matched_snippets,
					function ($entry) use ($most_specific_url, $highest_target_rank) {
						if ($most_specific_url > 0) {
							return $most_specific_url === $entry['url_length'];
						}

						return $highest_target_rank === $entry['target_rank'];
					}
				)
			);
		}

		usort(
			$matched_snippets,
			function ($first, $second) {
				if ($first['target_rank'] !== $second['target_rank']) {
					return $first['target_rank'] < $second['target_rank'] ? -1 : 1;
				}

				if ($first['url_length'] !== $second['url_length']) {
					return $first['url_length'] < $second['url_length'] ? -1 : 1;
				}

				if ($first['device_rank'] !== $second['device_rank']) {
					return $first['device_rank'] < $second['device_rank'] ? -1 : 1;
				}

				return $first['sequence'] < $second['sequence'] ? -1 : 1;
			}
		);

		foreach ($matched_snippets as $entry) {
			$this->output_snippet($entry['id'], $entry['snippet']);
		}
	}

	/**
	 * Executes enabled PHP snippets that match the current request.
	 *
	 * @return void
	 */
	private function execute_matching_php_snippets()
	{
		$snippets = $this->get_setting('snippets', array());
		if (! is_array($snippets)) {
			return;
		}

		foreach ($snippets as $id => $snippet) {
			if (
				! is_array($snippet) ||
				empty($snippet['enabled']) ||
				! empty($snippet['trashed']) ||
				! empty($snippet['shortcode_only']) ||
				! isset($snippet['language']) ||
				'php' !== $snippet['language'] ||
				! isset($snippet['location'], $this->locations[$snippet['location']]) ||
				! $this->matches_target($snippet)
			) {
				continue;
			}

			$this->php_output[$id] = $this->evaluate_php_snippet(
				$id,
				isset($snippet['code']) ? $snippet['code'] : '',
				isset($snippet['title']) && is_string($snippet['title']) ? $snippet['title'] : $id
			);
		}
	}

	/**
	 * Checks whether the current AJAX request originated from this site's front end.
	 *
	 * @return bool
	 */
	private function is_frontend_ajax_request()
	{
		if (! function_exists('wp_doing_ajax') || ! wp_doing_ajax()) {
			return false;
		}

		$referer = wp_get_referer();
		if (! is_string($referer) || '' === $referer) {
			return false;
		}

		$referer_host = wp_parse_url($referer, PHP_URL_HOST);
		$site_host    = wp_parse_url(home_url('/'), PHP_URL_HOST);
		if (! is_string($referer_host) || ! is_string($site_host) || 0 !== strcasecmp($referer_host, $site_host)) {
			return false;
		}

		$referer_path = wp_parse_url($referer, PHP_URL_PATH);
		$admin_path   = wp_parse_url(admin_url(), PHP_URL_PATH);
		if (! is_string($referer_path) || ! is_string($admin_path)) {
			return false;
		}

		$admin_path = trailingslashit($admin_path);
		return 0 !== strpos(trailingslashit($referer_path), $admin_path);
	}

	/**
	 * Renders a snippet only when its generated shortcode is used.
	 *
	 * @param array       $attributes Shortcode attributes.
	 * @param string|null $content    Enclosed shortcode content.
	 * @return string
	 */
	public function render_snippet_shortcode($attributes, $content = null)
	{
		unset($content);
		$attributes = shortcode_atts(array('id' => ''), $attributes, 'wa_suit_snippet');
		$id         = is_string($attributes['id']) ? sanitize_key($attributes['id']) : '';
		$snippets   = $this->get_setting('snippets', array());

		if ('' === $id || ! is_array($snippets) || ! isset($snippets[$id]) || ! is_array($snippets[$id])) {
			return '';
		}

		$snippet = $snippets[$id];
		if (empty($snippet['enabled']) || ! empty($snippet['trashed']) || empty($snippet['shortcode_only']) || ! isset($snippet['language'], $this->languages[$snippet['language']])) {
			return '';
		}

		ob_start();
		$this->output_snippet($id, $snippet);
		return (string) ob_get_clean();
	}

	/**
	 * Evaluates PHP in an isolated method scope.
	 *
	 * @param string $code PHP statements.
	 * @return void
	 */
	private function run_php_code($code)
	{
		eval($code); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Executing admin-authored PHP is an explicit module feature.
	}

	/**
	 * Prints the snippet in its selected language.
	 *
	 * @param string $id      Snippet identifier.
	 * @param array  $snippet Snippet settings.
	 * @return void
	 */
	private function output_snippet($id, $snippet)
	{
		$code = isset($snippet['code']) ? $snippet['code'] : '';
		if ('' === $code) {
			return;
		}

		$devices = $this->get_snippet_devices($snippet);

		switch ($snippet['language']) {
			case 'html':
				$this->output_html_for_devices($code, $id, $devices);
				break;
			case 'css':
				if (array('all') !== $devices) {
					$queries = $this->get_device_queries($devices);
					$code    = '@media screen and ' . implode(', screen and ', $queries) . " {\n" . $code . "\n}";
				}
				echo "\n<style>\n" . $code . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-authored CSS is the feature's purpose.
				break;
			case 'js':
				if (array('all') !== $devices) {
					$checks = array();
					foreach ($this->get_device_queries($devices) as $query) {
						$checks[] = 'window.matchMedia(' . wp_json_encode($query) . ').matches';
					}
					$code = 'if (' . implode(' || ', $checks) . ") {\n" . $code . "\n}";
				}
				echo "\n<script>\n" . $code . "\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-authored JavaScript is the feature's purpose.
				break;
			case 'php':
				if (! empty($snippet['shortcode_only'])) {
					$output = $this->evaluate_php_snippet(
						$id,
						$code,
						isset($snippet['title']) && is_string($snippet['title']) ? $snippet['title'] : $id
					);
				} else {
					$output = isset($this->php_output[$id]) ? $this->php_output[$id] : '';
				}
				echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PHP snippets intentionally generate front-end output.
				break;
			case 'shortcode':
				$this->output_html_for_devices(do_shortcode($code), $id, $devices);
				break;
		}
	}

	/**
	 * Outputs HTML immediately or only at matching viewport widths.
	 *
	 * @param string   $html    Rendered HTML.
	 * @param string   $id      Snippet identifier.
	 * @param string[] $devices Selected device groups.
	 * @return void
	 */
	private function output_html_for_devices($html, $id, $devices)
	{
		if (array('all') === $devices) {
			echo "\n" . $html . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-authored HTML/shortcode output is the feature's purpose.
			return;
		}

		$queries = wp_json_encode($this->get_device_queries($devices));
		echo "\n<template data-wa-suit-device-html>" . $html . "</template>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-authored HTML/shortcode output is the feature's purpose.
		echo '<script>(function(t,q){if(q.some(function(m){return window.matchMedia(m).matches;})){var f=t.content.cloneNode(true);f.querySelectorAll("script").forEach(function(o){var s=document.createElement("script");Array.from(o.attributes).forEach(function(a){s.setAttribute(a.name,a.value);});s.textContent=o.textContent;o.parentNode.replaceChild(s,o);});t.parentNode.insertBefore(f,t);}t.remove();})(document.currentScript.previousElementSibling,' . $queries . ");</script>\n";
	}

	/**
	 * Converts selected device names into viewport media queries.
	 *
	 * @param string[] $devices Selected device names.
	 * @return string[]
	 */
	private function get_device_queries($devices)
	{
		$queries = array();
		$map     = array(
			'mobile'  => '(max-width: 767px)',
			'tablet'  => '(min-width: 768px) and (max-width: 1024px)',
			'desktop' => '(min-width: 1025px)',
		);

		foreach ($devices as $device) {
			if (isset($map[$device])) {
				$queries[] = $map[$device];
			}
		}

		return $queries;
	}

	/**
	 * Returns validated device values for a saved snippet.
	 *
	 * @param array $snippet Snippet settings.
	 * @return string[]
	 */
	private function get_snippet_devices($snippet)
	{
		$devices = isset($snippet['devices']) && is_array($snippet['devices'])
			? array_values(array_intersect($snippet['devices'], array_keys($this->devices)))
			: array('all');

		if (empty($devices) || in_array('all', $devices, true)) {
			return array('all');
		}

		return $devices;
	}

	/**
	 * Returns all URL patterns, including the legacy single-pattern setting.
	 *
	 * @param array $snippet Snippet settings.
	 * @return string[]
	 */
	private function get_url_matches($snippet)
	{
		if (isset($snippet['url_matches']) && is_array($snippet['url_matches']) && ! empty($snippet['url_matches'])) {
			$patterns = $snippet['url_matches'];
		} elseif (isset($snippet['url_match']) && is_string($snippet['url_match'])) {
			$patterns = array($snippet['url_match']);
		} elseif (isset($snippet['url_matches']) && is_array($snippet['url_matches'])) {
			$patterns = $snippet['url_matches'];
		} else {
			$patterns = array();
		}

		return array_values(
			array_filter(
				array_map(
					'trim',
					array_filter(
						$patterns,
						function ($pattern) {
							return is_string($pattern);
						}
					)
				),
				function ($pattern) {
					return '' !== $pattern;
				}
			)
		);
	}

	/**
	 * Returns the longest URL pattern matching the current request.
	 *
	 * @param array $snippet Snippet settings.
	 * @return int
	 */
	private function get_matching_url_specificity($snippet)
	{
		$request_uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
			? wp_unslash($_SERVER['REQUEST_URI'])
			: '';
		$specificity = 0;

		foreach ($this->get_url_matches($snippet) as $pattern) {
			if (false !== stripos($request_uri, $pattern)) {
				$specificity = max($specificity, strlen($pattern));
			}
		}

		return $specificity;
	}

	/**
	 * Executes a PHP snippet and returns its captured output.
	 *
	 * PHP snippets may contain statements with or without outer PHP tags.
	 *
	 * @param string $id   Snippet identifier.
	 * @param string $code PHP statements.
	 * @return string
	 */
	private function evaluate_php_snippet($id, $code, $title = '')
	{
		if (array_key_exists($id, $this->php_execution_results)) {
			return $this->php_execution_results[$id];
		}

		$normalized_code = $this->normalize_php_code($code);
		$conflict = $this->find_declared_function_conflict($normalized_code);
		if ('' !== $conflict) {
			$this->disable_conflicting_snippet($id, $conflict);
			$this->php_execution_results[$id] = '';
			return $this->php_execution_results[$id];
		}

		$buffer_level = ob_get_level();
		ob_start();
		$has_previous_context = array_key_exists('wa_suit_current_php_snippet', $GLOBALS);
		$previous_context = $has_previous_context ? $GLOBALS['wa_suit_current_php_snippet'] : null;
		$GLOBALS['wa_suit_current_php_snippet'] = array(
			'id'    => sanitize_key($id),
			'title' => sanitize_text_field($title),
		);

		try {
			$this->run_php_code($normalized_code);
			$output = ob_get_clean();
			$this->php_execution_results[$id] = is_string($output) ? $output : '';
		} catch (Throwable $error) {
			while (ob_get_level() > $buffer_level) {
				ob_end_clean();
			}

			WA_Suit_Debug::handle_snippet_failure($id, $title, $error->getMessage());
			error_log(
				sprintf(
					'WA Suit PHP snippet "%s" failed: %s',
					sanitize_key($id),
					$error->getMessage()
				)
			);
			$this->php_execution_results[$id] = '';
		} finally {
			if ($has_previous_context) {
				$GLOBALS['wa_suit_current_php_snippet'] = $previous_context;
			} else {
				unset($GLOBALS['wa_suit_current_php_snippet']);
			}
		}

		return $this->php_execution_results[$id];
	}

	/**
	 * Finds global named functions that already exist or are repeated in this snippet.
	 *
	 * @param string $code Normalized PHP code.
	 * @return string
	 */
	private function find_declared_function_conflict($code)
	{
		$tokens = token_get_all('<?php ' . $code);
		$declared = array();
		$class_depths = array();
		$brace_depth = 0;
		$pending_class = false;
		$previous_significant_token = null;
		$token_count = count($tokens);

		for ($index = 0; $index < $token_count; $index++) {
			$token = $tokens[$index];
			$token_id = is_array($token) ? $token[0] : null;
			$token_text = is_array($token) ? $token[1] : $token;

			if (is_array($token) && in_array($token_id, array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				continue;
			}

			$class_tokens = array(T_CLASS, T_INTERFACE, T_TRAIT);
			if (defined('T_ENUM')) {
				$class_tokens[] = constant('T_ENUM');
			}

			if (
				is_array($token) &&
				in_array($token_id, $class_tokens, true) &&
				(! is_array($previous_significant_token) || T_DOUBLE_COLON !== $previous_significant_token[0])
			) {
				$pending_class = true;
			}

			if (is_array($token) && T_FUNCTION === $token_id && empty($class_depths)) {
				$name_index = $index + 1;
				while ($name_index < $token_count) {
					$name_token = $tokens[$name_index];
					if (is_array($name_token) && in_array($name_token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
						$name_index++;
						continue;
					}
					if ('&' === (is_array($name_token) ? $name_token[1] : $name_token)) {
						$name_index++;
						continue;
					}
					break;
				}

				if ($name_index < $token_count && is_array($tokens[$name_index]) && T_STRING === $tokens[$name_index][0]) {
					$function_name = $tokens[$name_index][1];
					$function_key = strtolower($function_name);
					if (isset($declared[$function_key]) || function_exists($function_name)) {
						return $function_name;
					}
					$declared[$function_key] = true;
				}
			}

			if ('{' === $token_text) {
				$brace_depth++;
				if ($pending_class) {
					$class_depths[] = $brace_depth;
					$pending_class = false;
				}
			} elseif ('}' === $token_text) {
				if (! empty($class_depths) && end($class_depths) === $brace_depth) {
					array_pop($class_depths);
				}
				$brace_depth = max(0, $brace_depth - 1);
			}

			$previous_significant_token = $token;
		}

		return '';
	}

	/**
	 * Disables a conflicting snippet and queues an administrator warning.
	 *
	 * @param string $id            Snippet identifier.
	 * @param string $function_name Conflicting function name.
	 * @return void
	 */
	private function disable_conflicting_snippet($id, $function_name)
	{
		$settings = $this->get_settings();
		$snippets = isset($settings['snippets']) && is_array($settings['snippets']) ? $settings['snippets'] : array();
		if (! isset($snippets[$id]) || ! is_array($snippets[$id])) {
			return;
		}

		$snippets[$id]['enabled'] = '0';
		$this->update_settings(array('snippets' => $snippets));

		$notices = get_option('wa_suit_code_snippet_conflict_notices', array());
		if (! is_array($notices)) {
			$notices = array();
		}

		$title = isset($snippets[$id]['title']) && is_string($snippets[$id]['title'])
			? $snippets[$id]['title']
			: $id;
		$notice_key = md5($id . '|' . $function_name);

		foreach ($notices as $notice) {
			if (is_array($notice) && isset($notice['key']) && $notice_key === $notice['key']) {
				return;
			}
		}

		$notices[] = array(
			'key'      => $notice_key,
			'title'    => $title,
			'function' => $function_name,
		);
		update_option('wa_suit_code_snippet_conflict_notices', array_slice($notices, -10), false);
	}

	/**
	 * Displays and clears queued PHP function conflict warnings.
	 *
	 * @return void
	 */
	public function render_php_conflict_notices()
	{
		if (! WA_Suit_Security::can_manage_settings()) {
			return;
		}

		$notices = get_option('wa_suit_code_snippet_conflict_notices', array());
		if (is_array($notices)) {
			foreach ($notices as $notice) {
				if (! is_array($notice) || ! isset($notice['title'], $notice['function'])) {
					continue;
				}

				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					esc_html(
						sprintf(
							__('Por segurança, o código “%1$s” foi desativado porque a função PHP “%2$s” já está declarada. Isso costuma acontecer quando o mesmo código também está ativo em outro plugin, como o WPCode. Desative a cópia duplicada antes de reativar este código.', 'wa-suit'),
							$notice['title'],
							$notice['function']
						)
					)
				);
			}
		}

		delete_option('wa_suit_code_snippet_conflict_notices');

		$failure_notices = get_option('wa_suit_php_snippet_failure_notices', array());
		if (! is_array($failure_notices) || empty($failure_notices)) {
			return;
		}

		foreach ($failure_notices as $notice) {
			if (! is_array($notice) || ! isset($notice['title'], $notice['message'])) {
				continue;
			}

			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						__('O código PHP “%1$s” foi desativado após uma falha: %2$s', 'wa-suit'),
						$notice['title'],
						$notice['message']
					)
				)
			);
		}

		delete_option('wa_suit_php_snippet_failure_notices');
	}

	/**
	 * Removes optional outer PHP tags from code entered in the editor.
	 *
	 * @param string $code PHP code.
	 * @return string
	 */
	private function normalize_php_code($code)
	{
		$code = trim($code);
		if (preg_match('/\A```(?:php)?[ \t]*\R(.*)\R```[ \t]*\z/is', $code, $matches)) {
			$code = $matches[1];
		}

		$code = preg_replace('/\A(?:\xEF\xBB\xBF)?\s*<\?php\b/i', '', $code, 1);
		$code = preg_replace('/\?>\s*\z/', '', $code, 1);

		return is_string($code) ? trim($code) : '';
	}

	/**
	 * Checks PHP syntax without executing the snippet.
	 *
	 * @param string $code PHP code.
	 * @return string Empty when syntax is valid, otherwise the parser message.
	 */
	private function get_php_syntax_error($code)
	{
		try {
			token_get_all('<?php ' . $this->normalize_php_code($code), TOKEN_PARSE);
		} catch (ParseError $error) {
			return $error->getMessage();
		}

		return '';
	}

	/**
	 * Checks whether a snippet's page or URL rule matches the current request.
	 *
	 * @param array $snippet Snippet settings.
	 * @return bool
	 */
	private function matches_target($snippet)
	{
		$display_on = $this->get_snippet_display_targets($snippet);
		if (in_array('all', $display_on, true)) {
			return true;
		}

		$ajax_post_type = '';
		if ($this->is_frontend_ajax_request()) {
			$referer = wp_get_referer();
			$post_id = is_string($referer) ? url_to_postid($referer) : 0;
			$ajax_post_type = $post_id ? get_post_type($post_id) : '';
		}

		if (in_array('posts', $display_on, true) && (is_singular('post') || 'post' === $ajax_post_type)) {
			return true;
		}

		if (in_array('pages', $display_on, true) && (is_page() || 'page' === $ajax_post_type)) {
			return true;
		}

		if (in_array('category', $display_on, true) && is_category()) {
			return true;
		}

		if (in_array('search', $display_on, true) && is_search()) {
			return true;
		}

		if (in_array('tag_archive', $display_on, true) && (is_tag() || (is_archive() && ! is_category()))) {
			return true;
		}

		if (in_array('product', $display_on, true) && (is_singular('product') || 'product' === $ajax_post_type)) {
			return true;
		}

		if (in_array('url_contains', $display_on, true)) {
			if ($this->is_frontend_ajax_request()) {
				$referer     = wp_get_referer();
				$request_uri = is_string($referer) ? wp_parse_url($referer, PHP_URL_PATH) : '';
				$query       = is_string($referer) ? wp_parse_url($referer, PHP_URL_QUERY) : '';
				if (is_string($request_uri) && is_string($query) && '' !== $query) {
					$request_uri .= '?' . $query;
				}
			} else {
				$request_uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
			}
			$patterns    = $this->get_url_matches($snippet);

			foreach ($patterns as $pattern) {
				if (is_string($pattern) && '' !== $pattern && false !== stripos($request_uri, $pattern)) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Renders the controls for one snippet.
	 *
	 * @param string $key     Snippet identifier.
	 * @param array  $snippet Snippet values.
	 * @return void
	 */
	private function render_snippet_fields($key, $snippet)
	{
		$targets = $this->get_available_targets();
		$snippet = wp_parse_args(
			$snippet,
			array(
				'title'     => '',
				'enabled'   => '1',
				'language'  => 'html',
				'location'  => 'footer',
				'target'    => 'all',
				'url_match' => '',
				'shortcode_only' => '0',
				'url_matches' => array(),
				'devices'   => array('all'),
				'code'      => '',
			)
		);
		$snippet['display_on'] = $this->get_snippet_display_targets($snippet);
		$snippet['url_matches'] = $this->get_url_matches($snippet);
		if (! is_array($snippet['url_matches'])) {
			$snippet['url_matches'] = array();
		}
		if (! is_array($snippet['devices']) || empty($snippet['devices'])) {
			$snippet['devices'] = array('all');
		}
		$created_by_name = isset($snippet['created_by_name']) && is_string($snippet['created_by_name'])
			? $snippet['created_by_name']
			: '';
		$created_by_id = isset($snippet['created_by_id']) ? absint($snippet['created_by_id']) : 0;
		if ($created_by_id) {
			$creator = get_userdata($created_by_id);
			if ($creator instanceof WP_User) {
				$created_by_name = $creator->display_name;
			}
		}
		$created_at = isset($snippet['created_at']) && is_string($snippet['created_at'])
			? $snippet['created_at']
			: '';
		$prefix  = 'snippets[' . $key . ']';
		$is_trashed = ! empty($snippet['trashed']);
		$duplicate_url = '__SNIPPET_KEY__' !== $key && ! $is_trashed
			? wp_nonce_url(
				add_query_arg(
					array(
						'action'     => 'wa_suit_duplicate_code_snippet',
						'snippet_id' => $key,
					),
					admin_url('admin-post.php')
				),
				'wa_suit_duplicate_code_snippet_' . $key
			)
			: '';
		$management_operation = $is_trashed ? 'restore' : 'trash';
		$management_url = '__SNIPPET_KEY__' !== $key
			? wp_nonce_url(
				add_query_arg(
					array(
						'action'     => 'wa_suit_manage_code_snippets',
						'operation'  => $management_operation,
						'snippet_id' => $key,
					),
					admin_url('admin-post.php')
				),
				'wa_suit_manage_code_snippet_' . $key . '_' . $management_operation
			)
			: '';
		$delete_url = $is_trashed && '__SNIPPET_KEY__' !== $key
			? wp_nonce_url(
				add_query_arg(
					array(
						'action'     => 'wa_suit_manage_code_snippets',
						'operation'  => 'delete',
						'snippet_id' => $key,
					),
					admin_url('admin-post.php')
				),
				'wa_suit_manage_code_snippet_' . $key . '_delete'
			)
			: '';
	?>
		<details class="wa-code-snippet" data-wa-snippet data-wa-snippet-id="<?php echo esc_attr($key); ?>" data-wa-snippet-language="<?php echo esc_attr($snippet['language']); ?>" data-wa-snippet-location="<?php echo esc_attr($snippet['location']); ?>" data-wa-snippet-trashed="<?php echo $is_trashed ? '1' : '0'; ?>">
			<summary class="wa-code-snippet__summary">
				<strong data-wa-snippet-title><?php echo esc_html('' !== $snippet['title'] ? $snippet['title'] : __('Novo código', 'wa-suit')); ?></strong>
				<span data-wa-snippet-summary><?php echo esc_html($this->snippet_summary($snippet)); ?></span>
				<span class="wa-code-snippet__type"><?php echo esc_html(isset($this->languages[$snippet['language']]) ? $this->languages[$snippet['language']] : __('Código', 'wa-suit')); ?></span>
				<?php if ('' !== $duplicate_url) : ?>
					<a class="wa-code-snippet__duplicate" href="<?php echo esc_url($duplicate_url); ?>">
						<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
						<?php echo esc_html__('Duplicar', 'wa-suit'); ?>
					</a>
				<?php endif; ?>
				<?php if ('' !== $management_url) : ?>
					<a class="wa-code-snippet__row-action <?php echo $is_trashed ? 'is-restore' : 'is-trash'; ?>" href="<?php echo esc_url($management_url); ?>" data-wa-snippet-action>
						<span class="dashicons <?php echo $is_trashed ? 'dashicons-undo' : 'dashicons-trash'; ?>" aria-hidden="true"></span>
						<?php echo $is_trashed ? esc_html__('Restaurar', 'wa-suit') : esc_html__('Lixeira', 'wa-suit'); ?>
					</a>
				<?php endif; ?>
				<?php if ('__SNIPPET_KEY__' === $key) : ?>
					<button type="button" class="wa-code-snippet__row-action is-trash" data-wa-snippet-draft-remove aria-label="<?php echo esc_attr__('Remover código não salvo', 'wa-suit'); ?>">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php echo esc_html__('Lixeira', 'wa-suit'); ?>
					</button>
				<?php endif; ?>
				<?php if ('' !== $delete_url) : ?>
					<a class="wa-code-snippet__row-action is-delete" href="<?php echo esc_url($delete_url); ?>" data-wa-snippet-action data-wa-confirm-delete>
						<span class="dashicons dashicons-no" aria-hidden="true"></span>
						<?php echo esc_html__('Excluir', 'wa-suit'); ?>
					</a>
				<?php endif; ?>
				<span class="wa-code-snippet__switch-wrap" data-wa-snippet-switch-wrap>
					<input type="hidden" name="<?php echo esc_attr($prefix); ?>[enabled]" value="0">
					<input class="wa-code-snippet__switch" type="checkbox" name="<?php echo esc_attr($prefix); ?>[enabled]" value="1" data-wa-snippet-enabled data-snippet-id="<?php echo esc_attr($key); ?>" data-snippet-persisted="<?php echo esc_attr('__SNIPPET_KEY__' === $key || $is_trashed ? '0' : '1'); ?>" aria-label="<?php echo esc_attr(sprintf(__('Ativar %s', 'wa-suit'), $snippet['title'])); ?>" <?php checked('1', (string) $snippet['enabled']); ?> <?php disabled($is_trashed); ?>>
					<span class="wa-code-snippet__switch-ui" aria-hidden="true"></span>
					<span class="wa-code-snippet__switch-status" data-wa-snippet-enabled-label><?php echo $is_trashed ? esc_html__('Na lixeira', 'wa-suit') : ('1' === (string) $snippet['enabled'] ? 'ON' : 'OFF'); ?></span>
				</span>
			</summary>
			<div class="wa-code-snippet__content">
				<?php if ('__SNIPPET_KEY__' !== $key) : ?>
					<p class="wa-code-snippet__metadata">
						<?php
						if ('' !== $created_at) {
							printf(
								/* translators: 1: creator name, 2: creation date and time. */
								esc_html__('Criado por %1$s em %2$s', 'wa-suit'),
								esc_html('' !== $created_by_name ? $created_by_name : __('Usuário removido', 'wa-suit')),
								esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $created_at, false))
							);
						} else {
							echo esc_html__('Autor e data serão registrados ao salvar este código.', 'wa-suit');
						}
						?>
					</p>
				<?php endif; ?>
				<div class="wa-code-snippet__grid">
					<label>
						<span><?php echo esc_html__('Nome', 'wa-suit'); ?></span>
						<input type="text" class="regular-text" name="<?php echo esc_attr($prefix); ?>[title]" value="<?php echo esc_attr($snippet['title']); ?>" data-wa-snippet-title-input placeholder="<?php echo esc_attr__('Ex.: Pixel da landing page', 'wa-suit'); ?>">
					</label>
					<label>
						<span><?php echo esc_html__('Linguagem', 'wa-suit'); ?></span>
						<select name="<?php echo esc_attr($prefix); ?>[language]" data-wa-snippet-language>
							<?php foreach ($this->languages as $language => $label) : ?>
								<option value="<?php echo esc_attr($language); ?>" <?php selected($snippet['language'], $language); ?>><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label data-wa-snippet-location-wrap>
						<span><?php echo esc_html__('Posição no site', 'wa-suit'); ?></span>
						<select name="<?php echo esc_attr($prefix); ?>[location]" data-wa-snippet-location>
							<?php foreach ($this->locations as $location => $label) : ?>
								<option value="<?php echo esc_attr($location); ?>" <?php selected($snippet['location'], $location); ?>><?php echo esc_html($label); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<fieldset class="wa-code-snippet__field wa-code-snippet__targets" data-wa-snippet-targets>
						<legend class="wa-code-snippet__field-label"><?php echo esc_html__('Exibir em', 'wa-suit'); ?></legend>
						<div class="wa-code-snippet__target-options">
							<?php foreach ($targets as $target => $label) : ?>
								<label class="wa-code-snippet__target-option">
									<input type="checkbox" name="<?php echo esc_attr($prefix); ?>[display_on][]" value="<?php echo esc_attr($target); ?>" data-wa-display-target <?php checked(in_array($target, $snippet['display_on'], true)); ?>>
									<span><?php echo esc_html($label); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<small><?php echo esc_html__('Selecione um ou mais tipos de página. “Site inteiro” não pode ser combinado com outras opções.', 'wa-suit'); ?></small>
					</fieldset>
					<div class="wa-code-snippet__field" data-wa-snippet-url <?php echo in_array('url_contains', $snippet['display_on'], true) ? '' : 'hidden'; ?>>
						<span class="wa-code-snippet__field-label"><?php echo esc_html__('URL contém', 'wa-suit'); ?></span>
						<div class="wa-code-snippet__url-fields" data-wa-url-fields data-wa-url-name="<?php echo esc_attr($prefix . '[url_matches][]'); ?>">
							<?php if (empty($snippet['url_matches'])) : ?>
								<div class="wa-code-snippet__url-row" data-wa-url-row>
									<input type="text" name="<?php echo esc_attr($prefix); ?>[url_matches][]" value="" data-wa-url-value placeholder="<?php echo esc_attr__('Ex.: campanha ou /promocao/', 'wa-suit'); ?>" aria-label="<?php echo esc_attr__('Texto que a URL deve conter', 'wa-suit'); ?>">
									<button type="button" class="wa-code-snippet__url-remove" data-wa-url-remove aria-label="<?php echo esc_attr__('Remover condição de URL', 'wa-suit'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
								</div>
							<?php else : ?>
								<?php foreach ($snippet['url_matches'] as $url_match) : ?>
									<div class="wa-code-snippet__url-row" data-wa-url-row>
										<input type="text" name="<?php echo esc_attr($prefix); ?>[url_matches][]" value="<?php echo esc_attr($url_match); ?>" data-wa-url-value placeholder="<?php echo esc_attr__('Ex.: campanha ou /promocao/', 'wa-suit'); ?>" aria-label="<?php echo esc_attr__('Texto que a URL deve conter', 'wa-suit'); ?>">
										<button type="button" class="wa-code-snippet__url-remove" data-wa-url-remove aria-label="<?php echo esc_attr__('Remover condição de URL', 'wa-suit'); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
						<button type="button" class="wa-code-snippet__url-add" data-wa-url-add>
							<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
							<?php echo esc_html__('Adicionar condição', 'wa-suit'); ?>
						</button>
						<small><?php echo esc_html__('O código será carregado quando a URL contiver qualquer um destes textos. Adicione uma condição para cada caminho ou palavra.', 'wa-suit'); ?></small>
					</div>
					<fieldset class="wa-code-snippet__field wa-code-snippet__shortcode">
						<legend class="wa-code-snippet__field-label"><?php echo esc_html__('Modo de exibição', 'wa-suit'); ?></legend>
						<label class="wa-code-snippet__target-option">
							<input type="hidden" name="<?php echo esc_attr($prefix); ?>[shortcode_only]" value="0">
							<input type="checkbox" name="<?php echo esc_attr($prefix); ?>[shortcode_only]" value="1" data-wa-shortcode-only <?php checked('1', (string) $snippet['shortcode_only']); ?>>
							<span><?php echo esc_html__('Somente por shortcode (não inserir automaticamente no site)', 'wa-suit'); ?></span>
						</label>
						<small data-wa-generated-shortcode <?php echo '1' === (string) $snippet['shortcode_only'] ? '' : 'hidden'; ?>>
							<?php echo esc_html__('Shortcode deste código:', 'wa-suit'); ?>
							<code>[wa_suit_snippet id="<?php echo esc_attr($key); ?>"]</code>
						</small>
					</fieldset>
					<fieldset class="wa-code-snippet__devices" data-wa-snippet-devices>
						<input type="hidden" name="<?php echo esc_attr($prefix); ?>[devices_submitted]" value="1">
						<legend><?php echo esc_html__('Executar em dispositivos', 'wa-suit'); ?></legend>
						<div class="wa-code-snippet__device-options">
							<?php foreach ($this->devices as $device => $label) : ?>
								<label class="wa-code-snippet__device-option">
									<input type="checkbox" name="<?php echo esc_attr($prefix); ?>[devices][]" value="<?php echo esc_attr($device); ?>" data-wa-snippet-device <?php checked(in_array($device, $snippet['devices'], true)); ?>>
									<span><?php echo esc_html($label); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
						<small data-wa-snippet-php-device-note hidden><?php echo esc_html__('PHP roda no servidor e não pode ser limitado por dispositivo. Para PHP, mantenha “Todos” selecionado.', 'wa-suit'); ?></small>
						<small><?php echo esc_html__('A seleção considera a largura da tela: celular até 767 px, tablet de 768 a 1024 px e desktop a partir de 1025 px.', 'wa-suit'); ?></small>
					</fieldset>
					<div class="wa-code-snippet__code">
						<label class="wa-code-snippet__field-label" for="<?php echo esc_attr('wa-code-' . sanitize_key((string) $key)); ?>"><?php echo esc_html__('Código', 'wa-suit'); ?></label>
						<span class="wa-code-editor-shell" data-wa-code-shell data-theme="light">
							<span class="wa-code-editor-toolbar">
								<span><?php echo esc_html__('Tema do editor', 'wa-suit'); ?></span>
								<span class="wa-code-editor-themes" role="group" aria-label="<?php echo esc_attr__('Tema do editor de código', 'wa-suit'); ?>">
									<button type="button" data-wa-code-theme="light" aria-pressed="true"><?php echo esc_html__('Claro', 'wa-suit'); ?></button>
									<button type="button" data-wa-code-theme="dark" aria-pressed="false"><?php echo esc_html__('Escuro', 'wa-suit'); ?></button>
								</span>
							</span>
							<span class="wa-code-editor" data-wa-code-editor>
								<pre class="wa-code-editor__lines" aria-hidden="true" data-wa-code-lines>1</pre>
								<textarea id="<?php echo esc_attr('wa-code-' . sanitize_key((string) $key)); ?>" name="<?php echo esc_attr($prefix); ?>[code]" rows="8" spellcheck="false" data-wa-code-input placeholder="<?php echo esc_attr__('Cole seu código aqui...', 'wa-suit'); ?>"><?php echo esc_textarea($snippet['code']); ?></textarea>
							</span>
						</span>
						<small data-wa-snippet-php-note hidden><?php echo esc_html__('PHP: você pode colar as instruções com ou sem as tags <?php e ?>. Filtros e callbacks são registrados antes da página ser montada.', 'wa-suit'); ?></small>
						<small data-wa-snippet-shortcode-note hidden><?php echo esc_html__('Cole o shortcode completo, por exemplo: [wa_link id="whatsapp"]. O resultado será inserido nesta posição.', 'wa-suit'); ?></small>
					</div>
				</div>
			</div>
		</details>
<?php
	}

	/**
	 * Validates and normalizes a submitted snippet.
	 *
	 * @param array $input Submitted snippet.
	 * @return array|null
	 */
	private function sanitize_snippet($input)
	{
		$language = isset($input['language']) && is_string($input['language']) ? sanitize_key($input['language']) : '';
		$location = isset($input['location']) && is_string($input['location']) ? sanitize_key($input['location']) : '';
		$legacy_target = isset($input['target']) && is_string($input['target']) ? sanitize_key($input['target']) : '';
		$title    = isset($input['title']) && is_string($input['title']) ? sanitize_text_field($input['title']) : '';
		$code     = isset($input['code']) && is_string($input['code']) ? $input['code'] : '';
		$raw_display_on = isset($input['display_on']) && is_array($input['display_on'])
			? $input['display_on']
			: array();
		$display_on = array_values(array_unique(array_filter(array_map(
			function ($target) {
				return is_string($target) ? sanitize_key($target) : '';
			},
			$raw_display_on
		))));
		if (! isset($input['display_on'])) {
			$display_on = $this->legacy_target_to_display_targets($legacy_target);
		}
		$raw_url_matches = isset($input['url_matches']) && is_array($input['url_matches'])
			? $input['url_matches']
			: (isset($input['url_matches']) && is_string($input['url_matches'])
				? preg_split('/\r\n|\r|\n/', $input['url_matches'])
				: (isset($input['url_match']) && is_string($input['url_match']) ? array($input['url_match']) : array()));
		$url_matches = array_values(
			array_unique(
				array_filter(
					array_map(
						function ($pattern) {
							return is_string($pattern) ? trim(sanitize_text_field($pattern)) : '';
						},
						$raw_url_matches
					),
					function ($pattern) {
						return '' !== $pattern;
					}
				)
			)
		);
		$raw_devices = isset($input['devices']) && is_array($input['devices']) ? $input['devices'] : array();
		$devices     = array();
		foreach ($raw_devices as $device) {
			if (! is_string($device)) {
				return null;
			}
			$devices[] = sanitize_key($device);
		}
		$devices = array_values(array_unique($devices));
		if (! isset($input['devices_submitted'])) {
			$devices = array('all');
		}

		$targets = $this->get_available_targets();
		$display_on = array_values(array_intersect($display_on, array_keys($targets)));
		if (in_array('all', $display_on, true)) {
			$display_on = array('all');
		}
		$shortcode_only = isset($input['shortcode_only']) && is_scalar($input['shortcode_only']) && '1' === (string) $input['shortcode_only'];
		if (! isset($this->languages[$language], $this->locations[$location]) || '' === trim($title) || '' === trim($code) || empty($devices) || (! $shortcode_only && empty($display_on))) {
			return null;
		}

		$devices = array_values(array_intersect($devices, array_keys($this->devices)));
		if (empty($devices)) {
			return null;
		}

		if (in_array('all', $devices, true)) {
			$devices = array('all');
		}

		if ('php' === $language && array('all') !== $devices) {
			return null;
		}

		if (! $shortcode_only && 'html' === $language && array('all') !== $devices && 'head' === $location) {
			return null;
		}

		if (! $shortcode_only && 'shortcode' === $language && 'head' === $location) {
			return null;
		}

		if (! $shortcode_only && in_array('url_contains', $display_on, true) && empty($url_matches)) {
			return null;
		}

		return array(
			'title'       => $title,
			'enabled'     => isset($input['enabled']) && is_scalar($input['enabled']) && '1' === (string) $input['enabled'] ? '1' : '0',
			'language'    => $language,
			'location'    => $location,
			'display_on'  => $display_on,
			'shortcode_only' => $shortcode_only ? '1' : '0',
			'url_matches' => $url_matches,
			'devices'     => $devices,
			'code'        => $code,
		);
	}

	/**
	 * Returns a compact display summary for the editor card.
	 *
	 * @param array $snippet Snippet settings.
	 * @return string
	 */
	private function snippet_summary($snippet)
	{
		$targets = $this->get_available_targets();
		$display_on = $this->get_snippet_display_targets($snippet);
		$labels = array_intersect_key($targets, array_flip($display_on));
		$label = implode(', ', $labels);
		if ('' === $label && empty($snippet['shortcode_only'])) {
			$label = $targets['all'];
		} elseif ('' === $label) {
			$label = __('Inserção por shortcode', 'wa-suit');
		}

		if (in_array('url_contains', $display_on, true)) {
			$patterns = $this->get_url_matches($snippet);
			if (! empty($patterns)) {
				$label .= ': ' . implode(', ', $patterns);
			}
		}
		if (! empty($snippet['shortcode_only'])) {
			$label = __('Somente shortcode', 'wa-suit') . ' · ' . $label;
		}

		$devices = $this->get_snippet_devices($snippet);
		if (array('all') !== $devices) {
			$labels = array_intersect_key($this->devices, array_flip($devices));
			$label .= ' · ' . implode(', ', $labels);
		}

		return $label;
	}

	/**
	 * Returns display conditions supported by this site.
	 *
	 * @return array<string, string>
	 */
	private function get_available_targets()
	{
		$targets = $this->targets;
		if (class_exists('WooCommerce') && post_type_exists('product')) {
			$targets['product'] = __('Produtos', 'wa-suit');
		}

		return $targets;
	}

	/**
	 * Gets the display contexts, translating settings saved by older versions.
	 *
	 * @param array $snippet Snippet settings.
	 * @return string[]
	 */
	private function get_snippet_display_targets($snippet)
	{
		if (isset($snippet['display_on']) && is_array($snippet['display_on'])) {
			$targets = array_values(array_filter($snippet['display_on'], 'is_string'));
		} else {
			$legacy_target = isset($snippet['target']) && is_string($snippet['target'])
				? sanitize_key($snippet['target'])
				: 'all';
			$targets = $this->legacy_target_to_display_targets($legacy_target);
		}

		$targets = array_values(array_intersect($targets, array_keys($this->get_available_targets())));
		return in_array('all', $targets, true) ? array('all') : $targets;
	}

	/**
	 * Converts a target value from the previous settings format.
	 *
	 * @param string $target Legacy target value.
	 * @return string[]
	 */
	private function legacy_target_to_display_targets($target)
	{
		$map = array(
			'all'          => array('all'),
			'page'         => array('pages', 'posts'),
			'post'         => array('posts'),
			'product'      => array('product'),
			'url_contains' => array('url_contains'),
		);

		return isset($map[$target]) ? $map[$target] : array('all');
	}
}

wa_suit_register_module(new WA_Suit_Module_Code_Snippets());
