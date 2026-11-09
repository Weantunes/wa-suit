=== WA Suit - WA Web Design ===
Contributors: wawebdesign
Tags: administration, modules, links, elementor, shortcodes
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 6.7
Stable tag: 1.1
License: MIT

Plugin oficial da WA Web Design com interface em preto e branco, controle modular de funções e gerenciador centralizado de links dinâmicos para botões do Elementor.

== Description ==

O **WA Suit** é o plugin oficial da **WA Web Design** desenvolvido para agilizar a criação e manutenção de sites em WordPress e Elementor.

### Principais Características:
* **Interface**: Visual minimalista em preto, branco e tons neutros, com tipografia do sistema e sem degradês.
* **Ordem Superior no Painel**: Posicionado logo abaixo do "Painel" do WordPress (ordem mais alta).
* **Arquitetura 100% Modular**: Cada função do plugin vive em seu próprio arquivo dentro da pasta `modules/`. O painel descobre arquivos automaticamente.
* **Navegação simples**: Acesse Painel, Módulos e Configuração pelo menu do plugin. Abra as configurações de cada módulo pelo cartão correspondente.

### Links Dinâmicos
Centralize links em shortcodes para usar em botões do Elementor.
1. Cadastre links (ex: WhatsApp, Checkout, Agendamento).
2. O plugin gera o shortcode `[wa_link id="slug"]`.
3. Cole o shortcode nos botões do Elementor.
4. Quando precisar trocar o número do WhatsApp ou o link do checkout, mude apenas no painel e **todos os botões do site mudam de uma vez só!**

### WA Widgets para Elementor
Com o Elementor ativo, o WA Suit adiciona a categoria **WA Widgets** no editor e inclui o widget **Popup**. Em **WA Suit > Configuração > Elementor Pro > Widgets**, você pode ativar ou desativar o Popup; ele vem ativado por padrão. Arraste-o para a página, configure o texto do botão e o conteúdo do popup. O popup começa oculto e só aparece quando o visitante clica no botão; pode ser fechado pelo botão de fechar, clicando fora ou pressionando Escape. Em **Estilo > Botão**, personalize tipografia, cores normal e hover, ícone, posição do ícone, alinhamento, espaçamento, borda, arredondamento, sombra e transição. Em **Estilo > Popup**, ajuste a largura e personalize a cor, o hover, o tamanho e a borda do botão de fechar.

### Máscaras nos formulários do Elementor Pro
No editor, use o tipo de campo **Texto** para aplicar os formatos prontos de CPF, CNPJ e CEP, máscaras brasileiras de celular/telefone fixo, o formato internacional com bandeira e pesquisa ou uma máscara personalizada. Use **Texto**, e não **Telefone**, para números com máscara: isso evita a validação `pattern` de telefone aplicada pelo Elementor. Ao escolher o formato internacional, configure por campo se deseja usar os países globais, mostrar somente países selecionados ou excluir países selecionados; também é possível escolher o país inicial desse campo. Em **WA Suit > Configuração > Elementor Pro**, defina a lista global e o país inicial usados pelos campos que herdam a configuração global. Campos do tipo Telefone, e-mail, seleção e outros tipos não recebem máscaras. As máscaras personalizadas usam `0` para números, `A` para letras e `*` para números ou letras.

O seletor usa intl-tel-input 29.5.6, distribuído sob licença MIT. A licença e os arquivos do componente estão em `assets/vendor/intl-tel-input`.

### Códigos Personalizados
Crie vários snippets em HTML, CSS, JavaScript, PHP ou Shortcode e escolha posição, escopo e dispositivos (desktop, tablet ou celular). A exibição pode ser limitada ao site, a páginas e conteúdos específicos, a posts, a produtos WooCommerce quando disponível ou a várias condições de URL. A lista permite pesquisar, filtrar por linguagem, status e localização, selecionar ações em massa, configurar a quantidade por página e mover códigos para a lixeira ou restaurá-los. Cada cópia recebe um nome numerado e fica desativada até ser ativada manualmente. O editor de código oferece temas claro e escuro e numeração de linhas. A página Suporte fica disponível no menu WA Suit.

Snippets mais específicos são impressos depois dos globais; entre regras de URL, a correspondência mais longa vem depois. Shortcodes são executados no body ou footer, não no head. Snippets PHP são executados antes da montagem do template e também em requisições AJAX iniciadas no site público, permitindo registrar filtros do WordPress e validações de formulários; a saída direta continua na posição escolhida. Antes de executar PHP, o WA Suit verifica declarações de funções globais: se uma função já existir (por exemplo, por estar definida por uma cópia do snippet no WPCode), o código é desativado automaticamente e um aviso é exibido no painel. No editor, selecione a linguagem PHP e cole as instruções com ou sem as tags `<?php` e `?>`; cercas de código Markdown também são removidas automaticamente. PHP executa no servidor e não aceita filtro por dispositivo. HTML com filtro de dispositivo deve ser colocado no body ou footer.

