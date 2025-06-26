(function(){
  interface WPGlobal {
    blocks?: { registerBlockType: (...args: unknown[]) => void };
    element?: { createElement: (...args: unknown[]) => unknown };
    i18n?: { __: (...args: unknown[]) => string };
  }
  const wp = window.wp as WPGlobal;
  if (!wp || !wp.blocks || !wp.element || !wp.i18n) {
    return;
  }
  const { registerBlockType } = wp.blocks;
  const { createElement } = wp.element;
  const { __ } = wp.i18n;
  registerBlockType('nuclear-engagement/quiz', {
    apiVersion: 2,
    title: __('Quiz', 'nuclear-engagement'),
    icon: 'editor-help',
    category: 'widgets',
    edit: () => createElement('p', null, __('Quiz will render on the front-end.', 'nuclear-engagement')),
    save: () => null,
  });
  registerBlockType('nuclear-engagement/summary', {
    apiVersion: 2,
    title: __('Summary', 'nuclear-engagement'),
    icon: 'excerpt-view',
    category: 'widgets',
    edit: () => createElement('p', null, __('Summary will render on the front-end.', 'nuclear-engagement')),
    save: () => null,
  });
})();
