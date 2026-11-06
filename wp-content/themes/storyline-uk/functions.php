<?php
/**
 * UNTITLED UK STORY SITE (theme slug "storyline-uk") — child theme of Viral Reader for a UK
 * true-story site (true crime, survival stories, unsolved mysteries, incredible people,
 * historical stories, strange-but-true events), read mostly from social feeds
 * on phones. PLACEHOLDER BRAND: the working title lives in content/site-profile.json; when
 * the owner picks the real brand it is applied in one pass (this file's strings included).
 *
 * Everything here is presentation: brand CSS, the multi-rail homepage (front-page.php), the
 * end-of-article block (share, next article), the sticky next-article bar, branded hero/covers
 * and social-card fallbacks for non-article pages.
 * Adapted from the dales-cobbles child theme (germ pattern) with all soap machinery removed.
 *
 * @package Untitled_UK_Story_Site
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'UK_VERSION', '1.0.4' );
define( 'UK_HERO_SIZES', '(max-width:1200px) 100vw, 1200px' );

function uk_asset( $rel ) {
	return get_stylesheet_directory_uri() . '/assets/' . ltrim( $rel, '/' );
}

/* ---------- Styles ----------
   The parent inlines get_stylesheet_directory()/style.css, which for a child theme is only the child's
   header. So inline the parent stylesheet first, then the brand layer, keeping the parent's no-blocking-CSS
   speed win. Font URLs in the brand CSS are made absolute because inline CSS resolves against the page. */
function uk_minify_css( $css ) {
	/* Conservative: comments stripped first, then double-quoted strings swapped out so
	   whitespace inside them survives. Page cache means this runs only on cache misses. */
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	$keep = array();
	$css  = preg_replace_callback( '#"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"#', function ( $m ) use ( &$keep ) {
		$k = "\x01" . count( $keep ) . "\x02";
		$keep[ $k ] = $m[0];
		return $k;
	}, $css );
	$css = preg_replace( '/\s+/', ' ', $css );
	$css = preg_replace( '/\s*([{}:;,>+~])\s*/', '$1', $css );
	$css = str_replace( array( ';}', ' 0px', ':0px', ' 0em' ), array( '}', ' 0', ':0', ' 0' ), $css );
	return trim( strtr( $css, $keep ) );
}

add_action( 'wp_enqueue_scripts', function () {
	$parent = get_template_directory() . '/style.css';
	$brand  = get_stylesheet_directory() . '/assets/storyline-uk.css';
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled theme files.
	$css = ( is_readable( $parent ) ? file_get_contents( $parent ) : '' ) . "\n" . ( is_readable( $brand ) ? file_get_contents( $brand ) : '' );
	$css = str_replace( '__THEME__', get_stylesheet_directory_uri(), $css );
	wp_add_inline_style( 'viral-reader', uk_minify_css( $css ) );
}, 11 );

/* Preload the reading font so the first paragraph paints in Literata, not a fallback.
   Anton (headlines) is preloaded too: it is the display face of the H1/logo, and as WOFF2 it is
   small enough (57 KB) that an early fetch beats the late swap flash. */
add_action( 'wp_head', function () {
	printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( uk_asset( 'fonts/literata-400.woff2' ) ) );
	printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( uk_asset( 'fonts/Anton-Regular.woff2' ) ) );
}, 2 );

/* Third-party origins every page needs once ads/analytics are live (Site Kit gtag + AdSense):
   preconnect once so the browser opens the connections while the HTML still parses. */
add_action( 'wp_head', function () {
	if ( is_admin() || is_feed() ) { return; }
	foreach ( array( 'https://www.googletagmanager.com', 'https://pagead2.googlesyndication.com', 'https://googleads.g.doubleclick.net' ) as $o ) {
		printf( '<link rel="preconnect" href="%s">' . "\n", esc_url( $o ) );
	}
}, 1 );

/* ---------- Parent feature switches ---------- */
/* Cinematic dark skin (viral-reader ships it; the REA MovieWeb spec §5 #19 calls for it
   on an entertainment title — the child CSS below layers the neutral placeholder tokens). */
