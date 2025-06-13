/**
 * @file admin/js/onboarding-pointers.js
 *
 * Nuclear Engagement – onboarding pointers
 * Handles per-screen WP Pointer display & dismissal.
 */
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

               const wrapper = document.createElement( 'div' );
               wrapper.className = 'wp-pointer pointer-' + ptr.position.edge;
               wrapper.style.position = 'absolute';
               wrapper.innerHTML =
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
               close.addEventListener( 'click', function ( e ) {
                       e.preventDefault();
                       const params = new URLSearchParams( {
                               action  : 'nuclen_dismiss_pointer',
                               pointer : ptr.id,
                       } );
                       if ( nonce ) {
                               params.append( 'nonce', nonce );
                       }
                       fetch( ajaxurl, {
                               method      : 'POST',
                               credentials : 'same-origin',
                               headers     : { 'Content-Type': 'application/x-www-form-urlencoded' },
                               body        : params.toString(),
                       } );

                       wrapper.remove();
                       index++;
                       showNext();
               } );
       }

       document.addEventListener( 'DOMContentLoaded', showNext );
} )();
