<?php
/**
 * Public infinite gallery template.
 *
 * @package InfinitePictureGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main ipg-site-main">
	<div class="ipg-gallery-wrapper">
		<header class="ipg-gallery-header">
			<h1 class="ipg-gallery-title"><?php echo esc_html( Infinite_Picture_Gallery::get_gallery_title() ); ?></h1>
		</header>

		<?php
		$infinite_picture_gallery_appearance = Infinite_Picture_Gallery::get_gallery_appearance();
		$infinite_picture_gallery_grid_class = 'ipg-grid';
		if ( 'masonry' === $infinite_picture_gallery_appearance ) {
			$infinite_picture_gallery_grid_class .= ' ipg-layout-masonry';
		}
		?>
		<div id="ipg-gallery-container" class="<?php echo esc_attr( $infinite_picture_gallery_grid_class ); ?>">
			<?php
			$infinite_picture_gallery_initial_query = new WP_Query(
				array(
					'post_type'           => Infinite_Picture_Gallery::POST_TYPE,
					'post_status'         => 'publish',
					'posts_per_page'      => Infinite_Picture_Gallery::ITEMS_PER_PAGE,
					'paged'               => 1,
					'orderby'             => 'date',
					'order'               => 'DESC',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);

			if ( $infinite_picture_gallery_initial_query->have_posts() ) {
				while ( $infinite_picture_gallery_initial_query->have_posts() ) {
					$infinite_picture_gallery_initial_query->the_post();
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped within get_picture_html().
					echo Infinite_Picture_Gallery::get_picture_html( get_the_ID() );
				}
				wp_reset_postdata();
			} else {
				?>
				<div class="ipg-empty-gallery">
					<p><?php esc_html_e( 'No pictures have been published yet.', 'peal333-infinite-picture-gallery' ); ?></p>
				</div>
				<?php
			}
			?>
		</div>

		<div id="ipg-load-more-sentinel" class="ipg-loader" role="status" aria-live="polite">
			<span class="ipg-spinner" aria-hidden="true"></span>
			<span class="ipg-loader-text"><?php esc_html_e( 'Loading more pictures…', 'peal333-infinite-picture-gallery' ); ?></span>
		</div>
	</div>
</main>

<?php get_footer(); ?>
