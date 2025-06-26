import { describe, it, beforeAll, beforeEach, afterEach, expect, vi } from 'vitest';

vi.mock('../../src/admin/ts/generation/api', async () => {
  const actual: any = await vi.importActual('../../src/admin/ts/generation/api');
  return { ...actual, nuclenFetchWithRetry: vi.fn() };
});

vi.mock('../../src/admin/ts/nuclen-admin-generate', async () => {
  const actual: any = await vi.importActual('../../src/admin/ts/nuclen-admin-generate');
  return { ...actual, NuclenPollAndPullUpdates: vi.fn() };
});

vi.mock('../../src/admin/ts/single/single-generation-utils', () => ({
  alertApiError: vi.fn(),
  populateQuizMetaBox: vi.fn(),
  populateSummaryMetaBox: vi.fn(),
  storeGenerationResults: vi.fn().mockResolvedValue({ ok: true, data: {} }),
}));

const { nuclenFetchWithRetry } = await import('../../src/admin/ts/generation/api');
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
    vi.restoreAllMocks();
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

    (nuclenFetchWithRetry as vi.Mock).mockResolvedValueOnce({
      ok: true,
      status: 200,
      data: { success: true, generation_id: 'gid' },
    });

    const btn = document.querySelector<HTMLButtonElement>('.nuclen-generate-single')!;
    btn.click();
    await Promise.resolve();

    expect(nuclenFetchWithRetry).toHaveBeenCalled();
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
    (nuclenFetchWithRetry as vi.Mock).mockResolvedValueOnce({
      ok: false,
      status: 500,
      data: null,
      error: 'fail',
    });

    const btn = document.querySelector<HTMLButtonElement>('.nuclen-generate-single')!;
    btn.click();
    await Promise.resolve();

    expect(utils.alertApiError).toHaveBeenCalledWith('fail');
    expect(btn.textContent).toBe('Generate');
    expect(btn.disabled).toBe(false);
  });
});

