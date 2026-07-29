/**
 * Beaver Builder Custom Admin — Entry Point
 *
 * @package OneDog\BBCustomAdmin
 */

import { render } from '@wordpress/element';
import App from './components/App';
import './styles/index.css';

const rootElement = document.getElementById( 'onedog-bbca-settings-root' );
if ( rootElement ) {
	render( <App />, rootElement );
}
