<?php
/**
 * Settings page and option handling.
 *
 * @package JB_Paste_To_Media
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and sanitizes the jbptm_settings option.
 */
class JBPTM_Settings {

	const OPTION = 'jbptm_settings';
	const PAGE   = 'jb-paste-to-media';

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
	}

	/**
	 * Load translations bundled with the plugin (WordPress.org packs load automatically).
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'jb-paste-to-media', false, dirname( plugin_basename( JBPTM_FILE ) ) . '/languages' );
	}

	/**
	 * Default settings. Grouped by file type so documents can be added later.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enable_block'   => 1,
			'enable_classic' => 1,
			'mode'           => 'silent',
			'image'          => array(
				'filename'      => '{post_slug}-{date}-{time}',
				'folder'        => 'pasted/{year}/{month}',
				'alt'           => '{post_title}',
				'title'         => '{post_title}',
				'caption'       => '',
				'description'   => '',
				'format'        => 'original',
				'quality'       => 82,
				'max_width'     => 0,
				'classic_size'  => 'large',
				'classic_align' => 'none',
				'classic_link'  => 'none',
			),
		);
	}

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = self::defaults();
		$saved    = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$merged          = array_merge( $defaults, $saved );
		$merged['image'] = array_merge( $defaults['image'], isset( $saved['image'] ) && is_array( $saved['image'] ) ? $saved['image'] : array() );
		return $merged;
	}

	/**
	 * Supported template tokens and their descriptions.
	 *
	 * @return array
	 */
	public static function tokens() {
		return array(
			'{post_id}'    => __( 'Post ID', 'jb-paste-to-media' ),
			'{post_title}' => __( 'Post title', 'jb-paste-to-media' ),
			'{post_slug}'  => __( 'Post slug (falls back to a slug of the title)', 'jb-paste-to-media' ),
			'{post_type}'  => __( 'Post type', 'jb-paste-to-media' ),
			'{user}'       => __( 'Current user login', 'jb-paste-to-media' ),
			'{site}'       => __( 'Site name', 'jb-paste-to-media' ),
			'{date}'       => __( 'Date, YYYY-MM-DD', 'jb-paste-to-media' ),
			'{time}'       => __( 'Time, HHMMSS', 'jb-paste-to-media' ),
			'{year}'       => __( 'Year, YYYY', 'jb-paste-to-media' ),
			'{month}'      => __( 'Month, MM', 'jb-paste-to-media' ),
			'{day}'        => __( 'Day, DD', 'jb-paste-to-media' ),
			'{rand}'       => __( 'Random 6-character string', 'jb-paste-to-media' ),
		);
	}

	/**
	 * Sanitize a folder template so it always stays inside uploads.
	 *
	 * @param string $folder Raw folder template or rendered folder.
	 * @return string
	 */
	public static function sanitize_folder( $folder ) {
		$folder = strtolower( (string) $folder );
		$folder = str_replace( '\\', '/', $folder );
		$folder = preg_replace( '#[^a-z0-9\-_/{}]#', '', $folder );
		$parts  = array_filter(
			explode( '/', $folder ),
			static function ( $part ) {
				return '' !== $part && '.' !== $part && '..' !== $part;
			}
		);
		return implode( '/', $parts );
	}

	/**
	 * Add the Settings > Paste to Media page.
	 */
	public static function add_page() {
		add_options_page(
			__( 'JB Paste to Media', 'jb-paste-to-media' ),
			__( 'Paste to Media', 'jb-paste-to-media' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register the option and fields.
	 */
	public static function register() {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section( 'general', __( 'General', 'jb-paste-to-media' ), '__return_false', self::PAGE );
		add_settings_section( 'naming', __( 'File name, folder and text', 'jb-paste-to-media' ), array( __CLASS__, 'render_tokens' ), self::PAGE );
		add_settings_section( 'format', __( 'Image format', 'jb-paste-to-media' ), '__return_false', self::PAGE );
		add_settings_section( 'classic', __( 'Classic editor insertion', 'jb-paste-to-media' ), '__return_false', self::PAGE );

		$fields = array(
			array( 'enable_block', __( 'Block editor', 'jb-paste-to-media' ), 'checkbox', 'general', __( 'Handle pasted images in the block editor', 'jb-paste-to-media' ) ),
			array( 'enable_classic', __( 'Classic editor', 'jb-paste-to-media' ), 'checkbox', 'general', __( 'Handle pasted images in the Classic editor and Classic block', 'jb-paste-to-media' ) ),
			array(
				'mode',
				__( 'When pasting', 'jb-paste-to-media' ),
				'select',
				'general',
				array(
					'silent' => __( 'Upload immediately using the templates', 'jb-paste-to-media' ),
					'prompt' => __( 'Show a dialog to review name and text first', 'jb-paste-to-media' ),
				),
			),
			array( 'image.filename', __( 'Filename', 'jb-paste-to-media' ), 'text', 'naming', __( 'Without extension. Example: {post_slug}-{date}-{time}', 'jb-paste-to-media' ) ),
			array( 'image.folder', __( 'Upload subfolder', 'jb-paste-to-media' ), 'text', 'naming', __( 'Relative to wp-content/uploads. Leave blank to use the WordPress default.', 'jb-paste-to-media' ) ),
			array( 'image.alt', __( 'Alt text', 'jb-paste-to-media' ), 'text', 'naming', '' ),
			array( 'image.title', __( 'Title', 'jb-paste-to-media' ), 'text', 'naming', '' ),
			array( 'image.caption', __( 'Caption', 'jb-paste-to-media' ), 'text', 'naming', '' ),
			array( 'image.description', __( 'Description', 'jb-paste-to-media' ), 'text', 'naming', '' ),
			array(
				'image.format',
				__( 'Convert to', 'jb-paste-to-media' ),
				'select',
				'format',
				array(
					'original' => __( 'Keep original format', 'jb-paste-to-media' ),
					'jpeg'     => __( 'JPEG', 'jb-paste-to-media' ),
					'webp'     => __( 'WebP', 'jb-paste-to-media' ),
				),
			),
			array( 'image.quality', __( 'Quality (1–100)', 'jb-paste-to-media' ), 'number', 'format', __( 'Used for JPEG and WebP conversion.', 'jb-paste-to-media' ) ),
			array( 'image.max_width', __( 'Max width (px)', 'jb-paste-to-media' ), 'number', 'format', __( '0 = no limit. Larger images are scaled down before upload.', 'jb-paste-to-media' ) ),
			array(
				'image.classic_size',
				__( 'Size', 'jb-paste-to-media' ),
				'select',
				'classic',
				array(
					'thumbnail' => __( 'Thumbnail', 'jb-paste-to-media' ),
					'medium'    => __( 'Medium', 'jb-paste-to-media' ),
					'large'     => __( 'Large', 'jb-paste-to-media' ),
					'full'      => __( 'Full size', 'jb-paste-to-media' ),
				),
			),
			array(
				'image.classic_align',
				__( 'Alignment', 'jb-paste-to-media' ),
				'select',
				'classic',
				array(
					'none'   => __( 'None', 'jb-paste-to-media' ),
					'left'   => __( 'Left', 'jb-paste-to-media' ),
					'center' => __( 'Center', 'jb-paste-to-media' ),
					'right'  => __( 'Right', 'jb-paste-to-media' ),
				),
			),
			array(
				'image.classic_link',
				__( 'Link to', 'jb-paste-to-media' ),
				'select',
				'classic',
				array(
					'none'       => __( 'None', 'jb-paste-to-media' ),
					'file'       => __( 'Media file', 'jb-paste-to-media' ),
					'attachment' => __( 'Attachment page', 'jb-paste-to-media' ),
				),
			),
		);

		foreach ( $fields as $field ) {
			add_settings_field(
				'jbptm_' . str_replace( '.', '_', $field[0] ),
				$field[1],
				array( __CLASS__, 'render_field' ),
				self::PAGE,
				$field[3],
				array(
					'key'   => $field[0],
					'type'  => $field[2],
					'extra' => $field[4],
				)
			);
		}
	}

	/**
	 * Sanitize the whole option.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$d     = self::defaults();
		$input = is_array( $input ) ? $input : array();
		$img   = isset( $input['image'] ) && is_array( $input['image'] ) ? $input['image'] : array();

		$pick = static function ( $value, $allowed, $fallback ) {
			return in_array( $value, $allowed, true ) ? $value : $fallback;
		};
		$text = static function ( $key ) use ( $img, $d ) {
			return isset( $img[ $key ] ) ? sanitize_text_field( wp_unslash( $img[ $key ] ) ) : $d['image'][ $key ];
		};

		return array(
			'enable_block'   => empty( $input['enable_block'] ) ? 0 : 1,
			'enable_classic' => empty( $input['enable_classic'] ) ? 0 : 1,
			'mode'           => $pick( isset( $input['mode'] ) ? $input['mode'] : '', array( 'silent', 'prompt' ), $d['mode'] ),
			'image'          => array(
				'filename'      => $text( 'filename' ),
				'folder'        => self::sanitize_folder( isset( $img['folder'] ) ? wp_unslash( $img['folder'] ) : '' ),
				'alt'           => $text( 'alt' ),
				'title'         => $text( 'title' ),
				'caption'       => $text( 'caption' ),
				'description'   => $text( 'description' ),
				'format'        => $pick( isset( $img['format'] ) ? $img['format'] : '', array( 'original', 'jpeg', 'webp' ), 'original' ),
				'quality'       => max( 1, min( 100, isset( $img['quality'] ) ? absint( $img['quality'] ) : 82 ) ),
				'max_width'     => isset( $img['max_width'] ) ? absint( $img['max_width'] ) : 0,
				'classic_size'  => $pick( isset( $img['classic_size'] ) ? $img['classic_size'] : '', array( 'thumbnail', 'medium', 'large', 'full' ), 'large' ),
				'classic_align' => $pick( isset( $img['classic_align'] ) ? $img['classic_align'] : '', array( 'none', 'left', 'center', 'right' ), 'none' ),
				'classic_link'  => $pick( isset( $img['classic_link'] ) ? $img['classic_link'] : '', array( 'none', 'file', 'attachment' ), 'none' ),
			),
		);
	}

	/**
	 * Render one field.
	 *
	 * @param array $args Field args.
	 */
	public static function render_field( $args ) {
		$settings = self::get();
		$path     = explode( '.', $args['key'] );
		$value    = 2 === count( $path ) ? $settings[ $path[0] ][ $path[1] ] : $settings[ $path[0] ];
		$name     = self::OPTION . '[' . implode( '][', $path ) . ']';
		$id       = 'jbptm_' . implode( '_', $path );

		switch ( $args['type'] ) {
			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 1, (int) $value, false ),
					esc_html( $args['extra'] )
				);
				break;
			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $args['extra'] as $opt => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;
			case 'number':
				printf( '<input type="number" min="0" class="small-text" id="%1$s" name="%2$s" value="%3$s" />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				if ( $args['extra'] ) {
					printf( '<p class="description">%s</p>', esc_html( $args['extra'] ) );
				}
				break;
			default:
				printf( '<input type="text" class="regular-text code" id="%1$s" name="%2$s" value="%3$s" />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
				if ( $args['extra'] ) {
					printf( '<p class="description">%s</p>', esc_html( $args['extra'] ) );
				}
		}
	}

	/**
	 * Token reference shown above the naming fields.
	 */
	public static function render_tokens() {
		echo '<p>' . esc_html__( 'These fields accept the following tokens:', 'jb-paste-to-media' ) . '</p><p>';
		$items = array();
		foreach ( self::tokens() as $token => $label ) {
			$items[] = '<code>' . esc_html( $token ) . '</code> ' . esc_html( $label );
		}
		echo wp_kses( implode( ' &nbsp;·&nbsp; ', $items ), array( 'code' => array() ) );
		echo '</p>';
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Paste an image from the clipboard into a post or page and it is uploaded to the Media Library and inserted at the cursor.', 'jb-paste-to-media' ); ?></p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
