<?php

namespace Svg2Png\Licensing;

/**
 * Class Licensing
 *
 * Handles automatic plugin updates by registering with an external store.
 * In order for the free license to work, the following steps have to be conducted once:
 *
 * 1. The license key (which is the plugin slug) has to be set in the download post
 *    of the store under Licensing > License Keys.
 * 2. The plugin needs to be downloaded once from the store defined in $store_url.
 * 3. The License needs to be activated in the store under Downloads > Licenses.
 *
 * The license for the plugin is activated automatically when new plugin data is fetched.
 *
 * @author Lukas Gaechter
 * @version 1.1.1
 */
class Licensing {
	public $slug;
	public $plugin_file;

	/**
	 * Plugin Data
	 *
	 * @var string
	 */
	public $plugin_name;
	public $plugin_author;
	public $plugin_version;

	/**
	 * License and update server url
	 *
	 * @var string
	 */
	public $store_url;

	/**
	 * Free license key.
	 *
	 * @var string
	 */
	private $_license_key;

	/**
	 * Name of key to use for cache transient
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private $_license_cache_key;

	/**
	 * Licensing constructor.
	 *
	 * @param string    $slug           The slug of the plugin.
	 * @param string    $plugin_file    The filepath to the main plugin file.
	 * @param string    $store_url      The URL to the store where Easy Digital Downloads is installed.
	 */
	public function __construct( $slug, $plugin_file, $store_url = 'https://products.mind.ch' ) {
		$this->slug = $slug;
		$this->plugin_file = $plugin_file;
		$this->store_url = $store_url;

		$this->_license_key = $slug;
		$this->_license_cache_key = $slug . '_license';

		add_action( 'admin_init', [ $this, 'init' ] );

		// Update license when new plugin data is fetched
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'maybe_activate_license' ], 11 );
	}

	/**
	 * Initializes automatic updates for this plugin.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$plugin_data = get_plugin_data( $this->plugin_file, false, false );

		$this->plugin_name = $plugin_data['Name'];
		$this->plugin_version = $plugin_data['Version'];
		$this->plugin_author = $plugin_data['Author'];

		// Setup the updater
		new EDD_SL_Plugin_Updater(
			$this->store_url,
			$this->plugin_file,
			[
				'version'   => $this->plugin_version,
				'license'   => $this->_license_key,
				'item_name' => $this->plugin_name,
				'author'    => $this->plugin_author,
			]
		);
	}

	/**
	 * Check with transient if plugin was activated before.
	 *
	 * @since 1.1.0
	 *
	 * @param $transient_data
	 * @return mixed
	 */
	public function maybe_activate_license( $transient_data ) {
		// Bailout if there’s no data for our plugin
		if ( ! is_object( $transient_data )
		     || empty( $transient_data->response )
		     || empty( $transient_data->response[ plugin_basename( $this->plugin_file ) ] )
		) {
			return $transient_data;
		}

		$license = get_transient( $this->_license_cache_key );

		if ( ! $license ) {
			$this->activate_free_license();
		}

		return $transient_data;
	}

	/**
	 * Activate the license for this site on the store.
	 *
	 * Sets a 30-day transient that will save the license key. As long as this key is set,
	 * the license will not be activated remotely.
	 *
	 * @since 1.0.0
	 */
	public function activate_free_license() {
		// Data to send in API request
		$api_params = [
			'edd_action' => 'activate_license',
			'license'    => $this->_license_key,
			'item_name'  => urlencode( $this->plugin_name ),
			'url'        => home_url(),
		];

		// Execute request
		$response = wp_remote_post(
			$this->store_url,
			[ 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ]
		);

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 'valid' === $license_data->license ) {
			// Set transient whith license key which is valid for 30 days
			set_transient( $this->_license_cache_key, $this->_license_key, 30 * 60 * 60 * 24 );

			return true;
		}

		return false;
	}

	/**
	 * Deactivate the license for this site on the store.
	 *
	 * You need to call this function from the deactivation hook of this plugin,
	 * e.g. in your main plugin file:
	 *
	 * register_deactivation_hook( __FILE__, function() {
	 *     require_once( __DIR__ . '/vendor/autoload.php' );
	 *
	 *     $licensing = new Licensing( 'your-plugin-slug', __FILE__ );
	 *     $licensing->deactivate_free_license();
	 * } );
	 *
	 * @since 1.1.0
	 */
	public function deactivate_free_license() {
		// Data to send in API request
		$api_params = [
			'edd_action' => 'deactivate_license',
			'license' => $this->_license_key,
			'item_name' => urlencode( $this->plugin_name ),
			'url' => home_url(),
		];

		// Execute request
		wp_remote_post(
			$this->store_url,
			[ 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ]
		);

		delete_transient( $this->_license_cache_key );
	}
}
