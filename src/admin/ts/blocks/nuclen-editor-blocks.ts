(function(){
  if (!window.wp || !window.wp.blocks || !window.wp.element) {
    return;
  }
  const { registerBlockType } = window.wp.blocks;
  const { createElement } = window.wp.element;

  registerBlockType('nuclear-engagement/quiz', {
    title: 'Nuclear Engagement Quiz',
    icon: 'clipboard',
    category: 'widgets',
    description: 'Insert the quiz generated for this post.',
    edit: () => createElement('p', null, 'Quiz will appear here on the front-end.'),
    save: () => null,
  });

  registerBlockType('nuclear-engagement/summary', {
    title: 'Nuclear Engagement Summary',
    icon: 'list-view',
    category: 'widgets',
    description: 'Insert the key facts summary for this post.',
    edit: () => createElement('p', null, 'Summary will appear here on the front-end.'),
    save: () => null,
  });
})();
