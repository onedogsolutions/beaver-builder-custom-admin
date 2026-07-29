/**
 * Beaver Builder Custom Admin — Welcome Screen
 *
 * Role-based Beaver Builder welcome template assignment.
 *
 * @package OneDog\BBCustomAdmin
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function WelcomeScreen( { showToast } ) {
	const [ layouts, setLayouts ] = useState( [] );
	const [ roles, setRoles ] = useState( {} );
	const [ settings, setSettings ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ bbActive, setBbActive ] = useState( true );

	// Load data.
	const loadData = useCallback( async () => {
		setLoading( true );
		try {
			const [ layoutData, settingsData ] = await Promise.all( [
				apiFetch( { path: '/onedog-bbca/v1/layouts' } ),
				apiFetch( { path: '/onedog-bbca/v1/settings' } ),
			] );
			setLayouts( layoutData.layouts || [] );
			setRoles( layoutData.roles || {} );
			setBbActive( layoutData.bb_active !== false );
			setSettings( settingsData.template || {} );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setLoading( false );
		}
	}, [ showToast ] );

	useEffect( () => {
		loadData();
	}, [ loadData ] );

	// Save settings.
	const save = async () => {
		setSaving( true );
		try {
			await apiFetch( {
				path: '/onedog-bbca/v1/settings',
				method: 'POST',
				data: { template: settings },
			} );
			showToast( __( 'Welcome screen settings saved.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) {
		return (
			<div className="flex justify-center py-12">
				<div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
			</div>
		);
	}

	const options = [
		{ label: __( '— None —', 'bb-custom-admin' ), value: 'none' },
		...layouts.map( ( l ) => ( { label: l.name, value: l.slug } ) ),
	];

	return (
		<div className="space-y-6">
			{ /* Beaver Builder Warning */ }
			{ ! bbActive && (
				<div className="rounded-md bg-yellow-50 p-4 border border-yellow-200">
					<div className="flex">
						<div className="flex-shrink-0">
							<span className="text-yellow-400 text-lg">⚠</span>
						</div>
						<div className="ml-3">
							<p className="text-sm text-yellow-700">
								{ __( 'Beaver Builder is not active. Layouts are unavailable.', 'bb-custom-admin' ) }
							</p>
						</div>
					</div>
				</div>
			) }

			{ /* Default Fallback Template */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Default Fallback Template', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Used when a role has no specific template assigned.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4">
					<select
						value={ settings._default || 'none' }
						onChange={ ( e ) => setSettings( ( p ) => ( { ...p, _default: e.target.value } ) ) }
						className="block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
					>
						{ options.map( ( opt ) => (
							<option key={ opt.value } value={ opt.value }>
								{ opt.label }
							</option>
						) ) }
					</select>
				</div>
			</div>

			{ /* Per-Role Templates */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Per-Role Templates', 'bb-custom-admin' ) }
					</h3>
				</div>
				<div className="p-4 space-y-4">
					{ Object.entries( roles ).map( ( [ key, name ] ) => (
						<div key={ key } className="flex items-center gap-4">
							<label className="w-40 text-sm font-medium text-gray-700">
								{ name }
							</label>
							<select
								value={ settings[ key ] || 'none' }
								onChange={ ( e ) => setSettings( ( p ) => ( { ...p, [ key ]: e.target.value } ) ) }
								className="block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
							>
								{ options.map( ( opt ) => (
									<option key={ opt.value } value={ opt.value }>
										{ opt.label }
									</option>
								) ) }
							</select>
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
						__( 'Save Settings', 'bb-custom-admin' )
					) }
				</button>
			</div>
		</div>
	);
}