### Hide Login
Personalize o endereço da tela oficial de login do WordPress; o caminho padrão é `wa-portal`. Visitantes não autenticados que acessarem o painel em `wp-admin` serão enviados para `/404`, e os que acessarem `wp-login.php` serão enviados à página inicial. A rota personalizada funciona com links permanentes amigáveis e, quando eles estão desativados, usa um parâmetro na URL. Os endpoints `admin-ajax.php` e `admin-post.php` permanecem disponíveis. Ative o módulo na página Módulos antes de usar a proteção. Esta função não substitui firewall, autenticação multifator ou limitação de tentativas de login.

### Cookies
Ative o módulo **Cookies** em **Módulos** para exibir um aviso de consentimento no site. Configure nome do site, mensagem, link para a política de privacidade, categorias de análise e publicidade (incluindo AdSense), posição, cores do painel e cores independentes dos botões, além da duração da escolha do visitante. O aviso usa um visual sólido, com ícone de cookie e marca WA Web Design. As preferências são salvas no navegador e podem ser abertas novamente pelo botão de preferências. O módulo registra as escolhas, mas não bloqueia automaticamente cookies ou scripts de serviços externos; essas integrações precisam ser configuradas separadamente.

### Configuração
As configurações estão organizadas nas abas **Geral**, **Desenvolvedor**, **Elementor Pro** e **Tela de erro**. Em **Geral**, ative a duplicação de páginas, posts e tipos de conteúdo públicos; o recurso vem ativado por padrão e cria cópias em rascunho. Páginas criadas com Elementor mantêm a estrutura dos widgets e têm o CSS regenerado para a cópia. Também é possível escolher um tema pai instalado, informar o nome do tema filho e decidir se quer ativá-lo imediatamente (opção marcada por padrão). A ativação pode alterar a aparência do site; faça um backup antes. Produtos WooCommerce usam o comando de duplicação nativo do WooCommerce.

Em **Tela de erro**, configure a identidade que será exibida se ocorrer um erro crítico do PHP ou uma falha no banco de dados. O WA Suit instala os drop-ins independentes `wp-content/php-error.php` e `wp-content/db-error.php`, que podem exibir uma tela mesmo se o WordPress ou um plugin não conseguir inicializar. O logo é obtido automaticamente da opção padrão **Logo do site** do WordPress; uma URL manual pode substituí-lo. Também é possível definir nome, cor principal, WhatsApp e a exibição opcional do crédito WA Web Design. A pré-visualização abre a tela configurada. O modo de manutenção pode ser ativado separadamente: visitantes recebem a tela 503, enquanto usuários conectados continuam acessando o site e o painel. Arquivos existentes que não pertençam ao WA Suit nunca são substituídos; conflitos e falta de permissão são informados no painel. Ao desativar o WA Suit, somente os arquivos que contenham a assinatura de propriedade do plugin são removidos.

Na aba **Desenvolvedor**, é possível ativar ou desativar o registro de erros fatais em log, consultar e limpar esse log e conferir informações do ambiente, incluindo o estado de `WP_DEBUG` e `WP_DEBUG_LOG`. Independentemente do log, o WA Suit tenta enviar para `suporte@wawebdesign.com.br` um e-mail com o site, a mensagem e o horário de erros fatais que ocorram depois que o plugin for carregado. Se um snippet PHP falhar, ele é desativado automaticamente e um aviso é exibido no painel; erros de sintaxe são identificados antes da execução e impedem a ativação. O editor também registra e mostra quem criou cada código e quando; duplicar um snippet registra o usuário e a data da cópia. O log do WA Suit é separado das constantes nativas do WordPress e fica em diretório privado, fora da raiz pública quando possível. A ferramenta não altera `wp-config.php` nem substitui o log nativo do WordPress. A tentativa de e-mail depende do funcionamento do servidor de envio ou de um SMTP configurado. Erros ocorridos antes do carregamento do WA Suit ou falhas externas ao PHP podem não ser detectados ou notificados; nenhum plugin consegue impedir universalmente falhas de processo, falta de memória ou `exit` em código arbitrário.

== Installation ==

1. Envie a pasta `Plugin-WA-Suit` para o diretório `/wp-content/plugins/` da sua instalação WordPress.
2. Ative o plugin no menu **Plugins** do WordPress.
3. Acesse **Painel > Módulos** para gerenciar as funções e links.

== Como Criar Uma Nova Função ==

Para adicionar uma nova função no futuro, basta criar um novo arquivo PHP na pasta `modules/` (ex: `modules/nova-funcao.php`):

```php
<?php
class WA_Suit_Module_Nova_Funcao extends WA_Suit_Module_Base {
    public function get_id()          { return 'nova-funcao'; }
    public function get_title()       { return 'Minha Nova Função'; }
    public function get_description() { return 'Descrição clara e objetiva.'; }
    public function get_icon()        { return 'dashicons-star-filled'; }
    public function get_category()    { return 'Ferramentas'; }

    public function init() {
        // Código executado quando a função for ativada
    }

    public function render_tab() {
        // HTML da aba lateral
    }
}
wa_suit_register_module( new WA_Suit_Module_Nova_Funcao() );
```

== Changelog ==

= 1.0.0 =
* Lançamento com design Apple e módulo de Links Dinâmicos para Elementor com auto-substituição de shortcodes em botões.
