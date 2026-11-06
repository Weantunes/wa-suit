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
		add_action('wp_head', array($this, 'render_head_snippets'), 99);
		add_action('wp_body_open', array($this, 'render_body_snippets'), 1);
		add_action('wp_footer', array($this, 'render_footer_snippets'), 99);
		add_shortcode('wa_suit_snippet', array($this, 'render_snippet_shortcode'));
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

		if ('' === $id || ! isset($snippets[$id]) || ! is_array($snippets[$id])) {
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
		$snippets  = array();

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

			$id                = sanitize_key((string) $key);
			$snippets[$id ? $id : wp_generate_uuid4()] = $snippet;
		}

		$this->update_settings(array('snippets' => $snippets));

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
		if ('' === $snippet_id || ! isset($snippets[$snippet_id]) || ! is_array($snippets[$snippet_id])) {
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
?>
		<div class="wa-code-snippets">
			<div class="wa-code-snippets__header">
				<div>
					<h2><?php echo esc_html__('Códigos Personalizados', 'wa-suit'); ?></h2>
					<p><?php echo esc_html__('Crie códigos separados e escolha onde cada um será carregado no site.', 'wa-suit'); ?></p>
				</div>
				<button type="button" class="wa-admin-btn wa-admin-btn--primary" data-wa-snippet-add>
					<?php echo esc_html__('Adicionar código', 'wa-suit'); ?>
				</button>
			</div>

			<?php if (isset($_GET['snippets-saved']) && is_string($_GET['snippets-saved']) && '1' === sanitize_text_field(wp_unslash($_GET['snippets-saved']))) : ?>
				<div class="notice notice-success inline">
					<p><?php echo esc_html__('Códigos salvos.', 'wa-suit'); ?></p>
				</div>
			<?php endif; ?>
			<?php if (isset($_GET['snippet-duplicated']) && is_string($_GET['snippet-duplicated']) && '1' === sanitize_text_field(wp_unslash($_GET['snippet-duplicated']))) : ?>
				<div class="notice notice-success inline">
					<p><?php echo esc_html__('Cópia criada desativada. Revise o código e ative quando estiver pronto.', 'wa-suit'); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-wa-snippet-form>
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

				<template data-wa-snippet-template>
					<?php $this->render_snippet_fields('__SNIPPET_KEY__', array()); ?>
				</template>

				<div class="wa-code-snippets__actions">
					<button type="submit" class="wa-admin-btn wa-admin-btn--primary">
						<?php echo esc_html__('Salvar códigos', 'wa-suit'); ?>
					</button>
				</div>
			</form>

			<div class="notice notice-warning inline">
				<p><?php echo esc_html__('HTML, CSS, JavaScript e o resultado de shortcodes são enviados ao navegador. PHP executa no servidor para todos os visitantes que correspondam à regra escolhida, com acesso às funções do WordPress. PHP não pode ser limitado por dispositivo. Use apenas código confiável e mantenha um backup.', 'wa-suit'); ?></p>
			</div>

			<div class="notice notice-info inline">
				<p><?php echo esc_html__('Quando mais de uma regra corresponder, a regra de URL mais específica tem prioridade; sem correspondência de URL, o tipo de página selecionado tem prioridade sobre “Site inteiro”. Regras igualmente específicas podem ser exibidas juntas. Códigos marcados como somente shortcode só aparecem onde o shortcode gerado for usado.', 'wa-suit'); ?></p>
			</div>

		</div>
	<?php
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
			if (! is_array($snippet) || empty($snippet['enabled']) || ! empty($snippet['shortcode_only']) || ! isset($snippet['location'], $snippet['language']) || $location !== $snippet['location'] || ! isset($this->languages[$snippet['language']]) || ! $this->matches_target($snippet)) {
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
		if (empty($snippet['enabled']) || empty($snippet['shortcode_only']) || ! isset($snippet['language'], $this->languages[$snippet['language']])) {
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
				$this->execute_php_snippet($id, $code);
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
	 * Executes a PHP snippet and logs runtime errors without exposing details to visitors.
	 *
	 * PHP snippets must contain statements without opening or closing PHP tags.
	 *
	 * @param string $id   Snippet identifier.
	 * @param string $code PHP statements.
	 * @return void
	 */
	private function execute_php_snippet($id, $code)
	{
		$buffer_level = ob_get_level();
		ob_start();

		try {
			$this->run_php_code($code);
			$output = ob_get_clean();
			echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PHP snippets intentionally generate front-end output.
		} catch (Throwable $error) {
			while (ob_get_level() > $buffer_level) {
				ob_end_clean();
			}

			error_log(
				sprintf(
					'WA Suit PHP snippet "%s" failed: %s',
					sanitize_key($id),
					$error->getMessage()
				)
			);
		}
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

		if (in_array('posts', $display_on, true) && is_singular('post')) {
			return true;
		}

		if (in_array('pages', $display_on, true) && is_page()) {
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

		if (in_array('product', $display_on, true) && is_singular('product')) {
			return true;
		}

		if (in_array('url_contains', $display_on, true)) {
			$request_uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
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
		$prefix  = 'snippets[' . $key . ']';
		$duplicate_url = '__SNIPPET_KEY__' !== $key
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
	?>
		<details class="wa-code-snippet" data-wa-snippet>
			<summary class="wa-code-snippet__summary">
				<strong data-wa-snippet-title><?php echo esc_html('' !== $snippet['title'] ? $snippet['title'] : __('Novo código', 'wa-suit')); ?></strong>
				<span data-wa-snippet-summary><?php echo esc_html($this->snippet_summary($snippet)); ?></span>
				<?php if ('' !== $duplicate_url) : ?>
					<a class="wa-code-snippet__duplicate" href="<?php echo esc_url($duplicate_url); ?>">
						<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
						<?php echo esc_html__('Duplicar', 'wa-suit'); ?>
					</a>
				<?php endif; ?>
				<span class="wa-code-snippet__switch-wrap" data-wa-snippet-switch-wrap>
					<input type="hidden" name="<?php echo esc_attr($prefix); ?>[enabled]" value="0">
					<input class="wa-code-snippet__switch" type="checkbox" name="<?php echo esc_attr($prefix); ?>[enabled]" value="1" data-wa-snippet-enabled data-snippet-id="<?php echo esc_attr($key); ?>" data-snippet-persisted="<?php echo esc_attr('__SNIPPET_KEY__' === $key ? '0' : '1'); ?>" aria-label="<?php echo esc_attr(sprintf(__('Ativar %s', 'wa-suit'), $snippet['title'])); ?>" <?php checked('1', (string) $snippet['enabled']); ?>>
					<span class="wa-code-snippet__switch-ui" aria-hidden="true"></span>
					<span class="wa-code-snippet__switch-status" data-wa-snippet-enabled-label><?php echo '1' === (string) $snippet['enabled'] ? esc_html__('Ativo', 'wa-suit') : esc_html__('Inativo', 'wa-suit'); ?></span>
				</span>
			</summary>
			<div class="wa-code-snippet__content">
				<div class="wa-code-snippet__top">
					<button type="button" class="wa-code-snippet__remove" data-wa-snippet-remove aria-label="<?php echo esc_attr__('Remover código', 'wa-suit'); ?>" title="<?php echo esc_attr__('Remover código', 'wa-suit'); ?>">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php echo esc_html__('Remover código', 'wa-suit'); ?></span>
					</button>
				</div>

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
						<small data-wa-snippet-php-note hidden><?php echo esc_html__('PHP: informe apenas as instruções, sem as tags de abertura ou fechamento do PHP.', 'wa-suit'); ?></small>
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
