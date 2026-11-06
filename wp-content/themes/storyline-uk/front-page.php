<?php
/**
 * Front page — Storyline UK multi-rail homepage (REA MovieWeb spec §3, UK-adapted).
 *
 * MovieWeb's front page is a stack of curated rails above a filterable Latest river;
 * this template mirrors that anatomy on viral-reader's card system:
 *   hero (newest story — or the brand hero while the site has no posts)
 *   → Popular rail (editorial `popular` tag) → Trending rail (`trending` tag, text rows)
 *   → Unsolved Mysteries rail → Historical Stories rail
 *   → topic band (the six pillars) → Latest grid + pagination.
 * Every rail renders ONLY when it has posts, so at launch (zero posts) the page is
 * hero + topic band + Latest heading — graceful, never broken-looking.
 *
 * @package Storyline_UK
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();

$uk_q = vr_front_query();

/* The hero is the newest story; it is taken out of the Latest grid below. */
$uk_hero    = null;
$uk_hero_id = 0;
if ( ! is_paged() && $uk_q->have_posts() ) {
	$uk_q->the_post();
	$uk_hero    = get_post();
	$uk_hero_id = (int) $uk_hero->ID;
}

if ( is_paged() ) :
	?>
	<section class="vr-container page-head">
		<p class="eyebrow"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
		<h1 class="page-title"><?php printf( esc_html__( 'Latest articles — page %d', 'storyline-uk' ), (int) get_query_var( 'paged' ) ); ?></h1>
	</section>
	<?php
elseif ( $uk_hero ) :
	vr_hero_media( $uk_hero );
elseif ( function_exists( 'vr_hero_image_url' ) && vr_hero_image_url() ) :
	/* Standing hero while the site has no articles yet (brand launch state). */
	?>
	<section class="home-hero">
		<img class="home-hero__img" src="<?php echo esc_url( vr_hero_image_url() ); ?>" alt="" fetchpriority="high" decoding="async">
		<div class="vr-container home-hero__in">
			<p class="home-hero__eyebrow"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></p>
			<h1 class="home-hero__title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
			<p class="home-hero__sub"><?php esc_html_e( 'True crime, survival stories, unsolved mysteries and the strangest true events — told in full, for people who love to read.', 'storyline-uk' ); ?></p>
			<a class="vr-btn" href="#explore"><?php esc_html_e( 'Explore the topics', 'storyline-uk' ); ?></a>
		</div>
	</section>
	<?php
endif;