add_filter( 'vr_skin', function () { return 'cinematic'; } );
add_filter( 'vr_enable_share_at_end', '__return_false' );     /* replaced by the richer end block below */
add_filter( 'vr_enable_pinterest_save', '__return_false' );   /* Facebook audience */
add_filter( 'vr_hero_image_url', function () { return uk_asset( 'brand/hero-1280.webp' ); } );

/* The parent preloads the newest article's cover as the home LCP image, but this child's home hero is the
   brand photo above, so that preload fetched a second, unused image. Preload the real hero. */
add_action( 'wp_head', function () {
	if ( ! is_front_page() || is_paged() ) { return; }
	remove_action( 'wp_head', 'vr_preload_lcp', 1 );
	printf( '<link rel="preload" as="image" href="%s" imagesrcset="%s" imagesizes="%s" type="image/webp" fetchpriority="high">' . "\n", esc_url( uk_asset( 'brand/hero-1280.webp' ) ), esc_attr( uk_hero_srcset() ), esc_attr( UK_HERO_SIZES ) );
}, 0 );

/* Category tiles on the home page use the newest article image of that category. */
add_filter( 'vr_category_cover_url', function ( $url, $term_id ) {
	if ( '' !== $url || ! $term_id ) { return $url; }
	$cache = get_transient( 'uk_cover2_' . $term_id );
	if ( false !== $cache ) { return $cache; }
	$q   = get_posts( array( 'cat' => (int) $term_id, 'numberposts' => 1, 'meta_key' => '_thumbnail_id', 'fields' => 'ids' ) );
	$img = $q ? (string) get_the_post_thumbnail_url( $q[0], 'uk-card' ) : '';
	set_transient( 'uk_cover2_' . $term_id, $img, DAY_IN_SECONDS );
	return $img;
}, 10, 2 );

/* ---------- The next article ----------
   Same category first (keeps the soap), newest older article; wraps to the newest of the category,
   then to any recent article. Cached per post for an hour. */
function uk_next_article( $post_id ) {
	$key  = 'uk_next_' . $post_id;
	$next = get_transient( $key );
	if ( false !== $next ) { return $next ? get_post( $next ) : null; }

	$cats = wp_get_post_categories( $post_id );
	$base = array( 'numberposts' => 1, 'post__not_in' => array( $post_id ), 'fields' => 'ids', 'ignore_sticky_posts' => true );
	$date = get_post_field( 'post_date', $post_id );
	$try  = array();
	if ( $cats ) {
		$try[] = $base + array( 'category__in' => $cats, 'date_query' => array( array( 'before' => $date ) ) );
		$try[] = $base + array( 'category__in' => $cats );
	}
	$try[] = $base;
	$id = 0;
	foreach ( $try as $args ) {
		$found = get_posts( $args );
		if ( $found ) { $id = (int) $found[0]; break; }
	}
	set_transient( $key, $id, HOUR_IN_SECONDS );
	return $id ? get_post( $id ) : null;
}

function uk_icon( $name ) {
	$icons = array(
		'fb'    => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.6l.4-3h-3V8.6c0-.9.3-1.5 1.6-1.5h1.6V4.4c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.9v3h2.6V21h3z"/></svg>',
		'wa'    => '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3zm4.6 12.6c-.2.6-1.1 1.1-1.6 1.2-.4.1-1 .1-1.6-.1-.4-.1-.9-.3-1.5-.6-2.6-1.1-4.3-3.8-4.4-4-.1-.2-1-1.4-1-2.6s.6-1.9.9-2.1c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.3 0 .5l-.3.5-.4.4c-.1.1-.3.3-.1.6.2.3.7 1.2 1.6 1.9 1.1.9 2 1.2 2.3 1.4.3.1.4.1.6-.1l.8-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.3z"/></svg>',
		'arrow' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
		'plus'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>',
	);
	return $icons[ $name ] ?? '';
}

