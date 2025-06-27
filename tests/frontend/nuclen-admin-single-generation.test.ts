import { describe, it, beforeAll, beforeEach, afterEach, expect, vi } from 'vitest';

vi.mock('../../src/admin/ts/generation/api', async () => {
  const actual: any = await vi.importActual('../../src/admin/ts/generation/api');
  const nuclenFetchWithRetry = vi.fn();
  return {
    ...actual,
    nuclenFetchWithRetry,
    NuclenStartGeneration: vi.fn(async (...args) => {
      return await nuclenFetchWithRetry(...args);
    }),
  };
});

// Re-export mocked generation helpers so NuclenStartGeneration uses the stubbed
// nuclenFetchWithRetry above.
vi.mock('../../src/admin/ts/nuclen-admin-generate', async () => {
  const api = await import('../../src/admin/ts/generation/api');
  const { NuclenPollAndPullUpdates } = await vi.importActual('../../src/admin/ts/generation/polling');
  return {
    ...api,
    NuclenPollAndPullUpdates: vi.fn(),
  };
});

vi.mock('../../src/admin/ts/single/single-generation-utils', () => ({
  alertApiError: vi.fn(),
  populateQuizMetaBox: vi.fn(),
  populateSummaryMetaBox: vi.fn(),
  storeGenerationResults: vi.fn().mockResolvedValue({ ok: true, data: {} }),
}));

const api = await import('../../src/admin/ts/generation/api');
const { NuclenPollAndPullUpdates } = await import('../../src/admin/ts/nuclen-admin-generate');
const utils = await import('../../src/admin/ts/single/single-generation-utils');

beforeAll(async () => {
  await import('../../src/admin/ts/nuclen-admin-single-generation');
});

describe('nuclen-admin-single-generation', () => {
  beforeEach(() => {
    document.body.innerHTML = '<button class="nuclen-generate-single" data-post-id="1" data-workflow="quiz">Generate</button>';
    (window as any).nuclenAdminVars = { ajax_url: 'a' };
    (window as any).nuclenAjax = { nonce: 'n', fetch_action: 'f', ajax_url: 'a' };
  });

  afterEach(() => {
    vi.clearAllMocks();
    document.body.innerHTML = '';
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    delete (window as any).nuclenAdminVars;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    delete (window as any).nuclenAjax;
  });

  it('starts generation and updates progress', async () => {
    let pollOpts: any;
    (NuclenPollAndPullUpdates as unknown as vi.Mock).mockImplementation((opts) => {
      pollOpts = opts;
    });

    (api.nuclenFetchWithRetry as vi.Mock).mockResolvedValueOnce({
        ok: true,
        status: 200,
        data: { success: true, generation_id: 'gid' },
      });

    const btn = document.querySelector<HTMLButtonElement>('.nuclen-generate-single')!;
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await Promise.resolve();
    await Promise.resolve();

    expect(api.nuclenFetchWithRetry).toHaveBeenCalled();
    expect(pollOpts).toBeDefined();

    pollOpts.onProgress(1, 2);
    expect(btn.textContent).toBe('Generating...');

    await pollOpts.onComplete({ workflow: 'quiz', results: { '1': {} } });
    await Promise.resolve();

    expect(utils.storeGenerationResults).toHaveBeenCalledWith('quiz', { '1': {} });
    expect(btn.textContent).toBe('Stored!');
    expect(btn.disabled).toBe(false);
  });

  it('displays error when generation fails', async () => {
    (api.nuclenFetchWithRetry as vi.Mock).mockResolvedValueOnce({
        ok: false,
        status: 500,
        data: null,
        error: 'fail',
      });

    const btn = document.querySelector<HTMLButtonElement>('.nuclen-generate-single')!;
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await Promise.resolve();
    await Promise.resolve();

    expect(utils.alertApiError).toHaveBeenCalledWith('fail');
    expect(btn.textContent).toBe('Generate');
    expect(btn.disabled).toBe(false);
  });
});

