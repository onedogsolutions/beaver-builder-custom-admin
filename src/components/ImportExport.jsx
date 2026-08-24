/**
 * Beaver Builder Custom Admin — Import / Export
 *
 * Download and import full capability configurations and menu restrictions.
 *
 * @package OneDog\BBCustomAdmin
 */

import { useState, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function ImportExport( { showToast } ) {
	const [ exporting, setExporting ] = useState( false );
	const [ importing, setImporting ] = useState( false );
	const [ importingUre, setImportingUre ] = useState( false );
	const fileInputRef = useRef( null );
	const ureFileInputRef = useRef( null );

	// Export configuration.
	const handleExport = async () => {
		setExporting( true );
		try {
			const data = await apiFetch( { path: '/onedog-bbca/v1/export' } );
			const blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
			const url = URL.createObjectURL( blob );
			const a = document.createElement( 'a' );
			a.href = url;
			a.download = `bbca-config-${ new Date().toISOString().slice( 0, 10 ) }.json`;
			document.body.appendChild( a );
			a.click();
			document.body.removeChild( a );
			URL.revokeObjectURL( url );
			showToast( __( 'Configuration exported successfully.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setExporting( false );
		}
	};

	// Import configuration.
	const handleImport = async ( event ) => {
		const file = event.target.files?.[ 0 ];
		if ( ! file ) return;

		setImporting( true );
		try {
			const text = await file.text();
			const config = JSON.parse( text );

			await apiFetch( {
				path: '/onedog-bbca/v1/import',
				method: 'POST',
				data: config,
			} );

			showToast( __( 'Configuration imported successfully. Reloading…', 'bb-custom-admin' ) );
			setTimeout( () => window.location.reload(), 1500 );
		} catch ( err ) {
			showToast( err.message || __( 'Invalid configuration file.', 'bb-custom-admin' ), 'error' );
		} finally {
			setImporting( false );
			if ( fileInputRef.current ) {
				fileInputRef.current.value = '';
			}
		}
	};

	// Import User Role Editor Pro export (.dat).
	const handleUreImport = async ( event ) => {
		const file = event.target.files?.[ 0 ];
		if ( ! file ) return;

		setImportingUre( true );
		try {
			const text = await file.text();

			const data = await apiFetch( {
				path: '/onedog-bbca/v1/import-ure',
				method: 'POST',
				data: { content: text },
			} );

			const count = data.imported?.length || 0;
			showToast(
				sprintf(
					/* translators: %d: number of imported roles */
					__( '%d role(s) imported from User Role Editor export. Reloading…', 'bb-custom-admin' ),
					count
				)
			);
			setTimeout( () => window.location.reload(), 1500 );
		} catch ( err ) {
			showToast( err.message || __( 'Invalid User Role Editor export file.', 'bb-custom-admin' ), 'error' );
		} finally {
			setImportingUre( false );
			if ( ureFileInputRef.current ) {
				ureFileInputRef.current.value = '';
			}
		}
	};

	return (
		<div className="space-y-6">
			{ /* Export Section */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Export Configuration', 'bb-custom-admin' ) }
					</h3>
				</div>
				<div className="p-4">
					<p className="text-sm text-gray-600 mb-4">
						{ __( 'Download all role capabilities, menu restrictions, and module settings as a JSON file. Use this to back up your configuration or sync across client sites.', 'bb-custom-admin' ) }
					</p>
					<button
						onClick={ handleExport }
						disabled={ exporting }
						className="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition disabled:opacity-50"
					>
						{ exporting ? (
							<>
								<span className="animate-spin -ml-1 mr-2 h-4 w-4 border-2 border-white border-t-transparent rounded-full inline-block"></span>
								{ __( 'Exporting…', 'bb-custom-admin' ) }
							</>
						) : (
							<>
								<svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
									<path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
								</svg>
								{ __( 'Export JSON', 'bb-custom-admin' ) }
							</>
						) }
					</button>
				</div>
			</div>

			{ /* Import Section */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Import Configuration', 'bb-custom-admin' ) }
					</h3>
				</div>
				<div className="p-4">
					<p className="text-sm text-gray-600 mb-4">
						{ __( 'Import a previously exported JSON configuration file. This will overwrite current settings.', 'bb-custom-admin' ) }
					</p>
					<div className="rounded-md bg-yellow-50 p-4 border border-yellow-200 mb-4">
						<div className="flex">
							<div className="flex-shrink-0">
								<span className="text-yellow-400 text-lg">⚠</span>
							</div>
							<div className="ml-3">
								<p className="text-sm text-yellow-700">
									{ __( 'Warning: Importing will replace all current role capabilities, menu restrictions, and module settings.', 'bb-custom-admin' ) }
								</p>
							</div>
						</div>
					</div>
					<input
						ref={ fileInputRef }
						type="file"
						accept=".json"
						onChange={ handleImport }
						className="hidden"
						id="bbca-import-file"
					/>
					<label
						htmlFor="bbca-import-file"
						className={ `inline-flex items-center gap-x-2 rounded-md px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-inset transition cursor-pointer ${
							importing
								? 'bg-gray-100 text-gray-400 ring-gray-200 cursor-not-allowed'
								: 'bg-white text-gray-900 ring-gray-300 hover:bg-gray-50'
						}` }
					>
						{ importing ? (
							<>
								<span className="animate-spin -ml-1 mr-2 h-4 w-4 border-2 border-gray-400 border-t-transparent rounded-full inline-block"></span>
								{ __( 'Importing…', 'bb-custom-admin' ) }
							</>
						) : (
							<>
								<svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
									<path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
								</svg>
								{ __( 'Import JSON', 'bb-custom-admin' ) }
							</>
						) }
					</label>
				</div>
			</div>

			{ /* User Role Editor Pro Import Section */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Import from User Role Editor Pro', 'bb-custom-admin' ) }
					</h3>
				</div>
				<div className="p-4">
					<p className="text-sm text-gray-600 mb-4">
						{ __( 'Import roles and capabilities from a User Role Editor Pro export file (.dat). Matching roles will be updated and missing roles will be created. Only role capabilities are imported — URE Pro addon settings are ignored.', 'bb-custom-admin' ) }
					</p>
					<input
						ref={ ureFileInputRef }
						type="file"
						accept=".dat,.json"
						onChange={ handleUreImport }
						className="hidden"
						id="bbca-import-ure-file"
					/>
					<label
						htmlFor="bbca-import-ure-file"
						className={ `inline-flex items-center gap-x-2 rounded-md px-4 py-2.5 text-sm font-semibold shadow-sm ring-1 ring-inset transition cursor-pointer ${
							importingUre
								? 'bg-gray-100 text-gray-400 ring-gray-200 cursor-not-allowed'
								: 'bg-white text-gray-900 ring-gray-300 hover:bg-gray-50'
						}` }
					>
						{ importingUre ? (
							<>
								<span className="animate-spin -ml-1 mr-2 h-4 w-4 border-2 border-gray-400 border-t-transparent rounded-full inline-block"></span>
								{ __( 'Importing…', 'bb-custom-admin' ) }
							</>
						) : (
							<>
								<svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
									<path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
								</svg>
								{ __( 'Import URE Export (.dat)', 'bb-custom-admin' ) }
							</>
						) }
					</label>
				</div>
			</div>
		</div>
	);
}