/* ---------- End of article: reader question, share, next article ----------
   Priority 30: after the plugin's in-content ads (15) so the block always closes the article. */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) { return $content; }
	$id       = get_the_ID();
	$url      = rawurlencode( get_permalink( $id ) );
	$question = trim( (string) get_post_meta( $id, '_uk_question', true ) );
	if ( '' === $question ) { $question = 'What do you think? Tell a fellow fan — and share it with someone who needs to see it.'; }

	$out  = '<aside class="uk-end" aria-label="Your turn">';
	$out .= '<img class="uk-end__mark" src="' . esc_url( uk_asset( 'brand/icon-192.webp' ) ) . '" alt="" width="44" height="44" loading="lazy" decoding="async">';
	$out .= '<p class="uk-end__q">' . esc_html( $question ) . '</p>';
	$out .= '<div class="uk-end__actions">';
	$out .= '<a class="uk-btn uk-btn--fb" href="https://www.facebook.com/sharer/sharer.php?u=' . $url . '" target="_blank" rel="noopener nofollow">' . uk_icon( 'fb' ) . 'Share on Facebook</a>';
	$out .= '<a class="uk-btn uk-btn--wa" href="https://wa.me/?text=' . $url . '" target="_blank" rel="noopener nofollow">' . uk_icon( 'wa' ) . 'Send on WhatsApp</a>';
	$out .= '<span class="uk-share"><button type="button" class="uk-btn uk-btn--more" data-uk-more aria-haspopup="true" aria-expanded="false">' . uk_icon( 'plus' ) . 'More</button>';
	$title   = rawurlencode( get_the_title( $id ) );
	$img     = rawurlencode( (string) get_the_post_thumbnail_url( $id, 'large' ) );
	$out    .= '<span class="uk-share__pop" role="menu">';
	$out    .= '<a role="menuitem" href="https://t.me/share/url?url=' . $url . '&text=' . $title . '" target="_blank" rel="noopener nofollow">Send on Telegram</a>';
	$out    .= '<a role="menuitem" href="https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title . '" target="_blank" rel="noopener nofollow">Share on X</a>';
	$out    .= '<a role="menuitem" href="mailto:?subject=' . $title . '&body=' . $url . '">Send by email</a>';
	$out    .= '<button type="button" role="menuitem" data-uk-copy>Copy link</button>';
	$out    .= '</span></span>';
	$out .= '</div>';
	$out .= '<p class="uk-follow">More true stories, told in full — start with <a href="' . esc_url( home_url( '/category/true-crime/' ) ) . '">true crime</a>.</p>';
	$out .= '</aside>';

	/* Share-more behaviour (printed once per page). */
	static $uk_share_js = false;
	if ( ! $uk_share_js ) {
		$uk_share_js = true;
		$out .= '<script>(function(){if(window.__dcShare)return;window.__dcShare=1;document.addEventListener("click",function(e){'
			. 'var m=e.target.closest("[data-uk-more]");var s=m&&m.closest(".uk-share");'
			. 'document.querySelectorAll(".uk-share.is-open").forEach(function(o){if(o!==s)o.classList.remove("is-open");});'
			. 'if(m&&s){e.preventDefault();s.classList.toggle("is-open");m.setAttribute("aria-expanded",s.classList.contains("is-open"));}else if(!e.target.closest(".uk-share__pop")){document.querySelectorAll(".uk-share.is-open").forEach(function(o){o.classList.remove("is-open");});}'
			. 'var c=e.target.closest("[data-uk-copy]");if(c){e.preventDefault();var t=document.title+" – "+location.href;'
			. 'function done(){c.textContent="Copied!";setTimeout(function(){c.textContent="Copy link";},1500);}'
			. 'if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(done,done);}else{var i=document.createElement("textarea");i.value=t;document.body.appendChild(i);i.select();try{document.execCommand("copy");}catch(x){}document.body.removeChild(i);done();}}'
			. '});document.addEventListener("keydown",function(e){if(e.key==="Escape"){document.querySelectorAll(".uk-share.is-open").forEach(function(o){o.classList.remove("is-open");});}});})();</script>';
	}

	$next = uk_next_article( $id );
	if ( $next ) {
		$out .= '<a class="uk-next" href="' . esc_url( get_permalink( $next ) ) . '">';
		$thumb = get_the_post_thumbnail_url( $next, 'thumbnail' );
		if ( $thumb ) { $out .= '<img src="' . esc_url( $thumb ) . '" alt="" width="120" height="120" loading="lazy">'; }
		$out .= '<span><span class="uk-next__eyebrow">Next article ' . uk_icon( 'arrow' ) . '</span><span class="uk-next__title">' . esc_html( get_the_title( $next ) ) . '</span></span></a>';
	}
	$out .= '<p class="uk-note">' . esc_html( get_bloginfo( 'name' ) ) . ' is an independent true-story publication. Names and trademarks belong to their respective owners; theories are labelled as theories.</p>';
	return $content . $out;
}, 30 );

