/**
 * Beaver Builder Custom Admin — Settings App Component
 *
 * @package OneDog\BBCustomAdmin
 */

import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	Panel,
	PanelBody,
} from '@wordpress/components';

const SettingsApp = () => {
	const [ layouts, setLayouts ] = useState( [] );
	const [ roles, setRoles ] = useState( {} );
	const [ settings, setSettings ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ bbActive, setBbActive ] = useState( true );

	useEffect( () => {
		const fetchData = async () => {
			try {
				const [ layoutsData, settingsData ] = await Promise.all( [
					apiFetch( { path: '/onedog-bbca/v1/layouts' } ),
					apiFetch( { path: '/onedog-bbca/v1/settings' } ),
				] );

				setLayouts( layoutsData.layouts || [] );
				setRoles( layoutsData.roles || {} );
				setSettings( settingsData.template || {} );
				setBbActive( layoutsData.bb_active !== false );
			} catch ( error ) {
				setNotice( {
					status: 'error',
					message:
						error.message ||
						__( 'Failed to load settings.', 'bb-custom-admin' ),
				} );
			} finally {
				setLoading( false );
			}
		};

		fetchData();
	}, [] );

	const handleRoleChange = ( roleKey, value ) => {
		setSettings( ( prev ) => ( {
			...prev,
			[ roleKey ]: value,
		} ) );
	};

	const handleSave = async () => {
		setSaving( true );
		setNotice( null );

		try {
			await apiFetch( {
				path: '/onedog-bbca/v1/settings',
				method: 'POST',
				data: { template: settings },
			} );

			setNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'bb-custom-admin' ),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message:
					error.message ||
					__( 'Failed to save settings.', 'bb-custom-admin' ),
			} );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) {
		return (
			<div className="onedog-bbca-loading">
				<Spinner />
				<p>{ __( 'Loading settings…', 'bb-custom-admin' ) }</p>
			</div>
		);
	}

	const layoutOptions = [
		{
			label: __( '— None —', 'bb-custom-admin' ),
			value: 'none',
		},
		...layouts.map( ( layout ) => ( {
			label: layout.name,
			value: layout.slug,
		} ) ),
	];

	const roleEntries = Object.entries( roles );

	return (
		<div className="onedog-bbca-settings wrap">
			<h1>{ __( 'Beaver Builder Custom Admin', 'bb-custom-admin' ) }</h1>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			{ ! bbActive && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Beaver Builder is not active. Layout templates will not be available until it is enabled.',
						'bb-custom-admin'
					) }
				</Notice>
			) }

			<Panel>
				<PanelBody
					title={ __(
						'Dashboard Welcome Panel',
						'bb-custom-admin'
					) }
					initialOpen={ true }
				>
					<p className="description">
						{ __(
							'Select a Beaver Builder layout to display on the dashboard welcome panel for each user role.',
							'bb-custom-admin'
						) }
					</p>

					{ roleEntries.length === 0 && (
						<p>
							{ __(
								'No user roles found.',
								'bb-custom-admin'
							) }
						</p>
					) }

					{ roleEntries.map( ( [ roleKey, roleName ] ) => (
						<SelectControl
							key={ roleKey }
							label={ roleName }
							value={ settings[ roleKey ] || 'none' }
							options={ layoutOptions }
							onChange={ ( value ) =>
								handleRoleChange( roleKey, value )
							}
							className="onedog-bbca-role-select"
						/>
					) ) }
				</PanelBody>
			</Panel>

			<p className="submit">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'bb-custom-admin' )
						: __( 'Save Settings', 'bb-custom-admin' ) }
				</Button>
			</p>
		</div>
	);
};

export default SettingsApp;
