/**
 * @file admin/js/onboarding-pointers.js
 *
 * Nuclear Engagement – onboarding pointers
 * Handles per-screen WP Pointer display & dismissal.
 */
function nuclenLog(){
	console.log.apply( console,arguments );}function nuclenWarn(){
	console.warn.apply( console,arguments );}function nuclenError(){
		console.error.apply( console,arguments );}async function nuclenFetchWithRetry(e,t,n=3,r=500){
		let o = 0,a = r,s;for (;o <= n;) {
			try {
				const l = await fetch( e,t ),{status:c,ok:i} = l,d = await l.text().catch( () => "" ),u = d ? (() => {try {
						return JSON.parse( d )} catch {}})() :null;if (i) {
						returnok : ! 0,status :c,data :u};return{ok : ! 1,status :c,data :u,error :d}} catch (l) {
				if (s = l,o === n) {
					break;
				}nuclenWarn( `Retrying request to ${e} with method ${t.method || "GET"} (${n - o} attempts left). Error : ${s.message}`,s ),await new Promise( m => setTimeout( m,a ) ),a *= 2}o += 1}nuclenError(
					`Max retries reached for ${
							e} with method ${t.method || "GET"} :`,
					s
				);throw s}function displayError(m){
			const t = document.createElement( 'div' );t.className = 'nuclen-error-toast';t.textContent = m;document.body.appendChild( t );setTimeout( () => t.remove(),5000 );console.error( m );}
						( function () {
							if ( typeof window.nePointerData === 'undefined' ) {
									return;
							}

								const { pointers, ajaxurl, nonce } = window.nePointerData;
							if ( ! Array.isArray( pointers ) || ! pointers.length ) {
									return;
							}

								let index = 0;

							function showNext() {
								if ( index >= pointers.length ) {
										return;
								}

									const ptr    = pointers[ index ];
									const target = document.querySelector( ptr.target );
								if ( ! target ) {
									index++;
									showNext();
									return;
								}

									const wrapper          = document.createElement( 'div' );
									wrapper.className      = 'wp-pointer pointer-' + ptr.position.edge;
									wrapper.style.position = 'absolute';
									wrapper.innerHTML      =
									'<div class="wp-pointer-content"><h3>' +
									ptr.title +
									'</h3><p>' +
									ptr.content +
									'</p><a class="close" href="#">Dismiss</a></div>';
									document.body.appendChild( wrapper );

									const rect = target.getBoundingClientRect();
									let top    = window.scrollY + rect.top;
									let left   = window.scrollX + rect.left;

								switch ( ptr.position.edge ) {
									case 'top':
										top -= wrapper.offsetHeight;
											break;
									case 'bottom':
											top += rect.height;
											break;
									case 'left':
											left -= wrapper.offsetWidth;
											break;
									case 'right':
											left += rect.width;
											break;
								}

								if ( ptr.position.align === 'center' ) {
									if ( ptr.position.edge === 'top' || ptr.position.edge === 'bottom' ) {
											left += ( rect.width - wrapper.offsetWidth ) / 2;
									} else {
											top += ( rect.height - wrapper.offsetHeight ) / 2;
									}
								} else if ( ptr.position.align === 'right' || ptr.position.align === 'bottom' ) {
									if ( ptr.position.edge === 'top' || ptr.position.edge === 'bottom' ) {
											left += rect.width - wrapper.offsetWidth;
									} else {
											top += rect.height - wrapper.offsetHeight;
									}
								}

								wrapper.style.top  = Math.max( top, 0 ) + 'px';
								wrapper.style.left = Math.max( left, 0 ) + 'px';

								const close = wrapper.querySelector( '.close' );
								close.addEventListener(
									'click',
									function ( e ) {
										e.preventDefault();
										const params = new URLSearchParams(
											{
												action  : 'nuclen_dismiss_pointer',
												pointer : ptr.id,
											}
										);
										if ( nonce ) {
												params.append( 'nonce', nonce );
										}
												( async() => {
													try {
															const r = await nuclenFetchWithRetry(
																ajaxurl,
																{
																	method      : 'POST',
																	credentials : 'same-origin',
																	headers     : { 'Content-Type': 'application/x-www-form-urlencoded' },
																	body        : params.toString(),
																}
															);
														if ( ! r.ok ) {
															nuclenError( 'Failed to dismiss pointer:', r.error );
															displayError( 'Failed to dismiss pointer.' );
														}
													} catch ( err ) {
															nuclenError( 'Error dismissing pointer:', err );
															displayError( 'Network error while dismissing pointer.' );
													}

														wrapper.remove();
														index++;
														showNext();
												} )();
									}
								);
							}

								document.addEventListener( 'DOMContentLoaded', showNext );
						} )();
