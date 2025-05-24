/* ---------- Nuclen TOC – public JS ----------
File: modules/toc/assets/js/nuclen-toc-front.js

Loaded only when toggle or scroll-spy is active.
Expects nuclenTocL10n.hide / .show strings injected by wp_localize_script.
------------------------------------------------- */

// Sticky TOC functionality
function initStickyToc() {
    const stickyTocs = document.querySelectorAll('.nuclen-toc-sticky');
    
    if (!stickyTocs.length) return;

    stickyTocs.forEach((tocWrapper) => {
        const toc = tocWrapper.querySelector('.nuclen-toc');
        if (!toc) return;

        // Create a placeholder to maintain the same space when TOC becomes sticky
        const placeholder = document.createElement('div');
        placeholder.className = 'nuclen-toc-placeholder';
        placeholder.style.display = 'none';
        placeholder.style.width = `${tocWrapper.offsetWidth}px`;
        placeholder.style.height = `${tocWrapper.offsetHeight}px`;
        tocWrapper.parentNode.insertBefore(placeholder, tocWrapper);

        // Store original position and dimensions
        const originalPosition = tocWrapper.getBoundingClientRect().top + window.pageYOffset;
        const originalLeft = tocWrapper.getBoundingClientRect().left;
        let originalWidth = toc.offsetWidth;
        const headerHeight = 20; // Should match the top value in CSS
        let isStuck = false;
        let rafId = null;
        let resizeObserver = null;

        function updateStickyState() {
            if (rafId) {
                cancelAnimationFrame(rafId);
            }
            
            rafId = requestAnimationFrame(() => {
                const scrollPosition = window.scrollY || window.pageYOffset;
                const viewportHeight = window.innerHeight;
                const viewportWidth = window.innerWidth;
                
                // Get current position relative to viewport
                const wrapperRect = tocWrapper.getBoundingClientRect();
                
                // Calculate if we should be stuck - when the top of the TOC hits the top of the viewport
                const shouldBeStuck = wrapperRect.top <= 20; // 20px offset from top
                
                // Update original position if needed (in case of dynamic content loading)
                if (scrollPosition <= 0) {
                    originalPosition = wrapperRect.top + scrollPosition;
                }
                
                if (shouldBeStuck && !isStuck) {
                    // About to become sticky
                    isStuck = true;
                    tocWrapper.classList.add('nuclen-toc-stuck');
                    placeholder.style.display = 'block';
                    placeholder.style.height = `${tocWrapper.offsetHeight}px`;
                    placeholder.style.width = `${originalWidth}px`;
                } else if (!shouldBeStuck && isStuck) {
                    // About to unstick
                    isStuck = false;
                    tocWrapper.classList.remove('nuclen-toc-stuck');
                    placeholder.style.display = 'none';
                    tocWrapper.style.position = '';
                    tocWrapper.style.top = '';
                    tocWrapper.style.left = '';
                    tocWrapper.style.maxHeight = '';
                    return;
                }
                
                if (isStuck) {
                    // Update position and dimensions when stuck
                    tocWrapper.style.position = 'fixed';
                    tocWrapper.style.top = '20px';
                    
                    // Calculate left position - use original left or viewport edge
                    const leftOffset = 20;
                    let contentLeft = originalLeft;
                    
                    // If content is centered, align with it
                    const contentContainer = document.querySelector('.entry-content, .post, .content-area, .site-main, main');
                    if (contentContainer) {
                        const containerRect = contentContainer.getBoundingClientRect();
                        if (containerRect.left > 0) {
                            contentLeft = containerRect.left;
                        }
                    }
                    
                    // Ensure we don't go beyond viewport edges
                    const leftPosition = Math.max(leftOffset, Math.min(contentLeft, viewportWidth - originalWidth - 20));
                    tocWrapper.style.left = `${leftPosition}px`;
                    
                    // Set max height with some padding
                    const maxHeight = viewportHeight - 40; // 20px top + 20px bottom
                    tocWrapper.style.maxHeight = `${maxHeight}px`;
                    
                    // Ensure consistent width
                    if (toc.style.width !== `${originalWidth}px`) {
                        toc.style.width = `${originalWidth}px`;
                    }
                }
            });

            if (scrollPosition > originalPosition && !isStuck) {
                // Save current scroll position
                const scrollY = window.scrollY;
                
                // Stick the TOC
                isStuck = true;
                tocWrapper.classList.add('nuclen-toc-stuck');
                toc.style.width = `${originalWidth}px`;
                
                // Update placeholder to maintain layout space
                placeholder.style.height = `${tocWrapper.offsetHeight}px`;
                placeholder.style.display = 'block';
                
                // Restore scroll position to prevent jumping
                window.scrollTo(0, scrollY);
                
                // Force recalculate the scroll limit after sticking
                requestAnimationFrame(updateStickyState);
            } else if (scrollPosition <= originalPosition && isStuck) {
                // Save current scroll position
                const scrollY = window.scrollY || window.pageYOffset;
                
                // Unstick the TOC
                isStuck = false;
                tocWrapper.classList.remove('nuclen-toc-stuck');
                toc.style.width = '';
                tocWrapper.style.position = 'static';
                tocWrapper.style.left = '';
                
                // Hide placeholder
                placeholder.style.display = 'none';
                
                // Restore scroll position to prevent jumping
                window.scrollTo(0, scrollY);
                
                // Force recalculate the scroll limit after un-sticking
                requestAnimationFrame(updateStickyState);
            } else if (isStuck) {
                // Keep the TOC at the top of the viewport when stuck
                toc.style.top = `${headerHeight}px`;
            }
        }

        // Initial check
        updateStickyState();

        // Handle scroll events with RAF for better performance
        const handleScroll = () => {
            updateStickyState();
        };
        
        // Handle window resize with debounce and RAF
        const handleResize = () => {
            if (rafId) {
                cancelAnimationFrame(rafId);
            }
            
            rafId = requestAnimationFrame(() => {
                // Update original dimensions
                originalWidth = toc.offsetWidth;
                
                // Update placeholder dimensions
                if (placeholder) {
                    placeholder.style.width = `${tocWrapper.offsetWidth}px`;
                    if (isStuck) {
                        placeholder.style.height = `${tocWrapper.offsetHeight}px`;
                    }
                }
                
                // Update sticky state to adjust to new dimensions
                updateStickyState();
            });
        };
        
        // Add event listeners
        window.addEventListener('scroll', handleScroll, { passive: true });
        window.addEventListener('resize', handleResize);
        
        // Cleanup function
        const cleanup = () => {
            if (rafId) {
                cancelAnimationFrame(rafId);
            }
            window.removeEventListener('scroll', handleScroll);
            window.removeEventListener('resize', handleResize);
        };
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', cleanup);
        
        // Initial update
        updateStickyState();
    });
}

