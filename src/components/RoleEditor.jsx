/**
 * Beaver Builder Custom Admin — Role Editor
 *
 * Role & Capability Management with categorized capability tree.
 *
 * @package OneDog\BBCustomAdmin
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Converts a capability slug to human-readable form.
 * e.g., edit_others_posts → Edit Others Posts
 */
const humanize = ( slug ) =>
	slug
		.replace( /_/g, ' ' )
		.replace( /\b\w/g, ( c ) => c.toUpperCase() );

/**
 * Categorizes capabilities into groups.
 */
const categorizeCapabilities = ( capabilities ) => {
	const groups = {
		general: { label: __( 'General', 'bb-custom-admin' ), caps: [] },
		posts: { label: __( 'Posts', 'bb-custom-admin' ), caps: [] },
		pages: { label: __( 'Pages', 'bb-custom-admin' ), caps: [] },
		themes: { label: __( 'Themes', 'bb-custom-admin' ), caps: [] },
		plugins: { label: __( 'Plugins', 'bb-custom-admin' ), caps: [] },
		users: { label: __( 'Users', 'bb-custom-admin' ), caps: [] },
		deprecated: { label: __( 'Deprecated', 'bb-custom-admin' ), caps: [] },
	};

	const customGroups = {};

	const corePatterns = {
		posts: /^(edit|delete|publish|read)_.*(post|posts)/i,
		pages: /^(edit|delete|publish|read)_.*(page|pages)/i,
		themes: /^(edit|switch|delete)_theme|customize|edit_theme/i,
		plugins: /^(activate|deactivate|edit|delete|install|update)_plugin/i,
		users: /^(edit|delete|create|list|promote|remove)_user/i,
		deprecated: /^(unfiltered_html|level_)/i,
	};

	const pluginPatterns = {
		woocommerce: /^manage_woocommerce|woocommerce/i,
		'beaver-builder': /^fl_builder|flbuilder/i,
		pods: /^pods_/i,
		amelia: /^amelia_/i,
	};

	Object.entries( capabilities ).forEach( ( [ cap, granted ] ) => {
		let categorized = false;

		// Check plugin patterns first.
		for ( const [ plugin, pattern ] of Object.entries( pluginPatterns ) ) {
			if ( pattern.test( cap ) ) {
				if ( ! customGroups[ plugin ] ) {
					customGroups[ plugin ] = {
						label: humanize( plugin ),
						caps: [],
					};
				}
				customGroups[ plugin ].caps.push( { slug: cap, granted } );
				categorized = true;
				break;
			}
		}

		if ( categorized ) return;

		// Check core patterns.
		for ( const [ group, pattern ] of Object.entries( corePatterns ) ) {
			if ( pattern.test( cap ) ) {
				groups[ group ].caps.push( { slug: cap, granted } );
				categorized = true;
				break;
			}
		}

		if ( categorized ) return;

		// Default to general.
		groups.general.caps.push( { slug: cap, granted } );
	} );

	// Merge custom groups.
	return { ...groups, ...customGroups };
};