/* ---------- Sticky next-article bar (phones + desktop corner) ---------- */
add_action( 'wp_footer', function () {
	if ( ! is_singular( 'post' ) ) { return; }
	$next = uk_next_article( get_queried_object_id() );
	if ( ! $next ) { return; }
	$thumb = get_the_post_thumbnail_url( $next, 'thumbnail' );
	?>
	<a class="uk-bar" id="uk-bar" href="<?php echo esc_url( get_permalink( $next ) ); ?>" aria-hidden="true" tabindex="-1">
		<?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb ); ?>" alt="" width="48" height="48" loading="lazy"><?php endif; ?>
		<span class="uk-bar__t"><b>Keep reading</b><span><?php echo esc_html( get_the_title( $next ) ); ?></span></span>
		<span class="uk-bar__go"><?php echo uk_icon( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG ?></span>
		<button class="uk-bar__x" type="button" aria-label="Close">&times;</button>
	</a>
	<script>
	(function(){
		var bar=document.getElementById('uk-bar'),src=document.querySelector('.entry-content');
		if(!bar||!src)return;
		try{if(sessionStorage.getItem('dcBarOff'))return;}catch(e){}
		bar.querySelector('.uk-bar__x').addEventListener('click',function(e){e.preventDefault();e.stopPropagation();bar.classList.remove('is-on');try{sessionStorage.setItem('dcBarOff','1');}catch(x){}});
		var tick=false,update=function(){var r=src.getBoundingClientRect(),seen=(innerHeight-r.top)/r.height;var on=seen>.6;bar.classList.toggle('is-on',on);bar.setAttribute('aria-hidden',on?'false':'true');bar.tabIndex=on?0:-1;tick=false;};
		addEventListener('scroll',function(){if(!tick){tick=true;requestAnimationFrame(update);}},{passive:true});
		/* Google's anchor ad is a fixed bar at the bottom of the screen; lift our bar above it so neither hides the other. */
		var anchor=function(){var a=document.querySelector('ins.adsbygoogle[data-anchor-status="displayed"],ins.adsbygoogle-noablate[data-anchor-status="displayed"]'),h=0;if(a){var r=a.getBoundingClientRect();if(r.bottom>=innerHeight-2&&r.height<200){h=Math.round(r.height);}}document.documentElement.style.setProperty('--uk-anchor',h+'px');};
		setInterval(anchor,1500);
	})();
	</script>
	<?php
}, 30 );

/* ---------- Social cards for the home page, categories and pages ----------
   Article pages get their own og: tags from the plugin; everything else falls back to the brand card so a
   shared category or home link never shows an empty preview on Facebook. */
