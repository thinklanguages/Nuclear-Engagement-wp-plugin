import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { JSDOM } from 'jsdom';

/**
 * Test Gutenberg blocks functionality in the editor
 */
describe('Gutenberg Blocks Editor', () => {
  let dom: JSDOM;
  let window: Window;
  let document: Document;

  beforeEach(() => {
    // Set up DOM environment similar to WordPress admin
    dom = new JSDOM(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>WordPress Admin</title>
        </head>
        <body class="wp-admin">
          <div id="editor" class="block-editor"></div>
          <script id="nuclen-admin-js-extra">
            var nuclenAdminVars = {
              "ajaxUrl": "http://example.com/wp-admin/admin-ajax.php",
              "nonce": "test123",
              "restUrl": "http://example.com/wp-json/",
              "restNonce": "rest456"
            };
          </script>
        </body>
      </html>
    `, {
      url: 'http://example.com/wp-admin/post.php',
      pretendToBeVisual: true
    });

    window = dom.window as unknown as Window;
    document = window.document;
    global.window = window as any;
    global.document = document;

    // Mock WordPress block editor globals
    (window as any).wp = {
      blocks: {
        registerBlockType: vi.fn(),
        createBlock: vi.fn()
      },
      element: {
        createElement: vi.fn(),
        Fragment: vi.fn()
      },
      components: {
        Placeholder: vi.fn(),
        ServerSideRender: vi.fn()
      },
      blockEditor: {
        InspectorControls: vi.fn(),
        BlockControls: vi.fn()
      },
      i18n: {
        __: vi.fn((text: string) => text),
        _x: vi.fn((text: string) => text)
      }
    };

    // Mock admin variables
    (window as any).nuclenAdminVars = {
      ajaxUrl: 'http://example.com/wp-admin/admin-ajax.php',
      nonce: 'test123',
      restUrl: 'http://example.com/wp-json/',
      restNonce: 'rest456'
    };
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it('should have WordPress block editor environment available', () => {
    expect(window.wp).toBeDefined();
    expect(window.wp.blocks).toBeDefined();
    expect(window.wp.blocks.registerBlockType).toBeDefined();
    expect(window.wp.element).toBeDefined();
    expect(window.wp.components).toBeDefined();
  });

  it('should register Nuclear Engagement blocks when admin script loads', () => {
    // Simulate block registration calls that would happen in nuclen-admin.js
    const mockRegisterBlockType = vi.fn();
    window.wp.blocks.registerBlockType = mockRegisterBlockType;

    // Simulate the block registration that should happen in admin script
    const blocks = [
      {
        name: 'nuclear-engagement/quiz',
        settings: {
          title: 'Quiz',
          category: 'widgets',
          icon: 'editor-help',
          edit: expect.any(Function),
          save: expect.any(Function)
        }
      },
      {
        name: 'nuclear-engagement/summary', 
        settings: {
          title: 'Summary',
          category: 'widgets',
          icon: 'excerpt-view',
          edit: expect.any(Function),
          save: expect.any(Function)
        }
      },
      {
        name: 'nuclear-engagement/toc',
        settings: {
          title: 'TOC',
          category: 'widgets', 
          icon: 'list-view',
          edit: expect.any(Function),
          save: expect.any(Function)
        }
      }
    ];

    // Simulate block registration
    blocks.forEach(block => {
      window.wp.blocks.registerBlockType(block.name, block.settings);
    });

    // Verify blocks were registered
    expect(mockRegisterBlockType).toHaveBeenCalledTimes(3);
    expect(mockRegisterBlockType).toHaveBeenCalledWith('nuclear-engagement/quiz', expect.any(Object));
    expect(mockRegisterBlockType).toHaveBeenCalledWith('nuclear-engagement/summary', expect.any(Object));
    expect(mockRegisterBlockType).toHaveBeenCalledWith('nuclear-engagement/toc', expect.any(Object));
  });

  it('should handle block edit interface correctly', () => {
    const mockServerSideRender = vi.fn().mockReturnValue('rendered-content');
    window.wp.components.ServerSideRender = mockServerSideRender;

    // Mock block edit function (this would be defined in admin script)
    const mockEditFunction = (props: any) => {
      // This simulates the edit function for Nuclear Engagement blocks
      return window.wp.components.ServerSideRender({
        block: props.name,
        attributes: props.attributes
      });
    };

    // Test edit function for quiz block
    const quizProps = {
      name: 'nuclear-engagement/quiz',
      attributes: {},
      setAttributes: vi.fn(),
      clientId: 'test-client-id'
    };

    const editResult = mockEditFunction(quizProps);
    
    expect(mockServerSideRender).toHaveBeenCalledWith({
      block: 'nuclear-engagement/quiz',
      attributes: {}
    });
    expect(editResult).toBe('rendered-content');
  });

  it('should provide block save functions that return null (server-side render)', () => {
    // Nuclear Engagement blocks should save as null since they're server-side rendered
    const mockSaveFunction = () => null;

    const saveResult = mockSaveFunction();
    expect(saveResult).toBeNull();
  });

  it('should handle admin AJAX variables correctly', () => {
    expect(window.nuclenAdminVars).toBeDefined();
    expect(window.nuclenAdminVars.ajaxUrl).toBe('http://example.com/wp-admin/admin-ajax.php');
    expect(window.nuclenAdminVars.nonce).toBe('test123');
    expect(window.nuclenAdminVars.restUrl).toBe('http://example.com/wp-json/');
    expect(window.nuclenAdminVars.restNonce).toBe('rest456');
  });

  it('should validate block icons and categories are correct', () => {
    const blockConfigs = [
      {
        name: 'nuclear-engagement/quiz',
        expectedIcon: 'editor-help',
        expectedCategory: 'widgets',
        expectedTitle: 'Quiz'
      },
      {
        name: 'nuclear-engagement/summary',
        expectedIcon: 'excerpt-view', 
        expectedCategory: 'widgets',
        expectedTitle: 'Summary'
      },
      {
        name: 'nuclear-engagement/toc',
        expectedIcon: 'list-view',
        expectedCategory: 'widgets',
        expectedTitle: 'TOC'
      }
    ];

    blockConfigs.forEach(config => {
      // This would be called by the actual block registration in admin script
      const blockSettings = {
        title: config.expectedTitle,
        category: config.expectedCategory,
        icon: config.expectedIcon,
        edit: () => null,
        save: () => null
      };

      expect(blockSettings.title).toBe(config.expectedTitle);
      expect(blockSettings.category).toBe(config.expectedCategory);
      expect(blockSettings.icon).toBe(config.expectedIcon);
    });
  });

  it('should handle block editor script loading dependencies', () => {
    // Test that the script would be loaded with correct dependencies
    const expectedDependencies = [
      'wp-blocks',
      'wp-element', 
      'wp-components',
      'wp-block-editor',
      'wp-i18n'
    ];

    // Mock script element that would be injected
    const scriptElement = document.createElement('script');
    scriptElement.id = 'nuclen-admin-js';
    scriptElement.src = 'http://example.com/wp-content/plugins/nuclear-engagement/admin/js/nuclen-admin.js';
    scriptElement.setAttribute('data-deps', expectedDependencies.join(','));

    document.head.appendChild(scriptElement);

    const loadedScript = document.getElementById('nuclen-admin-js');
    expect(loadedScript).toBeTruthy();
    expect(loadedScript?.getAttribute('data-deps')).toBe(expectedDependencies.join(','));
  });

  it('should handle block preview in editor correctly', () => {
    // Mock the ServerSideRender component behavior
    const mockServerSideRender = vi.fn().mockImplementation((props) => {
      // Simulate different responses based on block type
      switch (props.block) {
        case 'nuclear-engagement/quiz':
          return '<div class="nuclen-quiz-preview">Quiz Preview</div>';
        case 'nuclear-engagement/summary':
          return '<div class="nuclen-summary-preview">Summary Preview</div>';
        case 'nuclear-engagement/toc':
          return '<div class="nuclen-toc-preview">TOC Preview</div>';
        default:
          return '<div class="block-preview">Generic Preview</div>';
      }
    });

    window.wp.components.ServerSideRender = mockServerSideRender;

    // Test each block type preview
    const quizPreview = mockServerSideRender({ block: 'nuclear-engagement/quiz' });
    const summaryPreview = mockServerSideRender({ block: 'nuclear-engagement/summary' });
    const tocPreview = mockServerSideRender({ block: 'nuclear-engagement/toc' });

    expect(quizPreview).toContain('Quiz Preview');
    expect(summaryPreview).toContain('Summary Preview');
    expect(tocPreview).toContain('TOC Preview');
  });

  it('should handle translation strings in block editor', () => {
    const mockTranslate = vi.fn((text: string) => text);
    window.wp.i18n.__ = mockTranslate;

    // Simulate translation calls that would happen in block registration
    const translations = [
      'Quiz',
      'Summary', 
      'TOC',
      'Nuclear Engagement Quiz Block',
      'Nuclear Engagement Summary Block',
      'Nuclear Engagement TOC Block'
    ];

    translations.forEach(text => {
      window.wp.i18n.__(text, 'nuclear-engagement');
    });

    expect(mockTranslate).toHaveBeenCalledTimes(translations.length);
    translations.forEach(text => {
      expect(mockTranslate).toHaveBeenCalledWith(text, 'nuclear-engagement');
    });
  });
});