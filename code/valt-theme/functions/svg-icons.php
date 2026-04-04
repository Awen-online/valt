<?php
/**
 * SVG icon helpers for Valt.
 * Inline SVGs — no external requests, cacheable, color-inheritable.
 */

/**
 * Valt triskelion vault-door logo mark.
 */
function valt_svg_logo( int $size = 40 ): string {
	return '<svg class="valt-logo-mark" width="' . $size . '" height="' . $size . '" viewBox="0 0 200 200" fill="none">
		<!-- Outer vinyl edge -->
		<circle cx="100" cy="100" r="90" stroke="#E8C48B" stroke-width="4" fill="none"/>
		<!-- Record grooves -->
		<circle cx="100" cy="100" r="78" stroke="#E8C48B" stroke-width="0.7" opacity="0.18" fill="none"/>
		<circle cx="100" cy="100" r="68" stroke="#E8C48B" stroke-width="0.7" opacity="0.15" fill="none"/>
		<circle cx="100" cy="100" r="58" stroke="#E8C48B" stroke-width="0.7" opacity="0.12" fill="none"/>
		<circle cx="100" cy="100" r="48" stroke="#E8C48B" stroke-width="0.7" opacity="0.10" fill="none"/>
		<circle cx="100" cy="100" r="38" stroke="#E8C48B" stroke-width="0.7" opacity="0.08" fill="none"/>
		<!-- Label area ring -->
		<circle cx="100" cy="100" r="28" stroke="#E8C48B" stroke-width="1.5" opacity="0.25" fill="none"/>
		<!-- Short vault-handle spokes -->
		<line x1="100" y1="100" x2="100" y2="150" stroke="#E8C48B" stroke-width="5" stroke-linecap="round"/>
		<line x1="100" y1="100" x2="58" y2="72" stroke="#C9A66B" stroke-width="5" stroke-linecap="round"/>
		<line x1="100" y1="100" x2="148" y2="64" stroke="#C9A66B" stroke-width="5" stroke-linecap="round"/>
		<!-- Spoke end nodes -->
		<circle cx="100" cy="152" r="6" fill="#E8C48B"/>
		<circle cx="56" cy="71" r="5" fill="#C9A66B"/>
		<circle cx="150" cy="63" r="5" fill="#C9A66B"/>
		<!-- Center spindle hole -->
		<circle cx="100" cy="100" r="8" fill="#3D3C56" stroke="#E8C48B" stroke-width="2.5"/>
		<circle cx="100" cy="100" r="3" fill="#E8C48B" opacity="0.4"/>
	</svg>';
}

function valt_svg_wallet( int $size = 24 ): string {
	return '<svg class="valt-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/>
		<path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/>
		<path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>
	</svg>';
}

function valt_svg_collection( int $size = 24 ): string {
	return '<svg class="valt-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<rect x="3" y="3" width="7" height="7" rx="1"/>
		<rect x="14" y="3" width="7" height="7" rx="1"/>
		<rect x="3" y="14" width="7" height="7" rx="1"/>
		<rect x="14" y="14" width="7" height="7" rx="1"/>
	</svg>';
}

function valt_svg_music( int $size = 24 ): string {
	return '<svg class="valt-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<path d="M9 18V5l12-2v13"/>
		<circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
	</svg>';
}

function valt_svg_user( int $size = 24 ): string {
	return '<svg class="valt-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
		<circle cx="12" cy="7" r="4"/>
	</svg>';
}

function valt_svg_search( int $size = 24 ): string {
	return '<svg class="valt-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
	</svg>';
}

function valt_svg_trophy( int $size = 24 ): string {
	return '<svg class="valt-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
		<path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20 7 22"/>
		<path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20 17 22"/>
		<path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
	</svg>';
}

function valt_svg_chevron_down( int $size = 16 ): string {
	return '<svg class="valt-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<path d="m6 9 6 6 6-6"/>
	</svg>';
}

function valt_svg_external( int $size = 14 ): string {
	return '<svg class="valt-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
	</svg>';
}
