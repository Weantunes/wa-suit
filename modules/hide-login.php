<?php

/**
 * ============================================================
 * WA SUIT — HIDE LOGIN
 * ============================================================
 *
 * Proteção da URL de login do WordPress.
 *
 * Integração:
 * - WA_Suit_Module_Base
 * - wa_suit_register_module()
 * - WA_Suit_Security, quando disponível
 *
 * Recursos:
 * - URL de login personalizada.
 * - Bloqueio de wp-login.php para visitantes não autenticados.
 * - Redirecionamento de wp-login.php para a página inicial.
 * - Redirecionamento do painel para /404 para visitantes não autenticados.
 * - Preservação de AJAX e operações administrativas legítimas.
 * - Compatibilidade com recuperação de senha e logout.
 * - Configuração protegida por capability e nonce.
 *
 * IMPORTANTE:
 * Este módulo não substitui firewall, MFA ou proteção
 * contra tentativas de login.
 *
 * @package WASuit
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WA_Suit_Module_Hide_Login')) {

    class WA_Suit_Module_Hide_Login extends WA_Suit_Module_Base
    {

        const OPTION_SETTINGS = 'wa_suit_hide_login_settings';
        const QUERY_VAR       = 'wa_suit_hide_login';
        const DEFAULT_SLUG        = 'wa-portal';
        const LEGACY_DEFAULT_SLUG = 'acesso-wa-7x9k';

        /**
         * Evita processamento duplicado da rota personalizada.
         *
         * @var bool
         */
        private $login_route_processed = false;

        /**
         * Registers settings saving even while the front-end feature is disabled.
         */
        public function __construct()
        {
            add_action(
                'admin_post_wa_suit_save_hide_login',
                array($this, 'handle_save_settings')
            );
        }

        /**
         * Identificação do módulo.
         */
        public function get_id()
        {
            return 'hide-login';
        }

        public function get_title()
        {
            return __('Hide Login', 'wa-suit');
        }

        public function get_description()
        {
            return __(
                'Personalize a URL de acesso ao WordPress e redirecione visitantes não autenticados que acessarem as rotas antigas.',
                'wa-suit'
            );
        }

        public function get_icon()
        {
            return 'dashicons-shield-alt';
        }

        public function get_category()
        {
            return __('Segurança', 'wa-suit');
        }

        public function get_order()
        {
            return 90;
        }

        /**
         * Inicializa o módulo.
         */
        public function init()
        {

            // Registra a rota personalizada.
            add_action(
                'init',
                array($this, 'register_rewrite_rules'),
                1
            );

            add_action(
                'init',
                array($this, 'migrate_legacy_default_slug'),
                0
            );

            // Impede que o acesso ao painel revele a rota de login.
            add_action(
                'init',
                array($this, 'redirect_unauthenticated_admin'),
                0
            );

            // Registra a variável interna da rota.
            add_filter(
                'query_vars',
                array($this, 'register_query_vars')
            );

            // Executa o login pela rota personalizada.
            add_action(
                'template_redirect',
                array($this, 'handle_custom_login_route'),
                0
            );

            // Bloqueia o acesso direto à tela antiga.
            add_action(
                'login_init',
                array($this, 'redirect_legacy_login'),
                1
            );

            // Mantém os links de login apontando para a rota nova.
            add_filter(
                'login_url',
                array($this, 'filter_login_url'),
                10,
                3
            );

            add_filter(
                'lostpassword_url',
                array($this, 'filter_lostpassword_url'),
                10,
                2
            );

            // Atualiza URLs do WordPress que apontem diretamente
            // para wp-login.php, incluindo links de autenticação.
            add_filter(
                'site_url',
                array($this, 'filter_site_url'),
                10,
                4
            );

            add_filter(
                'network_site_url',
                array($this, 'filter_site_url'),
                10,
                3
            );
        }

        /**
         * Configurações padrão.
         */
        public function get_default_settings()
        {

            return array(
                'slug' => self::DEFAULT_SLUG,
            );
        }

        /**
         * Retorna as configurações armazenadas.
         */
        public function get_settings()
        {

            $saved = get_option(
                self::OPTION_SETTINGS,
                array()
            );

            if (! is_array($saved)) {
                $saved = array();
            }

            return wp_parse_args(
                $saved,
                $this->get_default_settings()
            );
        }

        /**
         * Retorna a URL personalizada de login.
         */
        public function get_login_url()
        {

            $settings = $this->get_settings();
            $slug     = $this->sanitize_login_slug(
                $settings['slug']
            );

            if ('' === (string) get_option('permalink_structure')) {
                return add_query_arg(self::QUERY_VAR, '1', home_url('/'));
            }

            return home_url('/' . $slug . '/');
        }

        /**
         * Valida o identificador da rota.
         *
         * Impede caminhos vazios, nomes reservados e caracteres
         * que poderiam causar conflitos com rotas do WordPress.
         *
         * @param string $slug Identificador informado.
         * @return string
         */
        private function sanitize_login_slug($slug)
        {

            $slug = strtolower(
                sanitize_title((string) $slug)
            );

            $slug = trim($slug, '/');

            if (
                '' === $slug ||
                strlen($slug) < 3 ||
                strlen($slug) > 80 ||
                ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)
            ) {
                return self::DEFAULT_SLUG;
            }

            $reserved = array(
                'wp-admin',
                'wp-login',
                'wp-login-php',
                'wp-content',
                'wp-includes',
                'wp-json',
                'wp-cron',
                'xmlrpc',
                'feed',
                'robots-txt',
                'sitemap-xml',
                'admin',
            );

            if (in_array($slug, $reserved, true)) {
                return self::DEFAULT_SLUG;
            }

            return $slug;
        }

        /**
         * Registra a regra de reescrita.
         *
         * A regra utiliza o slug atual, sem precisar de uma
         * rota fixa no código.
         */
        public function register_rewrite_rules()
        {

            $settings = $this->get_settings();
            $slug     = $this->sanitize_login_slug(
                $settings['slug']
            );

            if ('' === (string) get_option('permalink_structure')) {
                return;
            }

            add_rewrite_rule(
                '^' . preg_quote($slug, '/') . '/?$',
                'index.php?' . self::QUERY_VAR . '=1',
                'top'
            );
        }

        /**
         * Persists the current login route and removes old slug rules.
         *
         * @param bool   $enabled     Whether the login route is enabled.
         * @param string $previous_slug Previous saved route, if it changed.
         * @return void
         */
        public function refresh_rewrite_rules($enabled, $previous_slug = '')
        {
            if ('' === (string) get_option('permalink_structure')) {
                return;
            }

            global $wp_rewrite;

            $settings = $this->get_settings();
            $slugs    = array($settings['slug'], $previous_slug);
            foreach ($slugs as $slug) {
                if ('' === (string) $slug) {
                    continue;
                }

                $rule = '^' . preg_quote($this->sanitize_login_slug($slug), '/') . '/?$';
                unset($wp_rewrite->extra_rules_top[$rule], $wp_rewrite->extra_rules[$rule]);
            }

            if ($enabled) {
                $this->register_rewrite_rules();
            }

            flush_rewrite_rules(false);
        }

        /**
         * Atualiza a rota padrão anterior e suas regras salvas.
         *
         * @return void
         */
        public function migrate_legacy_default_slug()
        {

            $saved = get_option(
                self::OPTION_SETTINGS,
                array()
            );

            if (! is_array($saved)) {
                $saved = array();
            }

            if (
                isset($saved['slug']) &&
                self::LEGACY_DEFAULT_SLUG !== $saved['slug']
            ) {
                return;
            }

            $saved['slug'] = self::DEFAULT_SLUG;

            update_option(
                self::OPTION_SETTINGS,
                $saved,
                false
            );

            $this->refresh_rewrite_rules(
                $this->is_active(),
                self::LEGACY_DEFAULT_SLUG
            );
        }

        /**
         * Autoriza a variável de consulta personalizada.
         *
         * @param array $vars Variáveis registradas.
         * @return array
         */
        public function register_query_vars($vars)
        {

            $vars[] = self::QUERY_VAR;

            return $vars;
        }

        /**
         * Processa a URL personalizada.
         *
         * O WordPress continua responsável pela autenticação,
         * cookies, nonce e validação de senha.
         */
        public function handle_custom_login_route()
        {

            if ($this->login_route_processed) {
                return;
            }

            if (! $this->is_custom_login_request()) {
                return;
            }

            $this->login_route_processed = true;

            // Informa ao núcleo do WordPress que a tela carregada
            // é a tela oficial de autenticação.
            global $pagenow;

            $pagenow = 'wp-login.php';

            nocache_headers();

            require ABSPATH . 'wp-login.php';

            exit;
        }

        /**
         * Confirma que a variável interna veio da rota esperada.
         *
         * Em sites com links permanentes, impede que a variável
         * pública de consulta revele a tela de login por query string.
         *
         * @return bool
         */
        private function is_custom_login_request()
        {

            if ('1' !== (string) get_query_var(self::QUERY_VAR)) {
                return false;
            }

            $request_uri = isset($_SERVER['REQUEST_URI'])
                ? wp_unslash($_SERVER['REQUEST_URI'])
                : '';
            $request_path = wp_parse_url(
                $request_uri,
                PHP_URL_PATH
            );
            $login_path = wp_parse_url(
                $this->get_login_url(),
                PHP_URL_PATH
            );

            if (! is_string($request_path) || ! is_string($login_path)) {
                return false;
            }

            return untrailingslashit($request_path)
                === untrailingslashit($login_path);
        }

        /**
         * Envia visitantes não autenticados que tentam abrir o painel a /404.
         *
         * Mantém AJAX e formulários públicos do WordPress disponíveis.
         *
         * @return void
         */
        public function redirect_unauthenticated_admin()
        {

            $script_name = isset($_SERVER['SCRIPT_NAME'])
                ? basename($_SERVER['SCRIPT_NAME'])
                : '';
            $request_uri = isset($_SERVER['REQUEST_URI'])
                ? wp_unslash($_SERVER['REQUEST_URI'])
                : '';
            $request_path = wp_parse_url($request_uri, PHP_URL_PATH);
            $admin_path   = wp_parse_url(admin_url(), PHP_URL_PATH);
            $is_admin_path = is_string($request_path)
                && is_string($admin_path)
                && 0 === strpos(
                    trailingslashit($request_path),
                    trailingslashit($admin_path)
                );

            if (
                (! is_admin() && ! $is_admin_path) ||
                is_user_logged_in() ||
                wp_doing_ajax() ||
                wp_doing_cron() ||
                'admin-post.php' === $script_name
            ) {
                return;
            }

            nocache_headers();
            wp_safe_redirect(
                home_url('/404/'),
                302,
                'WA Suit Hide Login'
            );

            exit;
        }

        /**
         * Redireciona acessos antigos a wp-login.php.
         *
         * A rota personalizada continua liberada.
         * Usuários autenticados mantêm acesso à tela oficial,
         * incluindo operações legítimas de logout.
         */
        public function redirect_legacy_login()
        {

            if ($this->is_custom_login_request()) {
                return;
            }

            if (is_user_logged_in()) {
                return;
            }

            // Não interfere em requisições AJAX ou cron.
            if (wp_doing_ajax() || wp_doing_cron()) {
                return;
            }

            // Esta ação ocorre no processamento de wp-login.php.
            // Redireciona visitantes não autenticados para a home.
            wp_safe_redirect(
                home_url('/'),
                302,
                'WA Suit Hide Login'
            );

            exit;
        }

        /**
         * Substitui a URL gerada por login_url().
         *
         * @param string $login_url URL original.
         * @param string $redirect  Destino após autenticação.
         * @param bool   $force_reauth Forçar reautenticação.
         * @return string
         */
        public function filter_login_url(
            $login_url,
            $redirect,
            $force_reauth
        ) {

            $url = $this->get_login_url();

            if (! empty($redirect)) {
                $url = add_query_arg(
                    'redirect_to',
                    $redirect,
                    $url
                );
            }

            if ($force_reauth) {
                $url = add_query_arg(
                    'reauth',
                    '1',
                    $url
                );
            }

            return $url;
        }

        /**
         * Atualiza o link de recuperação de senha.
         *
         * @param string $lostpassword_url URL original.
         * @param string $redirect         Destino opcional.
         * @return string
         */
        public function filter_lostpassword_url(
            $lostpassword_url,
            $redirect
        ) {

            $url = add_query_arg(
                'action',
                'lostpassword',
                $this->get_login_url()
            );

            if (! empty($redirect)) {
                $url = add_query_arg(
                    'redirect_to',
                    $redirect,
                    $url
                );
            }

            return $url;
        }

        /**
         * Converte URLs diretas de wp-login.php para a rota nova.
         *
         * Mantém os parâmetros da URL original, necessários
         * para recuperação de senha, logout e autenticação.
         *
         * @param string      $url     URL gerada pelo WordPress.
         * @param string      $path    Caminho solicitado.
         * @param string|null $scheme  Esquema solicitado.
         * @param int|null    $blog_id ID do site, quando aplicável.
         * @return string
         */
        public function filter_site_url(
            $url,
            $path,
            $scheme = null,
            $blog_id = null
        ) {

            if (! is_string($path) || '' === $path) {
                return $url;
            }

            $path_only = wp_parse_url(
                $path,
                PHP_URL_PATH
            );

            if (! $path_only) {
                $path_only = $path;
            }

            if ('wp-login.php' !== basename($path_only)) {
                return $url;
            }

            $query_string = wp_parse_url(
                $url,
                PHP_URL_QUERY
            );

            $new_url = $this->get_login_url();

            if (! empty($query_string)) {
                $new_url = add_query_arg(wp_parse_args($query_string), $new_url);
            }

            return $new_url;
        }

        /**
         * Verifica a permissão administrativa.
         *
         * Utiliza a camada de segurança do WA Suit quando
         * disponível e compatível; caso contrário, exige
         * a capacidade administrativa padrão do WordPress.
         */
        private function can_manage_settings()
        {

            if (
                class_exists('WA_Suit_Security') &&
                is_callable(
                    array(
                        'WA_Suit_Security',
                        'can_manage_settings',
                    )
                )
            ) {
                return (bool) WA_Suit_Security::can_manage_settings();
            }

            return current_user_can('manage_options');
        }

        /**
         * Processa o salvamento das configurações.
         */
        public function handle_save_settings()
        {

            if (! $this->can_manage_settings()) {
                wp_die(
                    esc_html__(
                        'Você não tem permissão para alterar estas configurações.',
                        'wa-suit'
                    ),
                    '',
                    array('response' => 403)
                );
            }

            check_admin_referer(
                'wa_suit_save_hide_login',
                'wa_suit_hide_login_nonce'
            );

            $raw_slug = isset($_POST['slug'])
                ? sanitize_text_field(
                    wp_unslash($_POST['slug'])
                )
                : '';

            $slug = $this->sanitize_login_slug($raw_slug);

            // Não permite salvar silenciosamente um valor inválido.
            if (
                '' === $raw_slug ||
                $slug !== strtolower(
                    trim(
                        sanitize_title($raw_slug),
                        '/'
                    )
                )
            ) {
                $this->redirect_settings_page('invalid_slug');
            }

            $old_settings = $this->get_settings();

            $new_settings = array(
                'slug' => $slug,
            );

            update_option(
                self::OPTION_SETTINGS,
                $new_settings,
                false
            );

            // Atualiza as regras para que a nova rota funcione
            // imediatamente após o salvamento.
            if ($old_settings['slug'] !== $slug) {
                $this->refresh_rewrite_rules($this->is_active(), $old_settings['slug']);
            }

            $this->redirect_settings_page('saved');
        }

        /**
         * Redireciona de volta à interface do módulo.
         *
         * A URL é derivada da área administrativa atual.
         */
        private function redirect_settings_page($status)
        {

            $referer = wp_get_referer();

            if (! $referer) {
                $referer = admin_url();
            }

            $referer = remove_query_arg(
                array(
                    'wa_hide_login_status',
                ),
                $referer
            );

            $referer = add_query_arg(
                'wa_hide_login_status',
                sanitize_key($status),
                $referer
            );

            wp_safe_redirect($referer);
            exit;
        }

        /**
         * Renderiza a interface do módulo.
         *
         * O gerenciador do WA Suit deve chamar este método
         * quando a aba deste módulo estiver aberta.
         */
        public function render_tab()
        {

            if (! $this->can_manage_settings()) {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__(
                    'Você não tem permissão para acessar estas configurações.',
                    'wa-suit'
                );
                echo '</p></div>';
                return;
            }

            $settings = $this->get_settings();
            $slug     = $this->sanitize_login_slug(
                $settings['slug']
            );
            $login_url = $this->get_login_url();
            $module_active = $this->is_active();

            $status = isset($_GET['wa_hide_login_status'])
                ? sanitize_key(
                    wp_unslash($_GET['wa_hide_login_status'])
                )
                : '';

?>
            <div class="wa-hide-login" id="wa-hide-login">

                <style>
                    .wa-hide-login {
                        --wa-hl-bg: #ffffff;
                        --wa-hl-text: #111827;
                        --wa-hl-muted: #6b7280;
                        --wa-hl-border: #e5e7eb;
                        --wa-hl-accent: #171717;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                        color: var(--wa-hl-text);
                        max-width: 1000px;
                    }

                    .wa-hide-login * {
                        box-sizing: border-box;
                    }

                    .wa-hl-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: flex-start;
                        gap: 20px;
                        margin-bottom: 24px;
                    }

                    .wa-hl-title {
                        margin: 0 0 8px;
                        font-size: 23px;
                        line-height: 1.3;
                        font-weight: 700;
                        color: var(--wa-hl-text);
                    }

                    .wa-hl-description {
                        margin: 0;
                        max-width: 650px;
                        color: var(--wa-hl-muted);
                        font-size: 14px;
                        line-height: 1.7;
                    }

                    .wa-hl-status {
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        padding: 8px 12px;
                        border: 1px solid #dedede;
                        border-radius: 999px;
                        background: #f7f7f7;
                        color: #343434;
                        font-size: 12px;
                        font-weight: 600;
                        white-space: wrap;
                    }

                    .wa-hl-dot {
                        width: 7px;
                        height: 7px;
                        border-radius: 50%;
                        background: #171717;
                    }

                    .wa-hl-card {
                        background: var(--wa-hl-bg);
                        border: 1px solid var(--wa-hl-border);
                        border-radius: 8px;
                        padding: 24px;
                        margin-bottom: 18px;
                        box-shadow: none;
                    }

                    .wa-hl-card h4 {
                        margin: 0 0 8px;
                        font-size: 16px;
                        color: var(--wa-hl-text);
                    }

                    .wa-hl-card p {
                        color: var(--wa-hl-muted);
                        font-size: 13px;
                        line-height: 1.65;
                    }

                    .wa-hl-field {
                        margin-top: 22px;
                    }

                    .wa-hl-field label {
                        display: block;
                        margin-bottom: 8px;
                        font-size: 13px;
                        font-weight: 600;
                    }

                    .wa-hl-input-wrap {
                        display: flex;
                        align-items: stretch;
                        width: 100%;
                        max-width: 680px;
                    }

                    .wa-hl-prefix {
                        display: flex;
                        align-items: center;
                        padding: 0 12px;
                        background: #f9fafb;
                        border: 1px solid var(--wa-hl-border);
                        border-right: 0;
                        border-radius: 8px 0 0 8px;
                        color: var(--wa-hl-muted);
                        font-size: 13px;
                        white-space: wrap;
                    }

                    .wa-hl-input {
                        width: 100%;
                        min-width: 0;
                        min-height: 43px;
                        padding: 10px 12px;
                        border: 1px solid var(--wa-hl-border);
                        border-radius: 0 8px 8px 0;
                        box-shadow: none;
                    }

                    .wa-hl-input:focus {
                        border-color: var(--wa-hl-accent);
                        outline: 2px solid rgba(23, 23, 23, .12);
                    }

                    .wa-hl-hint {
                        display: block;
                        margin-top: 8px;
                        color: var(--wa-hl-muted);
                        font-size: 12px;
                    }

                    .wa-hl-preview {
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                        gap: 16px;
                        margin-top: 24px;
                        padding: 16px;
                        border: 1px solid var(--wa-hl-border);
                        border-radius: 6px;
                        background: #fafafa;
                    }

                    .wa-hl-preview-label {
                        display: block;
                        margin-bottom: 6px;
                        font-size: 11px;
                        color: var(--wa-hl-muted);
                        text-transform: uppercase;
                        letter-spacing: .05em;
                    }

                    .wa-hl-preview-url {
                        overflow-wrap: anywhere;
                        font-size: 13px;
                        font-weight: 600;
                    }

                    .wa-hl-button {
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 40px;
                        padding: 10px 16px;
                        border: 1px solid transparent;
                        border-radius: 0 6px 6px 0;
                        background: #171717;
                        color: #fff;
                        text-decoration: none;
                        font-size: 13px;
                        font-weight: 600;
                        cursor: pointer;
                    }

                    .wa-hl-button:hover {
                        background: #000;
                        color: #fff;
                    }

                    .wa-hl-alert {
                        margin: 0 0 18px;
                        padding: 13px 16px;
                        border: 1px solid var(--wa-hl-border);
                        border-radius: 6px;
                        background: #f7f7f7;
                        font-size: 13px;
                    }

                    .wa-hl-alert--success {
                        background: #f7f7f7;
                        border-color: #dedede;
                        color: #222;
                    }

                    .wa-hl-alert--error {
                        background: #f2f2f2;
                        border-color: #cfcfcf;
                        color: #333;
                    }

                    .wa-hl-list {
                        margin: 14px 0 0 18px;
                        padding: 0;
                        color: var(--wa-hl-muted);
                        font-size: 13px;
                        line-height: 1.9;
                    }

                    .wa-hl-actions {
                        display: flex;
                        justify-content: flex-end;
                        margin-top: 22px;
                    }

                    .wa-hl-save {
                        background: var(--wa-hl-accent);
                        color: #fff;
                        border: 0;
                    }

                    .wa-hl-save:hover {
                        background: #000;
                    }

                    @media (max-width: 640px) {

                        .wa-hl-header,
                        .wa-hl-preview {
                            flex-direction: column;
                            align-items: flex-start;
                        }

                        .wa-hl-card {
                            padding: 18px;
                        }

                        .wa-hl-prefix {
                            max-width: 58%;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        }

                        .wa-hl-actions,
                        .wa-hl-save {
                            width: 100%;
                        }
                    }
                </style>

                <div class="wa-hl-header">
                    <div>
                        <h3 class="wa-hl-title">
                            <?php
                            echo esc_html__(
                                'WA Hide Login',
                                'wa-suit'
                            );
                            ?>
                        </h3>

                        <p class="wa-hl-description">
                            <?php
                            echo esc_html__(
                                'Personalize o endereço de login e reduza a exposição da rota padrão do WordPress.',
                                'wa-suit'
                            );
                            ?>
                        </p>
                    </div>

                    <span class="wa-hl-status <?php echo $module_active ? 'is-active' : 'is-inactive'; ?>">
                        <span class="wa-hl-dot"></span>
                        <?php
                        echo esc_html__(
                            $module_active ? 'Módulo ativo' : 'Módulo inativo',
                            'wa-suit'
                        );
                        ?>
                    </span>
                </div>

                <?php if ('saved' === $status) : ?>
                    <div class="wa-hl-alert wa-hl-alert--success">
                        <?php
                        echo esc_html__(
                            'Configurações salvas. Teste a nova URL em uma janela anônima antes de encerrar sua sessão.',
                            'wa-suit'
                        );
                        ?>
                    </div>
                <?php elseif ('invalid_slug' === $status) : ?>
                    <div class="wa-hl-alert wa-hl-alert--error">
                        <?php
                        echo esc_html__(
                            'O caminho informado é inválido ou reservado. Escolha outro identificador.',
                            'wa-suit'
                        );
                        ?>
                    </div>
                <?php endif; ?>

                <div class="wa-hl-card">
                    <h4>
                        <?php
                        echo esc_html__(
                            'Endereço de acesso',
                            'wa-suit'
                        );
                        ?>
                    </h4>

                    <p>
                        <?php
                        echo esc_html__(
                            'Escolha um caminho exclusivo. Evite palavras óbvias como login, admin ou acesso.',
                            'wa-suit'
                        );
                        ?>
                    </p>

                    <form
                        method="post"
                        action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input
                            type="hidden"
                            name="action"
                            value="wa_suit_save_hide_login">

                        <?php
                        wp_nonce_field(
                            'wa_suit_save_hide_login',
                            'wa_suit_hide_login_nonce'
                        );
                        ?>

                        <div class="wa-hl-field">
                            <label for="wa-hl-slug">
                                <?php
                                echo esc_html__(
                                    'Caminho personalizado',
                                    'wa-suit'
                                );
                                ?>
                            </label>

                            <div class="wa-hl-input-wrap">
                                <span class="wa-hl-prefix">
                                    <?php echo esc_html(home_url('/')); ?>
                                </span>

                                <input
                                    class="wa-hl-input"
                                    id="wa-hl-slug"
                                    name="slug"
                                    type="text"
                                    value="<?php echo esc_attr($slug); ?>"
                                    minlength="3"
                                    maxlength="80"
                                    pattern="[a-zA-Z0-9]+(-[a-zA-Z0-9]+)*"
                                    autocomplete="off"
                                    spellcheck="false"
                                    required>
                            </div>

                            <span class="wa-hl-hint">
                                <?php
                                echo esc_html__(
                                    'Use letras sem acentos, números e hífens. A rota é alterada ao salvar.',
                                    'wa-suit'
                                );
                                ?>
                            </span>
                        </div>

                        <div class="wa-hl-preview">
                            <div>
                                <span class="wa-hl-preview-label">
                                    <?php
                                    echo esc_html__(
                                        'URL atual de login',
                                        'wa-suit'
                                    );
                                    ?>
                                </span>

                                <div class="wa-hl-preview-url">
                                    <code id="wa-hl-preview-url"><?php echo esc_html($login_url); ?></code>
                                </div>
                            </div>

                            <button
                                class="wa-hl-button"
                                type="button"
                                id="wa-hl-copy-url">
                                <?php
                                echo esc_html__(
                                    'Copiar URL',
                                    'wa-suit'
                                );
                                ?>
                            </button>
                        </div>

                        <div class="wa-hl-actions">
                            <button
                                type="submit"
                                class="wa-hl-button wa-hl-save">
                                <?php
                                echo esc_html__(
                                    'Salvar configurações',
                                    'wa-suit'
                                );
                                ?>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="wa-hl-card">
                    <h4>
                        <?php
                        echo esc_html__(
                            'Como funciona a proteção',
                            'wa-suit'
                        );
                        ?>
                    </h4>

                    <ul class="wa-hl-list">
                        <li>
                            <?php
                            echo esc_html__(
                                'Visitantes não autenticados que acessarem wp-admin serão enviados para /404.',
                                'wa-suit'
                            );
                            ?>
                        </li>
                        <li>
                            <?php
                            echo esc_html__(
                                'Visitantes não autenticados que acessarem wp-login.php serão redirecionados para a página inicial.',
                                'wa-suit'
                            );
                            ?>
                        </li>
                        <li>
                            <?php
                            echo esc_html__(
                                'O endereço personalizado utiliza a tela oficial de login do WordPress.',
                                'wa-suit'
                            );
                            ?>
                        </li>
                        <li>
                            <?php
                            echo esc_html__(
                                'A recuperação de senha utiliza a rota personalizada para os novos links gerados.',
                                'wa-suit'
                            );
                            ?>
                        </li>
                        <li>
                            <?php
                            echo esc_html__(
                                'O módulo não adiciona bloqueio de IP nem altera o funcionamento de admin-ajax.php e admin-post.php.',
                                'wa-suit'
                            );
                            ?>
                        </li>
                    </ul>
                </div>

                <div class="wa-hl-card">
                    <h4>
                        <?php
                        echo esc_html__(
                            'Recuperação de acesso',
                            'wa-suit'
                        );
                        ?>
                    </h4>

                    <p>
                        <?php
                        echo esc_html__(
                            'Guarde a URL personalizada em um gerenciador de senhas. Se perder o endereço, desative o módulo Hide Login no WA Suit. Se o painel estiver inacessível, desative temporariamente o plugin WA Suit pelo gerenciador de arquivos da hospedagem, renomeando sua pasta, e depois restaure o nome original.',
                            'wa-suit'
                        );
                        ?>
                    </p>

                    <p>
                        <?php
                        echo esc_html__(
                            'Depois de recuperar o acesso, verifique se a rota antiga voltou a funcionar e reative o módulo somente após confirmar as configurações.',
                            'wa-suit'
                        );
                        ?>
                    </p>
                </div>

                <script>
                    (function() {
                        'use strict';

                        var button = document.getElementById('wa-hl-copy-url');
                        var preview = document.getElementById('wa-hl-preview-url');

                        if (!button || !preview) {
                            return;
                        }

                        button.addEventListener('click', function() {
                            var url = preview.textContent.trim();

                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(url).then(function() {
                                    button.textContent = 'Copiado!';
                                }).catch(function() {
                                    window.prompt('Copie a URL:', url);
                                });
                            } else {
                                window.prompt('Copie a URL:', url);
                            }
                        });
                    })();
                </script>

            </div>
<?php
        }
    }
}

/**
 * Registra o módulo no gerenciador do WA Suit.
 */
wa_suit_register_module(
    new WA_Suit_Module_Hide_Login()
);
