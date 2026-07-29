<?php
/**
 * Template for the custom welcome panel rendered on the dashboard.
 *
 * @since 0.1.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;

$layout_slug = isset( self::$template[ self::$current_role ] )
	? self::$template[ self::$current_role ]
	: '';
?>
<style type="text/css" id="onedog-bbca-css">
	.welcome-panel {
		padding: 0;
	}
	.welcome-panel .welcome-panel-close {
		z-index: 1;
	}
	#onedog-bbca-panel {
		-webkit-font-smoothing: antialiased;
	}
	#onedog-bbca-panel .fl-builder-content ul,
	#onedog-bbca-panel .fl-builder-content ol {
		list-style: inherit;
	}
	#onedog-bbca-panel .fl-builder-content p {
		color: inherit;
		font-size: inherit;
		margin: inherit;
		margin-bottom: 10px;
	}
	#onedog-bbca-panel input:focus,
	#onedog-bbca-panel textarea:focus,
	#onedog-bbca-panel select:focus,
	#onedog-bbca-panel button:focus {
		-webkit-box-shadow: none;
		box-shadow: none;
	}
</style>

<div id="onedog-bbca-panel" class="<?php echo esc_attr( self::$classes ); ?>">
	<?php
	if ( ! empty( $layout_slug ) ) {
		echo do_shortcode( '[fl_builder_insert_layout slug="' . esc_attr( $layout_slug ) . '"]' );
	}
	?>
</div>

<?php if ( ! current_user_can( 'edit_theme_options' ) ) : ?>
<script type="text/javascript" id="onedog-bbca-js">
	;(function($) {
		$(document).ready(function() {
			$('#onedog-bbca-panel').insertBefore('#dashboard-widgets-wrap');
		});
	})(jQuery);
</script>
<?php endif; ?>