add_action( 'wp_head', function () {
	if ( is_singular( 'post' ) ) { return; }
	$title = is_front_page() ? get_bloginfo( 'name' ) . ' – ' . get_bloginfo( 'description' ) : wp_get_document_title();
	$desc  = is_category() ? wp_strip_all_tags( category_description() ) : '';
	if ( '' === $desc ) { $desc = 'True crime, survival stories, unsolved mysteries and the strangest true events — told in full, for people who love to read.'; }
	$url = is_front_page() ? home_url( '/' ) : ( is_category() ? get_category_link( get_queried_object_id() ) : get_permalink() );
	echo '<meta property="og:type" content="website">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:image" content="' . esc_url( uk_asset( 'brand/og.jpg' ) ) . '">' . "\n";
	echo '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="theme-color" content="#0B0E17">' . "\n";
	if ( is_front_page() || is_category() ) { echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n"; }
}, 5 );

/* ---------- Ads: fixed sizes, no layout jumps ----------
   Same architecture as the sibling sites (fixed 300x250 phone / 728x90 desktop, fallback card, deferred
   loader, lazy lower units). INERT while the plugin's ads are disabled (launch state: ADSENSE_ENABLE=0) —
   uk_ad_client() returns '' and nothing renders. No manual slot ids exist for this site yet; the seed only
   enables the Auto ads loader in stage 2. */
function uk_ad_client() {
	$ads = function_exists( 'wpap_get_ads' ) ? wpap_get_ads() : array();
	if ( empty( $ads['enabled'] ) ) { return ''; }
	return preg_match( '/client=(ca-pub-\d+)/', (string) ( $ads['auto_code'] ?? '' ), $m ) ? $m[1] : '';
}

function uk_ad_ins( $client, $slot ) {
	$size = wp_is_mobile() ? array( 300, 250 ) : array( 728, 90 );
	return '<ins class="adsbygoogle" style="display:inline-block;width:' . $size[0] . 'px;height:' . $size[1] . 'px" data-ad-client="' . esc_attr( $client ) . '" data-ad-slot="' . esc_attr( $slot ) . '"></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script>';
}

/* Phone and desktop get different ad sizes from the same URL: tell any cache in between. */
add_action( 'send_headers', function () { header( 'Vary: User-Agent', false ); } );

/* The ad right under the header, the first thing a Facebook visitor sees. The strip has a fixed height; if
   Google has no ad for a view, a "most read" card of exactly the same size takes its place. Renders nothing
   while ads are disabled. */
function uk_top_ad() {
	$client = uk_ad_client();
	if ( '' === $client ) { return; }
	$fb = null;
	if ( is_singular( 'post' ) ) { $fb = uk_next_article( get_queried_object_id() ); }
	if ( ! $fb ) {
		$latest = get_posts( array( 'numberposts' => 1, 'ignore_sticky_posts' => true, 'post__not_in' => is_singular() ? array( get_queried_object_id() ) : array() ) );
		$fb     = $latest ? $latest[0] : null;
	}
	echo '<div class="vr-ad-strip uk-top ' . ( wp_is_mobile() ? 'uk-top--m' : 'uk-top--d' ) . '"><div class="vr-ad-strip__inner">';
	echo '<div class="wpap-ad wpap-ad-zone-header"></div>';
	if ( $fb ) {
		echo '<a class="uk-top-fb" href="' . esc_url( get_permalink( $fb ) ) . '"><span>Most read</span><strong>' . esc_html( get_the_title( $fb ) ) . '</strong><em>Read now &rarr;</em></a>';
	}
	echo '</div></div>';
}

/* Site Kit's AdSense snippet loads the very same adsbygoogle.js a second time: block it when the plugin's
   loader is the one in use. */
add_filter( 'googlesitekit_adsense_tag_blocked', function ( $blocked ) {
	return '' !== uk_ad_client() ? true : $blocked;
} );

/* ---------- AdSense loader: deferred, so ads never race the content for first-paint bandwidth ---------- */
add_action( 'init', function () { remove_action( 'wp_head', 'wpap_ads_head', 8 ); }, 20 );
add_action( 'wp_head', function () {
	if ( is_admin() || is_feed() ) { return; }
	$client = uk_ad_client();
	if ( '' === $client ) { return; }
	echo '<link rel="preconnect" href="https://pagead2.googlesyndication.com" crossorigin>' . "\n";
	echo '<script>addEventListener("DOMContentLoaded",function(){setTimeout(function(){var s=document.createElement("script");s.async=true;s.crossOrigin="anonymous";s.src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . esc_js( $client ) . '";document.head.appendChild(s);},2500);});</script>' . "\n";
}, 7 );

/* ---------- In-feed ads: one after every 4 article cards on lists (inert while ads are disabled) ---------- */
define( 'UK_FEED_EVERY', 4 );
define( 'UK_FEED_MAX', 3 );

add_action( 'the_post', function ( $post, $query ) {
	static $counts = array();
	if ( is_admin() || is_singular() ) { return; }
	if ( ! ( is_home() || is_front_page() || is_archive() || is_search() ) ) { return; }
	if ( 'post' !== $post->post_type ) { return; }
	$key = spl_object_hash( $query );
	$counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1;
	$n = $counts[ $key ];
	$max = wp_is_mobile() ? UK_FEED_MAX : 1;
	if ( $n <= 1 || 0 !== ( $n - 1 ) % UK_FEED_EVERY || ( $n - 1 ) / UK_FEED_EVERY > $max ) { return; }
	$client = uk_ad_client();
	if ( '' === $client ) { return; }
	echo '<div class="uk-feed-ad"><div class="wpap-ad wpap-ad-feed"></div></div>';
}, 10, 2 );

/* ---------- Visitor statistics (Histats) ----------
   Counter in the footer, loaded after the page so it never competes with the article. Front end only;
   logged-in editors are skipped. Set HISTATS_ID in the environment; unset/"off" = nothing loaded. */
add_action( 'wp_footer', function () {
	$id = trim( (string) getenv( 'HISTATS_ID' ) );
	if ( '' === $id || 'off' === $id || ! ctype_digit( $id ) || is_admin() || is_user_logged_in() ) { return; }
	echo "<script>var _Hasync=_Hasync||[];_Hasync.push(['Histats.start','1," . esc_js( $id ) . ",4,0,0,0,00010000']);_Hasync.push(['Histats.fasi','1']);_Hasync.push(['Histats.track_hits','']);addEventListener('load',function(){setTimeout(function(){var hs=document.createElement('script');hs.async=true;hs.src='//s10.histats.com/js15_as.js';(document.head||document.body).appendChild(hs);},1200);});</script>"
		. '<noscript><img src="//sstatic1.histats.com/0.gif?' . esc_attr( $id ) . '&amp;101" alt="" width="1" height="1" style="position:absolute;left:-9999px"></noscript>' . "\n";
}, 50 );

/* ---------- Organization schema ----------
   The plugin's JSON-LD graph has a minimal Organization (@id home#organization). Emitting the same @id
   here MERGES with it for crawlers, adding the logo. (No sameAs yet — no social Page exists
   for the brand; add the Page URL here when one is created.) */
add_action( 'wp_head', function () {
	if ( is_admin() || is_feed() ) { return; }
	$home = home_url( '/' );
	$org  = array(
		'@context' => 'https://schema.org',
		'@type'    => 'Organization',
		'@id'      => $home . '#organization',
		'name'     => get_bloginfo( 'name' ),
		'url'      => $home,
		'logo'     => array( '@type' => 'ImageObject', 'url' => uk_asset( 'brand/storyline-uk-icon-512.png' ), 'width' => 512, 'height' => 512 ),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON-encoded
}, 24 );

/* ---------- Home intro (brand voice) ----------
   One compact, server-rendered strip on the first home page. Printed from header.php, under the header ad. */
function uk_home_intro() {
	if ( ! is_front_page() || is_paged() ) { return; }
	echo '<section class="uk-intro" aria-label="About this site"><div class="vr-container uk-intro__in">';
	echo '<img class="uk-intro__img" src="' . esc_url( uk_asset( 'brand/icon-192.webp' ) ) . '" alt="" width="64" height="64" loading="lazy" decoding="async">';
	echo '<div class="uk-intro__t"><p class="uk-intro__h">' . esc_html( get_bloginfo( 'name' ) ) . '</p>';
	echo '<p class="uk-intro__s">True crime, survival stories, unsolved mysteries, incredible people, historical stories and strange-but-true events — told in full, for people who love to read. <a href="' . esc_url( home_url( '/about/' ) ) . '">More about us</a>.</p></div>';
	echo '</div></section>';
}

/* ---------- AdSense ad-blocking recovery (Google Funding Choices) — inert while ads are disabled ---------- */
add_action( 'wp_head', function () {
	$client = uk_ad_client();
	if ( '' === $client || is_admin() ) { return; }
	$pub = str_replace( 'ca-', '', $client );
	echo '<script>addEventListener("load",function(){setTimeout(function(){var s=document.createElement("script");s.async=true;s.src="https://fundingchoicesmessages.google.com/i/' . esc_js( $pub ) . '?ers=1";document.head.appendChild(s);},2500);});</script>' . "\n";
	echo "<script>(function(){function signalGooglefcPresent(){if(!window.frames['googlefcPresent']){if(document.body){var f=document.createElement('iframe');f.style='width:0;height:0;border:none;z-index:-1000;left:-1000px;top:-1000px;';f.style.display='none';f.name='googlefcPresent';document.body.appendChild(f);}else{setTimeout(signalGooglefcPresent,0);}}}signalGooglefcPresent();})();</script>\n";
}, 3 );

/* ================= Speed layer ================= */

/* ---------- Hero: srcset ---------- */
function uk_hero_srcset() {
	return uk_asset( 'brand/hero-640.webp' ) . ' 640w, ' . uk_asset( 'brand/hero-768.webp' ) . ' 768w, ' . uk_asset( 'brand/hero-960.webp' ) . ' 960w, ' . uk_asset( 'brand/hero-1280.webp' ) . ' 1280w';
}
add_action( 'template_redirect', function () {
	if ( ! is_front_page() || is_paged() ) { return; }
	ob_start( function ( $html ) {
		$needle = 'src="' . esc_url( uk_asset( 'brand/hero-1280.webp' ) ) . '"';
		if ( false === strpos( $html, $needle ) ) { return $html; }
		return preg_replace(
			'#<img class="home-hero__img"( src="' . preg_quote( esc_url( uk_asset( 'brand/hero-1280.webp' ) ), '#' ) . '")#',
			'<img class="home-hero__img" width="1280" height="720" srcset="' . esc_attr( uk_hero_srcset() ) . '" sizes="' . esc_attr( UK_HERO_SIZES ) . '"$1',
			$html,
			1
		);
	} );
}, 0 );

/* ---------- Hardening CSS: inline, not a blocking <link> ---------- */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! wp_style_is( 'viral-reader-hardening', 'enqueued' ) ) { return; }
	$file = get_template_directory() . '/assets/css/hardening.css';
	if ( ! is_readable( $file ) ) { return; }
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- bundled theme file.
	$css = uk_minify_css( (string) file_get_contents( $file ) );
	if ( '' === $css || strlen( $css ) > 40000 ) { return; }   /* fall back to the normal <link> */
	wp_add_inline_style( 'viral-reader', $css );
	wp_dequeue_style( 'viral-reader-hardening' );
}, 12 );

