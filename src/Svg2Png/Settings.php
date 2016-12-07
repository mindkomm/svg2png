<?php

namespace Svg2Png;

use CloudConvert\Api;

/**
 * Class Settings
 *
 * Handles the settings page for the plugin.
 *
 * @package Svg2Png
 */
class Settings {
	/**
	 * The slug to use for the menu url and the settings key.
	 *
	 * @var string
	 */
	public $slug = 'svg2png-settings';

	/**
	 * Storage for all attachments with missing fallbacks.
	 *
	 * @var array
	 */
	public $missing = [];

	/**
	 * Storage for CloudConvert API key.
	 *
	 * @var string
	 */
	public $api_key;

	/**
	 * Storage for remaining conversion minutes.
	 *
	 * @var int
	 */
	public $minutes = null;

	/**
	 * Settings constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_options_page' ] );
		add_action( 'admin_init', array( $this, 'add_settings_sections' ) );
		add_action( 'admin_init', array( $this, 'add_settings_fields' ) );

		add_action( 'current_screen', [ $this, 'init' ] );
	}

	/**
	 * Initialize settings screen.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Screen $screen
	 */
	public function init( \WP_Screen $screen ) {
		// Bailout if we’re not in the right place
		if ( 'settings_page_' . $this->slug !== $screen->base ) {
			return;
		}

		$this->api_key = get_option( $this->slug . '-general' )['api-key'];

		$this->load_api_data();
		$this->check_actions();
		$this->get_admin_notices();

		if ( ! empty( $this->api_key ) ) {
			$this->get_missing_fallback_posts();
		}
	}

	/**
	 * Get user data from CloudConvert API.
	 *
	 * @since 1.0.0
	 */
	public function load_api_data() {
		if ( ! empty( $this->api_key ) ) {
			$api = $api = new Api( $this->api_key );

			try {
				$user = $api->get( '/user' );
				$this->minutes = $user['minutes'];

				add_action( 'admin_notices', function() use ( $user ) {
					echo '<div class="notice notice-large notice-info">
						<p><strong>' . __( 'Current user', 'svg2png' ) . ':</strong> ' . $user['user'] . '
						<br><strong>' . __( 'Conversion minutes left today', 'svg2png' ) . ':</strong> ' . $user['minutes'] . '</p>
					</div>';
				} );
			} catch ( \Exception $e ) {
				add_action( 'admin_notices', function() use ( $e ) {
					echo '<div class="notice notice-large notice-error">
						<p><strong>CloudConvert API:</strong> ' . $message . '</p>
					</div>';
				} );
			}
		}
	}

	/**
	 * Check for actions to act on.
	 *
	 * @since 1.0.0
	 */
	public function check_actions() {
		// Convert an attachment
		if ( ! empty( $_GET['action'] ) && 'convert' === $_GET['action'] ) {
			$post_id = filter_input( INPUT_GET, 'attachment_id', FILTER_SANITIZE_NUMBER_INT );
			$filepath = get_attached_file( $post_id );

			// The result will be a message or false
			$result = Svg2Png::create_png_from_svg( $filepath, true );

			// Build up url to redirect back to
			$url = '/options-general.php?page=' . $this->slug;

			if ( ! empty( $result ) ) {
				$url .= '&notice=' . urlencode( $result );
			}

			wp_safe_redirect( admin_url( $url ) );
		}
	}

