/**
 * Beaver Builder Custom Admin — Main App
 *
 * Tabbed interface for all admin customization modules.
 *
 * @package OneDog\BBCustomAdmin
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import RoleEditor from './RoleEditor';
import MenuRestrictor from './MenuRestrictor';
import WelcomeScreen from './WelcomeScreen';
import ModuleSettings from './ModuleSettings';
import ImportExport from './ImportExport';

const TABS = [
	{
		id: 'roles',
		label: __( 'Role Editor', 'bb-custom-admin' ),
	},
	{
		id: 'menus',
		label: __( 'Menu Restrictor', 'bb-custom-admin' ),
	},
	{
		id: 'welcome',
		label: __( 'Welcome Screen', 'bb-custom-admin' ),
	},
	{
		id: 'modules',
		label: __( 'Modules', 'bb-custom-admin' ),
	},
	{
		id: 'import-export',
		label: __( 'Import / Export', 'bb-custom-admin' ),
	},
];

const getTabFromHash = () => {
	const match = window.location.hash.match( /tab=([\w-]+)/ );
	const id = match ? match[ 1 ] : null;
	return TABS.some( ( tab ) => tab.id === id ) ? id : TABS[ 0 ].id;
};

export default function App() {
	const [ activeTab, setActiveTab ] = useState( getTabFromHash );
	const [ toast, setToast ] = useState( { message: '', type: null } );

	// Keep the URL hash in sync with the active tab.
	useEffect( () => {
		window.history.replaceState( null, '', `#tab=${ activeTab }` );
	}, [ activeTab ] );

	// Configure apiFetch nonce if present.
	useEffect( () => {
		const data = window.bbcaSettings || {};
		if ( data.nonce ) {
			apiFetch.use( apiFetch.createNonceMiddleware( data.nonce ) );
		}
	}, [] );

	const showToast = ( message, type = 'success' ) => {
		setToast( { message, type } );
		setTimeout( () => setToast( { message: '', type: null } ), 4000 );
	};

	return (
		<div className="bbca-admin-isolated mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
			{ /* Toast Notification */ }
			{ toast.message && (
				<div className="fixed bottom-5 right-5 z-50 max-w-sm rounded-lg p-4 shadow-lg border animate-slideIn transition-all duration-300 bg-white border-gray-200">
					<div className="flex items-center gap-x-3">
						{ toast.type === 'success' && (
							<span className="text-green-500 text-lg">✓</span>
						) }
						{ toast.type === 'error' && (
							<span className="text-red-500 text-lg">✗</span>
						) }
						<p className="text-sm font-medium text-gray-900">
							{ toast.message }
						</p>
					</div>
				</div>
			) }

			{ /* Header */ }
			<div className="md:flex md:items-center md:justify-between border-b border-gray-200 pb-5 mb-8">
				<div className="min-w-0 flex-1">
					<h1 className="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl tracking-tight">
						{ __( 'Beaver Builder Custom Admin', 'bb-custom-admin' ) }
					</h1>
					<p className="mt-1 text-sm text-gray-500">
						{ __(
							'Modular WordPress admin customization — roles, menus, welcome templates, and more.',
							'bb-custom-admin'
						) }
					</p>
				</div>
			</div>

			{ /* Tab Navigation */ }
			<div className="border-b border-gray-200 mb-6">
				<nav className="-mb-px flex space-x-8" aria-label="Tabs">
					{ TABS.map( ( tab ) => (
						<button
							key={ tab.id }
							onClick={ () => setActiveTab( tab.id ) }
							className={
								activeTab === tab.id
									? 'border-indigo-500 text-indigo-600 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium'
									: 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium'
							}
						>
							{ tab.label }
						</button>
					) ) }
				</nav>
			</div>

			{ /* Tab Content */ }
			<div className="space-y-8">
				{ activeTab === 'roles' && <RoleEditor showToast={ showToast } /> }
				{ activeTab === 'menus' && <MenuRestrictor showToast={ showToast } /> }
				{ activeTab === 'welcome' && <WelcomeScreen showToast={ showToast } /> }
				{ activeTab === 'modules' && <ModuleSettings showToast={ showToast } /> }
				{ activeTab === 'import-export' && <ImportExport showToast={ showToast } /> }
			</div>
		</div>
	);
}