/* ---------- Rails (first page only, and only once the site has stories) ---------- */
if ( ! is_paged() && $uk_hero ) :

	/* A card rail: renders the section only when the query finds posts. */
	$uk_rail = function ( $eyebrow, $title, $more_url, $query ) {
		if ( ! $query->have_posts() ) { wp_reset_postdata(); return; }
		?>
		<section class="vr-container uk-rail">
			<div class="uk-rail__head">
				<span class="uk-rail__eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
				<h2 class="uk-rail__title"><?php echo esc_html( $title ); ?></h2>
				<?php if ( $more_url ) : ?><a class="uk-rail__more" href="<?php echo esc_url( $more_url ); ?>"><?php esc_html_e( 'More', 'storyline-uk' ); ?> &rarr;</a><?php endif; ?>
			</div>
			<div class="post-grid">
				<?php
				while ( $query->have_posts() ) {
					$query->the_post();
					get_template_part( 'template-parts/card', '', array( 'heading' => 'h3' ) );
				}
				?>
			</div>
		</section>
		<?php
		wp_reset_postdata();
	};

	/* Term links, guarded (a missing term must never break the homepage). */
	$uk_link = function ( $slug, $taxonomy ) {
		$t = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $t ) { return ''; }
		$l = get_term_link( $t );
		return is_wp_error( $l ) ? '' : $l;
	};

	/* Popular — the editorial `popular` curation tag (REA §3 "Popular" rail). */
	$uk_rail( __( 'Most read', 'storyline-uk' ), __( 'Popular right now', 'storyline-uk' ), $uk_link( 'popular', 'post_tag' ),
		new WP_Query( array( 'tag' => 'popular', 'posts_per_page' => 4, 'post__not_in' => array( $uk_hero_id ), 'ignore_sticky_posts' => true, 'no_found_rows' => true ) ) );

	/* Trending — the `trending` tag as numbered text rows (REA §3 "Trending" rail). */
	$uk_trend = new WP_Query( array( 'tag' => 'trending', 'posts_per_page' => 5, 'post__not_in' => array( $uk_hero_id ), 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
	if ( $uk_trend->have_posts() ) :
		?>
		<section class="vr-container uk-rail">
			<div class="uk-rail__head">
				<span class="uk-rail__eyebrow"><?php esc_html_e( 'Buzzing', 'storyline-uk' ); ?></span>
				<h2 class="uk-rail__title"><?php esc_html_e( 'Trending now', 'storyline-uk' ); ?></h2>
				<a class="uk-rail__more" href="<?php echo esc_url( $uk_link( 'trending', 'post_tag' ) ); ?>"><?php esc_html_e( 'More', 'storyline-uk' ); ?> &rarr;</a>
			</div>
			<ol class="uk-trend">
				<?php
				while ( $uk_trend->have_posts() ) {
					$uk_trend->the_post();
					$uk_cats = get_the_category();
					?>
					<li><a href="<?php the_permalink(); ?>"><span class="uk-trend__t"><?php the_title(); ?></span><?php if ( $uk_cats ) : ?><span class="uk-trend__m"><?php echo esc_html( $uk_cats[0]->name ); ?></span><?php endif; ?></a></li>
					<?php
				}
				?>
			</ol>
		</section>
		<?php
	endif;
	wp_reset_postdata();

	/* Unsolved Mysteries — the cases the evidence never quite closed. */
	$uk_rail( __( 'Still unsolved', 'storyline-uk' ), __( 'Unsolved mysteries', 'storyline-uk' ), $uk_link( 'unsolved-mysteries', 'category' ),
		new WP_Query( array( 'category_name' => 'unsolved-mysteries', 'posts_per_page' => 3, 'post__not_in' => array( $uk_hero_id ), 'ignore_sticky_posts' => true, 'no_found_rows' => true ) ) );

	/* Historical Stories — true stories from history, retold for readers. */
	$uk_rail( __( 'Deep dives', 'storyline-uk' ), __( 'Stories from history', 'storyline-uk' ), $uk_link( 'historical-stories', 'category' ),
		new WP_Query( array( 'category_name' => 'historical-stories', 'posts_per_page' => 4, 'post__not_in' => array( $uk_hero_id ), 'ignore_sticky_posts' => true, 'no_found_rows' => true ) ) );

endif;

/* The six pillars as topic tiles (parent block; covers fall back gracefully pre-content).
   Wrapped in #explore: the brand hero's "Explore the topics" button anchors here. */
if ( function_exists( 'vr_showcase_categories' ) ) {
	echo '<div id="explore">';
	vr_showcase_categories( 6 );
	echo '</div>';
}
?>

<section class="vr-container section">
	<div class="section-head">
		<h2><?php esc_html_e( 'Latest articles', 'storyline-uk' ); ?></h2>
	</div>
	<?php if ( $uk_q->have_posts() ) : ?>
		<div class="post-grid">
			<?php
			$uk_posts = $uk_q->posts;
			if ( $uk_hero_id ) {
				/* The hero story was already consumed above — skip it in the grid. */
				$uk_posts = array_values( array_filter( $uk_posts, function ( $p ) use ( $uk_hero_id ) { return (int) $p->ID !== $uk_hero_id; } ) );
			}
			foreach ( $uk_posts as $post ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- template loop
				setup_postdata( $post );
				get_template_part( 'template-parts/card', '', array( 'heading' => 'h3' ) );
			}
			wp_reset_postdata();
			?>
		</div>
		<?php vr_pagination( $uk_q ); ?>
	<?php else : ?>
		<p class="empty-note"><?php esc_html_e( 'No articles published yet — the first stories are being written. Explore the topics above in the meantime.', 'storyline-uk' ); ?></p>
	<?php endif; ?>
</section>

<?php
get_footer();
