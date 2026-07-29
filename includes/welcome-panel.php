<?php
/**
 * Template for the custom welcome panel rendered on the dashboard.
 *
 * Variables provided by the calling module:
 * - $layout_slug (string) The BB layout slug to render.
 * - $classes     (string) Additional CSS classes for the wrapper.
 *
 * @since 0.1.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

$layout_slug = isset( $layout_slug ) ? $layout_slug : '';
$classes     = isset( $classes ) ? $classes : '';
?>
<div id="onedog-bbca-panel" class="<?php echo esc_attr( $classes ); ?>">
	<?php
	if ( ! empty( $layout_slug ) ) {
		echo do_shortcode( '[fl_builder_insert_layout slug="' . esc_attr( $layout_slug ) . '"]' );
	}
	?>
</div>
