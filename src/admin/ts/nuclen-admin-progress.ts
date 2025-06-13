(function ($) {
  'use strict';

  if (!window.nuclenAdminVars || !window.nuclenAdminVars.ajax_url) {
    return;
  }

  let interval: number | null = null;

  function pollProgress() {
    $.post(window.nuclenAdminVars.ajax_url, {
      action: 'nuclen_generation_progress',
      security: window.nuclenAdminVars.security || ''
    }).done((resp) => {
      if (!resp.success) {
        return;
      }
      // simple console log for now
      console.log('Generation progress', resp.data);
    });
  }

  function start() {
    if (interval) return;
    pollProgress();
    interval = window.setInterval(pollProgress, 10000);
  }

  function stop() {
    if (interval) {
      clearInterval(interval);
      interval = null;
    }
  }

  $(document).ready(start);
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stop();
    } else {
      start();
    }
  });
})(jQuery);