// Initialize everything when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', () => {
    initStickyToc();
    
    // Handle TOC toggle button click
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.nuclen-toc-toggle');
        if (!btn) return;

        const nav = document.getElementById(btn.getAttribute('aria-controls'));
        if (!nav) return;

        const expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        nav.style.display = expanded ? 'none' : '';
        btn.textContent = expanded ? nuclenTocL10n.show : nuclenTocL10n.hide;
    });
    
    // Handle clicks on TOC links
    document.addEventListener('click', (e) => {
        const tocLink = e.target.closest('.nuclen-toc a');
        if (!tocLink) return;
        
        const tocWrapper = tocLink.closest('.nuclen-toc-sticky');
        if (!tocWrapper) return;
        
        // Close the TOC after a short delay to allow the click to be processed
        setTimeout(() => {
            if (tocWrapper.classList.contains('nuclen-toc-stuck')) {
                const toggleBtn = tocWrapper.querySelector('.nuclen-toc-toggle');
                if (toggleBtn && toggleBtn.getAttribute('aria-expanded') === 'true') {
                    toggleBtn.click();
                }
            }
        }, 100);
    });
    
    // Handle clicks outside the TOC
    document.addEventListener('click', (e) => {
        const tocWrapper = document.querySelector('.nuclen-toc-sticky.nuclen-toc-stuck');
        if (!tocWrapper) return;
        
        const isClickInside = tocWrapper.contains(e.target);
        const isToggleBtn = e.target.closest('.nuclen-toc-toggle');
        
        if (!isClickInside || isToggleBtn) {
            const toggleBtn = tocWrapper.querySelector('.nuclen-toc-toggle');
            if (toggleBtn && toggleBtn.getAttribute('aria-expanded') === 'true') {
                toggleBtn.click();
            }
        }
    });

    // Existing scroll-spy functionality
    const navs = document.querySelectorAll('.nuclen-toc[data-highlight="true"]');
    if (!navs.length || !('IntersectionObserver' in window)) return;

    const opts = { rootMargin: '0px 0px -60%', threshold: 0 };

    navs.forEach((nav) => {
        const map = new Map();

        nav.querySelectorAll('a[href^="#"]').forEach((a) => {
            const id = a.getAttribute('href').substring(1);
            if (id) {
                const target = document.getElementById(id);
                if (target) {
                    map.set(target, a);
                }
            }
        });

        if (!map.size) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const link = map.get(entry.target);
                if (link) {
                    link.classList.toggle('is-active', entry.isIntersecting);
                    if (entry.isIntersecting) {
                        link.setAttribute('aria-current', 'location');
                    } else if (link.getAttribute('aria-current') === 'location') {
                        link.removeAttribute('aria-current');
                    }
                }
            });
        }, opts);

        map.forEach((_, target) => observer.observe(target));
    });
});

(() => {
	/* Collapse / expand */
	document.addEventListener('click', (e) => {
		const btn = e.target.closest('.nuclen-toc-toggle');
		if (!btn) return;

		const nav = document.getElementById(btn.getAttribute('aria-controls'));
		if (!nav) return;

		const expanded = btn.getAttribute('aria-expanded') === 'true';
		btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		nav.style.display = expanded ? 'none' : '';
		btn.textContent   = expanded ? nuclenTocL10n.show : nuclenTocL10n.hide;
	});

	/* Scroll-spy highlight */
	const navs = document.querySelectorAll('.nuclen-toc[data-highlight="true"]');
	if (!navs.length || !('IntersectionObserver' in window)) return;

	const opts = { rootMargin: '0px 0px -60%', threshold: 0 };

	navs.forEach((nav) => {
		const map = new Map();

		nav.querySelectorAll('a[href^="#"]').forEach((a) => {
			const target = document.getElementById(a.getAttribute('href').slice(1));
			if (target) map.set(target, a);
		});

		const io = new IntersectionObserver((entries) => {
			entries.forEach((en) => {
				const link = map.get(en.target);
				if (!link) return;

				if (en.isIntersecting) {
					nav.querySelectorAll('a.is-active').forEach((el) => {
						el.classList.remove('is-active');
						el.removeAttribute('aria-current');
					});
					link.classList.add('is-active');
					link.setAttribute('aria-current', 'location');
				}
			});
		}, opts);

		map.forEach((_l, h) => io.observe(h));
	});
})();
