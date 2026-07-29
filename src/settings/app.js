/**
 * Beaver Builder Custom Admin — Settings App
 *
 * Tabbed interface for module configuration.
 *
 * @package OneDog\BBCustomAdmin
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	Panel,
	PanelBody,
	TabPanel,
	ToggleControl,
	CheckboxControl,
} from '@wordpress/components';

/* ------------------------------------------------------------------ */
/* Shared: save notification hook                                      */
/* ------------------------------------------------------------------ */

const useSaveState = () => {
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	return { saving, setSaving, notice, setNotice };
};

const SaveButton = ( { saving, onClick } ) => (
	<p className="submit">
		<Button variant="primary" onClick={ onClick } isBusy={ saving } disabled={ saving }>
			{ saving ? __( 'Saving…', 'bb-custom-admin' ) : __( 'Save Settings', 'bb-custom-admin' ) }
		</Button>
	</p>
);

const NoticeBar = ( { notice, onDismiss } ) =>
	notice ? (
		<Notice status={ notice.status } onRemove={ onDismiss }>
			{ notice.message }
		</Notice>
	) : null;

/* ------------------------------------------------------------------ */
/* Tab 1: Welcome Screen                                              */
/* ------------------------------------------------------------------ */

const WelcomeScreenTab = () => {
	const [ layouts, setLayouts ] = useState( [] );
	const [ roles, setRoles ] = useState( {} );
	const [ settings, setSettings ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ bbActive, setBbActive ] = useState( true );
	const { saving, setSaving, notice, setNotice } = useSaveState();

	useEffect( () => {
		Promise.all( [
			apiFetch( { path: '/onedog-bbca/v1/layouts' } ),
			apiFetch( { path: '/onedog-bbca/v1/settings' } ),
		] )
			.then( ( [ layoutData, settingsData ] ) => {
				setLayouts( layoutData.layouts || [] );
				setRoles( layoutData.roles || {} );
				setBbActive( layoutData.bb_active !== false );
				setSettings( settingsData.template || {} );
			} )
			.catch( ( err ) => setNotice( { status: 'error', message: err.message } ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const save = async () => {
		setSaving( true );
		setNotice( null );
		try {
			await apiFetch( { path: '/onedog-bbca/v1/settings', method: 'POST', data: { template: settings } } );
			setNotice( { status: 'success', message: __( 'Settings saved.', 'bb-custom-admin' ) } );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) return <Spinner />;

	const options = [
		{ label: __( '— None —', 'bb-custom-admin' ), value: 'none' },
		...layouts.map( ( l ) => ( { label: l.name, value: l.slug } ) ),
	];

	return (
		<div>
			<NoticeBar notice={ notice } onDismiss={ () => setNotice( null ) } />
			{ ! bbActive && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'Beaver Builder is not active. Layouts unavailable.', 'bb-custom-admin' ) }
				</Notice>
			) }
			<Panel>
				<PanelBody title={ __( 'Default Fallback Template', 'bb-custom-admin' ) } initialOpen={ true }>
					<p className="description">
						{ __( 'Used when a role has no specific template assigned.', 'bb-custom-admin' ) }
					</p>
					<SelectControl
						label={ __( 'Default', 'bb-custom-admin' ) }
						value={ settings._default || 'none' }
						options={ options }
						onChange={ ( v ) => setSettings( ( p ) => ( { ...p, _default: v } ) ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Per-Role Templates', 'bb-custom-admin' ) } initialOpen={ true }>
					{ Object.entries( roles ).map( ( [ key, name ] ) => (
						<SelectControl
							key={ key }
							label={ name }
							value={ settings[ key ] || 'none' }
							options={ options }
							onChange={ ( v ) => setSettings( ( p ) => ( { ...p, [ key ]: v } ) ) }
						/>
					) ) }
				</PanelBody>
			</Panel>
			<SaveButton saving={ saving } onClick={ save } />
		</div>
	);
};

/* ------------------------------------------------------------------ */
/* Tab 2: Menu & Toolbar Visibility                                    */
/* ------------------------------------------------------------------ */

const MenuVisibilityTab = () => {
	const [ data, setData ] = useState( null );
	const [ menuRules, setMenuRules ] = useState( {} );
	const [ toolbarRules, setToolbarRules ] = useState( {} );
	const [ selectedRole, setSelectedRole ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const { saving, setSaving, notice, setNotice } = useSaveState();

	useEffect( () => {
		apiFetch( { path: '/onedog-bbca/v1/menu-visibility' } )
			.then( ( res ) => {
				setData( res );
				setMenuRules( res.menu_rules || {} );
				setToolbarRules( res.toolbar_rules || {} );
				const roleKeys = Object.keys( res.roles || {} );
				if ( roleKeys.length ) setSelectedRole( roleKeys[ 0 ] );
			} )
			.catch( ( err ) => setNotice( { status: 'error', message: err.message } ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const save = async () => {
		setSaving( true );
		setNotice( null );
		try {
			await apiFetch( {
				path: '/onedog-bbca/v1/menu-visibility',
				method: 'POST',
				data: { menu_rules: menuRules, toolbar_rules: toolbarRules },
			} );
			setNotice( { status: 'success', message: __( 'Settings saved.', 'bb-custom-admin' ) } );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) return <Spinner />;

	const roles = data?.roles || {};
	const menus = data?.available_menus || [];
	const toolbarNodes = data?.available_toolbar || [];
	const roleMenuHidden = menuRules[ selectedRole ] || [];
	const roleToolbarHidden = toolbarRules[ selectedRole ] || [];

	const toggleMenu = ( slug ) => {
		setMenuRules( ( prev ) => {
			const current = prev[ selectedRole ] || [];
			const next = current.includes( slug )
				? current.filter( ( s ) => s !== slug )
				: [ ...current, slug ];
			return { ...prev, [ selectedRole ]: next };
		} );
	};

	const toggleToolbar = ( id ) => {
		setToolbarRules( ( prev ) => {
			const current = prev[ selectedRole ] || [];
			const next = current.includes( id )
				? current.filter( ( s ) => s !== id )
				: [ ...current, id ];
			return { ...prev, [ selectedRole ]: next };
		} );
	};

	return (
		<div>
			<NoticeBar notice={ notice } onDismiss={ () => setNotice( null ) } />
			<SelectControl
				label={ __( 'Configure for Role', 'bb-custom-admin' ) }
				value={ selectedRole }
				options={ Object.entries( roles ).map( ( [ k, v ] ) => ( { label: v, value: k } ) ) }
				onChange={ setSelectedRole }
			/>
			<Panel>
				<PanelBody title={ __( 'Admin Sidebar Menus', 'bb-custom-admin' ) } initialOpen={ true }>
					<p className="description">{ __( 'Check items to hide them for this role.', 'bb-custom-admin' ) }</p>
					{ menus.map( ( item ) => (
						<div key={ item.slug } style={ { marginBottom: '4px' } }>
							<CheckboxControl
								label={ item.label }
								checked={ roleMenuHidden.includes( item.slug ) }
								onChange={ () => toggleMenu( item.slug ) }
							/>
							{ item.children?.map( ( child ) => (
								<div key={ child.slug } style={ { marginLeft: '24px' } }>
									<CheckboxControl
										label={ `— ${ child.label }` }
										checked={ roleMenuHidden.includes( child.slug ) }
										onChange={ () => toggleMenu( child.slug ) }
									/>
								</div>
							) ) }
						</div>
					) ) }
				</PanelBody>
				<PanelBody title={ __( 'Admin Toolbar Nodes', 'bb-custom-admin' ) } initialOpen={ true }>
					<p className="description">{ __( 'Check items to remove them from the toolbar.', 'bb-custom-admin' ) }</p>
					{ toolbarNodes.map( ( node ) => (
						<CheckboxControl
							key={ node.id }
							label={ node.label }
							checked={ roleToolbarHidden.includes( node.id ) }
							onChange={ () => toggleToolbar( node.id ) }
						/>
					) ) }
				</PanelBody>
			</Panel>
			<SaveButton saving={ saving } onClick={ save } />
		</div>
	);
};

/* ------------------------------------------------------------------ */
/* Tab 3: Notice Cleaner                                              */
/* ------------------------------------------------------------------ */

const NoticeCleanerTab = () => {
	const [ settings, setSettings ] = useState( null );
	const [ roles, setRoles ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const { saving, setSaving, notice, setNotice } = useSaveState();

	useEffect( () => {
		apiFetch( { path: '/onedog-bbca/v1/notice-cleaner' } )
			.then( ( res ) => {
				setSettings( res.settings );
				setRoles( res.roles || {} );
			} )
			.catch( ( err ) => setNotice( { status: 'error', message: err.message } ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const save = async () => {
		setSaving( true );
		setNotice( null );
		try {
			await apiFetch( { path: '/onedog-bbca/v1/notice-cleaner', method: 'POST', data: { settings } } );
			setNotice( { status: 'success', message: __( 'Settings saved.', 'bb-custom-admin' ) } );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setSaving( false );
		}
	};

	if ( loading || ! settings ) return <Spinner />;

	const toggle = ( key ) => setSettings( ( p ) => ( { ...p, [ key ]: ! p[ key ] } ) );

	const toggleExcludedRole = ( role ) => {
		setSettings( ( p ) => {
			const current = p.excluded_roles || [];
			const next = current.includes( role )
				? current.filter( ( r ) => r !== role )
				: [ ...current, role ];
			return { ...p, excluded_roles: next };
		} );
	};

	return (
		<div>
			<NoticeBar notice={ notice } onDismiss={ () => setNotice( null ) } />
			<Panel>
				<PanelBody title={ __( 'Notice & Alert Visibility', 'bb-custom-admin' ) } initialOpen={ true }>
					<ToggleControl
						label={ __( 'Hide update notices', 'bb-custom-admin' ) }
						help={ __( 'Removes core/plugin update nags for non-admin roles.', 'bb-custom-admin' ) }
						checked={ settings.hide_update_notices }
						onChange={ () => toggle( 'hide_update_notices' ) }
					/>
					<ToggleControl
						label={ __( 'Hide core admin alerts', 'bb-custom-admin' ) }
						help={ __( 'Removes all .notice, .error, .updated elements.', 'bb-custom-admin' ) }
						checked={ settings.hide_core_alerts }
						onChange={ () => toggle( 'hide_core_alerts' ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Toolbar Cleanup', 'bb-custom-admin' ) } initialOpen={ true }>
					<ToggleControl
						label={ __( 'Remove WordPress logo', 'bb-custom-admin' ) }
						checked={ settings.remove_wp_logo }
						onChange={ () => toggle( 'remove_wp_logo' ) }
					/>
					<ToggleControl
						label={ __( 'Remove toolbar dropdowns (Comments, New +)', 'bb-custom-admin' ) }
						checked={ settings.remove_toolbar_dropdowns }
						onChange={ () => toggle( 'remove_toolbar_dropdowns' ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Excluded Roles', 'bb-custom-admin' ) } initialOpen={ false }>
					<p className="description">
						{ __( 'These roles still see all notices and toolbar items. Administrators always see everything.', 'bb-custom-admin' ) }
					</p>
					{ Object.entries( roles ).map( ( [ key, name ] ) => (
						<CheckboxControl
							key={ key }
							label={ name }
							checked={ ( settings.excluded_roles || [] ).includes( key ) }
							onChange={ () => toggleExcludedRole( key ) }
						/>
					) ) }
				</PanelBody>
			</Panel>
			<SaveButton saving={ saving } onClick={ save } />
		</div>
	);
};

/* ------------------------------------------------------------------ */
/* Tab 4: Modules                                                     */
/* ------------------------------------------------------------------ */

const ModulesTab = () => {
	const [ modules, setModules ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const { saving, setSaving, notice, setNotice } = useSaveState();

	useEffect( () => {
		apiFetch( { path: '/onedog-bbca/v1/modules' } )
			.then( ( res ) => setModules( res.modules || [] ) )
			.catch( ( err ) => setNotice( { status: 'error', message: err.message } ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const save = async () => {
		setSaving( true );
		setNotice( null );
		const enabled = modules.filter( ( m ) => m.enabled ).map( ( m ) => m.slug );
		try {
			await apiFetch( { path: '/onedog-bbca/v1/modules', method: 'POST', data: { modules: enabled } } );
			setNotice( { status: 'success', message: __( 'Modules saved. Reload the page for changes to take effect.', 'bb-custom-admin' ) } );
		} catch ( err ) {
			setNotice( { status: 'error', message: err.message } );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) return <Spinner />;

	const toggleModule = ( slug ) => {
		setModules( ( prev ) =>
			prev.map( ( m ) => ( m.slug === slug ? { ...m, enabled: ! m.enabled } : m ) )
		);
	};

	return (
		<div>
			<NoticeBar notice={ notice } onDismiss={ () => setNotice( null ) } />
			<Panel>
				<PanelBody title={ __( 'Active Modules', 'bb-custom-admin' ) } initialOpen={ true }>
					<p className="description">
						{ __( 'Toggle modules on or off. Disabled modules have zero runtime overhead.', 'bb-custom-admin' ) }
					</p>
					{ modules.map( ( mod ) => (
						<ToggleControl
							key={ mod.slug }
							label={ mod.label }
							help={ mod.description }
							checked={ mod.enabled }
							onChange={ () => toggleModule( mod.slug ) }
						/>
					) ) }
				</PanelBody>
			</Panel>
			<SaveButton saving={ saving } onClick={ save } />
		</div>
	);
};

/* ------------------------------------------------------------------ */
/* Root App                                                           */
/* ------------------------------------------------------------------ */

const SettingsApp = () => (
	<div className="onedog-bbca-settings wrap">
		<h1>{ __( 'Beaver Builder Custom Admin', 'bb-custom-admin' ) }</h1>
		<TabPanel
			className="onedog-bbca-tabs"
			tabs={ [
				{ name: 'welcome', title: __( 'Welcome Screen', 'bb-custom-admin' ) },
				{ name: 'menus', title: __( 'Menu & Toolbar', 'bb-custom-admin' ) },
				{ name: 'notices', title: __( 'Notice Cleaner', 'bb-custom-admin' ) },
				{ name: 'modules', title: __( 'Modules', 'bb-custom-admin' ) },
			] }
		>
			{ ( tab ) => {
				switch ( tab.name ) {
					case 'welcome':
						return <WelcomeScreenTab />;
					case 'menus':
						return <MenuVisibilityTab />;
					case 'notices':
						return <NoticeCleanerTab />;
					case 'modules':
						return <ModulesTab />;
					default:
						return null;
				}
			} }
		</TabPanel>
	</div>
);

export default SettingsApp;
