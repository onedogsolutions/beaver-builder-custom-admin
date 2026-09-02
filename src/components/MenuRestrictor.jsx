/**
 * Beaver Builder Custom Admin — Menu Restrictor
 *
 * Per-role admin menu and toolbar visibility management.
 *
 * @package OneDog\BBCustomAdmin
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export default function MenuRestrictor( { showToast } ) {
	const [ roles, setRoles ] = useState( {} );
	const [ selectedRole, setSelectedRole ] = useState( '' );
	const [ menus, setMenus ] = useState( [] );
	const [ toolbarNodes, setToolbarNodes ] = useState( [] );
	const [ availableExtraItems, setAvailableExtraItems ] = useState( [] );
	const [ menuRules, setMenuRules ] = useState( {} );
	const [ toolbarRules, setToolbarRules ] = useState( {} );
	const [ extraMenuRules, setExtraMenuRules ] = useState( {} );
	const [ customMenuItems, setCustomMenuItems ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ filter, setFilter ] = useState( 'all' ); // all, selected, not-selected
	const [ customLabel, setCustomLabel ] = useState( '' );
	const [ customSlug, setCustomSlug ] = useState( '' );
	const [ customParent, setCustomParent ] = useState( '' );

	// Load data.
	const loadData = useCallback( async () => {
		setLoading( true );
		try {
			const data = await apiFetch( { path: '/onedog-bbca/v1/menu-visibility' } );
			setRoles( data.roles || {} );
			setMenus( data.available_menus || [] );
			setToolbarNodes( data.available_toolbar || [] );
			setAvailableExtraItems( data.available_extra_items || [] );
			setMenuRules( data.menu_rules || {} );
			setToolbarRules( data.toolbar_rules || {} );
			setExtraMenuRules( data.extra_menu_rules || {} );
			setCustomMenuItems( data.custom_menu_items || [] );
			const roleKeys = Object.keys( data.roles || {} );
			if ( roleKeys.length ) {
				setSelectedRole( roleKeys[ 0 ] );
			}
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setLoading( false );
		}
	}, [ showToast ] );

	useEffect( () => {
		loadData();
	}, [ loadData ] );

	// Save rules.
	const saveRules = async () => {
		setSaving( true );
		try {
			await apiFetch( {
				path: '/onedog-bbca/v1/menu-visibility',
				method: 'POST',
				data: {
					menu_rules: menuRules,
					toolbar_rules: toolbarRules,
					extra_menu_rules: extraMenuRules,
					custom_menu_items: customMenuItems,
				},
			} );
			showToast( __( 'Menu restrictions saved.', 'bb-custom-admin' ) );
		} catch ( err ) {
			showToast( err.message, 'error' );
		} finally {
			setSaving( false );
		}
	};

	// Toggle menu item.
	const toggleMenu = ( slug ) => {
		setMenuRules( ( prev ) => {
			const current = prev[ selectedRole ] || [];
			const next = current.includes( slug )
				? current.filter( ( s ) => s !== slug )
				: [ ...current, slug ];
			return { ...prev, [ selectedRole ]: next };
		} );
	};

	// Toggle toolbar node.
	const toggleToolbar = ( id ) => {
		setToolbarRules( ( prev ) => {
			const current = prev[ selectedRole ] || [];
			const next = current.includes( id )
				? current.filter( ( s ) => s !== id )
				: [ ...current, id ];
			return { ...prev, [ selectedRole ]: next };
		} );
	};

	// Check if item is hidden.
	const isMenuHidden = ( slug ) => ( menuRules[ selectedRole ] || [] ).includes( slug );
	const isExtraMenuHidden = ( slug ) => ( extraMenuRules[ selectedRole ] || [] ).includes( slug );
	const isToolbarHidden = ( id ) => ( toolbarRules[ selectedRole ] || [] ).includes( id );

	// Toggle supplemental/custom menu item.
	const toggleExtraMenu = ( slug ) => {
		setExtraMenuRules( ( prev ) => {
			const current = prev[ selectedRole ] || [];
			const next = current.includes( slug )
				? current.filter( ( s ) => s !== slug )
				: [ ...current, slug ];
			return { ...prev, [ selectedRole ]: next };
		} );
	};

	// Add a manually defined custom menu item.
	const addCustomMenuItem = () => {
		const label = customLabel.trim();
		const slug = customSlug.trim();
		const parent = customParent.trim();

		if ( ! label || ! slug ) {
			showToast( __( 'Label and slug are required.', 'bb-custom-admin' ), 'error' );
			return;
		}

		const fullSlug = parent ? `submenu:${ parent }>${ slug }` : slug;
		const newItem = {
			slug: fullSlug,
			label,
			type: parent ? 'submenu' : 'menu',
		};

		setAvailableExtraItems( ( prev ) => {
			if ( prev.some( ( item ) => item.slug === fullSlug ) ) {
				showToast( __( 'That slug is already listed.', 'bb-custom-admin' ), 'error' );
				return prev;
			}
			return [ ...prev, newItem ];
		} );
		setCustomMenuItems( ( prev ) => [ ...prev, newItem ] );

		setCustomLabel( '' );
		setCustomSlug( '' );
		setCustomParent( '' );
	};

	// Checks whether an item was manually added (and can be removed).
	const isCustomItem = ( slug ) => customMenuItems.some( ( item ) => item.slug === slug );

	// Remove a manually added custom menu item.
	const removeCustomMenuItem = ( slug ) => {
		setAvailableExtraItems( ( prev ) => prev.filter( ( item ) => item.slug !== slug ) );
		setCustomMenuItems( ( prev ) => prev.filter( ( item ) => item.slug !== slug ) );
		setExtraMenuRules( ( prev ) => {
			const cleaned = {};
			Object.keys( prev ).forEach( ( role ) => {
				cleaned[ role ] = prev[ role ].filter( ( s ) => s !== slug );
			} );
			return cleaned;
		} );
	};

	// Filter items.
	const filterItems = ( items, isHidden ) => {
		if ( filter === 'all' ) return items;
		if ( filter === 'selected' ) return items.filter( ( item ) => isHidden( item.slug || item.id ) );
		return items.filter( ( item ) => ! isHidden( item.slug || item.id ) );
	};

	if ( loading ) {
		return (
			<div className="flex justify-center py-12">
				<div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
			</div>
		);
	}

	const filteredMenus = filterItems( menus, isMenuHidden );
	const filteredToolbar = filterItems( toolbarNodes, isToolbarHidden );

	return (
		<div className="space-y-6">
			{ /* Role Selector & Filter */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
				<div className="flex flex-wrap items-end gap-4">
					<div className="flex-1 min-w-[200px]">
						<label className="block text-sm font-medium text-gray-700 mb-1">
							{ __( 'Configure for Role', 'bb-custom-admin' ) }
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
					<div className="min-w-[150px]">
						<label className="block text-sm font-medium text-gray-700 mb-1">
							{ __( 'Filter', 'bb-custom-admin' ) }
						</label>
						<select
							value={ filter }
							onChange={ ( e ) => setFilter( e.target.value ) }
							className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
						>
							<option value="all">{ __( 'All Items', 'bb-custom-admin' ) }</option>
							<option value="selected">{ __( 'Blocked Only', 'bb-custom-admin' ) }</option>
							<option value="not-selected">{ __( 'Visible Only', 'bb-custom-admin' ) }</option>
						</select>
					</div>
				</div>
			</div>

			{ /* Admin Sidebar Menus */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Admin Sidebar Menus', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Check items to block/hide them for this role. Direct URL access will also be blocked.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4 space-y-1">
					{ filteredMenus.length === 0 ? (
						<p className="text-sm text-gray-500 py-4 text-center">
							{ __( 'No menu items match the current filter.', 'bb-custom-admin' ) }
						</p>
					) : (
						filteredMenus.map( ( item ) => (
							<div key={ item.slug }>
								<label
									className={ `flex items-center gap-3 text-sm cursor-pointer rounded px-3 py-2 ${
										isMenuHidden( item.slug )
											? 'bg-red-50 text-red-700'
											: 'hover:bg-gray-50 text-gray-700'
									}` }
								>
									<input
										type="checkbox"
										checked={ isMenuHidden( item.slug ) }
										onChange={ () => toggleMenu( item.slug ) }
										className="rounded border-gray-300 text-red-600 focus:ring-red-500"
									/>
									<span className="font-medium">{ item.label }</span>
									<span className="text-xs text-gray-400 ml-auto">{ item.slug }</span>
								</label>
								{ /* Submenu items */ }
								{ item.children && item.children.length > 0 && (
									<div className="ml-8 border-l border-gray-200 pl-4 space-y-1">
										{ filterItems( item.children, isMenuHidden ).map( ( child ) => (
											<label
												key={ child.slug }
												className={ `flex items-center gap-3 text-sm cursor-pointer rounded px-3 py-1.5 ${
													isMenuHidden( child.slug )
														? 'bg-red-50 text-red-700'
														: 'hover:bg-gray-50 text-gray-600'
												}` }
											>
												<input
													type="checkbox"
													checked={ isMenuHidden( child.slug ) }
													onChange={ () => toggleMenu( child.slug ) }
													className="rounded border-gray-300 text-red-600 focus:ring-red-500"
												/>
												<span>{ child.label }</span>
											</label>
										) ) }
									</div>
								) }
							</div>
						) )
					) }
				</div>
			</div>

			{ /* Premium / Custom Menus */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Premium / Custom Menus', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Menus not detected automatically (e.g. SEOPress) or added manually. Check items to hide them for this role.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4 space-y-1">
					{ availableExtraItems.length === 0 ? (
						<p className="text-sm text-gray-500 py-4 text-center">
							{ __( 'No premium or custom menu items are registered.', 'bb-custom-admin' ) }
						</p>
					) : (
						filterItems( availableExtraItems, isExtraMenuHidden ).map( ( item ) => (
							<div
								key={ item.slug }
								className={ `flex items-center gap-3 text-sm rounded px-3 py-2 ${
									isExtraMenuHidden( item.slug )
										? 'bg-red-50 text-red-700'
										: 'text-gray-700'
								}` }
							>
								<label className="flex items-center gap-3 flex-1 cursor-pointer">
									<input
										type="checkbox"
										checked={ isExtraMenuHidden( item.slug ) }
										onChange={ () => toggleExtraMenu( item.slug ) }
										className="rounded border-gray-300 text-red-600 focus:ring-red-500"
									/>
									<span className="font-medium">{ item.label }</span>
								</label>
								<span className="text-xs text-gray-400">{ item.slug }</span>
								{ isCustomItem( item.slug ) && (
									<button
										onClick={ () => removeCustomMenuItem( item.slug ) }
										className="text-xs text-red-600 hover:text-red-800 underline ml-2"
									>
										{ __( 'Remove', 'bb-custom-admin' ) }
									</button>
								) }
							</div>
						) )
					) }
				</div>
				<div className="px-4 py-3 border-t border-gray-200 bg-gray-50">
					<h4 className="text-xs font-semibold text-gray-700 mb-2">
						{ __( 'Add Custom Menu Item', 'bb-custom-admin' ) }
					</h4>
					<div className="flex flex-wrap items-end gap-3">
						<div className="flex-1 min-w-[160px]">
							<label className="block text-xs font-medium text-gray-700 mb-1">
								{ __( 'Label', 'bb-custom-admin' ) }
							</label>
							<input
								type="text"
								value={ customLabel }
								onChange={ ( e ) => setCustomLabel( e.target.value ) }
								placeholder={ __( 'e.g. SEO Advanced', 'bb-custom-admin' ) }
								className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
							/>
						</div>
						<div className="flex-1 min-w-[160px]">
							<label className="block text-xs font-medium text-gray-700 mb-1">
								{ __( 'Slug', 'bb-custom-admin' ) }
							</label>
							<input
								type="text"
								value={ customSlug }
								onChange={ ( e ) => setCustomSlug( e.target.value ) }
								placeholder={ __( 'e.g. seopress-advanced', 'bb-custom-admin' ) }
								className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
							/>
						</div>
						<div className="flex-1 min-w-[160px]">
							<label className="block text-xs font-medium text-gray-700 mb-1">
								{ __( 'Parent Slug (optional)', 'bb-custom-admin' ) }
							</label>
							<input
								type="text"
								value={ customParent }
								onChange={ ( e ) => setCustomParent( e.target.value ) }
								placeholder={ __( 'e.g. seopress-option', 'bb-custom-admin' ) }
								className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm border px-3 py-2"
							/>
						</div>
						<button
							onClick={ addCustomMenuItem }
							className="inline-flex items-center rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-gray-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-800 transition"
						>
							{ __( 'Add', 'bb-custom-admin' ) }
						</button>
					</div>
					<p className="text-xs text-gray-500 mt-2">
						{ __( 'Manually added items can be removed with the Remove button. Built-in items such as SEOPress cannot be removed.', 'bb-custom-admin' ) }
					</p>
				</div>
			</div>

			{ /* Admin Toolbar Nodes */ }
			<div className="bg-white rounded-lg shadow-sm border border-gray-200">
				<div className="px-4 py-3 border-b border-gray-200 bg-gray-50 rounded-t-lg">
					<h3 className="text-sm font-semibold text-gray-900">
						{ __( 'Admin Toolbar Nodes', 'bb-custom-admin' ) }
					</h3>
					<p className="text-xs text-gray-500 mt-1">
						{ __( 'Check items to remove them from the admin toolbar.', 'bb-custom-admin' ) }
					</p>
				</div>
				<div className="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
					{ filteredToolbar.length === 0 ? (
						<p className="text-sm text-gray-500 py-4 text-center col-span-full">
							{ __( 'No toolbar items match the current filter.', 'bb-custom-admin' ) }
						</p>
					) : (
						filteredToolbar.map( ( node ) => (
							<label
								key={ node.id }
								className={ `flex items-center gap-3 text-sm cursor-pointer rounded px-3 py-2 ${
									isToolbarHidden( node.id )
										? 'bg-red-50 text-red-700'
										: 'hover:bg-gray-50 text-gray-700'
								}` }
							>
								<input
									type="checkbox"
									checked={ isToolbarHidden( node.id ) }
									onChange={ () => toggleToolbar( node.id ) }
									className="rounded border-gray-300 text-red-600 focus:ring-red-500"
								/>
								<span>{ node.label }</span>
							</label>
						) )
					) }
				</div>
			</div>

			{ /* Save Button */ }
			<div className="flex justify-end border-t border-gray-200 pt-6">
				<button
					onClick={ saveRules }
					disabled={ saving }
					className="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition disabled:opacity-50"
				>
					{ saving ? (
						<>
							<span className="animate-spin -ml-1 mr-2 h-4 w-4 border-2 border-white border-t-transparent rounded-full inline-block"></span>
							{ __( 'Saving…', 'bb-custom-admin' ) }
						</>
					) : (
						__( 'Save Restrictions', 'bb-custom-admin' )
					) }
				</button>
			</div>
		</div>
	);
}
