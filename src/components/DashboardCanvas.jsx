/**
 * Beaver Builder Custom Admin — Dashboard Canvas Settings
 *
 * Full-bleed Beaver Builder dashboard replacement with 3rd-party
 * injection squashing and WordPress branding removal.
 *
 * @package OneDog\BBCustomAdmin
 * @since 1.3.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function DashboardCanvas( { showToast } ) {
	const [ settings, setSettings ] = useState( {
		layout_id: 0,
		target_roles: [],
		enable_squash: false,
		hide_wp_branding: false,
		full_bleed_rows: false,
		load_theme_styles: false,
	} );
	const [ layouts, setLayouts ] = useState( [] );
	const [ roles, setRoles ] = useState( {} );
	const [ bbActive, setBbActive ] = useState( true );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );

	// Load data.
	const loadData = useCallback( async () => {
		setLoading( true );
		try {
			const [ canvasData, layoutData ] = await Promise.all( [
				apiFetch( { path: '/onedog-bbca/v1/dashboard-canvas' } ),
				apiFetch( { path: '/onedog-bbca/v1/layouts' } ),
			] );
			setSettings( canvasData.settings || {
				layout_id: 0,
				target_roles: [],
				enable_squash: false,
				hide_wp_branding: false,
				full_bleed_rows: false,
				load_theme_styles: false,
			} );
			setLayouts( layoutData.layouts || [] );
			setRoles( layoutData.roles || {} );
			setBbActive( layoutData.bb_active !== false );
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
				path: '/onedog-bbca/v1/dashboard-canvas',
				method: 'POST',
				data: { settings },
			} );
			showToast( __( 'Dashboard canvas settings saved.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setSaving( false );
		}
	};

	// Toggle a role in the target roles array.
	const toggleRole = ( roleKey ) => {
		setSettings( ( prev ) => {
			const roles = prev.target_roles || [];
			const updated = roles.includes( roleKey )
				? roles.filter( ( r ) => r !== roleKey )
				: [ ...roles, roleKey ];
			return { ...prev, target_roles: updated };
		} );
	};

	if ( loading ) {
		return (
			<div className="flex justify-center py-12">
				<div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
			</div>
		);
	}

	const layoutOptions = [
		{ label: __( '— None (Disabled) —', 'bb-custom-admin' ), value: 0 },
		...layouts.map( ( l ) => ( {
			label: l.name,
			value: l.id,
		} ) ),
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
								{ __( 'Beaver Builder is not active. The dashboard canvas requires Beaver Builder to render layouts.', 'bb-custom-admin' ) }
							</p>
						</div>
					</div>
				</div>
			) }

			{ /* Layout Selector */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Dashboard Layout', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Select a Beaver Builder layout to replace the entire dashboard for targeted roles. Set to "None" to disable the canvas.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4">
					<select
						value={ settings.layout_id || 0 }
						onChange={ ( e ) => setSettings( ( p ) => ( {
							...p,
							layout_id: parseInt( e.target.value, 10 ) || 0,
						} ) ) }
						className="block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
					>
						{ layoutOptions.map( ( opt ) => (
							<option key={ opt.value } value={ opt.value }>
								{ opt.label }
							</option>
						) ) }
					</select>
				</div>
			</div>

			{ /* Target Roles */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Target Roles', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Select which user roles will see the dashboard canvas and be subject to squashing. Administrators always bypass via ?bbca_bypass=1.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4">
					<div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
						{ Object.entries( roles ).map( ( [ key, name ] ) => (
							<label
								key={ key }
								className="flex items-center gap-x-3 rounded-md border border-gray-200 px-3 py-2 cursor-pointer hover:bg-gray-50 transition"
							>
								<input
									type="checkbox"
									checked={ ( settings.target_roles || [] ).includes( key ) }
									onChange={ () => toggleRole( key ) }
									className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
								/>
								<span className="text-sm font-medium text-gray-700">{ name }</span>
							</label>
						) ) }
					</div>
				</div>
			</div>

			{ /* Squash Toggle */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( '3rd-Party Injection Squashing', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Aggressively suppresses third-party admin notices, toolbar items, and dashboard widgets for targeted roles on the dashboard page.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4">
					<div className="flex items-center justify-between max-w-md">
						<div>
							<p className="text-sm font-medium text-gray-900">
								{ __( 'Enable Squashing', 'bb-custom-admin' ) }
							</p>
							<p className="text-sm text-gray-500">
								{ __( 'Output-buffers and discards all admin notices, strips non-essential toolbar nodes, and hides notice CSS.', 'bb-custom-admin' ) }
							</p>
						</div>
						<button
							type="button"
							onClick={ () => setSettings( ( p ) => ( {
								...p,
								enable_squash: ! p.enable_squash,
							} ) ) }
							className={ `relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${
								settings.enable_squash ? 'bg-indigo-600' : 'bg-gray-200'
							}` }
							role="switch"
							aria-checked={ settings.enable_squash }
						>
							<span
								className={ `pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
									settings.enable_squash ? 'translate-x-5' : 'translate-x-0'
								}` }
							/>
						</button>
					</div>
				</div>
			</div>

			{ /* WP Branding Toggle */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'WordPress Branding', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Remove WordPress logos, update naggers, and footer credits on the dashboard for targeted roles.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4">
					<div className="flex items-center justify-between max-w-md">
						<div>
							<p className="text-sm font-medium text-gray-900">
								{ __( 'Hide WP Branding', 'bb-custom-admin' ) }
							</p>
							<p className="text-sm text-gray-500">
								{ __( 'Strips the WP logo from the toolbar, hides update nag notices, and clears the admin footer text and version.', 'bb-custom-admin' ) }
							</p>
						</div>
						<button
							type="button"
							onClick={ () => setSettings( ( p ) => ( {
								...p,
								hide_wp_branding: ! p.hide_wp_branding,
							} ) ) }
							className={ `relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${
								settings.hide_wp_branding ? 'bg-indigo-600' : 'bg-gray-200'
							}` }
							role="switch"
							aria-checked={ settings.hide_wp_branding }
						>
							<span
								className={ `pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
									settings.hide_wp_branding ? 'translate-x-5' : 'translate-x-0'
								}` }
							/>
						</button>
					</div>
				</div>
			</div>

			{ /* Full-Bleed Rows Toggle */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Row Width', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'By default the layout honours the fixed row width set in Beaver Builder’s global settings, which leaves a gutter on each side of the dashboard.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4">
					<div className="flex items-center justify-between max-w-md">
						<div>
							<p className="text-sm font-medium text-gray-900">
								{ __( 'Full-Bleed Rows', 'bb-custom-admin' ) }
							</p>
							<p className="text-sm text-gray-500">
								{ __( 'Let rows fill the full width of the admin content column instead. Only affects the dashboard canvas — the layout renders unchanged on the front end.', 'bb-custom-admin' ) }
							</p>
						</div>
						<button
							type="button"
							onClick={ () => setSettings( ( p ) => ( {
								...p,
								full_bleed_rows: ! p.full_bleed_rows,
							} ) ) }
							className={ `relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${
								settings.full_bleed_rows ? 'bg-indigo-600' : 'bg-gray-200'
							}` }
							role="switch"
							aria-checked={ settings.full_bleed_rows }
						>
							<span
								className={ `pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
									settings.full_bleed_rows ? 'translate-x-5' : 'translate-x-0'
								}` }
							/>
						</button>
					</div>
				</div>
			</div>

			{ /* Theme Styles Toggle */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Theme Styles', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Beaver Builder’s own layout, global and font styles are always loaded on the dashboard. Your theme’s stylesheet is not.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4">
					<div className="flex items-center justify-between max-w-md">
						<div>
							<p className="text-sm font-medium text-gray-900">
								{ __( 'Load Theme Stylesheet', 'bb-custom-admin' ) }
							</p>
							<p className="text-sm text-gray-500">
								{ __( 'Enable only if the layout still looks wrong — for example if your buttons take their colours from the Beaver Builder Theme customizer rather than from Beaver Builder’s global styles.', 'bb-custom-admin' ) }
							</p>
						</div>
						<button
							type="button"
							onClick={ () => setSettings( ( p ) => ( {
								...p,
								load_theme_styles: ! p.load_theme_styles,
							} ) ) }
							className={ `relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${
								settings.load_theme_styles ? 'bg-indigo-600' : 'bg-gray-200'
							}` }
							role="switch"
							aria-checked={ settings.load_theme_styles }
						>
							<span
								className={ `pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
									settings.load_theme_styles ? 'translate-x-5' : 'translate-x-0'
								}` }
							/>
						</button>
					</div>
					{ settings.load_theme_styles && (
						<div className="mt-3 rounded-md bg-amber-50 border border-amber-200 p-3 max-w-md">
							<p className="text-xs text-amber-800">
								{ __( 'Theme stylesheets are written for the front end, so this also restyles the admin menu, toolbar and footer. If the admin looks off after saving, turn it back off.', 'bb-custom-admin' ) }
							</p>
						</div>
					) }
				</div>
			</div>

			{ /* Safety Info */ }
			<div className="rounded-md bg-blue-50 p-4 border border-blue-200">
				<div className="flex">
					<div className="flex-shrink-0">
						<span className="text-blue-400 text-lg">ℹ</span>
					</div>
					<div className="ml-3">
						<p className="text-sm text-blue-700">
							{ __( 'Administrators can always bypass the canvas by appending', 'bb-custom-admin' ) }
							{ ' ' }
							<code className="bg-blue-100 px-1 py-0.5 rounded text-xs">?bbca_bypass=1</code>
							{ ' ' }
							{ __( 'to the dashboard URL. The canvas automatically disables when Beaver Builder is deactivated.', 'bb-custom-admin' ) }
						</p>
					</div>
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
