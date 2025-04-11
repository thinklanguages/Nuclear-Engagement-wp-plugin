// nuclen-front-lazy.ts

/*************************************************
 * 2) Lazy-Load + GA Intersection Observers
 *************************************************/

/**
 * 2a) Lazy-load container. Observes 'containerId' and triggers `initFunctionName` once visible.
 */
window.NuclenLazyLoadComponent = function (containerId: string, initFunctionName: string | null = null) {
    const component = document.getElementById(containerId);
    if (!component) return;
  
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            if (initFunctionName && typeof (window as any)[initFunctionName] === 'function') {
              (window as any)[initFunctionName]();
            }
            observer.disconnect();
          }
        });
      },
      {
        rootMargin: '0px 0px -200px 0px',
        threshold: 0.1
      }
    );
  
    observer.observe(component);
  };
  
  /**
   * 2b) Fire a GA event when a specific element is fully in view (threshold=1.0).
   */
  function nuclenInitIntersectionObserver(selector: string, gtagEventName: string) {
    const target = document.querySelector(selector);
    if (!target) return;
  
    const observer = new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          // intersectionRatio === 1 means the element is fully in view
          if (entry.isIntersecting && entry.intersectionRatio === 1) {
            if (typeof gtag === 'function') {
              gtag('event', gtagEventName);
            }
            // Stop observing after first event
            obs.unobserve(entry.target);
          }
        });
      },
      {
        root: null,
        rootMargin: '0px',
        threshold: 1.0 // require 100% of the element to be visible
      }
    );
  
    observer.observe(target);
  }
  
  /**
   * 2c) Wait for #nuclen-quiz-container and #nuclen-summary-container in DOM, then attach GA observers.
   */
  const mutationObs = new MutationObserver((_mutations, obs) => {
    const quizEl = document.getElementById('nuclen-quiz-container');
    const summaryEl = document.getElementById('nuclen-summary-container');
    if (quizEl && summaryEl) {
      nuclenInitIntersectionObserver('#nuclen-summary-container', 'nuclen_summary_view');
      nuclenInitIntersectionObserver('#nuclen-quiz-container', 'nuclen_quiz_view');
      obs.disconnect(); // stop once attached
    }
  });
  mutationObs.observe(document.body, { childList: true, subtree: true });
  
  /**
   * 2d) Immediately call lazy-loading for the quiz container, telling it to run nuclearEngagementInitQuiz() once in view.
   */
  window.NuclenLazyLoadComponent('nuclen-quiz-container', 'nuclearEngagementInitQuiz');
  