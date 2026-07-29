/**
 * Beaver Builder Custom Admin — Settings Entry Point
 *
 * @package OneDog\BBCustomAdmin
 */

import { createRoot } from '@wordpress/element';
import SettingsApp from './app';

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'onedog-bbca-settings-root' );
	if ( container ) {
		createRoot( container ).render( <SettingsApp /> );
	}
} );
