# SvgToPng

A WordPress plugin that creates a PNG copy of each uploaded SVG image uploaded using [CloudConvert](https://cloudconvert.com/svg-to-png).

* This plugin is **intended for theme developers** who want to make it possible to include a PNG fallback for SVG images uploaded through WordPress.
* This plugin doesn’t include a fallback method itself, it will just convert SVG images to PNG.

## Features

* Creates a PNG copy of each uploaded SVG image.
* Deletes PNG copies when deleting SVG images.
* Lists SVG images without a PNG fallback on the Settings page in the backend with the possibility to create a PNG copy for them.

## Requirements

### API key from your CloudConvert account

You will need a [CloudConvert](https://cloudconvert.com) account to use this plugin. Get your API key from the account dashboard and enter it under *Settings > Svg2Png*.

### Allow uploading of SVG images

For SVG images to work, make sure that you allow uploading of SVG images:

```php
/**
 * Add additional allowed upload file types
 *
 * @param array $mimes
 * @return array
 */
add_filter( 'upload_mimes', function( $mimes ) {
	$mimes['svg']  = 'image/svg+xml';

	return $mimes;
) };

```

Be aware of the implications that [allowing the uploads of SVG images](https://bjornjohansen.no/svg-in-wordpress) might have.

## SVG fallback

This plugin doesn’t come with a solution to add an SVG fallback. You will have to come up with your own method of including it in your theme. [Sara Soueidan provides a very handy overview](https://sarasoueidan.com/blog/svg-picture/) on the different ways you can go to implement a fallback for SVG.

You can use the following function that works when you use it with [Picturefill](http://scottjehl.github.io/picturefill/):

```php
/**
 * Get an SVG-image with proper PNG fallback.
 *
 * See https://sarasoueidan.com/blog/svg-picture/ for details.
 * This function does not check for the existence of the PNG fallback.
 *
 * @param string $img_url   The URL of the image, possibly without file suffix
 * @param string $class     Optional class to set
 * @param string $id        Optional id to set
 * @param string $alt       The alt tag for the image
 * @param string $title     The title tag for the image
 *
 * @return string           HTML to display the image with fallback
 */
function get_svg_with_fallback( $img_url, $class = '', $id = '', $alt = '', $title = '' ) {
	$img_url_svg = $img_url;

	if ( substr( $img_url, -4 ) !== '.svg' ) {
		$img_url_svg = $img_url . '.svg';
		$img_url_png = $img_url . '.png';
	} else {
		$img_url_png = substr( $img_url_svg, 0, -3 ) . 'png';
	}

	if ( ! empty( $class ) ) {
		$class = ' class="' . $class . '"';
	}

	if ( ! empty( $id ) ) {
		$id = ' id="' . $id . '"';
	}

	$alt = ' alt="' . $alt . '"';

	if ( ! empty( $title ) ) {
		$title = ' title="' . $title . '"';
	}

	return '<picture>
		<!--[if IE 9]><video style="display: none;"><![endif]-->
			<source type="image/svg+xml" srcset="' . $img_url_svg . '"' . $class . $id .'>
		<!--[if IE 9]></video><![endif]-->
			<img src="' . $img_url_png . '"' . $class . $id . $alt . $title . ' />
		</picture>';
}
```
