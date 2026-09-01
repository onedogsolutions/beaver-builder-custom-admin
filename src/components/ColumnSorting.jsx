/**
 * Beaver Builder Custom Admin — Column Sorting & Filtering Settings
 *
 * Configure per-screen sortable columns and smart filter dropdowns
 * for WordPress admin list tables.
 *
 * @package OneDog\BBCustomAdmin
 * @since 1.4.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function ColumnSorting( { showToast } ) {
	const [ settings, setSettings ] = useState( { screens: {} } );
	const [ availableScreens, setAvailableScreens ] = useState( [] );
	const [ selectedScreen, setSelectedScreen ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );

	// Load data.
	const loadData = useCallback( async () => {
		setLoading( true );
		try {
			const data = await apiFetch( { path: '/onedog-bbca/v1/column-sorting' } );
			setSettings( data.settings || { screens: {} } );
			setAvailableScreens( data.available_screens || [] );

			// Auto-select the first screen.
			if ( data.available_screens?.length && ! selectedScreen ) {
				setSelectedScreen( data.available_screens[ 0 ].id );
			}
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setLoading( false );
		}
	}, [ showToast, selectedScreen ] );

	useEffect( () => {
		loadData();
	}, [ loadData ] );

	// Get settings for the selected screen.
	const getScreenSettings = ( screenId ) => {
		return settings.screens?.[ screenId ] || {
			sorting: false,
			filtering: false,
			default_sort: '',
			default_order: 'desc',
			filter_columns: [],
		};
	};

	// Update settings for the selected screen.
	const updateScreenSettings = ( screenId, updates ) => {
		setSettings( ( prev ) => ( {
			...prev,
			screens: {
				...prev.screens,
				[ screenId ]: {
					...getScreenSettings( screenId ),
					...updates,
				},
			},
		} ) );
	};

	// Toggle a column in the filter_columns array.
	const toggleFilterColumn = ( screenId, columnKey ) => {
		const current = getScreenSettings( screenId );
		const columns = current.filter_columns || [];
		const updated = columns.includes( columnKey )
			? columns.filter( ( c ) => c !== columnKey )
			: [ ...columns, columnKey ];
		updateScreenSettings( screenId, { filter_columns: updated } );
	};

	// Save settings.
	const save = async () => {
		setSaving( true );
		try {
			await apiFetch( {
				path: '/onedog-bbca/v1/column-sorting',
				method: 'POST',
				data: { settings },
			} );
			showToast( __( 'Column sorting settings saved.', 'bb-custom-admin' ) );
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

	const currentScreen = availableScreens.find( ( s ) => s.id === selectedScreen );
	const screenSettings = getScreenSettings( selectedScreen );
	const screenColumns = currentScreen?.columns || [];

	// Columns that can be used for default sort.
	const sortableColumns = screenColumns.filter(
		( c ) => c.key !== 'cb' && c.key !== 'comments'
	);

	return (
		<div className="space-y-6">
			{ /* Info Banner */ }
			<div className="rounded-md bg-blue-50 p-4 border border-blue-200">
				<div className="flex">
					<div className="flex-shrink-0">
						<span className="text-blue-400 text-lg">i</span>
					</div>
					<div className="ml-3">
						<p className="text-sm text-blue-700">
							{ __( 'Enable sorting and filtering for each list table screen below. When sorting is enabled, all columns become clickable for sorting. When filtering is enabled, dropdown filters appear above the table.', 'bb-custom-admin' ) }
						</p>
					</div>
				</div>
			</div>

			{ /* Screen Selector */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Select Screen', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Choose which list table to configure sorting and filtering for.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4">
					<select
						value={ selectedScreen }
						onChange={ ( e ) => setSelectedScreen( e.target.value ) }
						className="block w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
					>
						{ availableScreens.map( ( screen ) => (
							<option key={ screen.id } value={ screen.id }>
								{ screen.label }
							</option>
						) ) }
					</select>
				</div>
			</div>

			{ /* Screen Settings */ }
			{ currentScreen && (
				<>
					{ /* Sorting & Filtering Toggles */ }
					<div className="bg-white rounded-lg shadow-sm border border-gray-200">
						<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
							<h3 className="text-sm font-semibold text-gray-900">
								{ currentScreen.label } — { __( 'Features', 'bb-custom-admin' ) }
							</h3>
						</div>
						<div className="p-4 space-y-4">
							{ /* Sorting Toggle */ }
							<div className="flex items-center justify-between max-w-lg">
								<div>
									<p className="text-sm font-medium text-gray-900">
										{ __( 'Enable Sorting', 'bb-custom-admin' ) }
									</p>
									<p className="text-sm text-gray-500">
										{ __( 'Make all columns on this screen clickable for ascending/descending sort.', 'bb-custom-admin' ) }
									</p>
								</div>
								<button
									type="button"
									onClick={ () => updateScreenSettings( selectedScreen, {
										sorting: ! screenSettings.sorting,
									} ) }
									className={ `relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${
										screenSettings.sorting ? 'bg-indigo-600' : 'bg-gray-200'
									}` }
									role="switch"
									aria-checked={ screenSettings.sorting }
								>
									<span
										className={ `pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
											screenSettings.sorting ? 'translate-x-5' : 'translate-x-0'
										}` }
									/>
								</button>
							</div>

							{ /* Filtering Toggle */ }
							<div className="flex items-center justify-between max-w-lg">
								<div>
									<p className="text-sm font-medium text-gray-900">
										{ __( 'Enable Smart Filtering', 'bb-custom-admin' ) }
									</p>
									<p className="text-sm text-gray-500">
										{ __( 'Show filter dropdowns above the list table to narrow results by column values.', 'bb-custom-admin' ) }
									</p>
								</div>
								<button
									type="button"
									onClick={ () => updateScreenSettings( selectedScreen, {
										filtering: ! screenSettings.filtering,
									} ) }
									className={ `relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 ${
										screenSettings.filtering ? 'bg-indigo-600' : 'bg-gray-200'
									}` }
									role="switch"
									aria-checked={ screenSettings.filtering }
								>
									<span
										className={ `pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
											screenSettings.filtering ? 'translate-x-5' : 'translate-x-0'
										}` }
									/>
								</button>
							</div>
						</div>
					</div>

					{ /* Default Sort (only when sorting is enabled) */ }
					{ screenSettings.sorting && (
						<div className="bg-white rounded-lg shadow-sm border border-gray-200">
							<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
								<h3 className="text-sm font-semibold text-gray-900">
									{ __( 'Default Sort', 'bb-custom-admin' ) }
								</h3>
								<p className="text-xs text-gray-500 mt-1">
									{ __( 'Optionally set a default sort column and direction when the screen first loads.', 'bb-custom-admin' ) }
								</p>
							</div>
							<div className="p-4">
								<div className="flex items-center gap-x-4 max-w-lg">
									<select
										value={ screenSettings.default_sort || '' }
										onChange={ ( e ) => updateScreenSettings( selectedScreen, {
											default_sort: e.target.value,
										} ) }
										className="block flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
									>
										<option value="">{ __( '— No Default (WordPress default) —', 'bb-custom-admin' ) }</option>
										{ sortableColumns.map( ( col ) => (
											<option key={ col.key } value={ col.key }>
												{ col.label }
											</option>
										) ) }
									</select>

									<select
										value={ screenSettings.default_order || 'desc' }
										onChange={ ( e ) => updateScreenSettings( selectedScreen, {
											default_order: e.target.value,
										} ) }
										className="block w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
									>
										<option value="asc">{ __( 'Ascending', 'bb-custom-admin' ) }</option>
										<option value="desc">{ __( 'Descending', 'bb-custom-admin' ) }</option>
									</select>
								</div>
							</div>
						</div>
					) }

					{ /* Filter Column Selection (only when filtering is enabled) */ }
					{ screenSettings.filtering && screenColumns.length > 0 && (
						<div className="bg-white rounded-lg shadow-sm border border-gray-200">
							<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
								<h3 className="text-sm font-semibold text-gray-900">
									{ __( 'Filter Columns', 'bb-custom-admin' ) }
								</h3>
								<p className="text-xs text-gray-500 mt-1">
									{ __( 'Select which columns should have filter dropdowns. If none are selected, all columns will be filterable.', 'bb-custom-admin' ) }
								</p>
							</div>
							<div className="p-4">
								<div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
									{ screenColumns.map( ( col ) => (
										<label
											key={ col.key }
											className="flex items-center gap-x-3 rounded-md border border-gray-200 px-3 py-2 cursor-pointer hover:bg-gray-50 transition"
										>
											<input
												type="checkbox"
												checked={ ( screenSettings.filter_columns || [] ).includes( col.key ) }
												onChange={ () => toggleFilterColumn( selectedScreen, col.key ) }
												className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
											/>
											<span className="text-sm font-medium text-gray-700">
												{ col.label }
											</span>
										</label>
									) ) }
								</div>
								{ ( screenSettings.filter_columns || [] ).length === 0 && (
									<p className="text-xs text-gray-500 mt-3 italic">
										{ __( 'No columns selected — all columns will be filterable.', 'bb-custom-admin' ) }
									</p>
								) }
							</div>
						</div>
					) }
				</>
			) }

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
							{ __( 'Saving...', 'bb-custom-admin' ) }
						</>
					) : (
						__( 'Save Settings', 'bb-custom-admin' )
					) }
				</button>
			</div>
		</div>
	);
}
