<?php
/**
 * Duplicate content settings.
 *
 * @package WASuit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages settings for duplicating posts and pages.
 */
class WA_Suit_Setting_Duplicate_Items {

	/**
	 * Gets validated settings with defaults for a first-time install.
	 *
	 * @param array $saved Saved general settings.
	 * @return array
	 */
	public function get_settings( $saved ) {
		$public_types  = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);
		$allowed_types = array_values( array_diff( $public_types, array( 'attachment', 'revision', 'product' ) ) );
		$default_types = array_values( array_unique( array_merge( array( 'page', 'post' ), $allowed_types ) ) );
		$saved_types   = isset( $saved['duplicate_post_types'] ) && is_array( $saved['duplicate_post_types'] )
			? array_map( 'sanitize_key', array_filter( $saved['duplicate_post_types'], 'is_string' ) )
			: $default_types;

		return array(
			'duplicate_enabled'    => isset( $saved['duplicate_enabled'] ) ? (bool) $saved['duplicate_enabled'] : true,
			'duplicate_post_types' => array_values( array_unique( array_intersect( $saved_types, $allowed_types ) ) ),
		);
	}

	/**
	 * Validates submitted duplicate-content settings.
	 *
	 * @param array $input Submitted general settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$public_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'names'
		);
		$public_types = array_diff( $public_types, array( 'attachment', 'revision', 'product' ) );
		$selected     = isset( $input['duplicate_post_types'] ) && is_array( $input['duplicate_post_types'] )
			? array_map( 'sanitize_key', array_filter( $input['duplicate_post_types'], 'is_string' ) )
			: array();

		return array(
			'duplicate_enabled'    => ! empty( $input['duplicate_enabled'] ),
			'duplicate_post_types' => array_values( array_intersect( $selected, $public_types ) ),
		);
	}

	/**
	 * Renders the duplicate-content settings card.
	 *
	 * @param array $settings All general settings.
	 * @return void
	 */
	public function render_settings( $settings ) {
		$post_types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		?>
		<section class="wa-general-settings__card">
			<div class="wa-general-settings__card-heading">
				<span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
				<div>
					<h3><?php echo esc_html__( 'Duplicar conteúdo', 'wa-suit' ); ?></h3>
					<p><?php echo esc_html__( 'Crie uma cópia em rascunho diretamente pela lista de conteúdos.', 'wa-suit' ); ?></p>
				</div>
			</div>

			<label class="wa-general-settings__toggle">
				<input type="checkbox" name="settings[duplicate_enabled]" value="1" <?php checked( $settings['duplicate_enabled'] ); ?>>
				<span>
					<strong><?php echo esc_html__( 'Ativar duplicação', 'wa-suit' ); ?></strong>
					<small><?php echo esc_html__( 'Ativado por padrão. As cópias são criadas como rascunho, preservam os dados e estilos do Elementor e registram quem fez a cópia.', 'wa-suit' ); ?></small>
				</span>
			</label>

			<fieldset class="wa-general-settings__types">
				<legend><?php echo esc_html__( 'Tipos de conteúdo disponíveis', 'wa-suit' ); ?></legend>
				<div class="wa-general-settings__type-list">
					<?php foreach ( $post_types as $post_type => $post_type_object ) : ?>
						<?php
						if ( in_array( $post_type, array( 'attachment', 'revision', 'product' ), true ) || empty( $post_type_object->public ) || empty( $post_type_object->show_ui ) ) {
							continue;
						}
						?>
						<label>
							<input type="checkbox" name="settings[duplicate_post_types][]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( in_array( $post_type, $settings['duplicate_post_types'], true ) ); ?>>
							<span><?php echo esc_html( $post_type_object->labels->name ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="description"><?php echo esc_html__( 'Produtos WooCommerce usam a opção nativa “Duplicar” da própria lista de produtos.', 'wa-suit' ); ?></p>
			</fieldset>
		</section>
		<?php
	}
}
