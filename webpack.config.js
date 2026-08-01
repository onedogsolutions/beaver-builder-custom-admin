const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

// Override the babel-loader rule to use the classic JSX runtime.
// @wordpress/babel-preset-default hardcodes runtime: 'automatic' which
// produces a react-jsx-runtime external requiring WP 6.5+. We replace it
// with equivalent presets using runtime: 'classic' for WP 5.0+ support.
const rules = ( defaultConfig.module?.rules || [] ).map( ( rule ) => {
	if ( ! Array.isArray( rule.use ) ) {
		return rule;
	}

	const hasBabel = rule.use.some(
		( u ) => u.loader && u.loader.includes( 'babel-loader' )
	);

	if ( ! hasBabel ) {
		return rule;
	}

	return {
		...rule,
		use: rule.use.map( ( u ) => {
			if ( ! u.loader || ! u.loader.includes( 'babel-loader' ) ) {
				return u;
			}
			return {
				...u,
				options: {
					...u.options,
					presets: [
						[
							require.resolve( '@babel/preset-env' ),
							{ bugfixes: true, modules: false },
						],
						[
							require.resolve( '@babel/preset-react' ),
							{ runtime: 'classic' },
						],
						require.resolve( '@babel/preset-typescript' ),
					],
					plugins: [],
				},
			};
		} ),
	};
} );

module.exports = {
	...defaultConfig,
	entry: {
		index: path.resolve( __dirname, 'src', 'index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
	module: {
		...defaultConfig.module,
		rules,
	},
};
