(function ($: any) {
  'use strict';

  if (!window.nuclenAdminVars || !window.nuclenAdminVars.ajax_url) {
    return;
  }

  let pollInterval: number | null = null;
  const activeGenerations = new Map<string, string>();

  function container(): any {
    let c = $('#nuclen-floating-notices');
    if (!c.length) {
      c = $('<div id="nuclen-floating-notices"></div>');
      $('body').append(c);
    }
    return c;
  }

  function updateNotice(gen: any) {
    const c = container();
    let notice = c.find(`[data-generation-id="${gen.generation_id}"]`);
    if (!notice.length) {
      notice = $(
        `<div class="nuclen-progress-notice" data-generation-id="${gen.generation_id}">` +
          '<button class="nuclen-progress-dismiss" aria-label="Dismiss">×</button>' +
          '<div class="nuclen-progress-text"></div>' +
          '<div class="nuclen-progress-bar"><div class="nuclen-progress-fill"></div></div>' +
        '</div>'
      );
      c.append(notice);
      notice.find('.nuclen-progress-dismiss').on('click', () => dismiss(gen.generation_id));
    }

    const processed = parseInt(gen.processed, 10) || 0;
    const total = parseInt(gen.total, 10) || 1;
    const percent = Math.round((processed / total) * 100);
    let text = '';

    if (gen.status === 'complete') {
      text = `✓ ${gen.workflow_type} generation complete! (${processed}/${total})`;
      notice.addClass('nuclen-complete');
    } else if (gen.status === 'failed') {
      text = `✗ ${gen.workflow_type} generation failed`;
      notice.addClass('nuclen-failed');
    } else {
      text = `Generating ${gen.workflow_type}: ${processed}/${total} (${percent}%)`;
    }

    notice.find('.nuclen-progress-text').text(text);
    notice.find('.nuclen-progress-fill').css('width', percent + '%');

    activeGenerations.set(gen.generation_id, gen.status);
  }

  function removeNotice(id: string) {
    $(`[data-generation-id="${id}"]`).remove();
    activeGenerations.delete(id);
  }

  function dismiss(id: string) {
    removeNotice(id);
    $.post(window.nuclenAdminVars?.ajax_url || '', {
        action: 'nuclen_dismiss_generation',
        generation_id: id,
        security: window.nuclenAdminVars?.security || ''
    });
  }

  function poll() {
    $.post(window.nuclenAdminVars?.ajax_url || '', {
        action: 'nuclen_generation_progress',
        security: window.nuclenAdminVars?.security || ''
    }).done((resp: any) => {
      if (!resp.success) {
        return;
      }
      const gens = resp.data.generations || [];
      const ids = new Set<string>();
      gens.forEach((g: any) => {
        ids.add(g.generation_id);
        updateNotice(g);
      });

      activeGenerations.forEach((_status, id) => {
        if (!ids.has(id)) {
          setTimeout(() => removeNotice(id), 5000);
        }
      });

      if (activeGenerations.size === 0) {
        stopPolling();
      }
    });
  }

  function startPolling() {
    if (pollInterval) {
      return;
    }
    poll();
    pollInterval = window.setInterval(poll, 10000);
  }

  function stopPolling() {
    if (pollInterval) {
      clearInterval(pollInterval);
      pollInterval = null;
    }
  }

  $(document).ready(() => {
    startPolling();
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        stopPolling();
      } else if (activeGenerations.size > 0) {
        startPolling();
      }
    });
  });

  (window as any).nuclenStartProgressPolling = startPolling;
})((window as any).jQuery);
