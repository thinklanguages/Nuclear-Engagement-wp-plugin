(function(){
  if (!window.wp || !window.wp.blocks || !window.wp.element || !window.wp.i18n) {
    return;
  }
  const { registerBlockType } = window.wp.blocks;
  const { createElement } = window.wp.element;
  const { __ } = window.wp.i18n;
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
