/**
 * @file admin/js/onboarding-pointers.js
 *
 * Nuclear Engagement – onboarding pointers
 * Handles per-screen WP Pointer display & dismissal.
 */

function nuclenLog() {
    console.log.apply(console, arguments);
}

function nuclenWarn() {
    console.warn.apply(console, arguments);
}

function nuclenError() {
    console.error.apply(console, arguments);
}

async function nuclenFetchWithRetry(url, options, maxRetries = 3, initialDelay = 500) {
    let attempt = 0;
    let delay = initialDelay;
    let lastError;
    
    while (attempt <= maxRetries) {
        try {
            const response = await fetch(url, options);
            const { status, ok } = response;
            const text = await response.text().catch(() => "");
            const data = text ? (() => {
                try {
                    return JSON.parse(text);
                } catch {
                    return null;
                }
            })() : null;
            
            if (ok) {
                return { ok: true, status: status, data: data };
            }
            
            return { ok: false, status: status, data: data, error: text };
            
        } catch (error) {
            lastError = error;
            
            if (attempt === maxRetries) {
                break;
            }
            
            nuclenWarn(
                `Retrying request to ${url} with method ${options.method || "GET"} (${maxRetries - attempt} attempts left). Error: ${lastError.message}`,
                lastError
            );
            
            await new Promise(resolve => setTimeout(resolve, delay));
            delay *= 2;
        }
        
        attempt += 1;
    }
    
    nuclenError(
        `Max retries reached for ${url} with method ${options.method || "GET"}:`,
        lastError
    );
    
    throw lastError;
}

function displayError(message) {
    const toast = document.createElement('div');
    toast.className = 'nuclen-error-toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
    console.error(message);
}

(function() {
    if (typeof window.nePointerData === 'undefined') {
        return;
    }
    
    const { pointers, ajaxurl, nonce } = window.nePointerData;
    
    if (!Array.isArray(pointers) || !pointers.length) {
        return;
    }
    
    let index = 0;
    
    function showNext() {
        if (index >= pointers.length) {
            return;
        }
        
        const ptr = pointers[index];
        const target = document.querySelector(ptr.target);
        
        if (!target) {
            index++;
            showNext();
            return;
        }
        
        const wrapper = document.createElement('div');
        wrapper.className = 'wp-pointer pointer-' + ptr.position.edge;
        wrapper.style.position = 'absolute';
        wrapper.innerHTML =
            '<div class="wp-pointer-content"><h3>' +
            ptr.title +
            '</h3><p>' +
            ptr.content +
            '</p><a class="close" href="#">Dismiss</a></div>';
        
        document.body.appendChild(wrapper);
        
        const rect = target.getBoundingClientRect();
        let top = window.scrollY + rect.top;
        let left = window.scrollX + rect.left;
        
        switch (ptr.position.edge) {
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
        
        if (ptr.position.align === 'center') {
            if (ptr.position.edge === 'top' || ptr.position.edge === 'bottom') {
                left += (rect.width - wrapper.offsetWidth) / 2;
            } else {
                top += (rect.height - wrapper.offsetHeight) / 2;
            }
        } else if (ptr.position.align === 'right' || ptr.position.align === 'bottom') {
            if (ptr.position.edge === 'top' || ptr.position.edge === 'bottom') {
                left += rect.width - wrapper.offsetWidth;
            } else {
                top += rect.height - wrapper.offsetHeight;
            }
        }
        
        wrapper.style.top = Math.max(top, 0) + 'px';
        wrapper.style.left = Math.max(left, 0) + 'px';
        
        const close = wrapper.querySelector('.close');
        if (close) {
            close.addEventListener('click', function(e) {
                e.preventDefault();
                
                const params = new URLSearchParams({
                    action: 'nuclen_dismiss_pointer',
                    pointer: ptr.id,
                });
                
                if (nonce) {
                    params.append('nonce', nonce);
                }
                
                (async () => {
                    try {
                        const response = await nuclenFetchWithRetry(
                            ajaxurl,
                            {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: params.toString(),
                            }
                        );
                        
                        if (!response.ok) {
                            nuclenError('Failed to dismiss pointer:', response.error);
                            displayError('Failed to dismiss pointer.');
                        }
                    } catch (err) {
                        nuclenError('Error dismissing pointer:', err);
                        displayError('Network error while dismissing pointer.');
                    }
                    
                    wrapper.remove();
                    index++;
                    showNext();
                })();
            });
        }
    }
    
    document.addEventListener('DOMContentLoaded', showNext);
})();