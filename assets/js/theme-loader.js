(function() {
    'use strict';

    class ThemeLoader {
        constructor(config) {
            this.config = config;
            this.loadedThemes = new Set();
            this.observer = null;
            this.themeQueue = new Set();
            this.isLoading = false;
            
            this.init();
        }

        init() {
            if (!this.config.components || !Object.keys(this.config.components).length) {
                return;
            }

            this.setupIntersectionObserver();
            this.observeComponents();
        }

        setupIntersectionObserver() {
            if (!('IntersectionObserver' in window)) {
                this.loadThemeImmediate();
                return;
            }

            const options = {
                rootMargin: `${this.config.offset}px`,
                threshold: 0
            };

            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const component = entry.target.dataset.nuclenComponent;
                        if (component) {
                            this.queueThemeLoad(this.config.activeThemeId);
                        }
                    }
                });
            }, options);
        }

        observeComponents() {
            Object.entries(this.config.components).forEach(([name, selector]) => {
                const elements = document.querySelectorAll(selector);
                
                elements.forEach(element => {
                    element.dataset.nuclenComponent = name;
                    
                    if (this.observer) {
                        this.observer.observe(element);
                    }
                });
            });

            if (!this.observer && document.querySelector(Object.values(this.config.components).join(','))) {
                this.loadThemeImmediate();
            }
        }

        queueThemeLoad(themeId) {
            if (this.loadedThemes.has(themeId)) {
                return;
            }

            this.themeQueue.add(themeId);
            
            if (!this.isLoading) {
                this.processQueue();
            }
        }

        async processQueue() {
            if (this.themeQueue.size === 0 || this.isLoading) {
                return;
            }

            this.isLoading = true;
            const themeIds = Array.from(this.themeQueue);
            this.themeQueue.clear();

            try {
                const urls = await this.fetchThemeUrls(themeIds);
                await Promise.all(
                    Object.entries(urls).map(([themeId, url]) => 
                        this.loadCssFile(url, `nuclen-theme-${themeId}`)
                    )
                );

                themeIds.forEach(id => this.loadedThemes.add(parseInt(id)));
            } catch (error) {
                console.warn('Failed to load theme CSS:', error);
            } finally {
                this.isLoading = false;
                
                if (this.themeQueue.size > 0) {
                    setTimeout(() => this.processQueue(), 100);
                }
            }
        }

        async fetchThemeUrls(themeIds) {
            const formData = new FormData();
            formData.append('action', 'nuclen_get_theme_urls');
            formData.append('nonce', this.config.nonce);
            formData.append('theme_ids', JSON.stringify(themeIds));

            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.data || 'Failed to fetch theme URLs');
            }

            return result.data;
        }

        loadCssFile(url, id) {
            return new Promise((resolve, reject) => {
                if (document.getElementById(id)) {
                    resolve();
                    return;
                }

                const link = document.createElement('link');
                link.id = id;
                link.rel = 'stylesheet';
                link.href = url;
                
                link.onload = () => {
                    this.triggerThemeLoadEvent(id);
                    resolve();
                };
                
                link.onerror = () => reject(new Error(`Failed to load CSS: ${url}`));
                
                document.head.appendChild(link);
            });
        }

        loadThemeImmediate() {
            this.queueThemeLoad(this.config.activeThemeId);
        }

        triggerThemeLoadEvent(themeId) {
            const event = new CustomEvent('nuclenThemeLoaded', {
                detail: { themeId }
            });
            document.dispatchEvent(event);
        }

        destroy() {
            if (this.observer) {
                this.observer.disconnect();
                this.observer = null;
            }
            
            this.loadedThemes.clear();
            this.themeQueue.clear();
        }
    }

    function initThemeLoader() {
        if (typeof nuclenThemeLoader === 'undefined') {
            return;
        }

        window.nuclenThemeLoaderInstance = new ThemeLoader(nuclenThemeLoader);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeLoader);
    } else {
        initThemeLoader();
    }

    window.addEventListener('beforeunload', function() {
        if (window.nuclenThemeLoaderInstance) {
            window.nuclenThemeLoaderInstance.destroy();
        }
    });

})();