// nuclen-admin-onboarding.ts

// 1) Declare the global shape of window.nePointerData and jQuery.
declare global {
  interface Window {
    nePointerData?: NuclenPointerData;
    jQuery: any; // or better types if you prefer
  }
}

// 2) Describe the structure of the pointer data
export interface NuclenPointerData {
  pointers: NuclenPointer[];
  ajaxurl: string;
  nonce?: string; // Add the nonce
}

// 3) Each pointer has the properties your PHP code provides
export interface NuclenPointer {
  id: string;
  target: string;
  title: string;
  content: string;
  position: {
    edge: 'top' | 'bottom' | 'left' | 'right';
    align: 'top' | 'bottom' | 'left' | 'right' | 'center';
  };
}

// Wrap our logic in an IIFE
(function($: any) {
  // Wait for DOM ready
  $(document).ready(() => {
    const pointerData = window.nePointerData;

    // Check that pointerData exists and has pointers
    if (!pointerData || !pointerData.pointers || pointerData.pointers.length === 0) {
      return;
    }

    let currentIndex = 0;
    const pointers = pointerData.pointers;
    const ajaxurl = pointerData.ajaxurl;
    const nonce = pointerData.nonce; // We'll send this in our AJAX

    function nuclenShowNextPointer() {
      if (currentIndex >= pointers.length) {
        return;
      }

      const ptr = pointers[currentIndex];
      const $target = $(ptr.target);

      if (!$target.length) {
        currentIndex++;
        nuclenShowNextPointer();
        return;
      }

      // Initialize WP pointer
      $target.pointer({
        content: `<h3>${ptr.title}</h3><p>${ptr.content}</p>`,
        position: ptr.position,
        close: () => {
          // Call our custom AJAX action to dismiss pointer
          $.post(ajaxurl, {
            action: 'nuclen_dismiss_pointer',
            pointer: ptr.id,
            nonce: nonce // Pass the pointer nonce
          });
          currentIndex++;
          nuclenShowNextPointer();
        }
      }).pointer('open');
    }

    // Start the pointer sequence
    nuclenShowNextPointer();
  });
})(window.jQuery);
