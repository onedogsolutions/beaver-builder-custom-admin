<?php
/**
 * Template for the custom welcome panel rendered on the dashboard.
 *
 * Styles are enqueued via assets/css/frontend.css.
 * Script is enqueued via assets/js/frontend.js.
 *
 * @since 0.1.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

$layout_slug = isset( self::$template[ self::$current_role ] )
	? self::$template[ self::$current_role ]
	: '';
?>
<div id="onedog-bbca-panel" class="<?php echo esc_attr( self::$classes ); ?>">
	<?php
	if ( ! empty( $layout_slug ) ) {
		echo do_shortcode( '[fl_builder_insert_layout slug="' . esc_attr( $layout_slug ) . '"]' );
	}
	?>
</div>
