/**
 * Beaver Builder Custom Admin — Option Cleaner
 *
 * Scan for and remove orphaned wp_options left behind by uninstalled plugins.
 *
 * @package OneDog\BBCustomAdmin
 */

import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Formats a byte count into a human-readable string.
 *
 * @param {number} bytes Byte count.
 * @return {string} Formatted size.
 */
const formatSize = ( bytes ) => {
	if ( bytes >= 1048576 ) {
		return ( bytes / 1048576 ).toFixed( 1 ) + ' MB';
	}
	if ( bytes >= 1024 ) {
		return ( bytes / 1024 ).toFixed( 1 ) + ' KB';
	}
	return bytes + ' B';
};

export default function OptionCleaner( { showToast } ) {
	const [ groups, setGroups ] = useState( [] );
	const [ totalOptions, setTotalOptions ] = useState( 0 );
	const [ scanned, setScanned ] = useState( false );
	const [ scanning, setScanning ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );
	const [ confirming, setConfirming ] = useState( false );
	const [ selected, setSelected ] = useState( [] );
	const [ prefix, setPrefix ] = useState( '' );
	const [ expanded, setExpanded ] = useState( {} );

	// Run scan (auto or manual prefix).
	const handleScan = async () => {
		setScanning( true );
		setConfirming( false );
		setSelected( [] );
		setExpanded( {} );
		try {
			const path = prefix.trim()
				? `/onedog-bbca/v1/option-cleaner/scan?prefix=${ encodeURIComponent( prefix.trim() ) }`
				: '/onedog-bbca/v1/option-cleaner/scan';

			const data = await apiFetch( { path } );
			setGroups( data.groups || [] );
			setTotalOptions( data.total_options || 0 );
			setScanned( true );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setScanning( false );
		}
	};

	// Toggle a single group selection.
	const toggleGroup = ( groupPrefix ) => {
		setSelected( ( prev ) =>
			prev.includes( groupPrefix )
				? prev.filter( ( p ) => p !== groupPrefix )
				: [ ...prev, groupPrefix ]
		);
	};

	// Toggle select-all.
	const toggleAll = () => {
		if ( selected.length === groups.length ) {
			setSelected( [] );
		} else {
			setSelected( groups.map( ( g ) => g.prefix ) );
		}
	};

	// Toggle sample list expansion.
	const toggleExpand = ( groupPrefix ) => {
		setExpanded( ( prev ) => ( { ...prev, [ groupPrefix ]: ! prev[ groupPrefix ] } ) );
	};

	// Delete selected groups.
	const handleDelete = async () => {
		setDeleting( true );
		try {
			const data = await apiFetch( {
				path: '/onedog-bbca/v1/option-cleaner/delete',
				method: 'POST',
				data: { prefixes: selected },
			} );
			showToast(
				sprintf(
					/* translators: %d: number of options removed */
					__( 'Removed %d orphaned options.', 'bb-custom-admin' ),
					data.deleted
				)
			);
			setConfirming( false );
			setSelected( [] );
			// Re-scan to refresh the list.
			const rescan = await apiFetch( { path: '/onedog-bbca/v1/option-cleaner/scan' } );
			setGroups( rescan.groups || [] );
			setTotalOptions( rescan.total_options || 0 );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setDeleting( false );
		}
	};

	const selectedCount = groups
		.filter( ( g ) => selected.includes( g.prefix ) )
		.reduce( ( sum, g ) => sum + g.count, 0 );

	return (
		<div className="space-y-6">
			{ /* Scan Controls */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Scan for Orphaned Options', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __(
							'Finds leftover wp_options entries from plugins that were removed without cleaning up. Options belonging to installed plugins and WordPress core are automatically excluded.',
							'bb-custom-admin'
						) }
					</p>
				</div>
				<div className="p-4">
					<div className="flex flex-col sm:flex-row gap-3">
						<input
							type="text"
							value={ prefix }
							onChange={ ( e ) => setPrefix( e.target.value ) }
							placeholder={ __( 'Optional prefix filter, e.g. rank_math_', 'bb-custom-admin' ) }
							className="block w-full sm:max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm border px-3 py-2"
						/>
						<button
							onClick={ handleScan }
							disabled={ scanning }
							className="inline-flex items-center justify-center gap-x-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition disabled:opacity-50"
						>
							{ scanning ? (
								<>
									<span className="animate-spin -ml-1 mr-2 h-4 w-4 border-2 border-white border-t-transparent rounded-full inline-block"></span>
									{ __( 'Scanning…', 'bb-custom-admin' ) }
								</>
							) : (
								<>
									<svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
										<path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
									</svg>
									{ __( 'Scan for Orphaned Options', 'bb-custom-admin' ) }
								</>
							) }
						</button>
					</div>
				</div>
			</div>

			{ /* Warning Banner */ }
			<div className="rounded-md bg-yellow-50 p-4 border border-yellow-200">
				<div className="flex">
					<div className="flex-shrink-0">
						<span className="text-yellow-400 text-lg">⚠</span>
					</div>
					<div className="ml-3">
						<p className="text-sm text-yellow-700">
							{ __(
								'Deleting options is irreversible. Review the results carefully and consider taking a database backup before removing anything.',
								'bb-custom-admin'
							) }
						</p>
					</div>
				</div>
			</div>

			{ /* Results */ }
			{ scanned && ! scanning && (
				<div className="bg-white rounded-lg shadow-sm border border-gray-200">
					<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg flex items-center justify-between">
						<div>
							<h3 className="text-sm font-semibold text-gray-900">
								{ __( 'Scan Results', 'bb-custom-admin' ) }
							</h3>
							<p className="text-xs text-gray-500 mt-1">
								{ sprintf(
									/* translators: 1: number of orphan groups, 2: total options scanned */
									__( '%1$d orphaned group(s) found out of %2$d total options scanned.', 'bb-custom-admin' ),
									groups.length,
									totalOptions
								) }
							</p>
						</div>
						{ groups.length > 0 && (
							<label className="flex items-center gap-x-2 text-sm text-gray-600 cursor-pointer">
								<input
									type="checkbox"
									checked={ selected.length === groups.length && groups.length > 0 }
									onChange={ toggleAll }
									className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
								/>
								{ __( 'Select all', 'bb-custom-admin' ) }
							</label>
						) }
					</div>

					{ groups.length === 0 ? (
						<div className="p-8 text-center">
							<svg className="mx-auto h-10 w-10 text-green-400" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
								<path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
							</svg>
							<p className="mt-3 text-sm font-medium text-gray-900">
								{ __( 'No orphaned options found', 'bb-custom-admin' ) }
							</p>
							<p className="mt-1 text-sm text-gray-500">
								{ __( 'Your options table looks clean. Nothing to remove.', 'bb-custom-admin' ) }
							</p>
						</div>
					) : (
						<div className="p-4 divide-y divide-gray-200">
							{ groups.map( ( group ) => (
								<div key={ group.prefix } className="py-4 first:pt-0 last:pb-0">
									<div className="flex items-center justify-between">
										<div className="flex items-center gap-x-3 flex-1 min-w-0">
											<input
												type="checkbox"
												checked={ selected.includes( group.prefix ) }
												onChange={ () => toggleGroup( group.prefix ) }
												className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600 flex-shrink-0"
											/>
											<div className="min-w-0">
												<p className="text-sm font-medium text-gray-900 font-mono truncate">
													{ group.prefix }_*
												</p>
												<p className="text-xs text-gray-500">
													{ sprintf(
														/* translators: 1: option count, 2: total size */
														__( '%1$d options · %2$s', 'bb-custom-admin' ),
														group.count,
														formatSize( group.size )
													) }
												</p>
											</div>
										</div>
										<button
											onClick={ () => toggleExpand( group.prefix ) }
											className="text-xs text-indigo-600 hover:text-indigo-500 font-medium flex-shrink-0 ml-3"
										>
											{ expanded[ group.prefix ]
												? __( 'Hide samples', 'bb-custom-admin' )
												: __( 'Show samples', 'bb-custom-admin' ) }
										</button>
									</div>
									{ expanded[ group.prefix ] && (
										<ul className="mt-2 ml-7 space-y-1">
											{ group.samples.map( ( sample ) => (
												<li key={ sample } className="text-xs font-mono text-gray-500 bg-gray-50 rounded px-2 py-1">
													{ sample }
												</li>
											) ) }
											{ group.count > group.samples.length && (
												<li className="text-xs text-gray-400 italic px-2">
													{ sprintf(
														/* translators: %d: number of remaining options */
														__( '…and %d more', 'bb-custom-admin' ),
														group.count - group.samples.length
													) }
												</li>
											) }
										</ul>
									) }
								</div>
							) ) }
						</div>
					) }
				</div>
			) }

			{ /* Delete Flow */ }
			{ scanned && groups.length > 0 && (
				<div className="border-t border-gray-200 pt-6">
					{ ! confirming ? (
						<div className="flex justify-end">
							<button
								onClick={ () => setConfirming( true ) }
								disabled={ selected.length === 0 }
								className="inline-flex items-center gap-x-2 rounded-md bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
							>
								<svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
									<path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
								</svg>
								{ sprintf(
									/* translators: %d: number of selected groups */
									__( 'Delete Selected (%d groups)', 'bb-custom-admin' ),
									selected.length
								) }
							</button>
						</div>
					) : (
						<div className="rounded-md bg-red-50 border border-red-200 p-4">
							<div className="flex items-start gap-x-3">
								<span className="text-red-500 text-lg flex-shrink-0">⚠</span>
								<div className="flex-1">
									<p className="text-sm font-medium text-red-800">
										{ sprintf(
											/* translators: 1: number of options, 2: number of groups */
											__( 'This will permanently remove %1$d options across %2$d group(s). This cannot be undone.', 'bb-custom-admin' ),
											selectedCount,
											selected.length
										) }
									</p>
									<div className="mt-3 flex gap-x-3">
										<button
											onClick={ handleDelete }
											disabled={ deleting }
											className="inline-flex items-center gap-x-2 rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 transition disabled:opacity-50"
										>
											{ deleting ? (
												<>
													<span className="animate-spin -ml-1 mr-2 h-4 w-4 border-2 border-white border-t-transparent rounded-full inline-block"></span>
													{ __( 'Deleting…', 'bb-custom-admin' ) }
												</>
											) : (
												__( 'Confirm Delete', 'bb-custom-admin' )
											) }
										</button>
										<button
											onClick={ () => setConfirming( false ) }
											disabled={ deleting }
											className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 transition"
										>
											{ __( 'Cancel', 'bb-custom-admin' ) }
										</button>
									</div>
								</div>
							</div>
						</div>
					) }
				</div>
			) }
		</div>
	);
}
