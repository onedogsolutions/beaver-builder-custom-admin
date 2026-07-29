<?php
/**
 * Settings form rendered inside Beaver Builder's admin settings panel.
 *
 * @since 0.1.0
 * @package OneDog\BBCustomAdmin
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="onedog-bbca-form" class="fl-settings-form">
	<h2 style="display: none;"><?php esc_html_e( 'Custom Admin', 'bb-custom-admin' ); ?></h2>

	<?php if ( ! class_exists( 'FLBuilderAdminSettings' ) ) : ?>
		<p><?php esc_html_e( 'Beaver Builder admin settings are not available.', 'bb-custom-admin' ); ?></p>
	<?php else : ?>
		<form method="post" id="onedog-bbca-settings-form" action="<?php FLBuilderAdminSettings::render_form_action( 'onedog-bbca' ); ?>">
			<table class="onedog-bbca-settings-table wp-list-table widefat">
				<tr valign="top">
					<th scope="row">
						<strong><?php esc_html_e( 'User Role', 'bb-custom-admin' ); ?></strong>
					</th>
					<th scope="row">
						<strong><?php esc_html_e( 'Select Template', 'bb-custom-admin' ); ?></strong>
					</th>
				</tr>
				<?php
				$count = 0;
				foreach ( self::$roles as $key => $value ) :
					$row_class = ( $count % 2 === 0 ) ? 'alternate' : '';
					?>
					<tr class="<?php echo esc_attr( $row_class ); ?>">
						<td><?php echo esc_html( $value ); ?></td>
						<td>
							<select name="onedog_bbca_template[<?php echo esc_attr( $key ); ?>]" class="onedog-bbca-input">
								<option value="none"<?php echo self::get_selected( $key, 'none', self::$template ); ?>><?php esc_html_e( 'None', 'bb-custom-admin' ); ?></option>
								<?php foreach ( self::$templates as $template ) : ?>
									<option value="<?php echo esc_attr( $template['slug'] ); ?>"<?php echo self::get_selected( $key, $template['slug'], self::$template ); ?>><?php echo esc_html( $template['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<?php
					$count++;
				endforeach;
				?>
			</table>
			<?php submit_button(); ?>
			<?php wp_nonce_field( 'onedog-bbca-settings', 'onedog-bbca-settings-nonce' ); ?>
		</form>
	<?php endif; ?>
</div>
