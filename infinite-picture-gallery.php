<?php
/**
 * Plugin Name:       Infinite Picture Gallery
 * Description:       Create picture collections with images and videos and display them in a responsive, infinitely scrolling gallery.
 * Version:           1.6.2
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * Author:            Panupan Sriautharawong
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       infinite-picture-gallery
 *
 * @package InfinitePictureGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class Infinite_Picture_Gallery {

	/** Plugin version. */
	const VERSION = '1.6.2';

	/** Custom post type used by existing installations. */
	const POST_TYPE = 'pictures';

	/** Number of gallery cards loaded per request. */
	const ITEMS_PER_PAGE = 12;

	/** Legacy description meta key retained for existing content. */
	const DESCRIPTION_META_KEY = '_ipg_description';

	/** Legacy gallery-media meta key retained for existing content. */
	const GALLERY_META_KEY = '_ipg_gallery_ids';

	/** Settings option name. */
	const SETTINGS_OPTION = 'infinite_picture_gallery_settings';

	/** Stored plugin version option. */
	const VERSION_OPTION = 'infinite_picture_gallery_version';

	/** Rewrite-flush flag option. */
	const REWRITE_FLUSH_OPTION = 'infinite_picture_gallery_rewrite_flush_needed';

	/** Default public gallery base. */
	const DEFAULT_GALLERY_BASE = 'gallery';

	/** Current AJAX action. */
	const AJAX_ACTION = 'infinite_picture_gallery_load_more';

	/** Legacy AJAX action retained for backwards compatibility. */
	const LEGACY_AJAX_ACTION = 'ipg_load_more';

	/** Current gallery query variable. */
	const GALLERY_QUERY_VAR = 'infinite_picture_gallery_view';

	/** Legacy gallery query variable retained for backwards compatibility. */
	const LEGACY_GALLERY_QUERY_VAR = 'ipg_gallery';

	/** Legacy-route query variable used for redirects. */
	const LEGACY_ROUTE_QUERY_VAR = 'infinite_picture_gallery_legacy_route';

	/** Picture IDs queued for AIOSEO synchronization at the end of the request. */
	private $aioseo_sync_queue = array();

	/** Settings page hook suffix for conditional admin assets. */
	private $settings_page_hook = '';

	/**
	 * Register plugin hooks.
	 */
	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'wp_loaded', array( $this, 'maybe_upgrade_and_flush_rewrites' ), 20 );

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'parse_query', array( $this, 'fix_query_flags' ) );
		add_action( 'template_redirect', array( $this, 'handle_legacy_redirects' ), 1 );
		add_action( 'template_redirect', array( $this, 'fix_gallery_headers' ) );
		add_filter( 'template_include', array( $this, 'load_templates' ) );

		add_filter( 'pre_get_document_title', array( $this, 'custom_pre_document_title' ), PHP_INT_MAX );
		add_filter( 'document_title_parts', array( $this, 'custom_document_title' ), PHP_INT_MAX );
		add_filter( 'wp_title', array( $this, 'custom_wp_title' ), PHP_INT_MAX, 2 );

		// Compatibility with popular SEO plugins already supported by previous versions.
		add_filter( 'wpseo_title', array( $this, 'custom_seo_plugin_title' ), PHP_INT_MAX );
		add_filter( 'rank_math/frontend/title', array( $this, 'custom_seo_plugin_title' ), PHP_INT_MAX );
		add_filter( 'aioseo_title', array( $this, 'custom_seo_plugin_title' ), PHP_INT_MAX );

		add_action( 'add_meta_boxes', array( $this, 'setup_admin_creation_page' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_custom_meta' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'sync_aioseo_on_save' ), 30, 3 );
		add_action( 'added_post_meta', array( $this, 'sync_aioseo_on_thumbnail_change' ), 20, 4 );
		add_action( 'updated_post_meta', array( $this, 'sync_aioseo_on_thumbnail_change' ), 20, 4 );
		add_action( 'shutdown', array( $this, 'process_aioseo_sync_queue' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_load_more' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( $this, 'ajax_load_more' ) );

		// Retain the pre-1.6 AJAX action so cached pages and third-party integrations keep working.
		add_action( 'wp_ajax_' . self::LEGACY_AJAX_ACTION, array( $this, 'ajax_load_more' ) );
		add_action( 'wp_ajax_nopriv_' . self::LEGACY_AJAX_ACTION, array( $this, 'ajax_load_more' ) );
	}

	/**
	 * Register the picture collection post type.
	 *
	 * The post type key is retained for existing content. Public permalinks use the
	 * configured gallery base, with legacy routes redirected separately.
	 */
	public function register_cpt() {
		$labels = array(
			'name'                  => __( 'Pictures', 'infinite-picture-gallery' ),
			'singular_name'         => __( 'Picture', 'infinite-picture-gallery' ),
			'menu_name'             => __( 'Pictures', 'infinite-picture-gallery' ),
			'name_admin_bar'        => __( 'Picture', 'infinite-picture-gallery' ),
			'add_new'               => __( 'Add New', 'infinite-picture-gallery' ),
			'add_new_item'          => __( 'Upload New Picture Collection', 'infinite-picture-gallery' ),
			'new_item'              => __( 'New Picture Collection', 'infinite-picture-gallery' ),
			'edit_item'             => __( 'Edit Picture Collection', 'infinite-picture-gallery' ),
			'view_item'             => __( 'View Picture Collection', 'infinite-picture-gallery' ),
			'all_items'             => __( 'All Pictures', 'infinite-picture-gallery' ),
			'search_items'          => __( 'Search Pictures', 'infinite-picture-gallery' ),
			'not_found'             => __( 'No pictures found.', 'infinite-picture-gallery' ),
			'not_found_in_trash'    => __( 'No pictures found in Trash.', 'infinite-picture-gallery' ),
			'featured_image'        => __( 'Cover photo', 'infinite-picture-gallery' ),
			'set_featured_image'    => __( 'Set cover photo', 'infinite-picture-gallery' ),
			'remove_featured_image' => __( 'Remove cover photo', 'infinite-picture-gallery' ),
			'use_featured_image'    => __( 'Use as cover photo', 'infinite-picture-gallery' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => true,
			'has_archive'  => true,
			'rewrite'      => array(
				'slug'       => self::get_gallery_base(),
				'with_front' => false,
			),
			'menu_icon'    => 'dashicons-format-gallery',
			'supports'     => array( 'title', 'thumbnail' ),
			'show_in_rest' => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Get plugin setting defaults.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'gallery_base'       => self::DEFAULT_GALLERY_BASE,
			'gallery_title'      => __( 'Picture Gallery', 'infinite-picture-gallery' ),
			'gallery_appearance' => 'classic',
			'enable_aioseo'      => 0,
		);
	}

	/**
	 * Determine whether the active AIOSEO installation exposes the API used by this plugin.
	 *
	 * @return bool
	 */
	private static function is_aioseo_supported() {
		$class = '\\AIOSEO\\Plugin\\Common\\Models\\Post';

		return function_exists( 'aioseo' ) && class_exists( $class ) && is_callable( array( $class, 'getPost' ) );
	}

	/**
	 * Get normalized plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::SETTINGS_OPTION, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::get_default_settings() );
	}

	/**
	 * Get the configured gallery base path without leading/trailing slashes.
	 *
	 * @return string
	 */
	public static function get_gallery_base() {
		$settings     = self::get_settings();
		$gallery_base = isset( $settings['gallery_base'] ) ? (string) $settings['gallery_base'] : self::DEFAULT_GALLERY_BASE;
		$gallery_base = self::sanitize_gallery_base_value( $gallery_base );

		return '' !== $gallery_base ? $gallery_base : self::DEFAULT_GALLERY_BASE;
	}

	/**
	 * Get the configured gallery URL.
	 *
	 * @return string
	 */
	public static function get_gallery_url() {
		return home_url( user_trailingslashit( self::get_gallery_base() ) );
	}

	/**
	 * Get the configured gallery title.
	 *
	 * @return string
	 */
	public static function get_gallery_title() {
		$settings = self::get_settings();
		$title    = isset( $settings['gallery_title'] ) ? sanitize_text_field( $settings['gallery_title'] ) : '';

		return '' !== $title ? $title : __( 'Picture Gallery', 'infinite-picture-gallery' );
	}

	/**
	 * Get the configured gallery appearance.
	 *
	 * @return string
	 */
	public static function get_gallery_appearance() {
		$settings   = self::get_settings();
		$appearance = isset( $settings['gallery_appearance'] ) ? sanitize_key( $settings['gallery_appearance'] ) : 'classic';

		return in_array( $appearance, array( 'classic', 'masonry' ), true ) ? $appearance : 'classic';
	}

	/**
	 * Determine whether AIOSEO syncing is enabled.
	 *
	 * @return bool
	 */
	private static function is_aioseo_enabled() {
		$settings = self::get_settings();
		return ! empty( $settings['enable_aioseo'] );
	}

	/**
	 * Sanitize a gallery base path.
	 *
	 * @param string $value Raw gallery base.
	 * @return string
	 */
	private static function sanitize_gallery_base_value( $value ) {
		$value = sanitize_text_field( (string) $value );
		$value = trim( $value );

		if ( preg_match( '#^https?://#i', $value ) ) {
			$parsed_path = wp_parse_url( $value, PHP_URL_PATH );
			$value       = is_string( $parsed_path ) ? $parsed_path : '';
		}

		$value    = trim( $value, "/ \\t\\n\\r\\0\\x0B" );
		$segments = array_filter( explode( '/', $value ), 'strlen' );
		$clean    = array();

		foreach ( $segments as $segment ) {
			$segment = sanitize_title( $segment );
			if ( '' !== $segment ) {
				$clean[] = $segment;
			}
		}

		return implode( '/', $clean );
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'infinite_picture_gallery_settings_group',
			self::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_default_settings(),
			)
		);
	}

	/**
	 * Sanitize plugin settings and schedule rewrite flushing when required.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = self::get_default_settings();
		$current  = self::get_settings();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		$gallery_base = isset( $input['gallery_base'] ) ? self::sanitize_gallery_base_value( wp_unslash( $input['gallery_base'] ) ) : self::DEFAULT_GALLERY_BASE;
		if ( '' === $gallery_base ) {
			$gallery_base = self::DEFAULT_GALLERY_BASE;
			add_settings_error(
				self::SETTINGS_OPTION,
				'infinite_picture_gallery_empty_base',
				__( 'The gallery URL cannot be empty. The default “gallery” path was used instead.', 'infinite-picture-gallery' ),
				'warning'
			);
		}

		$reserved_first_segments = array( 'author', 'category', 'comments', 'feed', 'search', 'tag', 'wp-admin', 'wp-content', 'wp-includes', 'wp-json' );
		$first_segment           = strtok( $gallery_base, '/' );
		if ( in_array( $first_segment, $reserved_first_segments, true ) ) {
			$gallery_base = isset( $current['gallery_base'] ) ? self::sanitize_gallery_base_value( $current['gallery_base'] ) : self::DEFAULT_GALLERY_BASE;
			add_settings_error(
				self::SETTINGS_OPTION,
				'infinite_picture_gallery_reserved_base',
				__( 'That gallery URL conflicts with a WordPress-reserved route. The previous URL was kept.', 'infinite-picture-gallery' ),
				'error'
			);
		}

		$current_base = isset( $current['gallery_base'] ) ? self::sanitize_gallery_base_value( $current['gallery_base'] ) : self::DEFAULT_GALLERY_BASE;
		if ( $gallery_base !== $current_base ) {
			$existing_page = get_page_by_path( $gallery_base, OBJECT, 'page' );
			if ( $existing_page && 'trash' !== $existing_page->post_status ) {
				$gallery_base = $current_base;
				add_settings_error(
					self::SETTINGS_OPTION,
					'infinite_picture_gallery_page_conflict',
					__( 'That gallery URL is already used by a WordPress Page. Choose a different path to avoid a routing conflict.', 'infinite-picture-gallery' ),
					'error'
				);
			}
		}

		$output['gallery_base'] = $gallery_base;

		$gallery_title = isset( $input['gallery_title'] ) ? sanitize_text_field( wp_unslash( $input['gallery_title'] ) ) : '';
		$output['gallery_title'] = '' !== $gallery_title ? $gallery_title : $defaults['gallery_title'];

		$appearance = isset( $input['gallery_appearance'] ) ? sanitize_key( wp_unslash( $input['gallery_appearance'] ) ) : 'classic';
		$output['gallery_appearance'] = in_array( $appearance, array( 'classic', 'masonry' ), true ) ? $appearance : 'classic';
		$output['enable_aioseo']      = ! empty( $input['enable_aioseo'] ) ? 1 : 0;

		if ( $output['gallery_base'] !== $current_base ) {
			update_option( self::REWRITE_FLUSH_OPTION, 1, false );
		}

		return $output;
	}

	/**
	 * Add Pictures > Settings.
	 */
	public function add_settings_page() {
		$this->settings_page_hook = add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			__( 'Picture Gallery Settings', 'infinite-picture-gallery' ),
			__( 'Settings', 'infinite-picture-gallery' ),
			'manage_options',
			'infinite-picture-gallery-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the plugin settings screen.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings      = self::get_settings();
		$aioseo_status = $this->get_aioseo_status();
		$gallery_url   = self::get_gallery_url();
		?>
		<div class="wrap infinite-picture-gallery-settings">
			<div class="infinite-picture-gallery-settings-heading">
				<div>
					<h1><?php esc_html_e( 'Infinite Picture Gallery Settings', 'infinite-picture-gallery' ); ?></h1>
					<p><?php esc_html_e( 'Control the public gallery URL, presentation, and optional integrations.', 'infinite-picture-gallery' ); ?></p>
				</div>
				<span class="infinite-picture-gallery-version"><?php echo esc_html( 'v' . self::VERSION ); ?></span>
			</div>

			<?php settings_errors( self::SETTINGS_OPTION ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'infinite_picture_gallery_settings_group' ); ?>

				<section class="infinite-picture-gallery-settings-card">
					<div class="infinite-picture-gallery-settings-card-header">
						<h2><?php esc_html_e( 'Gallery', 'infinite-picture-gallery' ); ?></h2>
						<p><?php esc_html_e( 'Configure where the gallery lives and how visitors see it.', 'infinite-picture-gallery' ); ?></p>
					</div>

					<div class="infinite-picture-gallery-setting-row">
						<div class="infinite-picture-gallery-setting-label">
							<label for="infinite-picture-gallery-base"><?php esc_html_e( 'Gallery URL', 'infinite-picture-gallery' ); ?></label>
							<p><?php esc_html_e( 'This path is used for both the gallery index and individual picture permalinks.', 'infinite-picture-gallery' ); ?></p>
						</div>
						<div class="infinite-picture-gallery-setting-control">
							<div class="infinite-picture-gallery-url-field">
								<span><?php echo esc_html( trailingslashit( home_url() ) ); ?></span>
								<input id="infinite-picture-gallery-base" type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[gallery_base]" value="<?php echo esc_attr( $settings['gallery_base'] ); ?>" class="regular-text" autocomplete="off">
							</div>
							<p class="description">
								<?php
								printf(
									/* translators: 1: Gallery URL, 2: Example picture URL. */
									esc_html__( 'Gallery: %1$s · Pictures: %2$s', 'infinite-picture-gallery' ),
									esc_url( $gallery_url ),
									esc_url( trailingslashit( $gallery_url ) . 'example-picture/' )
								);
								?>
							</p>
						</div>
					</div>

					<div class="infinite-picture-gallery-setting-row">
						<div class="infinite-picture-gallery-setting-label">
							<label for="infinite-picture-gallery-title"><?php esc_html_e( 'Gallery title', 'infinite-picture-gallery' ); ?></label>
							<p><?php esc_html_e( 'Displayed as the main heading and used in the gallery document title.', 'infinite-picture-gallery' ); ?></p>
						</div>
						<div class="infinite-picture-gallery-setting-control">
							<input id="infinite-picture-gallery-title" type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[gallery_title]" value="<?php echo esc_attr( $settings['gallery_title'] ); ?>" class="regular-text">
						</div>
					</div>

					<div class="infinite-picture-gallery-setting-row infinite-picture-gallery-setting-row-stack">
						<div class="infinite-picture-gallery-setting-label">
							<span class="infinite-picture-gallery-setting-title"><?php esc_html_e( 'Gallery appearance', 'infinite-picture-gallery' ); ?></span>
							<p><?php esc_html_e( 'Choose how picture collections are presented. Infinite scrolling works with every appearance.', 'infinite-picture-gallery' ); ?></p>
						</div>
						<div class="infinite-picture-gallery-appearance-options">
							<?php
							$appearances = array(
								'classic' => array(
									'label'       => __( 'Classic Cards', 'infinite-picture-gallery' ),
									'description' => __( 'The original balanced card grid with square media and titles below.', 'infinite-picture-gallery' ),
								),
								'masonry' => array(
									'label'       => __( 'Masonry', 'infinite-picture-gallery' ),
									'description' => __( 'A flowing, editorial layout that keeps the natural proportions of cover media.', 'infinite-picture-gallery' ),
								),
							);
							foreach ( $appearances as $appearance_key => $appearance_data ) :
								?>
								<label class="infinite-picture-gallery-appearance-option">
									<input type="radio" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[gallery_appearance]" value="<?php echo esc_attr( $appearance_key ); ?>" <?php checked( $settings['gallery_appearance'], $appearance_key ); ?>>
									<span class="infinite-picture-gallery-appearance-preview infinite-picture-gallery-appearance-preview-<?php echo esc_attr( $appearance_key ); ?>" aria-hidden="true"><i></i><i></i><i></i></span>
									<strong><?php echo esc_html( $appearance_data['label'] ); ?></strong>
									<span><?php echo esc_html( $appearance_data['description'] ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</section>

				<section class="infinite-picture-gallery-settings-card">
					<div class="infinite-picture-gallery-settings-card-header">
						<h2><?php esc_html_e( 'Integrations', 'infinite-picture-gallery' ); ?></h2>
						<p><?php esc_html_e( 'Optional integrations only run when the related plugin is available.', 'infinite-picture-gallery' ); ?></p>
					</div>
					<div class="infinite-picture-gallery-setting-row">
						<div class="infinite-picture-gallery-setting-label">
							<label for="infinite-picture-gallery-aioseo"><?php esc_html_e( 'Enable AIOSEO integration', 'infinite-picture-gallery' ); ?></label>
							<p><?php esc_html_e( 'When a Picture has a featured image, use it as AIOSEO’s Facebook and Twitter/X custom image.', 'infinite-picture-gallery' ); ?></p>
						</div>
						<div class="infinite-picture-gallery-setting-control">
							<label class="infinite-picture-gallery-toggle">
								<input id="infinite-picture-gallery-aioseo" type="checkbox" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[enable_aioseo]" value="1" <?php checked( ! empty( $settings['enable_aioseo'] ) ); ?>>
								<span><?php esc_html_e( 'Synchronize featured images with AIOSEO social images', 'infinite-picture-gallery' ); ?></span>
							</label>
							<?php $this->render_aioseo_status( $aioseo_status ); ?>
						</div>
					</div>
				</section>

				<?php submit_button( __( 'Save Settings', 'infinite-picture-gallery' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render AIOSEO availability status.
	 *
	 * @param array $status AIOSEO status data.
	 */
	private function render_aioseo_status( $status ) {
		$state = isset( $status['state'] ) ? $status['state'] : 'absent';
		if ( 'active' === $state ) {
			$text  = __( 'AIOSEO is active and the integration API is available.', 'infinite-picture-gallery' );
			$class = 'is-active';
		} elseif ( 'unsupported' === $state ) {
			$text  = __( 'AIOSEO is active, but the required post model API is unavailable.', 'infinite-picture-gallery' );
			$class = 'is-warning';
		} elseif ( 'inactive' === $state ) {
			$text  = __( 'AIOSEO is installed but inactive. Activate AIOSEO before enabling synchronization.', 'infinite-picture-gallery' );
			$class = 'is-neutral';
		} else {
			$text  = __( 'AIOSEO is not currently installed. Install and activate AIOSEO before enabling synchronization.', 'infinite-picture-gallery' );
			$class = 'is-neutral';
		}
		?>
		<p class="infinite-picture-gallery-integration-status <?php echo esc_attr( $class ); ?>">
			<span aria-hidden="true"></span><?php echo esc_html( $text ); ?>
		</p>
		<?php
	}

	/**
	 * Inspect AIOSEO availability.
	 *
	 * @return array
	 */
	private function get_aioseo_status() {
		if ( function_exists( 'aioseo' ) ) {
			$version = defined( 'AIOSEO_VERSION' ) ? AIOSEO_VERSION : '';

			if ( ! self::is_aioseo_supported() ) {
				return array( 'state' => 'unsupported', 'version' => $version );
			}

			return array( 'state' => 'active', 'version' => $version );
		}

		$installed = file_exists( WP_PLUGIN_DIR . '/all-in-one-seo-pack/all_in_one_seo_pack.php' );
		if ( ! $installed && is_admin() ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			foreach ( get_plugins() as $plugin_file => $plugin_data ) {
				$text_domain = isset( $plugin_data['TextDomain'] ) ? (string) $plugin_data['TextDomain'] : '';
				if ( 'all-in-one-seo-pack' === $text_domain || 0 === strpos( $plugin_file, 'all-in-one-seo-pack/' ) ) {
					$installed = true;
					break;
				}
			}
		}

		return array( 'state' => $installed ? 'inactive' : 'absent', 'version' => '' );
	}

	/**
	 * Replace the standard editor meta boxes with the gallery workflow.
	 */
	public function setup_admin_creation_page() {
		remove_meta_box( 'postimagediv', self::POST_TYPE, 'side' );

		add_meta_box(
			'postimagediv',
			__( '1. Featured / Cover Photo', 'infinite-picture-gallery' ),
			'post_thumbnail_meta_box',
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'ipg_description_meta',
			__( '2. Optional Description', 'infinite-picture-gallery' ),
			array( $this, 'render_description_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'ipg_gallery_meta',
			__( '3. Additional Gallery Media', 'infinite-picture-gallery' ),
			array( $this, 'render_gallery_meta_box' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the description field.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_description_meta_box( $post ) {
		wp_nonce_field( 'infinite_picture_gallery_save_description', 'infinite_picture_gallery_description_nonce' );
		$description = get_post_meta( $post->ID, self::DESCRIPTION_META_KEY, true );
		?>
		<div class="ipg-field-panel">
			<p class="ipg-field-intro">
				<?php esc_html_e( 'Add context for this collection. The description appears beneath the cover photo on the individual picture page.', 'infinite-picture-gallery' ); ?>
			</p>
			<label class="screen-reader-text" for="ipg_description"><?php esc_html_e( 'Picture description', 'infinite-picture-gallery' ); ?></label>
			<textarea id="ipg_description" name="infinite_picture_gallery_description" class="widefat ipg-description-field" rows="5" placeholder="<?php echo esc_attr__( 'Write an optional description…', 'infinite-picture-gallery' ); ?>"><?php echo esc_textarea( $description ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * Render the sortable image/video gallery field.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_gallery_meta_box( $post ) {
		wp_nonce_field( 'infinite_picture_gallery_save_gallery', 'infinite_picture_gallery_gallery_nonce' );
		$gallery_ids = $this->sanitize_gallery_ids( get_post_meta( $post->ID, self::GALLERY_META_KEY, true ) );
		?>
		<div id="ipg-gallery-editor" class="ipg-gallery-editor">
			<div class="ipg-gallery-toolbar">
				<div>
					<p class="ipg-field-intro">
						<?php esc_html_e( 'Add images or videos, then drag them into the order you want visitors to see.', 'infinite-picture-gallery' ); ?>
					</p>
					<p class="ipg-gallery-summary" aria-live="polite">
						<span id="ipg-gallery-count"><?php echo esc_html( count( $gallery_ids ) ); ?></span>
						<span><?php esc_html_e( 'selected', 'infinite-picture-gallery' ); ?></span>
					</p>
				</div>
				<button type="button" class="button button-primary ipg-add-media" id="ipg-add-gallery-images">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php esc_html_e( 'Add Media', 'infinite-picture-gallery' ); ?>
				</button>
			</div>

			<input type="hidden" id="ipg_gallery_ids" name="infinite_picture_gallery_gallery_ids" value="<?php echo esc_attr( implode( ',', $gallery_ids ) ); ?>">

			<div id="ipg-gallery-empty" class="ipg-gallery-empty"<?php if ( $gallery_ids ) : ?> hidden<?php endif; ?>>
				<span class="dashicons dashicons-format-gallery" aria-hidden="true"></span>
				<strong><?php esc_html_e( 'Build your gallery', 'infinite-picture-gallery' ); ?></strong>
				<span><?php esc_html_e( 'Choose images or videos from the Media Library.', 'infinite-picture-gallery' ); ?></span>
			</div>

			<ul id="ipg-gallery-preview" class="ipg-gallery-preview" aria-label="<?php echo esc_attr__( 'Selected gallery media', 'infinite-picture-gallery' ); ?>">
				<?php foreach ( $gallery_ids as $attachment_id ) : ?>
					<?php $this->render_admin_media_item( $attachment_id ); ?>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render one media item in the admin gallery editor.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private function render_admin_media_item( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return;
		}

		$is_video = wp_attachment_is( 'video', $attachment_id );
		?>
		<li class="ipg-media-item" data-id="<?php echo esc_attr( $attachment_id ); ?>">
			<span class="ipg-drag-handle dashicons dashicons-menu" aria-hidden="true"></span>
			<div class="ipg-media-preview">
				<?php if ( $is_video ) : ?>
					<?php $video_url = wp_get_attachment_url( $attachment_id ); ?>
					<?php if ( $video_url ) : ?>
						<video src="<?php echo esc_url( $video_url ); ?>#t=0.5" muted preload="metadata" aria-hidden="true"></video>
						<span class="dashicons dashicons-controls-play ipg-video-indicator" aria-hidden="true"></span>
					<?php endif; ?>
				<?php else : ?>
					<?php
					$image_html = wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'alt' => '' ) );
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by WordPress core for a known attachment ID.
					echo $image_html;
					?>
				<?php endif; ?>
			</div>
			<button type="button" class="ipg-remove-image" aria-label="<?php echo esc_attr__( 'Remove media from gallery', 'infinite-picture-gallery' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</li>
		<?php
	}

	/**
	 * Save gallery post metadata.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_custom_meta( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$gallery_nonce = '';
		$gallery_value = '';
		if ( isset( $_POST['infinite_picture_gallery_gallery_nonce'], $_POST['infinite_picture_gallery_gallery_ids'] ) && is_string( $_POST['infinite_picture_gallery_gallery_nonce'] ) && is_string( $_POST['infinite_picture_gallery_gallery_ids'] ) ) {
			$gallery_nonce = sanitize_text_field( wp_unslash( $_POST['infinite_picture_gallery_gallery_nonce'] ) );
			$gallery_value = sanitize_text_field( wp_unslash( $_POST['infinite_picture_gallery_gallery_ids'] ) );
		} elseif ( isset( $_POST['ipg_gallery_nonce'], $_POST['ipg_gallery_ids'] ) && is_string( $_POST['ipg_gallery_nonce'] ) && is_string( $_POST['ipg_gallery_ids'] ) ) {
			// Compatibility with edit screens loaded before upgrading from 1.5.x.
			$gallery_nonce = sanitize_text_field( wp_unslash( $_POST['ipg_gallery_nonce'] ) );
			$gallery_value = sanitize_text_field( wp_unslash( $_POST['ipg_gallery_ids'] ) );
		}

		$gallery_nonce_valid = wp_verify_nonce( $gallery_nonce, 'infinite_picture_gallery_save_gallery' ) || wp_verify_nonce( $gallery_nonce, 'ipg_save_gallery' );
		if ( $gallery_nonce_valid ) {
			$gallery_ids = $this->sanitize_gallery_ids( $gallery_value );
			update_post_meta( $post_id, self::GALLERY_META_KEY, implode( ',', $gallery_ids ) );
		}

		$description_nonce = '';
		$description_value = null;
		if ( isset( $_POST['infinite_picture_gallery_description_nonce'], $_POST['infinite_picture_gallery_description'] ) && is_string( $_POST['infinite_picture_gallery_description_nonce'] ) && is_string( $_POST['infinite_picture_gallery_description'] ) ) {
			$description_nonce = sanitize_text_field( wp_unslash( $_POST['infinite_picture_gallery_description_nonce'] ) );
			$description_value = wp_kses_post( wp_unslash( $_POST['infinite_picture_gallery_description'] ) );
		} elseif ( isset( $_POST['ipg_desc_nonce'], $_POST['ipg_description'] ) && is_string( $_POST['ipg_desc_nonce'] ) && is_string( $_POST['ipg_description'] ) ) {
			// Compatibility with edit screens loaded before upgrading from 1.5.x.
			$description_nonce = sanitize_text_field( wp_unslash( $_POST['ipg_desc_nonce'] ) );
			$description_value = wp_kses_post( wp_unslash( $_POST['ipg_description'] ) );
		}

		$description_nonce_valid = wp_verify_nonce( $description_nonce, 'infinite_picture_gallery_save_description' ) || wp_verify_nonce( $description_nonce, 'ipg_save_desc' );
		if ( null !== $description_value && $description_nonce_valid ) {
			update_post_meta( $post_id, self::DESCRIPTION_META_KEY, $description_value );
		}
	}

	/**
	 * Sync AIOSEO when a Picture is saved and already has a featured image.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post update.
	 */
	public function sync_aioseo_on_save( $post_id, $post, $update ) {
		unset( $update );

		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		$this->queue_aioseo_sync( $post_id );
	}

	/**
	 * Queue AIOSEO synchronization when a Picture featured image is added or changed.
	 *
	 * @param int    $meta_id    Metadata row ID.
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Metadata key.
	 * @param mixed  $meta_value Metadata value.
	 */
	public function sync_aioseo_on_thumbnail_change( $meta_id, $object_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );

		if ( '_thumbnail_id' !== $meta_key || self::POST_TYPE !== get_post_type( $object_id ) ) {
			return;
		}

		$this->queue_aioseo_sync( $object_id );
	}

	/**
	 * Queue a Picture for AIOSEO synchronization after all normal save handlers run.
	 *
	 * @param int $post_id Picture post ID.
	 */
	private function queue_aioseo_sync( $post_id ) {
		if ( ! self::is_aioseo_enabled() ) {
			return;
		}

		$post_id = absint( $post_id );
		if ( ! $post_id || self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}

		$this->aioseo_sync_queue[ $post_id ] = true;
	}

	/**
	 * Process deferred AIOSEO synchronization after WordPress and AIOSEO save hooks.
	 */
	public function process_aioseo_sync_queue() {
		if ( empty( $this->aioseo_sync_queue ) ) {
			return;
		}

		$post_ids               = array_keys( $this->aioseo_sync_queue );
		$this->aioseo_sync_queue = array();

		foreach ( $post_ids as $post_id ) {
			$this->sync_aioseo_for_post( $post_id );
		}
	}

	/**
	 * Synchronize a Picture featured image with AIOSEO social-image settings.
	 *
	 * @param int $post_id Picture post ID.
	 */
	private function sync_aioseo_for_post( $post_id ) {
		if ( ! self::is_aioseo_enabled() ) {
			return;
		}

		$post_id       = absint( $post_id );
		$attachment_id = get_post_thumbnail_id( $post_id );
		if ( ! $attachment_id ) {
			return;
		}

		$image_url = wp_get_attachment_image_url( $attachment_id, 'full' );
		if ( ! $image_url ) {
			return;
		}

		$this->update_aioseo_social_images( $post_id, $attachment_id, $image_url );
	}

	/**
	 * Update AIOSEO Facebook and Twitter/X custom images without replacing other SEO data.
	 *
	 * @param int    $post_id       Picture post ID.
	 * @param int    $attachment_id Featured-image attachment ID.
	 * @param string $image_url     Featured-image URL.
	 * @return true|WP_Error
	 */
	private function update_aioseo_social_images( $post_id, $attachment_id, $image_url ) {
		$status = $this->get_aioseo_status();
		if ( 'active' !== $status['state'] ) {
			return new WP_Error( 'infinite_picture_gallery_aioseo_unavailable', __( 'AIOSEO is not active or does not provide the required integration API.', 'infinite-picture-gallery' ) );
		}

		$image_url = esc_url_raw( $image_url );
		if ( '' === $image_url ) {
			return new WP_Error( 'infinite_picture_gallery_aioseo_invalid_url', __( 'The featured image URL could not be passed to AIOSEO.', 'infinite-picture-gallery' ) );
		}

		$class = '\AIOSEO\Plugin\Common\Models\Post';

		try {
			$aioseo_post = $class::getPost( $post_id );
			if ( ! is_object( $aioseo_post ) || ! is_callable( array( $aioseo_post, 'save' ) ) ) {
				return new WP_Error( 'infinite_picture_gallery_aioseo_model_unavailable', __( 'AIOSEO did not return a writable post model.', 'infinite-picture-gallery' ) );
			}

			$aioseo_post->og_image_type            = 'custom_image';
			$aioseo_post->og_image_custom_url      = $image_url;
			$aioseo_post->twitter_use_og           = false;
			$aioseo_post->twitter_image_type       = 'custom_image';
			$aioseo_post->twitter_image_custom_url = $image_url;
			$aioseo_post->og_image_url             = $image_url;
			$aioseo_post->twitter_image_url        = $image_url;

			$image_src = wp_get_attachment_image_src( absint( $attachment_id ), 'full' );
			if ( is_array( $image_src ) ) {
				$aioseo_post->og_image_width  = absint( $image_src[1] );
				$aioseo_post->og_image_height = absint( $image_src[2] );
			}

			$aioseo_post->save();

			if ( property_exists( $aioseo_post, 'lastError' ) && ! empty( $aioseo_post->lastError ) ) {
				return new WP_Error( 'infinite_picture_gallery_aioseo_save_error', __( 'AIOSEO reported a database error while updating the social images.', 'infinite-picture-gallery' ) );
			}

			$saved_post = $class::getPost( $post_id );
			if (
				! is_object( $saved_post ) ||
				'custom_image' !== (string) $saved_post->og_image_type ||
				$image_url !== (string) $saved_post->og_image_custom_url ||
				'custom_image' !== (string) $saved_post->twitter_image_type ||
				$image_url !== (string) $saved_post->twitter_image_custom_url ||
				(bool) $saved_post->twitter_use_og
			) {
				return new WP_Error( 'infinite_picture_gallery_aioseo_verify_error', __( 'AIOSEO did not persist the Facebook and Twitter/X custom-image settings.', 'infinite-picture-gallery' ) );
			}

			$aioseo = aioseo();
			if ( isset( $aioseo->meta->metaData ) && is_callable( array( $aioseo->meta->metaData, 'bustPostCache' ) ) ) {
				$aioseo->meta->metaData->bustPostCache( $post_id, $saved_post );
			}
		} catch ( Throwable $error ) {
			unset( $error );
			return new WP_Error( 'infinite_picture_gallery_aioseo_exception', __( 'AIOSEO could not be updated.', 'infinite-picture-gallery' ) );
		}

		return true;
	}

	/**
	 * Sanitize a comma-separated list (or array) of attachment IDs.
	 *
	 * @param string|array $value Gallery IDs.
	 * @return int[]
	 */
	private function sanitize_gallery_ids( $value ) {
		if ( is_array( $value ) ) {
			$raw_ids = $value;
		} elseif ( is_scalar( $value ) ) {
			$raw_ids = explode( ',', (string) $value );
		} else {
			return array();
		}

		$ids = array();
		foreach ( $raw_ids as $raw_id ) {
			if ( ! is_scalar( $raw_id ) ) {
				continue;
			}

			$raw_id = trim( (string) $raw_id );
			if ( '' === $raw_id || ! preg_match( '/^\d+$/', $raw_id ) ) {
				continue;
			}

			$attachment_id = absint( $raw_id );
			if ( $attachment_id ) {
				$ids[] = $attachment_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Load editor assets only on Picture edit screens.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function admin_enqueue_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$is_settings = '' !== $this->settings_page_hook && $hook === $this->settings_page_hook;
		$is_picture   = self::POST_TYPE === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true );

		if ( ! $is_settings && ! $is_picture ) {
			return;
		}

		wp_enqueue_style(
			'infinite-picture-gallery-admin',
			plugin_dir_url( __FILE__ ) . 'assets/admin-gallery.css',
			array(),
			self::VERSION
		);

		if ( $is_settings || 'edit.php' === $hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'infinite-picture-gallery-admin',
			plugin_dir_url( __FILE__ ) . 'assets/admin-gallery.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			self::VERSION,
			true
		);

		wp_localize_script(
			'infinite-picture-gallery-admin',
			'infinitePictureGalleryAdmin',
			array(
				'mediaFrameTitle'  => __( 'Select Media for Gallery', 'infinite-picture-gallery' ),
				'mediaFrameButton' => __( 'Add to Gallery', 'infinite-picture-gallery' ),
				'removeLabel'      => __( 'Remove media from gallery', 'infinite-picture-gallery' ),
				'aioseoEnabled'    => self::is_aioseo_enabled(),
				'aioseoSupported'  => self::is_aioseo_supported(),
			)
		);
	}

	/**
	 * Add useful columns to the Pictures list screen.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_admin_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;
			if ( 'cb' === $key ) {
				$new_columns['infinite_picture_gallery_cover'] = __( 'Cover', 'infinite-picture-gallery' );
			}
		}

		$new_columns['infinite_picture_gallery_media_count'] = __( 'Gallery Media', 'infinite-picture-gallery' );

		return $new_columns;
	}

	/**
	 * Render custom Pictures list columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_admin_column( $column, $post_id ) {
		if ( 'infinite_picture_gallery_cover' === $column ) {
			if ( has_post_thumbnail( $post_id ) ) {
				$image = get_the_post_thumbnail( $post_id, array( 56, 56 ), array( 'class' => 'ipg-list-thumbnail' ) );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by WordPress core for a known post ID.
				echo $image;
			} else {
				echo '<span class="ipg-list-placeholder dashicons dashicons-format-image" aria-hidden="true"></span>';
			}
		}

		if ( 'infinite_picture_gallery_media_count' === $column ) {
			$count = count( $this->sanitize_gallery_ids( get_post_meta( $post_id, self::GALLERY_META_KEY, true ) ) );
			echo esc_html( number_format_i18n( $count ) );
		}
	}

	/**
	 * Add the configured gallery rewrite rules and legacy redirects.
	 */
	public function add_rewrite_rules() {
		$gallery_base  = self::get_gallery_base();
		$gallery_regex = preg_quote( $gallery_base, '#' );

		add_rewrite_rule( '^' . $gallery_regex . '/?$', 'index.php?' . self::GALLERY_QUERY_VAR . '=1', 'top' );

		// Pre-1.6 public routes remain redirectable so existing links do not break.
		if ( 'gallery' !== $gallery_base ) {
			add_rewrite_rule( '^gallery/?$', 'index.php?' . self::LEGACY_ROUTE_QUERY_VAR . '=gallery', 'top' );
		}

		$legacy_picture_bases = array( 'picture' );
		global $wp_rewrite;
		if ( is_object( $wp_rewrite ) && isset( $wp_rewrite->front ) ) {
			$legacy_front = trim( (string) $wp_rewrite->front, '/' );
			if ( '' !== $legacy_front ) {
				$legacy_picture_bases[] = $legacy_front . '/picture';
			}
		}

		foreach ( array_unique( $legacy_picture_bases ) as $legacy_picture_base ) {
			if ( $legacy_picture_base === $gallery_base ) {
				continue;
			}

			$legacy_picture_regex = preg_quote( $legacy_picture_base, '#' );
			add_rewrite_rule( '^' . $legacy_picture_regex . '/?$', 'index.php?' . self::LEGACY_ROUTE_QUERY_VAR . '=gallery', 'top' );
			add_rewrite_rule( '^' . $legacy_picture_regex . '/([^/]+)/?$', 'index.php?post_type=' . self::POST_TYPE . '&name=$matches[1]&' . self::LEGACY_ROUTE_QUERY_VAR . '=single', 'top' );
		}
	}

	/**
	 * Register plugin query variables.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::GALLERY_QUERY_VAR;
		$vars[] = self::LEGACY_GALLERY_QUERY_VAR;
		$vars[] = self::LEGACY_ROUTE_QUERY_VAR;
		return array_values( array_unique( $vars ) );
	}

	/**
	 * Determine whether the current query is the public gallery index.
	 *
	 * @return bool
	 */
	private function is_gallery_request() {
		if ( 1 === absint( get_query_var( self::GALLERY_QUERY_VAR ) ) || 1 === absint( get_query_var( self::LEGACY_GALLERY_QUERY_VAR ) ) ) {
			return true;
		}

		// WordPress may resolve the configured base through the post type's native archive rewrite.
		// Treat that route as the plugin gallery as well so the bundled template, title, and assets apply.
		return is_post_type_archive( self::POST_TYPE );
	}

	/**
	 * Ensure the custom gallery route is not treated as the posts index or a 404.
	 *
	 * @param WP_Query $query Main query.
	 */
	public function fix_query_flags( $query ) {
		$is_gallery = 1 === absint( $query->get( self::GALLERY_QUERY_VAR ) ) || 1 === absint( $query->get( self::LEGACY_GALLERY_QUERY_VAR ) );

		if ( ! is_admin() && $query->is_main_query() && $is_gallery ) {
			$query->is_home     = false;
			$query->is_singular = false;
			$query->is_page     = false;
			$query->is_archive  = true;
			$query->is_404      = false;
		}
	}

	/**
	 * Redirect pre-1.6 public routes to their current canonical URLs.
	 */
	public function handle_legacy_redirects() {
		$legacy_route = sanitize_key( (string) get_query_var( self::LEGACY_ROUTE_QUERY_VAR ) );

		if ( 'gallery' === $legacy_route ) {
			wp_safe_redirect( self::get_gallery_url(), 301, 'Infinite Picture Gallery' );
			exit;
		}

		if ( 'single' === $legacy_route && is_singular( self::POST_TYPE ) ) {
			$permalink = get_permalink();
			if ( $permalink ) {
				wp_safe_redirect( $permalink, 301, 'Infinite Picture Gallery' );
				exit;
			}
		}
	}

	/**
	 * Send a successful status for the custom gallery route.
	 */
	public function fix_gallery_headers() {
		if ( $this->is_gallery_request() ) {
			global $wp_query;
			$wp_query->is_404 = false;
			status_header( 200 );
		}
	}

	/**
	 * Load bundled templates for gallery routes.
	 *
	 * @param string $template Theme-selected template.
	 * @return string
	 */
	public function load_templates( $template ) {
		if ( $this->is_gallery_request() ) {
			$gallery_template = plugin_dir_path( __FILE__ ) . 'templates/gallery-template.php';
			if ( file_exists( $gallery_template ) ) {
				return $gallery_template;
			}
		}

		if ( is_singular( self::POST_TYPE ) ) {
			$single_template = plugin_dir_path( __FILE__ ) . 'templates/single-pictures.php';
			if ( file_exists( $single_template ) ) {
				return $single_template;
			}
		}

		return $template;
	}

	/**
	 * Override SEO-plugin titles on the gallery route.
	 *
	 * @param string $title Existing title.
	 * @return string
	 */
	public function custom_seo_plugin_title( $title ) {
		if ( $this->is_gallery_request() ) {
			return sprintf( '%1$s - %2$s', self::get_gallery_title(), get_bloginfo( 'name' ) );
		}
		return $title;
	}

	/**
	 * Override the native document title for the gallery route.
	 *
	 * @param string $title Existing title.
	 * @return string
	 */
	public function custom_pre_document_title( $title ) {
		return $this->custom_seo_plugin_title( $title );
	}

	/**
	 * Adjust document title parts.
	 *
	 * @param array $title_parts Document title parts.
	 * @return array
	 */
	public function custom_document_title( $title_parts ) {
		if ( $this->is_gallery_request() ) {
			$title_parts['title'] = self::get_gallery_title();
		} elseif ( is_singular( self::POST_TYPE ) ) {
			$post_title = get_the_title();
			if ( empty( $post_title ) || '(no title)' === $post_title ) {
				$title_parts['title'] = __( 'Picture', 'infinite-picture-gallery' );
			}
		}
		return $title_parts;
	}

	/**
	 * Compatibility title filter for older themes.
	 *
	 * @param string $title Existing title.
	 * @param string $sep   Title separator.
	 * @return string
	 */
	public function custom_wp_title( $title, $sep = '' ) {
		if ( $this->is_gallery_request() ) {
			$separator = $sep ? ' ' . $sep . ' ' : ' - ';
			return self::get_gallery_title() . $separator . get_bloginfo( 'name' );
		}

		if ( is_singular( self::POST_TYPE ) ) {
			$post_title = get_the_title();
			if ( empty( $post_title ) || '(no title)' === $post_title ) {
				$separator = $sep ? ' ' . $sep . ' ' : ' - ';
				return __( 'Picture', 'infinite-picture-gallery' ) . $separator . get_bloginfo( 'name' );
			}
		}

		return $title;
	}

	/**
	 * Enqueue public assets on plugin-owned views only.
	 */
	public function enqueue_assets() {
		$is_gallery = $this->is_gallery_request();
		$is_single  = is_singular( self::POST_TYPE );

		if ( $is_gallery || $is_single ) {
			wp_enqueue_style(
				'infinite-picture-gallery-style',
				plugin_dir_url( __FILE__ ) . 'assets/gallery.css',
				array(),
				self::VERSION
			);
		}

		if ( $is_gallery ) {
			wp_enqueue_script(
				'infinite-picture-gallery-script',
				plugin_dir_url( __FILE__ ) . 'assets/infinite-scroll.js',
				array(),
				self::VERSION,
				true
			);

			wp_localize_script(
				'infinite-picture-gallery-script',
				'infinitePictureGalleryVars',
				array(
					'ajax_url'     => admin_url( 'admin-ajax.php' ),
					'action'       => self::AJAX_ACTION,
					'nonce'        => wp_create_nonce( 'infinite_picture_gallery_load_more_nonce' ),
					'loading'      => __( 'Loading more pictures…', 'infinite-picture-gallery' ),
					'end'          => __( 'You have reached the end of the gallery.', 'infinite-picture-gallery' ),
					'error'        => __( 'Unable to load more pictures. Please try again.', 'infinite-picture-gallery' ),
					'retry'        => __( 'Try again', 'infinite-picture-gallery' ),
					'load_more'    => __( 'Load more', 'infinite-picture-gallery' ),
				)
			);
		}
	}

	/**
	 * AJAX endpoint for infinite scrolling.
	 */
	public function ajax_load_more() {
		$nonce = isset( $_POST['nonce'] ) && is_string( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		$valid = wp_verify_nonce( $nonce, 'infinite_picture_gallery_load_more_nonce' ) || wp_verify_nonce( $nonce, 'ipg_load_more_nonce' );

		if ( ! $valid ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'infinite-picture-gallery' ) ), 403 );
		}

		$page = 1;
		if ( isset( $_POST['page'] ) && ! is_array( $_POST['page'] ) ) {
			$raw_page = sanitize_text_field( wp_unslash( $_POST['page'] ) );
			$page     = max( 1, absint( $raw_page ) );
		}

		$query = new WP_Query(
			array(
				'post_type'           => self::POST_TYPE,
				'post_status'         => 'publish',
				'posts_per_page'      => self::ITEMS_PER_PAGE,
				'paged'               => $page,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		if ( ! $query->have_posts() ) {
			wp_send_json_error( array( 'message' => 'no_more_posts' ) );
		}

		ob_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped within get_picture_html().
			echo self::get_picture_html( get_the_ID() );
		}
		wp_reset_postdata();

		wp_send_json_success( array( 'html' => ob_get_clean() ) );
	}

	/**
	 * Build a gallery card for a picture collection.
	 *
	 * @param int $post_id Picture post ID.
	 * @return string Escaped gallery-card HTML.
	 */
	public static function get_picture_html( $post_id ) {
		$post_id  = absint( $post_id );
		$media    = '';
		$has_media = false;

		if ( has_post_thumbnail( $post_id ) ) {
			$media     = get_the_post_thumbnail( $post_id, 'medium_large', array( 'loading' => 'lazy' ) );
			$has_media = ! empty( $media );
		} else {
			$gallery_ids = get_post_meta( $post_id, self::GALLERY_META_KEY, true );
			$ids         = array_values( array_filter( array_map( 'absint', explode( ',', (string) $gallery_ids ) ) ) );

			if ( ! empty( $ids ) ) {
				$first_id = $ids[0];
				if ( wp_attachment_is( 'video', $first_id ) ) {
					$url  = wp_get_attachment_url( $first_id );
					$mime = get_post_mime_type( $first_id );
					if ( $url ) {
						$media = sprintf(
							'<video class="ipg-grid-video" muted loop playsinline preload="metadata" aria-hidden="true"><source src="%1$s#t=0.5" type="%2$s"></video>',
							esc_url( $url ),
							esc_attr( $mime )
						);
						$has_media = true;
					}
				} else {
					$media     = wp_get_attachment_image( $first_id, 'medium_large', false, array( 'loading' => 'lazy' ) );
					$has_media = ! empty( $media );
				}
			}
		}

		if ( ! $has_media ) {
			return '';
		}

		$permalink = get_permalink( $post_id );
		$title     = get_the_title( $post_id );
		$has_title = ! empty( $title ) && '(no title)' !== $title;
		$text_html = '';

		if ( $has_title ) {
			$text_html = '<div class="ipg-grid-item-text"><h3 class="ipg-grid-title">' . esc_html( $title ) . '</h3></div>';
		}

		$link_label = $has_title ? $title : __( 'View picture', 'infinite-picture-gallery' );

		return sprintf(
			'<article class="ipg-grid-item"><a href="%1$s" title="%2$s" aria-label="%2$s" class="ipg-grid-img-link">%3$s</a>%4$s</article>',
			esc_url( $permalink ),
			esc_attr( $link_label ),
			$media,
			$text_html
		);
	}

	/**
	 * Apply one-time upgrades and deferred rewrite flushing after routes are registered.
	 */
	public function maybe_upgrade_and_flush_rewrites() {
		$stored_version = (string) get_option( self::VERSION_OPTION, '' );
		$needs_flush    = (bool) get_option( self::REWRITE_FLUSH_OPTION, false );

		if ( self::VERSION !== $stored_version ) {
			update_option( self::VERSION_OPTION, self::VERSION, false );
			$needs_flush = true;
		}

		if ( $needs_flush ) {
			flush_rewrite_rules( false );
			delete_option( self::REWRITE_FLUSH_OPTION );
		}
	}

	/**
	 * Flush rewrites on activation after registering routes.
	 */
	public function activate() {
		add_option( self::SETTINGS_OPTION, self::get_default_settings(), '', 'no' );
		update_option( self::VERSION_OPTION, self::VERSION, false );
		delete_option( self::REWRITE_FLUSH_OPTION );

		$this->register_cpt();
		$this->add_rewrite_rules();
		flush_rewrite_rules();
	}

	/**
	 * Flush rewrites on deactivation.
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}
}

new Infinite_Picture_Gallery();
