declare const gtag: (command: string, eventName: string, parameters?: any) => void;

export class TocAnalytics {
    private tocNav: HTMLElement;
    private toggleButton: HTMLButtonElement | null;
    private hasViewEventFired: boolean = false;

    constructor(tocWrapper: HTMLElement) {
        this.tocNav = tocWrapper.querySelector('.nuclen-toc') as HTMLElement;
        this.toggleButton = tocWrapper.querySelector('.nuclen-toc-toggle') as HTMLButtonElement | null;

        if (!this.tocNav) {
            return;
        }

        this.init();
    }

    private init(): void {
        this.setupViewTracking();
        this.setupClickTracking();
        this.setupToggleTracking();
    }

    private setupViewTracking(): void {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting && entry.intersectionRatio >= 0.8 && !this.hasViewEventFired) {
                        this.hasViewEventFired = true;
                        gtag('event', 'nuclen_toc_view', {
                            event_category: 'TOC',
                            event_label: 'TOC fully visible',
                            value: window.location.pathname
                        });
                    }
                });
            },
            {
                threshold: 0.8
            }
        );

        observer.observe(this.tocNav);
    }

    private setupClickTracking(): void {
        const links = this.tocNav.querySelectorAll('a');
        
        links.forEach(link => {
            link.addEventListener('click', (event) => {
                const target = event.currentTarget as HTMLAnchorElement;
                const linkText = target.textContent?.trim() || '';
                const href = target.getAttribute('href') || '';

                gtag('event', 'nuclen_toc_click', {
                    event_category: 'TOC',
                    event_label: linkText,
                    value: href
                });
            });
        });
    }

    private setupToggleTracking(): void {
        if (!this.toggleButton) {
            return;
        }

        this.toggleButton.addEventListener('click', () => {
            const isExpanded = this.toggleButton!.getAttribute('aria-expanded');
            const action = isExpanded === 'true' ? 'hide' : 'show';

            gtag('event', 'nuclen_toc_toggle', {
                event_category: 'TOC',
                event_label: action
            });
        });
    }
}