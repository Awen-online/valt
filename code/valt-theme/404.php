<?php
/**
 * 404 template — "Empty Vault".
 *
 * Brand-themed not-found page: an open, hollow vault door. Uses the same
 * self-contained document shell as single-song.php (nav + footer chrome).
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
	<style>
		.valt-404 { flex: 1 0 auto; display: flex; align-items: center; justify-content: center; padding: 4rem 0; }
		.valt-404__inner { text-align: center; max-width: 560px; }
		.valt-404__vault { width: 240px; max-width: 70%; height: auto; margin: 0 auto 1.75rem; display: block; animation: v404-float 6s ease-in-out infinite; }
		.valt-404__code { font-family: var(--valt-font); font-weight: 800; letter-spacing: .35em; font-size: var(--valt-fs-small); color: var(--valt-mid); margin: 0 0 .5rem; text-transform: uppercase; padding-left: .35em; }
		.valt-404__title { font-family: var(--valt-font); font-size: var(--valt-fs-h1); color: var(--valt-gold); margin: 0 0 .75rem; }
		.valt-404__text { color: var(--valt-tan); font-size: var(--valt-fs-body); line-height: 1.6; margin: 0 auto 2rem; max-width: 42ch; }
		.valt-404__cta { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }

		/* Vault SVG */
		.v404-wall      { fill: var(--valt-dark); stroke: var(--valt-brown); stroke-width: 2; }
		.v404-opening   { fill: var(--valt-darker); stroke: var(--valt-gold-dark); stroke-width: 6; }
		.v404-rim       { fill: none; stroke: var(--valt-brown); stroke-width: 2; }
		.v404-void      { fill: var(--valt-darker); }
		.v404-bolt      { fill: var(--valt-gold-dark); }
		.v404-hinge     { fill: var(--valt-gold-dark); }
		.v404-door      { fill: var(--valt-dark); stroke: var(--valt-gold); stroke-width: 4; }
		.v404-door-edge { fill: none; stroke: var(--valt-gold-dark); stroke-width: 2; }
		.v404-wheel     { fill: none; stroke: var(--valt-gold); stroke-width: 3; transform-box: fill-box; transform-origin: center; animation: v404-spin 22s linear infinite; }
		.v404-hub       { fill: var(--valt-gold); }

		@keyframes v404-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
		@keyframes v404-spin  { to { transform: rotate(360deg); } }
		@media (prefers-reduced-motion: reduce) {
			.valt-404__vault, .v404-wheel { animation: none; }
		}
	</style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="valt-site">
	<?php valt_render_nav(); ?>

	<main class="valt-404">
		<div class="valt-container valt-404__inner">

			<svg class="valt-404__vault" viewBox="0 0 240 200" role="img" aria-label="An open, empty vault">
				<!-- vault wall -->
				<rect class="v404-wall" x="20" y="20" width="170" height="160" rx="16" />
				<!-- round opening (the empty interior) -->
				<circle class="v404-opening" cx="105" cy="100" r="70" />
				<circle class="v404-void"    cx="105" cy="100" r="64" />
				<circle class="v404-rim"     cx="105" cy="100" r="52" />
				<!-- bolts around the rim -->
				<circle class="v404-bolt" cx="158.7" cy="69"  r="4" />
				<circle class="v404-bolt" cx="105"   cy="38"  r="4" />
				<circle class="v404-bolt" cx="51.3"  cy="69"  r="4" />
				<circle class="v404-bolt" cx="51.3"  cy="131" r="4" />
				<circle class="v404-bolt" cx="105"   cy="162" r="4" />
				<circle class="v404-bolt" cx="158.7" cy="131" r="4" />
				<!-- hinges joining the swung-open door on the right -->
				<rect class="v404-hinge" x="170" y="74"  width="22" height="9" rx="3" />
				<rect class="v404-hinge" x="170" y="117" width="22" height="9" rx="3" />
				<!-- the heavy round door, swung open to the right -->
				<ellipse class="v404-door"      cx="205" cy="100" rx="20" ry="68" />
				<ellipse class="v404-door-edge" cx="205" cy="100" rx="12" ry="58" />
				<!-- spinning handle wheel on the door -->
				<g class="v404-wheel">
					<circle cx="205" cy="100" r="13" />
					<line x1="205" y1="84"  x2="205" y2="116" />
					<line x1="189" y1="100" x2="221" y2="100" />
					<line x1="194" y1="89"  x2="216" y2="111" />
					<line x1="216" y1="89"  x2="194" y2="111" />
				</g>
				<circle class="v404-hub" cx="205" cy="100" r="3.5" />
			</svg>

			<p class="valt-404__code">Error 404</p>
			<h1 class="valt-404__title">This Valt is empty</h1>
			<p class="valt-404__text">The track, artist, or treasure you were looking for isn&rsquo;t in this vault. It may have been moved, never minted, or never existed.</p>

			<div class="valt-404__cta">
				<a class="valt-btn valt-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to Home</a>
				<a class="valt-btn valt-btn--secondary" href="<?php echo esc_url( home_url( '/discover/' ) ); ?>">Discover Artists</a>
			</div>

		</div>
	</main>

	<?php valt_render_footer(); ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
