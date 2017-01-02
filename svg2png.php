<?php
/**
 * Plugin Name: Svg2Png
 * Plugin URI: https://github.com/MINDKomm/svg2png
 * Description: Converts each uploaded SVG to a PNG using CloudConvert
 * Text Domain: svg2png
 * Domain Path: /languages
 * Author: MIND
 * Author URI: https://www.mind.ch
 * License: GNU General Public License v2.0 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Version: 1.0.1
 */
namespace Svg2Png;

use Svg2Png\Licensing\Licensing;

add_action( 'plugins_loaded', function() {
	if ( is_admin() ) {
		require_once( __DIR__ . '/vendor/autoload.php' );

		load_plugin_textdomain(
			'svg2png',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);

		new Svg2Png();
		new Settings();
		new Licensing( 'svg2png', plugin_dir_path( __FILE__ ) . 'svg2png.php' );
	}
} );

/**
 * Activate the free license for this plugin on products.mind.ch.
 */
register_activation_hook( __FILE__, function() {
	require_once( __DIR__ . '/vendor/autoload.php' );

	$licensing = new Licensing( 'svg2png', plugin_dir_path( __FILE__ ) . 'svg2png.php' );
	$licensing->activate_free_license();
} );
