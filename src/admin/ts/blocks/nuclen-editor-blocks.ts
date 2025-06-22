(function(){
  if (!window.wp || !window.wp.blocks) {
    return;
  }
  const { registerBlockType } = window.wp.blocks;
  registerBlockType('nuclear-engagement/quiz', {
    edit: () => null,
    save: () => null,
  });
  registerBlockType('nuclear-engagement/summary', {
    edit: () => null,
    save: () => null,
  });
})();
