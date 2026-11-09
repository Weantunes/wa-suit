<?php
/**
 * Plugin Name: WA Suit | Wa Web Design
 * Description: Ferramentas da WA WEB Design para os sites de seus clientes.
 * Version: 1.3
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Wa Web Design
 * Author URI: https://wawebdesign.com.br/
 * Text Domain: wa-suit
 * Domain Path: /languages
 *
 * @package WASuit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WA_SUIT_VERSION', '1.0.0' );
define( 'WA_SUIT_FILE', __FILE__ );
define( 'WA_SUIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WA_SUIT_URL', plugin_dir_url( __FILE__ ) );

require_once WA_SUIT_PATH . 'modules/error-screen/class-wa-suit-error-screen.php';
WA_Suit_Error_Screen::init();
register_activation_hook( WA_SUIT_FILE, array( 'WA_Suit_Error_Screen', 'install' ) );
register_deactivation_hook( WA_SUIT_FILE, array( 'WA_Suit_Error_Screen', 'uninstall' ) );

require_once WA_SUIT_PATH . 'includes/class-wa-suit.php';

$wa_suit = new WA_Suit();
$wa_suit->run();
