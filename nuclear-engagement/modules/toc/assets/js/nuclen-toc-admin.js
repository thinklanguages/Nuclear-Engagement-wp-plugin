/**
 * Nuclen TOC – admin generator JS.
 *
 * Builds live shortcode and copies it to the clipboard.
 * Expects nuclenTocAdmin.copy / .done strings.
 *
 * @file modules/toc/assets/js/nuclen-toc-admin.js
 */

(() => {
  const $ = (id) => document.getElementById(id);

  const f = {
    min: $('nuclen-min'),
    max: $('nuclen-max'),
    list: $('nuclen-list'),
    title: $('nuclen-title'),
    tog: $('nuclen-tog'),
    col: $('nuclen-col'),
    smo: $('nuclen-smo'),
    hil: $('nuclen-hil'),
    off: $('nuclen-off'),
  };

  const out = $('nuclen-shortcode-preview');
  const btn = $('nuclen-copy');

  function build() {
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
    navigator.clipboard.writeText(out.textContent).then(() => {
      btn.textContent = nuclenTocAdmin.done;
      setTimeout(() => {
        btn.textContent = nuclenTocAdmin.copy;
      }, 2000);
    });
  });
})();