	/**
	 * Check if notices should be displayed.
	 *
	 * @since 1.0.0
	 */
	public function get_admin_notices() {
		if ( null !== $this->minutes && $this->minutes < 1 ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-warning"><p>' . __( 'No conversion minutes left for today. Come back tomorrow!', 'svg2png' ) . '</p></div>';
			} );
		}

		if ( ! empty( $_GET['notice'] ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-success"><p>' . $_GET['notice'] . '</p></div>';
			} );
		}
	}

	/**
	 * Get attachments of type SVG that miss a PNG fallback.
	 *
	 * @since 1.0.0
	 */
	public function get_missing_fallback_posts() {
		// Get all attachments of type SVG
		$query = new \WP_Query( [
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_mime_type' => 'image/svg+xml',
		] );

		$attachments = $query->get_posts();

		// Filter out attachments that already have a fallback
		$attachments = array_filter( $attachments, function( $attachment ) {
			$filepath = get_attached_file( $attachment->ID );
			$png_path = substr( $filepath, 0, -3 ) . 'png';

			if ( file_exists( $png_path ) ) {
				return false;
			}

			// Assign filepath back to attachment to later use it in HTML output
			$attachment->filepath = $filepath;

			return true;
		} );

		$this->missing = $attachments;
	}

	/**
	 * Register options page
	 *
	 * @since 1.0.0
	 */
	public function add_options_page() {
		add_options_page(
			'Svg2Png',
			'Svg2Png',
			'manage_options',
			$this->slug,
			[ $this, 'render' ]
		);
	}

	/**
	 * Register sections.
	 *
	 * There has to be at least one section. The default section is called 'general'.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_sections() {
		$sections = [ 'general' ];

		foreach ( $sections as $section ) {
			add_settings_section(
				$this->slug . '-' . $section,
				__( 'Settings' ),
				[ $this, 'render_section_' . $section ],
				$this->slug
			);

			/**
			 * Settings are registered for each section and not for each field,
			 * because all sections contain the serialized values of their fields.
			 */
			register_setting( $this->slug, $this->slug . '-' . $section );
		}
	}

	/**
	 * Register fields.
	 *
	 * All the fields are defined in an array $fields.
	 * Possible values for each field:
	 *
	 * label            The form label to be used.
	 * type             Which input type to use. Default: input
	 * description      The description to append to the form field.
	 * section          The name of the section the field should be appended to.
	 *                  Default: general
	 *
	 * @since 1.0.0
	 */
	public function add_settings_fields() {
		$fields = [
			'api-key' => [
				'label' => 'API Key',
				'description' => __( 'Enter your <a href="https://cloudconvert.com">CloudConvert</a> API key.', 'svg2png' ),
			],
			'resize' => [
				'label' => 'Resize',
				'description' => __( 'Defines how the image should be resized.<br>&ndash; Examples: Resize to width (1000) or predefined width and height (1000x540).<br>&ndash; If this value is not empty <strong>Density</strong> will be ignored.<br>&ndash; If both <strong>Resize</strong> and <strong>Density</strong> are empty, the resulting PNG will have the same size as the SVG.', 'svg2png' ),
			],
			'density' => [
				'label' => 'Density',
				'description' => __( 'Defines what resolution should be applied to the resulting PNG.<br>&ndash; In PPI, for example: 150<br>&ndash; This value will only be used if <strong>Resize</strong> is empty.', 'svg2png' ),
			],
		];

		foreach ( $fields as $id => $args ) {
			// Set defaults
			$defaults = [
				'type' => 'input',
				'description' => null,
				'section' => 'general',
			];

			$args = wp_parse_args( $args, $defaults );

			add_settings_field(
				$id,
				$args['label'],
				array( $this, 'render_' . $args['type'] . '_field' ),
				$this->slug,
				$this->slug . '-' . $args['section'],
				[
					'id' => $id,
					'description' => $args['description'],
					'section' => $args['section'],
				]
			);
		}
	}

	/**
	 * Renders the settings contents
	 *
	 * @since 1.0.0
	 */
	public function render() {
		?>
		<div class="wrap">
			<h1>Svg2Png</h1>
			<form method="post" action="options.php">
				<?php
					settings_fields( $this->slug );
					do_settings_sections( $this->slug );
					submit_button();
				?>
			</form>
			<?php $this->render_missing_fallbacks(); ?>
		</div>
		<?php
	}

	/**
	 * Renders general section
	 *
	 * @since 1.0.0
	 */
	public function render_section_general() {}

	/**
	 * Renders a basic input field together with an optional description
	 *
	 * All values are serialized by sections.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $args
	 * @return void
	 */
	public function render_input_field( $args ) {
		$section = $this->slug . '-' . $args['section'];

		$name = $section . '[' . $args['id'] . ']';
		$id = $section . '-' . $args['id'];

		$value = get_option( $section )[ $args['id'] ];

		$html = '<input type="text" class="regular-text" id="' . $id . '" name="' . $name . '" value="' . $value . '" />';

		if ( ! empty( $args['description'] ) ) {
			$html .= $this->render_field_description( $id, $args['description'] );
		}

		echo $html;
	}

	/**
	 * Returns a field description.
	 *
	 * @since 1.0.0
	 *
	 * @param string    $id
	 * @param string    $text
	 *
	 * @return string
	 */
	public function render_field_description( $id, $text ) {
		return '<p class="description" id="' . $id .'-description">' . $text . '</p>';
	}

	/**
	 * Renders a table with missing fallback images.
	 *
	 * Each entry includes a link from which the conversion of an image can be triggered.
	 *
	 * @since 1.0.0
	 */
	public function render_missing_fallbacks() {
		if ( empty( $this->missing ) ) {
			return;
		}

		?>

		<h3><?php _e( 'Images with missing fallbacks', 'svg2png' ); ?></h3>

		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th><?php _e( 'Filename', 'svg2png' ); ?></th>
					<th><?php _e( 'Filepath', 'svg2png' ); ?></th>
					<th><?php _e( 'Convert', 'svg2png' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $this->missing as $image ) : ?>
					<tr>
						<td><?php echo basename( $image->filepath ) ?></td>
						<td><?php echo $image->filepath; ?></td>
						<td>
							<?php if ( $this->minutes > 0 ) : ?>
								<a href="<?php echo admin_url( 'options-general.php?page=' . $this->slug . '&action=convert&attachment_id=' . $image->ID ); ?>">
									<?php echo _e( 'Create fallback', 'svg2png' ); ?>
								</a>
							<?php else : ?>
								<?php _e( 'Not enough minutes left', 'svg2png' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
	}
}
