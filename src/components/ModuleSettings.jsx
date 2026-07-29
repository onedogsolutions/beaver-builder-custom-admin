/**
 * Beaver Builder Custom Admin — Module Settings
 *
 * Toggle individual modules on/off.
 *
 * @package OneDog\BBCustomAdmin
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function ModuleSettings( { showToast } ) {
	const [ modules, setModules ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );

	// Load modules.
	const loadData = useCallback( async () => {
		setLoading( true );
		try {
			const data = await apiFetch( { path: '/onedog-bbca/v1/modules' } );
			setModules( data.modules || [] );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setLoading( false );
		}
	}, [ showToast ] );

	useEffect( () => {
		loadData();
	}, [ loadData ] );

	// Save modules.
	const save = async () => {
		setSaving( true );
		const enabled = modules.filter( ( m ) => m.enabled ).map( ( m ) => m.slug );
		try {
			await apiFetch( {
				path: '/onedog-bbca/v1/modules',
				method: 'POST',
				data: { modules: enabled },
			} );
			showToast( __( 'Modules saved. Reload the page for changes to take effect.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setSaving( false );
		}
	};

	// Toggle module.
	const toggleModule = ( slug ) => {
		setModules( ( prev ) =>
			prev.map( ( m ) => ( m.slug === slug ? { ...m, enabled: ! m.enabled } : m ) )
		);
	};

	if ( loading ) {
		return (
			<div className="flex justify-center py-12">
				<div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
			</div>
		);
	}

	return (
		<div className="space-y-6">
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Active Modules', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Toggle modules on or off. Disabled modules have zero runtime overhead.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4 divide-y divide-gray-200">
					{ modules.map( ( mod ) => (
						<div key={ mod.slug } className="py-4 first:pt-0 last:pb-0">
							<div className="flex items-center justify-between">
								<div className="flex-1">
									<p className="text-sm font-medium text-gray-900">{ mod.label }</p>
									<p className="text-sm text-gray-500">{ mod.description }</p>
								</div>
								<button
									type="button"
									onClick={ () => toggleModule( mod.slug ) }
									className={ `relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${
										mod.enabled ? 'bg-indigo-600' : 'bg-gray-200'
									}` }
									role="switch"
									aria-checked={ mod.enabled }
								>
									<span
										className={ `pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
											mod.enabled ? 'translate-x-5' : 'translate-x-0'
										}` }
									/>
								</button>
							</div>
						</div>
					) ) }
				</div>
			</div>

			{ /* Save Button */ }
			<div className="flex justify-end border-t border-gray-200 pt-6">
				<button
					onClick={ save }
					disabled={ saving }
					className="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition disabled:opacity-50"
				>
					{ saving ? (
						<>
							<span className="animate-spin -ml-1 mr-2 h-4 w-4 border-2 border-white border-t-transparent rounded-full inline-block"></span>
							{ __( 'Saving…', 'bb-custom-admin' ) }
						</>
					) : (
						__( 'Save Modules', 'bb-custom-admin' )
					) }
				</button>
			</div>
		</div>
	);
}
