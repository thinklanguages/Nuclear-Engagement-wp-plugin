import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// Mock the global nuclenTocAdmin object
declare global {
  var nuclenTocAdmin: { copy: string; done: string };
}

describe('nuclen-toc-admin', () => {
  let mockElements: Record<string, HTMLElement>;
  let getElementByIdSpy: ReturnType<typeof vi.spyOn>;
  let mockClipboard: { writeText: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    vi.clearAllMocks();
    
    // Mock global nuclenTocAdmin
    global.nuclenTocAdmin = {
      copy: 'Copy to Clipboard',
      done: 'Copied!'
    };

    // Mock navigator.clipboard
    mockClipboard = {
      writeText: vi.fn().mockResolvedValue(undefined)
    };
    Object.defineProperty(global.navigator, 'clipboard', {
      value: mockClipboard,
      writable: true
    });

    // Mock nuclenTocAdmin global
    (global as any).nuclenTocAdmin = {
      copy: 'Copy to Clipboard',
      done: 'Copied!'
    };

    // Create mock elements with proper default values and options
    const minSelect = document.createElement('select');
    ['1', '2', '3', '4', '5', '6'].forEach(val => {
      const option = document.createElement('option');
      option.value = val;
      option.textContent = val;
      minSelect.appendChild(option);
    });
    minSelect.value = '2';

    const maxSelect = document.createElement('select');
    ['1', '2', '3', '4', '5', '6'].forEach(val => {
      const option = document.createElement('option');
      option.value = val;
      option.textContent = val;
      maxSelect.appendChild(option);
    });
    maxSelect.value = '6';

    const listSelect = document.createElement('select');
    [['ul', 'Unordered List'], ['ol', 'Ordered List']].forEach(([val, text]) => {
      const option = document.createElement('option');
      option.value = val;
      option.textContent = text;
      listSelect.appendChild(option);
    });
    listSelect.value = 'ul';

    mockElements = {
      'nuclen-min': minSelect,
      'nuclen-max': maxSelect,
      'nuclen-list': listSelect,
      'nuclen-title': Object.assign(document.createElement('input'), { value: '', type: 'text' }),
      'nuclen-tog': Object.assign(document.createElement('input'), { checked: true, type: 'checkbox' }),
      'nuclen-col': Object.assign(document.createElement('input'), { checked: false, type: 'checkbox' }),
      'nuclen-smo': Object.assign(document.createElement('input'), { checked: true, type: 'checkbox' }),
      'nuclen-hil': Object.assign(document.createElement('input'), { checked: true, type: 'checkbox' }),
      'nuclen-off': Object.assign(document.createElement('input'), { value: '72', type: 'number' }),
      'nuclen-shortcode-preview': document.createElement('div'),
      'nuclen-copy': Object.assign(document.createElement('button'), { textContent: 'Copy to Clipboard' })
    };

    // Mock getElementById
    getElementByIdSpy = vi.spyOn(document, 'getElementById') as any;
    getElementByIdSpy.mockImplementation(((id: string) => mockElements[id] || null) as any);

    // Clear modules to ensure fresh import
    vi.resetModules();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('initialization', () => {
    it('should throw error if required element is missing', async () => {
      getElementByIdSpy.mockReturnValue(null);
      
      await expect(import('../../src/modules/toc/ts/nuclen-toc-admin')).rejects.toThrow('Missing element');
    });

    it('should initialize with default shortcode', async () => {
      await import('../../src/modules/toc/ts/nuclen-toc-admin');
      
      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc]');
    });

    it('should add event listeners to all form elements', async () => {
      const addEventListenerSpies: Record<string, ReturnType<typeof vi.spyOn>> = {};
      
      Object.keys(mockElements).forEach(id => {
        if (id !== 'nuclen-shortcode-preview' && id !== 'nuclen-copy') {
          addEventListenerSpies[id] = vi.spyOn(mockElements[id], 'addEventListener');
        }
      });

      await import('../../src/modules/toc/ts/nuclen-toc-admin');

      // Check select elements
      expect(addEventListenerSpies['nuclen-min']).toHaveBeenCalledWith('input', expect.any(Function));
      expect(addEventListenerSpies['nuclen-max']).toHaveBeenCalledWith('input', expect.any(Function));
      expect(addEventListenerSpies['nuclen-list']).toHaveBeenCalledWith('input', expect.any(Function));
      expect(addEventListenerSpies['nuclen-title']).toHaveBeenCalledWith('input', expect.any(Function));
      expect(addEventListenerSpies['nuclen-off']).toHaveBeenCalledWith('input', expect.any(Function));

      // Check checkbox elements
      expect(addEventListenerSpies['nuclen-tog']).toHaveBeenCalledWith('change', expect.any(Function));
      expect(addEventListenerSpies['nuclen-col']).toHaveBeenCalledWith('change', expect.any(Function));
      expect(addEventListenerSpies['nuclen-smo']).toHaveBeenCalledWith('change', expect.any(Function));
      expect(addEventListenerSpies['nuclen-hil']).toHaveBeenCalledWith('change', expect.any(Function));
    });
  });

  const resetToDefaults = () => {
    (mockElements['nuclen-min'] as HTMLSelectElement).value = '2';
    (mockElements['nuclen-max'] as HTMLSelectElement).value = '6';
    (mockElements['nuclen-list'] as HTMLSelectElement).value = 'ul';
    (mockElements['nuclen-title'] as HTMLInputElement).value = '';
    (mockElements['nuclen-tog'] as HTMLInputElement).checked = true;
    (mockElements['nuclen-col'] as HTMLInputElement).checked = false;
    (mockElements['nuclen-smo'] as HTMLInputElement).checked = true;
    (mockElements['nuclen-hil'] as HTMLInputElement).checked = true;
    (mockElements['nuclen-off'] as HTMLInputElement).value = '72';
  };

  describe('shortcode building', () => {
    beforeEach(async () => {
      resetToDefaults();
      await import('../../src/modules/toc/ts/nuclen-toc-admin');
    });

    it('should update shortcode when min level changes', () => {
      const minSelect = mockElements['nuclen-min'] as HTMLSelectElement;
      minSelect.value = '3';
      minSelect.dispatchEvent(new Event('input'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc min_level="3"]');
    });

    it('should update shortcode when max level changes', () => {
      
      const maxSelect = mockElements['nuclen-max'] as HTMLSelectElement;
      maxSelect.value = '4';
      maxSelect.dispatchEvent(new Event('input'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc max_level="4"]');
    });

    it('should update shortcode when list type changes', () => {
      
      const listSelect = mockElements['nuclen-list'] as HTMLSelectElement;
      listSelect.value = 'ol';
      listSelect.dispatchEvent(new Event('input'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc list="ol"]');
    });

    it('should update shortcode with title', () => {
      
      const titleInput = mockElements['nuclen-title'] as HTMLInputElement;
      titleInput.value = 'Table of Contents';
      titleInput.dispatchEvent(new Event('input'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc title="Table of Contents"]');
    });

    it('should escape quotes in title', () => {
      
      const titleInput = mockElements['nuclen-title'] as HTMLInputElement;
      titleInput.value = 'My "Special" Title';
      titleInput.dispatchEvent(new Event('input'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc title="My &quot;Special&quot; Title"]');
    });

    it('should handle toggle checkbox', () => {
      
      const togCheckbox = mockElements['nuclen-tog'] as HTMLInputElement;
      togCheckbox.checked = false;
      togCheckbox.dispatchEvent(new Event('change'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc toggle="false"]');
    });

    it('should handle collapsed checkbox', () => {
      
      const colCheckbox = mockElements['nuclen-col'] as HTMLInputElement;
      colCheckbox.checked = true;
      colCheckbox.dispatchEvent(new Event('change'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc collapsed="true"]');
    });

    it('should handle smooth scrolling checkbox', () => {
      
      const smoCheckbox = mockElements['nuclen-smo'] as HTMLInputElement;
      smoCheckbox.checked = false;
      smoCheckbox.dispatchEvent(new Event('change'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc smooth="false"]');
    });

    it('should handle highlight checkbox', () => {
      
      const hilCheckbox = mockElements['nuclen-hil'] as HTMLInputElement;
      hilCheckbox.checked = false;
      hilCheckbox.dispatchEvent(new Event('change'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc highlight="false"]');
    });

    it('should update shortcode with offset', () => {
      
      const offInput = mockElements['nuclen-off'] as HTMLInputElement;
      offInput.value = '100';
      offInput.dispatchEvent(new Event('input'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc offset="100"]');
    });

    it('should build complex shortcode with multiple options', () => {
      
      const minSelect = mockElements['nuclen-min'] as HTMLSelectElement;
      const maxSelect = mockElements['nuclen-max'] as HTMLSelectElement;
      const listSelect = mockElements['nuclen-list'] as HTMLSelectElement;
      const titleInput = mockElements['nuclen-title'] as HTMLInputElement;
      const togCheckbox = mockElements['nuclen-tog'] as HTMLInputElement;
      const colCheckbox = mockElements['nuclen-col'] as HTMLInputElement;
      const offInput = mockElements['nuclen-off'] as HTMLInputElement;

      minSelect.value = '1';
      maxSelect.value = '3';
      listSelect.value = 'ol';
      titleInput.value = 'Contents';
      togCheckbox.checked = false;
      colCheckbox.checked = true;
      offInput.value = '50';

      // Trigger all changes
      minSelect.dispatchEvent(new Event('input'));
      maxSelect.dispatchEvent(new Event('input'));
      listSelect.dispatchEvent(new Event('input'));
      titleInput.dispatchEvent(new Event('input'));
      togCheckbox.dispatchEvent(new Event('change'));
      colCheckbox.dispatchEvent(new Event('change'));
      offInput.dispatchEvent(new Event('input'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe(
        '[simple_toc min_level="1" max_level="3" list="ol" title="Contents" toggle="false" collapsed="true" offset="50"]'
      );
    });

    it('should trim title whitespace', () => {
      
      const titleInput = mockElements['nuclen-title'] as HTMLInputElement;
      titleInput.value = '  My Title  ';
      titleInput.dispatchEvent(new Event('input'));

      expect(mockElements['nuclen-shortcode-preview'].textContent).toBe('[simple_toc title="My Title"]');
    });
  });

  describe('copy functionality', () => {
    beforeEach(async () => {
      resetToDefaults();
      await import('../../src/modules/toc/ts/nuclen-toc-admin');
    });

    it('should copy shortcode to clipboard on button click', async () => {
      
      const copyButton = mockElements['nuclen-copy'] as HTMLButtonElement;
      
      copyButton.click();
      
      expect(mockClipboard.writeText).toHaveBeenCalledWith('[simple_toc]');
      
      // Wait for promise to resolve
      await new Promise(resolve => setTimeout(resolve, 0));
      
      expect(copyButton.textContent).toBe('Copied!');
    });

    it('should reset button text after 2 seconds', async () => {
      vi.useFakeTimers();
      
      const copyButton = mockElements['nuclen-copy'] as HTMLButtonElement;
      
      copyButton.click();
      
      // Wait for the promise to resolve and then initial change
      await new Promise(resolve => process.nextTick(resolve));
      
      expect(copyButton.textContent).toBe('Copied!');
      
      // Fast forward 2 seconds
      vi.advanceTimersByTime(2000);
      
      expect(copyButton.textContent).toBe('Copy to Clipboard');
      
      vi.useRealTimers();
    });

    it('should copy custom shortcode to clipboard', async () => {
      
      const titleInput = mockElements['nuclen-title'] as HTMLInputElement;
      titleInput.value = 'My TOC';
      titleInput.dispatchEvent(new Event('input'));
      
      const copyButton = mockElements['nuclen-copy'] as HTMLButtonElement;
      copyButton.click();
      
      expect(mockClipboard.writeText).toHaveBeenCalledWith('[simple_toc title="My TOC"]');
    });

    it('should handle empty shortcode preview', async () => {
      mockElements['nuclen-shortcode-preview'].textContent = '';
      
      const copyButton = mockElements['nuclen-copy'] as HTMLButtonElement;
      copyButton.click();
      
      expect(mockClipboard.writeText).toHaveBeenCalledWith('');
    });
  });
});