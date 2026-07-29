/**
 * Beaver Builder Custom Admin — Dashboard Panel Script
 *
 * Repositions the custom welcome panel above dashboard widgets
 * for users who cannot edit theme options.
 *
 * @since 0.2.0
 * @package OneDog\BBCustomAdmin
 */

const panel = document.getElementById( 'onedog-bbca-panel' );
const widgets = document.getElementById( 'dashboard-widgets-wrap' );

if ( panel && widgets ) {
	widgets.before( panel );
}
