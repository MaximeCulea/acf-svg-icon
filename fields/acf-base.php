<?php class acf_field_svg_icon extends acf_field {

	/**
	 * Defaults for the svg.
	 *
	 * @var array
	 */
	public $defaults = array();

	public $cache_key = 'acf_svg_icon_files';

	function __construct() {
		// vars
		$this->name     = 'svg_icon';
		$this->label    = __( 'SVG Icon selector', 'acf-svg-icon' );
		$this->category = __( 'Basic', 'acf' );
		$this->defaults = array(
			'allow_clear' => 0,
		);

		// do not delete!
		parent::__construct();

		// Hooks !
		add_action( 'save_post_attachment', array( $this, 'save_post_attachment' ) );
	}

	/**
	 *
	 *  Create the HTML interface for your field
	 *
	 * @param    $field - an array holding all the field's data
	 *
	 * @type    action
	 * @since    3.6
	 * @date     23/01/13
	 */
	function render_field( $field ) {
		// create Field HTML
		?>
		<input class="widefat acf-svg-icon-<?php echo esc_attr( $field['type'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>" data-placeholder="<?php _e( 'Select an icon', 'acf-svg-icon' ); ?>" data-allow-clear="<?php echo esc_attr( $field['allow_clear'] ) ?>" />
		<?php
	}

	/**
	 *  render_field_settings()
	 *
	 *  Create extra options for your field. This is rendered when editing a field.
	 *  The value of $field['name'] can be used (like bellow) to save extra data to the $field
	 *
	 * @type    action
	 * @since    3.6
	 * @date     23/01/13
	 *
	 * @param    $field - an array holding all the field's data
	 */
	function render_field_settings( $field ) {

		// allow clear.
		acf_render_field_setting( $field, array(
			'label'        => __( 'Display clear button?', 'acf-svg-icon' ),
			'instructions' => __( 'Whether or not a clear button is displayed when the select box has a selection.', 'acf-svg-icon' ),
			'name'         => 'allow_clear',
			'type'         => 'true_false',
			'ui'           => 1,
		) );
	}

	/**
	 * Get the SVG filepath from theme.
	 *
	 * @return array
	 * @author Nicolas JUEN
	 */
	private function get_svg_files_path() {
		$custom_svg_path_icons = apply_filters( 'acf_svg_icon_filepath', array() );

		return array_map( function ( $val ) {
			return [
				'type' => 'custom',
				'file' => $val,
			];
		}, (array) $custom_svg_path_icons );
	}

	/**
	 * Merge WP Medias SVG and custom SVG files
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_all_svg_files() {
		// First try to load files from the cache.
		$files = wp_cache_get( $this->cache_key );
		if ( ! empty( $files ) ) {
			return $files;
		}

		/**
		 * Get WP Media SVGs
		 *
		 * @since 2.0.0
		 */
		$media_svg_files = $this->get_medias_svg();

		/**
		 * The path to the svg file.
		 *
		 * @since 1.0.0
		 */
		$custom_svg_files = $this->get_svg_files_path();

		$files = array_merge( $media_svg_files, $custom_svg_files );

		// Cache 24 hours.
		wp_cache_set( $this->cache_key, $files, '', HOUR_IN_SECONDS * 24 );

		return $files;
	}

	/**
	 * Extract icons from svg file.
	 *
	 * @since 1.0.0
	 *
	 * @return array|bool
	 */
	public function parse_svg() {
		$files = $this->get_all_svg_files();
		if ( empty( $files ) ) {
			return false;
		}

		/**
		 * Get the allowed tags to parse icon's ids
		 *
		 * @param string $allowed_tags : Passed directly to strip_tags
		 *
		 * @author david-treblig
		 * @since 2.0.1
		 *
		 * @return string
		 */
		$allowed_tags = apply_filters( 'acf_svg_icon_svg_parse_tags', '<symbol><g>' );

		$out = array();
		foreach ( $files as $file ) {
			if ( ! is_file( $file['file'] ) ) {
				continue;
			}

			if ( 'media' === $file['type'] ) {
				$pathinfo = pathinfo( $file['file'] );
				$out[]    = array(
					'id'       => $file['id'],
					'text'     => self::get_nice_display_text( $pathinfo['filename'], false ),
					'url'      => $file['file_url'],
					'disabled' => false,
				);
			} else {
				// If not extract them from the CSS file.
				$contents = file_get_contents( $file['file'] );
				preg_match_all( '/id="(\S+)"/m', strip_tags( $contents, $allowed_tags ), $svg );

				foreach ( $svg[1] as $id ) {
					$id    = sanitize_title( $id );
					$out[] = array(
						'id'       => $id,
						'text'     => self::get_nice_display_text( $id ),
						'disabled' => false,
					);
				}
			}
		}

		return $out;
	}

	/**
	 * Get WP Medias SVGs
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_medias_svg() {
		$args = array(
			'post_type'      => 'attachment',
			'posts_per_page' => '-1',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image/svg+xml',
		);

		/**
		 * Filter WP Query get attachments args
		 *
		 * @since 2.0.0
		 *
		 * @param array $args
		 */
		$args = apply_filters( 'acf_svg_icon_wp_medias_svg_args', $args );

		$attachments = new WP_Query( $args );
		if ( empty( $attachments->posts ) ) {
			return array();
		}

		$svg = array();
		foreach ( $attachments->posts as $attachment ) {
			$svg[] = [
				'type'     => 'media',
				'id'       => $attachment->ID,
				'file'     => get_attached_file( $attachment->ID ),
				'file_url' => wp_get_attachment_url( $attachment->ID ),
			];
		}

		return $svg;
	}

	/**
	 * Format the icon id to get his nicename for display purpose
	 *
	 * @param $id
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public static function get_nice_display_text( $id, $delete_suffixe = true ) {
		// Split up the string based on the '-' carac
		$ex = explode( '-', $id );
		if ( empty( $ex ) ) {
			return $id;
		}

		// Delete the first value, as it has no real value for the icon name.
		if ( $delete_suffixe ) {
			unset( $ex[0] );
		}

		// Remix values into one with spaces
		$text = implode( ' ', $ex );

		// Add uppercase to the first word
		return Ucfirst( $text );
	}

	/**
	 * Display the css based on the vars given for dynamic fonts url.
	 *
	 * @since 1.0.0
	 */
	public function display_svg() {

		/**
		 * The svg's files URLs
		 *
		 * @since 1.0.0
		 *
		 * @param array $font_urls the default svg file url
		 */
		$files = $this->get_all_svg_files();
		if ( empty( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( ! is_file( $file['file'] ) ) {
				continue;
			}
			ob_start();
			include_once( $file['file'] );
			$svg = ob_get_clean();

			if ( true === strpos( $svg, 'style="' ) ) {
				$svg = str_replace( 'style="', 'style="display:none; ', $svg );
			} else {
				$svg = str_replace( '<svg ', '<svg style="display:none;" ', $svg );
			}

			echo $svg;
		}
	}

	/**
	 * Enqueue assets for the SVG icon field in admin
	 *
	 * @since 1.0.0
	 */
	function input_admin_enqueue_scripts() {
		// The suffix
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG === true ? '' : '.min';

		// Localizing the data
		wp_localize_script( 'acf-input-svg-icon', 'svg_icon_format_data', $this->parse_svg() );

		wp_register_style( 'acf-input-svg-icon', ACF_SVG_ICON_URL . 'assets/css/style' . $suffix . '.css', array( 'select2' ), ACF_SVG_ICON_VER );

		// Enqueuing
		wp_enqueue_script( 'acf-input-svg-icon' );
		wp_enqueue_style( 'acf-input-svg-icon' );
	}

	/**
	 * Display SVG style in head.
	 *
	 * @since 1.0.0
	 */
	public function input_admin_footer() {
		$this->display_svg();
	}

	/**
	 * Flush cache on new SVG added to medias
	 *
	 * @since 2.0.0
	 *
	 * @param $post_ID
	 *
	 * @return bool
	 */
	public function save_post_attachment( $post_ID ) {
		$mime_type = get_post_mime_type( $post_ID );
		if ( 'image/svg+xml' != $mime_type ) {
			return false;
		}

		wp_cache_delete( $this->cache_key );
	}

	function format_value( $value, $post_id, $field ) {
		if ( ! is_int( $value ) ) {
			return $value;
		}

		$file = get_attached_file( $value );
		echo ob_start( $file );
	}
}
