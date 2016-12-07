<?php

namespace Svg2Png\Licensing;

/**
 * Class Licensing
 *
 * Handles automatic plugin updates by registering with an external store.
 * In order for the free license to work, the following steps have to be conducted once:
 *
 * - The license key, which is the plugin slug has to be set in the download post
 *   of the store under Licensing > License Keys.
 * - Then the plugin needs to be downloaded once from the store defined
 *   in $store_url.
 * - Then the License needs to be activated on the store under Downloads > Licenses.
 *
 * @author Lukas Gaechter
 * @version 1.0.0
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
	 * Public license key.
	 *
	 * @var string
	 */
	private $_license_key;

	/**
	 * Licensing constructor.
	 *
	 * @param string    $slug           The slug of the plugin.
	 * @param string    $plugin_file    The filepath to the main plugin file.
	 * @param string    $store_url      The URL to the store where Easy Digital Downloads is installed.
	 */
	public function __construct( $slug, $plugin_file, $store_url = 'https://products.mind.ch' ) {
		if ( ! class_exists( 'EDD_SL_Plugin_Updater' ) ) {
			require_once( __DIR__ . '/EDD_SL_Plugin_Updater.php' );
		}

		$this->slug = $slug;
		$this->plugin_file = $plugin_file;
		$this->store_url = $store_url;

		$this->_license_key = $slug;

		$plugin_data = get_plugin_data( $this->plugin_file, false, false );

		$this->plugin_name = $plugin_data['Name'];
		$this->plugin_version = $plugin_data['Version'];
		$this->plugin_author = $plugin_data['Author'];

		add_action( 'admin_init', [ $this, 'init' ] );
	}

	/**
	 * Initializes automatic updates for this plugin.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Setup the updater
		new \EDD_SL_Plugin_Updater(
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
	 * Activate the license for this site on the store URL.
	 *
	 * You need to call this function from the activation hook of this plugin, e.g. like this:
	 *
	 * register_activation_hook( __FILE__, function() {
	 *     require_once( __DIR__ . '/vendor/autoload.php' );
	 *
	 *     $licensing = new Licensing( 'svg2png', plugin_dir_path( __FILE__ ) . 'svg2png' );
	 *     $licensing->activate_free_license();
	 * } );
	 *
	 * @since 1.0.0
	 */
	public function activate_free_license() {
		// Data to send in our API request
		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $this->_license_key,
			'item_name'  => urlencode( $this->plugin_name ),
			'url'        => home_url(),
		);

		// Execute request
		wp_remote_post(
			$this->store_url,
			[ 'timeout' => 15, 'sslverify' => false, 'body' => $api_params ]
		);
	}
}