/* ---------- Logo: WebP, and the light-on-dark variant only in dark mode ---------- */
add_filter( 'get_custom_logo', function ( $html ) {
	$img = '<img width="559" height="150" src="' . esc_url( uk_asset( 'brand/storyline-uk-logo.webp' ) ) . '" class="custom-logo" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '" decoding="async" />';
	$pic = '<picture><source media="(prefers-color-scheme:dark)" srcset="' . esc_url( uk_asset( 'brand/storyline-uk-logo-light.webp' ) ) . '">' . $img . '</picture>';
	return preg_replace( '#<img\b[^>]*>#', $pic, $html, 1 );
} );

/* ---------- Article card thumbnails ---------- */
add_action( 'after_setup_theme', function () {
	add_image_size( 'uk-card', 480, 9999, false );
}, 20 );
add_filter( 'post_thumbnail_size', function ( $size ) {
	return ( 'medium_large' === $size && ! is_singular() && ! is_admin() ) ? 'uk-card' : $size;
} );
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $att, $size ) {
	if ( 'uk-card' === $size ) { $attr['sizes'] = '(max-width:640px) calc(100vw - 32px), 380px'; }
	return $attr;
}, 10, 3 );
add_filter( 'image_editor_output_format', function ( $formats ) {
	$formats['image/jpeg'] = 'image/webp';
	/* PNG uploads (brand mark, screenshots) otherwise keep PNG sub-sizes: the 512 brand icon's
	   300px sub-size is the author avatar shown at 40/72px on every article, and as PNG it weighs
	   several times its WebP equivalent. Originals stay untouched (schema/site-icon source). */
	$formats['image/png'] = 'image/webp';
	return $formats;
} );