export default function RoleEditor( { showToast } ) {
	const [ roles, setRoles ] = useState( {} );
	const [ selectedRole, setSelectedRole ] = useState( '' );
	const [ capabilities, setCapabilities ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );

	// UI state.
	const [ search, setSearch ] = useState( '' );
	const [ humanReadable, setHumanReadable ] = useState( true );
	const [ grantedOnly, setGrantedOnly ] = useState( false );

	// Modal state.
	const [ showAddModal, setShowAddModal ] = useState( false );
	const [ showRenameModal, setShowRenameModal ] = useState( false );
	const [ showDeleteModal, setShowDeleteModal ] = useState( false );
	const [ newRoleName, setNewRoleName ] = useState( '' );
	const [ newRoleSlug, setNewRoleSlug ] = useState( '' );
	const [ cloneFromRole, setCloneFromRole ] = useState( '' );
	const [ renameLabel, setRenameLabel ] = useState( '' );

	// Load roles and capabilities.
	const loadData = useCallback( async () => {
		setLoading( true );
		try {
			const data = await apiFetch( { path: '/onedog-bbca/v1/roles' } );
			setRoles( data.roles || {} );
			const roleKeys = Object.keys( data.roles || {} );
			if ( roleKeys.length && ! selectedRole ) {
				setSelectedRole( roleKeys[ 0 ] );
			}
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setLoading( false );
		}
	}, [ selectedRole, showToast ] );

	// Load capabilities for selected role.
	const loadCapabilities = useCallback( async () => {
		if ( ! selectedRole ) return;
		setLoading( true );
		try {
			const data = await apiFetch( {
				path: `/onedog-bbca/v1/roles/${ encodeURIComponent( selectedRole ) }`,
			} );
			setCapabilities( data.capabilities || {} );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setLoading( false );
		}
	}, [ selectedRole, showToast ] );

	useEffect( () => {
		loadData();
	}, [ loadData ] );

	useEffect( () => {
		loadCapabilities();
	}, [ loadCapabilities ] );

	// Save capabilities.
	const saveCapabilities = async () => {
		setSaving( true );
		try {
			await apiFetch( {
				path: `/onedog-bbca/v1/roles/${ encodeURIComponent( selectedRole ) }`,
				method: 'POST',
				data: { capabilities },
			} );
			showToast( __( 'Capabilities saved.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setSaving( false );
		}
	};

	// Toggle a single capability.
	const toggleCapability = ( slug ) => {
		setCapabilities( ( prev ) => ( {
			...prev,
			[ slug ]: ! prev[ slug ],
		} ) );
	};

	// Clear all capabilities.
	const clearAllCapabilities = async () => {
		if ( ! window.confirm( __( 'Are you sure you want to clear ALL capabilities for this role?', 'bb-custom-admin' ) ) ) {
			return;
		}
		setSaving( true );
		try {
			await apiFetch( {
				path: `/onedog-bbca/v1/roles/${ encodeURIComponent( selectedRole ) }/clear`,
				method: 'POST',
			} );
			setCapabilities( {} );
			showToast( __( 'All capabilities cleared.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setSaving( false );
		}
	};

	// Rollback / Reset role.
	const rollbackRole = async () => {
		if ( ! window.confirm( __( 'Reset this role to its default WordPress capabilities?', 'bb-custom-admin' ) ) ) {
			return;
		}
		setSaving( true );
		try {
			const data = await apiFetch( {
				path: `/onedog-bbca/v1/roles/${ encodeURIComponent( selectedRole ) }/rollback`,
				method: 'POST',
			} );
			setCapabilities( data.capabilities || {} );
			showToast( __( 'Role reset to default.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setSaving( false );
		}
	};

	// Add new role.
	const addRole = async () => {
		if ( ! newRoleName.trim() ) {
			showToast( __( 'Role name is required.', 'bb-custom-admin' ), 'error' );
			return;
		}
		setSaving( true );
		try {
			const data = await apiFetch( {
				path: '/onedog-bbca/v1/roles',
				method: 'POST',
				data: {
					name: newRoleName,
					slug: newRoleSlug || undefined,
					clone_from: cloneFromRole || undefined,
				},
			} );
			setRoles( data.roles || {} );
			setSelectedRole( data.slug || '' );
			setShowAddModal( false );
			setNewRoleName( '' );
			setNewRoleSlug( '' );
			setCloneFromRole( '' );
			showToast( __( 'Role created.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setSaving( false );
		}
	};

	// Rename role.
	const renameRole = async () => {
		if ( ! renameLabel.trim() ) {
			showToast( __( 'Display name is required.', 'bb-custom-admin' ), 'error' );
			return;
		}
		setSaving( true );
		try {
			const data = await apiFetch( {
				path: `/onedog-bbca/v1/roles/${ encodeURIComponent( selectedRole ) }/rename`,
				method: 'POST',
				data: { label: renameLabel },
			} );
			setRoles( data.roles || {} );
			setShowRenameModal( false );
			showToast( __( 'Role renamed.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setSaving( false );
		}
	};

	// Delete role.
	const deleteRole = async () => {
		setSaving( true );
		try {
			const data = await apiFetch( {
				path: `/onedog-bbca/v1/roles/${ encodeURIComponent( selectedRole ) }`,
				method: 'DELETE',
			} );
			setRoles( data.roles || {} );
			const roleKeys = Object.keys( data.roles || {} );
			setSelectedRole( roleKeys[ 0 ] || '' );
			setShowDeleteModal( false );
			showToast( __( 'Role deleted.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setSaving( false );
		}
	};

	// Check if role is a core role.
	const isCoreRole = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ].includes( selectedRole );

	// Filter and categorize capabilities.
	const grouped = categorizeCapabilities( capabilities );
	const filteredGroups = Object.entries( grouped )
		.map( ( [ key, group ] ) => ( {
			key,
			...group,
			caps: group.caps.filter( ( cap ) => {
				const matchesSearch =
					! search ||
					cap.slug.toLowerCase().includes( search.toLowerCase() ) ||
					humanize( cap.slug ).toLowerCase().includes( search.toLowerCase() );
				const matchesGranted = ! grantedOnly || cap.granted;
				return matchesSearch && matchesGranted;
			} ),
		} ) )
		.filter( ( group ) => group.caps.length > 0 );

	if ( loading && ! Object.keys( roles ).length ) {
		return (
			<div className="flex justify-center py-12">
				<div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
			</div>
		);
	}

	return (
		<div className="space-y-6">
			{ /* Action Bar */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
				<div className="flex flex-wrap items-center gap-4">
					{ /* Role Selector */ }
					<div className="flex-1 min-w-[200px]">
						<label className="block text-sm font-medium text-gray-700 mb-1">
							{ __( 'Select Role', 'bb-custom-admin' ) }
						</label>
						<select
							value={ selectedRole }
							onChange={ ( e ) => setSelectedRole( e.target.value ) }
							className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
						>
							{ Object.entries( roles ).map( ( [ slug, name ] ) => (
								<option key={ slug } value={ slug }>
									{ name }
								</option>
							) ) }
						</select>
					</div>

					{ /* Action Buttons */ }
					<div className="flex flex-wrap gap-2 pt-5">
						<button
							onClick={ () => setShowAddModal( true ) }
							className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
						>
							{ __( 'Add Role', 'bb-custom-admin' ) }
						</button>
						<button
							onClick={ () => {
								setRenameLabel( roles[ selectedRole ] || '' );
								setShowRenameModal( true );
							} }
							disabled={ isCoreRole }
							className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
						>
							{ __( 'Rename', 'bb-custom-admin' ) }
						</button>
						<button
							onClick={ () => setShowDeleteModal( true ) }
							disabled={ isCoreRole }
							className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-red-300 hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
						>
							{ __( 'Delete', 'bb-custom-admin' ) }
						</button>
						<button
							onClick={ clearAllCapabilities }
							className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
						>
							{ __( 'Clear All', 'bb-custom-admin' ) }
						</button>
						<button
							onClick={ rollbackRole }
							disabled={ ! isCoreRole }
							className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
							title={ isCoreRole ? __( 'Reset to default capabilities', 'bb-custom-admin' ) : __( 'Only core roles can be reset', 'bb-custom-admin' ) }
						>
							{ __( 'Reset', 'bb-custom-admin' ) }
						</button>
					</div>
				</div>
			</div>

			{ /* Search & Filters */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
				<div className="flex flex-wrap items-center gap-4">
					<div className="flex-1 min-w-[200px]">
						<input
							type="text"
							placeholder={ __( 'Search capabilities…', 'bb-custom-admin' ) }
							value={ search }
							onChange={ ( e ) => setSearch( e.target.value ) }
							className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
						/>
					</div>
					<label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
						<input
							type="checkbox"
							checked={ humanReadable }
							onChange={ ( e ) => setHumanReadable( e.target.checked ) }
							className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
						/>
						{ __( 'Human readable', 'bb-custom-admin' ) }
					</label>
					<label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
						<input
							type="checkbox"
							checked={ grantedOnly }
							onChange={ ( e ) => setGrantedOnly( e.target.checked ) }
							className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
						/>
						{ __( 'Granted only', 'bb-custom-admin' ) }
					</label>
				</div>
			</div>

			{ /* Capability Tree */ }
			<div className="space-y-4">
				{ filteredGroups.map( ( group ) => (
					<div key={ group.key } className="bg-white rounded-lg shadow-sm border border-gray-200">
						<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
							<h3 className="text-sm font-semibold text-gray-900">
								{ group.label }
								<span className="ml-2 text-xs font-normal text-gray-500">
									( { group.caps.filter( ( c ) => c.granted ).length } / { group.caps.length } )
								</span>
							</h3>
						</div>
						<div className="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
							{ group.caps.map( ( cap ) => (
								<label
									key={ cap.slug }
									className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer hover:bg-gray-50 rounded px-2 py-1"
								>
									<input
										type="checkbox"
										checked={ cap.granted }
										onChange={ () => toggleCapability( cap.slug ) }
										className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
									/>
									<span title={ cap.slug }>
										{ humanReadable ? humanize( cap.slug ) : cap.slug }
									</span>
								</label>
							) ) }
						</div>
					</div>
				) ) }
			</div>

			{ /* Save Button */ }
			<div className="flex justify-end border-t border-gray-200 pt-6">
				<button
					onClick={ saveCapabilities }
					disabled={ saving }
					className="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition disabled:opacity-50"
				>
					{ saving ? (
						<>
							<span className="animate-spin -ml-1 mr-2 h-4 w-4 border-2 border-white border-t-transparent rounded-full inline-block"></span>
							{ __( 'Saving…', 'bb-custom-admin' ) }
						</>
					) : (
						__( 'Save Capabilities', 'bb-custom-admin' )
					) }
				</button>
			</div>

			{ /* Add Role Modal */ }
			{ showAddModal && (
				<div className="fixed inset-0 z-50 overflow-y-auto">
					<div className="flex min-h-full items-center justify-center p-4">
						<div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={ () => setShowAddModal( false ) }></div>
						<div className="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
							<h3 className="text-lg font-semibold text-gray-900 mb-4">
								{ __( 'Add New Role', 'bb-custom-admin' ) }
							</h3>
							<div className="space-y-4">
								<div>
									<label className="block text-sm font-medium text-gray-700 mb-1">
										{ __( 'Role Name', 'bb-custom-admin' ) }
									</label>
									<input
										type="text"
										value={ newRoleName }
										onChange={ ( e ) => setNewRoleName( e.target.value ) }
										placeholder={ __( 'e.g., Content Manager', 'bb-custom-admin' ) }
										className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
									/>
								</div>
								<div>
									<label className="block text-sm font-medium text-gray-700 mb-1">
										{ __( 'Role Slug (optional)', 'bb-custom-admin' ) }
									</label>
									<input
										type="text"
										value={ newRoleSlug }
										onChange={ ( e ) => setNewRoleSlug( e.target.value.toLowerCase().replace( /[^a-z0-9_]/g, '_' ) ) }
										placeholder={ __( 'auto-generated if empty', 'bb-custom-admin' ) }
										className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
									/>
								</div>
								<div>
									<label className="block text-sm font-medium text-gray-700 mb-1">
										{ __( 'Clone From (optional)', 'bb-custom-admin' ) }
									</label>
									<select
										value={ cloneFromRole }
										onChange={ ( e ) => setCloneFromRole( e.target.value ) }
										className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
									>
										<option value="">{ __( 'Start Blank', 'bb-custom-admin' ) }</option>
										{ Object.entries( roles ).map( ( [ slug, name ] ) => (
											<option key={ slug } value={ slug }>
												{ name }
											</option>
										) ) }
									</select>
								</div>
							</div>
							<div className="mt-6 flex justify-end gap-3">
								<button
									onClick={ () => setShowAddModal( false ) }
									className="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
								>
									{ __( 'Cancel', 'bb-custom-admin' ) }
								</button>
								<button
									onClick={ addRole }
									disabled={ saving }
									className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
								>
									{ __( 'Create Role', 'bb-custom-admin' ) }
								</button>
							</div>
						</div>
					</div>
				</div>
			) }

			{ /* Rename Role Modal */ }
			{ showRenameModal && (
				<div className="fixed inset-0 z-50 overflow-y-auto">
					<div className="flex min-h-full items-center justify-center p-4">
						<div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={ () => setShowRenameModal( false ) }></div>
						<div className="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
							<h3 className="text-lg font-semibold text-gray-900 mb-4">
								{ __( 'Rename Role', 'bb-custom-admin' ) }
							</h3>
							<div>
								<label className="block text-sm font-medium text-gray-700 mb-1">
									{ __( 'Display Name', 'bb-custom-admin' ) }
								</label>
								<input
									type="text"
									value={ renameLabel }
									onChange={ ( e ) => setRenameLabel( e.target.value ) }
									className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
								/>
							</div>
							<div className="mt-6 flex justify-end gap-3">
								<button
									onClick={ () => setShowRenameModal( false ) }
									className="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
								>
									{ __( 'Cancel', 'bb-custom-admin' ) }
								</button>
								<button
									onClick={ renameRole }
									disabled={ saving }
									className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
								>
									{ __( 'Rename', 'bb-custom-admin' ) }
								</button>
							</div>
						</div>
					</div>
				</div>
			) }

			{ /* Delete Role Modal */ }
			{ showDeleteModal && (
				<div className="fixed inset-0 z-50 overflow-y-auto">
					<div className="flex min-h-full items-center justify-center p-4">
						<div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={ () => setShowDeleteModal( false ) }></div>
						<div className="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6">
							<h3 className="text-lg font-semibold text-gray-900 mb-4">
								{ __( 'Delete Role', 'bb-custom-admin' ) }
							</h3>
							<p className="text-sm text-gray-600">
								{ __( 'Are you sure you want to delete the role', 'bb-custom-admin' ) }{ ' ' }
								<strong>{ roles[ selectedRole ] }</strong>?
								{ __( 'Users assigned to this role will need to be reassigned.', 'bb-custom-admin' ) }
							</p>
							<div className="mt-6 flex justify-end gap-3">
								<button
									onClick={ () => setShowDeleteModal( false ) }
									className="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
								>
									{ __( 'Cancel', 'bb-custom-admin' ) }
								</button>
								<button
									onClick={ deleteRole }
									disabled={ saving }
									className="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 disabled:opacity-50"
								>
									{ __( 'Delete Role', 'bb-custom-admin' ) }
								</button>
							</div>
						</div>
					</div>
				</div>
			) }
		</div>
	);
}
