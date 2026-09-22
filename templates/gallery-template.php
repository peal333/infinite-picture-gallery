<?php
/**
 * Public infinite gallery template.
 *
 * @package PEALIPG
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main pealipg-site-main">
	<div class="pealipg-gallery-wrapper">
		<header class="pealipg-gallery-header">
			<h1 class="pealipg-gallery-title"><?php echo esc_html( PEALIPG_Plugin::get_gallery_title() ); ?></h1>
		</header>

		<?php
		$pealipg_appearance = PEALIPG_Plugin::get_gallery_appearance();
		$pealipg_grid_class = 'pealipg-grid';
		if ( 'masonry' === $pealipg_appearance ) {
			$pealipg_grid_class .= ' pealipg-layout-masonry';
		}
		?>
		<?php
		$pealipg_current_page = max( 1, absint( get_query_var( 'paged' ) ) );
		$pealipg_initial_query = new WP_Query( PEALIPG_Plugin::get_gallery_query_args( $pealipg_current_page ) );
		$pealipg_max_pages     = absint( $pealipg_initial_query->max_num_pages );
		?>

		<div id="pealipg-gallery-container" class="<?php echo esc_attr( $pealipg_grid_class ); ?>">
			<?php

			if ( $pealipg_initial_query->have_posts() ) {
				while ( $pealipg_initial_query->have_posts() ) {
					$pealipg_initial_query->the_post();
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped within get_picture_html().
					echo PEALIPG_Plugin::get_picture_html( get_the_ID() );
				}
				wp_reset_postdata();
			} else {
				?>
				<div class="pealipg-empty-gallery">
					<p><?php esc_html_e( 'No pictures have been published yet.', 'peal333-infinite-picture-gallery' ); ?></p>
				</div>
				<?php
			}
			?>
		</div>

		<?php if ( $pealipg_current_page < $pealipg_max_pages ) : ?>
			<div
				id="pealipg-load-more-sentinel"
				class="pealipg-loader"
				data-pealipg-current-page="<?php echo esc_attr( $pealipg_current_page ); ?>"
				data-pealipg-max-pages="<?php echo esc_attr( $pealipg_max_pages ); ?>"
			>
				<div class="pealipg-loader-status" role="status" aria-live="polite" aria-atomic="true">
					<span class="pealipg-spinner" aria-hidden="true"></span>
					<span class="pealipg-loader-text"></span>
				</div>
				<a class="pealipg-load-more-button" href="<?php echo esc_url( PEALIPG_Plugin::get_gallery_page_url( $pealipg_current_page + 1 ) ); ?>">
					<?php esc_html_e( 'Load more', 'peal333-infinite-picture-gallery' ); ?>
				</a>
			</div>
		<?php endif; ?>

		<?php
		if ( $pealipg_max_pages > 1 ) {
			$pealipg_pagination_base  = str_replace( '999999999', '%#%', esc_url( PEALIPG_Plugin::get_gallery_page_url( 999999999 ) ) );
			$pealipg_pagination_links = paginate_links(
				array(
					'base'      => $pealipg_pagination_base,
					'format'    => '',
					'current'   => $pealipg_current_page,
					'total'     => $pealipg_max_pages,
					'mid_size'  => 1,
					'prev_text' => __( 'Previous', 'peal333-infinite-picture-gallery' ),
					'next_text' => __( 'Next', 'peal333-infinite-picture-gallery' ),
					'type'      => 'list',
				)
			);

			if ( $pealipg_pagination_links ) {
				?>
				<nav class="pealipg-pagination" aria-label="<?php echo esc_attr__( 'Gallery pages', 'peal333-infinite-picture-gallery' ); ?>">
					<?php echo wp_kses_post( $pealipg_pagination_links ); ?>
				</nav>
				<?php
			}
		}
		?>
	</div>
</main>

<?php get_footer(); ?>