/* Site-icon tags: after the PNG→WebP sub-size conversion the generated 180px icon would be WebP,
   which iOS ignores for apple-touch-icon. Pin the touch icon and the 32px favicon to the brand
   pack's purpose-built PNGs; the 192px icon may stay a WebP sub-size (fine in modern browsers). */
add_filter( 'site_icon_meta_tags', function ( $tags ) {
	foreach ( $tags as $i => $tag ) {
		if ( false !== strpos( $tag, 'apple-touch-icon' ) ) {
			$tags[ $i ] = '<link rel="apple-touch-icon" href="' . esc_url( uk_asset( 'brand/apple-touch-180.png' ) ) . '" />';
		} elseif ( false !== strpos( $tag, 'sizes="32x32"' ) ) {
			$tags[ $i ] = '<link rel="icon" href="' . esc_url( uk_asset( 'brand/favicon-32.png' ) ) . '" sizes="32x32" />';
		}
	}
	return $tags;
} );

/* YouTube embeds ([embed] in article bodies): the oEmbed iframe is heavy (player JS/CSS) and sits
   below the featured image, so load it lazily instead of competing with the article's first paint. */
add_filter( 'embed_oembed_html', function ( $html ) {
	if ( false !== strpos( $html, '<iframe' ) && false === strpos( $html, 'loading=' ) ) {
		$html = str_replace( '<iframe ', '<iframe loading="lazy" ', $html );
	}
	return $html;
} );

