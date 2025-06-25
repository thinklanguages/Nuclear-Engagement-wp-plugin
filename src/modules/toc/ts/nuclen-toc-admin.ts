// modules/toc/ts/nuclen-toc-admin.ts

/**
 * Admin page shortcode generator logic.
 * Builds a live shortcode preview and copies it to the clipboard.
 */

declare const nuclenTocAdmin: { copy: string; done: string };

const $ = (id: string): HTMLElement => {
  const el = document.getElementById(id);
  if (!el) {
    throw new Error(`Missing element: ${id}`);
  }
  return el;
};

const f = {
  min: $('nuclen-min') as HTMLSelectElement,
  max: $('nuclen-max') as HTMLSelectElement,
  list: $('nuclen-list') as HTMLSelectElement,
  title: $('nuclen-title') as HTMLInputElement,
  tog: $('nuclen-tog') as HTMLInputElement,
  col: $('nuclen-col') as HTMLInputElement,
  smo: $('nuclen-smo') as HTMLInputElement,
  hil: $('nuclen-hil') as HTMLInputElement,
  off: $('nuclen-off') as HTMLInputElement,
};

const out = $('nuclen-shortcode-preview');
const btn = $('nuclen-copy');

function build(): void {
  let sc = '[simple_toc';

  if (f.min.value !== '2') {
    sc += ` min_level="${f.min.value}"`;
  }
  if (f.max.value !== '6') {
    sc += ` max_level="${f.max.value}"`;
  }
  if (f.list.value !== 'ul') {
    sc += ` list="${f.list.value}"`;
  }

  const t = f.title.value.trim();
  if (t) {
    sc += ` title="${t.replace(/"/g, '&quot;')}"`;
  }

  if (!f.tog.checked) {
    sc += ' toggle="false"';
  }
  if (f.col.checked) {
    sc += ' collapsed="true"';
  }
  if (!f.smo.checked) {
    sc += ' smooth="false"';
  }
  if (!f.hil.checked) {
    sc += ' highlight="false"';
  }
  if (f.off.value !== '72') {
    sc += ` offset="${f.off.value}"`;
  }

  sc += ']';
  out.textContent = sc;
}

Object.values(f).forEach((el) => {
  el.addEventListener(el.type === 'checkbox' ? 'change' : 'input', build);
});
build();

btn.addEventListener('click', () => {
  navigator.clipboard.writeText(out.textContent || '').then(() => {
    btn.textContent = nuclenTocAdmin.done;
    setTimeout(() => {
      btn.textContent = nuclenTocAdmin.copy;
    }, 2000);
  });
});
