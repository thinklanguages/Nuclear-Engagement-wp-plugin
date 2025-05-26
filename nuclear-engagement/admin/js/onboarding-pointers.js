/**
 * @file admin/js/onboarding-pointers.js
 *
 * Nuclear Engagement – onboarding pointers
 * Handles per-screen WP Pointer display & dismissal.
 */
jQuery( function ( $ ) {
	if ( typeof window.nePointerData === 'undefined' ) {
		return;
	}

	const { pointers, ajaxurl, nonce } = window.nePointerData;
	if ( ! Array.isArray( pointers ) || ! pointers.length ) {
		return;
	}

	pointers.forEach( ( p ) => {
		$( p.target ).pointer( {
			content  : `<h3>${ p.title }</h3><p>${ p.content }</p>`,
			position : p.position,
			close    : function () {
				// Persist dismissal
				wp.ajax.post( 'nuclen_dismiss_pointer', {
					pointer  : p.id,
					nonce,
				} );
			},
		} ).pointer( 'open' );
	} );
} );