/* ---------- Home topic tiles: load cover photos only when scrolled into view ---------- */
add_action( 'template_redirect', function () {
	if ( ! is_front_page() || is_paged() ) { return; }
	ob_start( function ( $html ) {
		$html = preg_replace( '#(<a class="topic-tile[^"]*" href="[^"]*") style="--cover:url\(\x27([^\x27]+)\x27\)"#', '$1 data-cover="$2"', $html );
		$js   = "<script>(function(){var t=document.querySelectorAll('.topic-tile[data-cover]');if(!t.length)return;function s(e){e.style.setProperty('--cover',\"url('\"+e.getAttribute('data-cover')+\"')\");e.removeAttribute('data-cover');}if(!('IntersectionObserver' in window)){t.forEach(s);return;}var o=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){s(e.target);o.unobserve(e.target);}});},{rootMargin:'300px 0px'});t.forEach(function(e){o.observe(e);});})();</script>";
		return str_replace( '</body>', $js . '</body>', $html );
	} );
}, 1 );

/* ---------- No WordPress emoji script (phones render emoji natively; saves a request) ---------- */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );

/* ---------- Article pages: lazy-load the ads further down (inert while ads are disabled) ---------- */
define( 'UK_EAGER_ADS', 2 );
add_action( 'template_redirect', function () {
	if ( ! is_singular( 'post' ) ) { return; }
	ob_start( function ( $html ) {
		$push  = '#(<ins class="adsbygoogle")([^>]*></ins>)\s*<script>\s*\(adsbygoogle\s*=\s*window\.adsbygoogle\s*\|\|\s*\[\]\)\.push\(\{\}\);\s*</script>#';
		$count = 0;
		$html  = preg_replace_callback( $push, function ( $m ) use ( &$count ) {
			$count++;
			if ( $count <= UK_EAGER_ADS ) { return $m[0]; }
			return $m[1] . ' data-uk-lazy="1"' . $m[2];
		}, $html );
		if ( $count <= UK_EAGER_ADS ) { return $html; }
		$js = "<script>(function(){var l=document.querySelectorAll('ins.adsbygoogle[data-uk-lazy]');if(!l.length)return;function go(e){if(!e.hasAttribute('data-uk-lazy'))return;e.removeAttribute('data-uk-lazy');(window.adsbygoogle=window.adsbygoogle||[]).push({});}if(!('IntersectionObserver' in window)){l.forEach(go);return;}var o=new IntersectionObserver(function(es){es.forEach(function(x){if(x.isIntersecting){o.unobserve(x.target);go(x.target);}});},{rootMargin:'600px 0px'});l.forEach(function(e){o.observe(e);});})();</script>";
		return str_replace( '</body>', $js . '</body>', $html );
	} );
}, 2 );
