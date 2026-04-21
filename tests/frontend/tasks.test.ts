/**
 * tasks.test.ts - Frontend tests for tasks page functionality
 */

import { afterEach, beforeEach, describe, expect, it, type Mock, vi } from 'vitest';
import { JSDOM } from 'jsdom';

declare global {
    var ajaxurl: string;
    var nuclen_tasks: {
        nonce: string;
        i18n: {
            processing: string;
            cancelling: string;
            error: string;
            success: string;
        };
    };
}

const createFetchResponse = (payload: unknown, ok = true) => ({
    ok,
    json: () => Promise.resolve(payload),
});

const flushPromises = async (): Promise<void> => {
    await new Promise(resolve => setTimeout(resolve, 0));
};

const setupDOM = (): void => {
    const dom = new JSDOM(
        `
        <!DOCTYPE html>
        <html>
        <body>
            <div class="wrap">
                <h1>Nuclear Engagement - Tasks</h1>
                <a href="?page=nuclen-tasks&refresh=1">Refresh</a>
                <table class="wp-list-table nuclen-tasks-table">
                    <tbody>
                        <tr data-task-id="gen_123">
                            <td>2026-04-21 10:00</td>
                            <td>Now</td>
                            <td class="column-id">gen_123</td>
                            <td class="column-type">Summary</td>
                            <td class="column-status">
                                <span class="nuclen-badge nuclen-badge-warning">Pending</span>
                            </td>
                            <td class="column-progress">
                                <div class="nuclen-progress-container">
                                    <div class="nuclen-progress-bar">
                                        <div class="nuclen-progress-fill" style="width: 0%"></div>
                                    </div>
                                    <span class="nuclen-progress-text">0%</span>
                                </div>
                            </td>
                            <td class="column-details">Waiting to run</td>
                            <td class="column-actions">
                                <button class="button button-small nuclen-run-now" data-task-id="gen_123">Run Now</button>
                                <button class="button button-small nuclen-cancel" data-task-id="gen_123">Cancel</button>
                            </td>
                        </tr>
                        <tr data-task-id="gen_124">
                            <td>2026-04-21 10:01</td>
                            <td>Now</td>
                            <td class="column-id">gen_124</td>
                            <td class="column-type">Quiz</td>
                            <td class="column-status">
                                <span class="nuclen-badge nuclen-badge-info">Processing</span>
                            </td>
                            <td class="column-progress">
                                <div class="nuclen-progress-container">
                                    <div class="nuclen-progress-bar">
                                        <div class="nuclen-progress-fill" style="width: 50%"></div>
                                    </div>
                                    <span class="nuclen-progress-text">50%</span>
                                </div>
                            </td>
                            <td class="column-details">Halfway there</td>
                            <td class="column-actions">
                                <span class="spinner is-active"></span>
                                <button class="button button-small nuclen-cancel" data-task-id="gen_124">Cancel</button>
                            </td>
                        </tr>
                        <tr data-task-id="gen_125">
                            <td>2026-04-21 10:02</td>
                            <td>-</td>
                            <td class="column-id">gen_125</td>
                            <td class="column-type">Summary</td>
                            <td class="column-status">
                                <span class="nuclen-badge nuclen-badge-success">Completed</span>
                            </td>
                            <td class="column-progress">
                                <div class="nuclen-progress-container">
                                    <div class="nuclen-progress-bar">
                                        <div class="nuclen-progress-fill" style="width: 100%"></div>
                                    </div>
                                    <span class="nuclen-progress-text">100%</span>
                                </div>
                            </td>
                            <td class="column-details">Done</td>
                            <td class="column-actions">
                                <span class="nuclen-no-actions">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </body>
        </html>
    `,
        { url: 'http://localhost' }
    );

    global.window = dom.window as typeof global.window;
    global.document = dom.window.document;
    global.Element = dom.window.Element;
    global.HTMLElement = dom.window.HTMLElement;
    global.Event = dom.window.Event;
    global.fetch = vi.fn();

    global.ajaxurl = 'http://localhost/wp-admin/admin-ajax.php';
    global.nuclen_tasks = {
        nonce: 'test_nonce_123',
        i18n: {
            processing: 'Processing...',
            cancelling: 'Cancelling...',
            error: 'An error occurred',
            success: 'Operation successful',
        },
    };
};

describe('TasksManager', () => {
    let fetchMock: Mock;
    let consoleErrorSpy: any;

    const getActionCalls = (action: string) => {
        return fetchMock.mock.calls.filter(([, init]) => {
            const body = init?.body;
            return body instanceof URLSearchParams && body.get('action') === action;
        });
    };

    const getActionBody = (action: string): URLSearchParams => {
        const calls = getActionCalls(action);
        expect(calls).toHaveLength(1);
        return calls[0][1].body as URLSearchParams;
    };

    const createManager = async (
        recentCompletions: unknown[] = [],
        options: { clearFetch?: boolean } = {}
    ) => {
        const { clearFetch = true } = options;

        fetchMock.mockResolvedValueOnce(
            createFetchResponse({
                success: true,
                data: recentCompletions,
            })
        );

        const { default: TasksManager } = await import('../../src/admin/ts/tasks');
        const manager = new TasksManager();
        await flushPromises();

        if (clearFetch) {
            fetchMock.mockClear();
        }

        return manager;
    };

    beforeEach(() => {
        vi.resetModules();
        vi.useRealTimers();
        setupDOM();
        fetchMock = global.fetch as Mock;
        consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        global.window.confirm = vi.fn(() => true);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.clearAllMocks();
        consoleErrorSpy.mockRestore();
    });

    describe('Initialization', () => {
        it('checks for recent completions on page load', async () => {
            fetchMock.mockResolvedValueOnce(
                createFetchResponse({
                    success: true,
                    data: [{ task_id: 'gen_100', status: 'completed' }],
                })
            );

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();
            await flushPromises();

            expect(fetchMock).toHaveBeenCalledWith(
                'http://localhost/wp-admin/admin-ajax.php',
                expect.objectContaining({
                    method: 'POST',
                    body: expect.any(URLSearchParams),
                })
            );

            const firstBody = fetchMock.mock.calls[0][1].body as URLSearchParams;
            expect(firstBody.get('action')).toBe('nuclen_get_recent_completions');
            expect(firstBody.get('nonce')).toBe('test_nonce_123');
        });

        it('wires action handlers to the existing buttons', async () => {
            await createManager();

            fetchMock.mockResolvedValueOnce(
                createFetchResponse({
                    success: true,
                    data: { message: 'Task gen_123 has been queued for processing.' },
                })
            );

            const runButton = document.querySelector(
                '[data-task-id="gen_123"].nuclen-run-now'
            ) as HTMLButtonElement;

            runButton.click();
            await flushPromises();

            expect(getActionCalls('nuclen_run_task')).toHaveLength(1);
        });
    });

    describe('Run Task Action', () => {
        it('handles run task success', async () => {
            await createManager();

            fetchMock.mockResolvedValueOnce(
                createFetchResponse({
                    success: true,
                    data: { message: 'Task gen_123 has been queued for processing.' },
                })
            );

            const runButton = document.querySelector(
                '[data-task-id="gen_123"].nuclen-run-now'
            ) as HTMLButtonElement;

            runButton.click();

            expect(runButton.getAttribute('disabled')).toBe('disabled');
            expect(runButton.textContent).toBe('Processing...');

            await flushPromises();

            const body = getActionBody('nuclen_run_task');
            expect(body.get('task_id')).toBe('gen_123');
            expect(body.get('nonce')).toBe('test_nonce_123');

            const statusCell = document.querySelector('tr[data-task-id="gen_123"] .column-status');
            const actionsCell = document.querySelector('tr[data-task-id="gen_123"] .column-actions');

            expect(statusCell?.innerHTML).toContain('Processing');
            expect(actionsCell?.innerHTML).toContain('spinner is-active');
            expect(actionsCell?.querySelector('.nuclen-cancel-task')).not.toBeNull();
        });

        it('shows the generic error notice when run task fails', async () => {
            await createManager();

            fetchMock.mockResolvedValueOnce(
                createFetchResponse({
                    success: false,
                    data: 'Task is already processing',
                })
            );

            const runButton = document.querySelector(
                '[data-task-id="gen_123"].nuclen-run-now'
            ) as HTMLButtonElement;
            const originalText = runButton.textContent;

            runButton.click();
            await flushPromises();

            expect(runButton.textContent).toBe(originalText);
            expect(runButton.hasAttribute('disabled')).toBe(false);
            expect(runButton.classList.contains('disabled')).toBe(false);

            const notice = document.querySelector('.notice-error');
            expect(notice?.textContent).toContain('An error occurred');
            expect(consoleErrorSpy).toHaveBeenCalledWith(
                '[Nuclear Engagement]',
                'Task action failed:',
                expect.any(Error)
            );
        });

        it('does nothing when another task action is already in flight', async () => {
            const manager = await createManager();
            (manager as any).isProcessing = true;

            const runButton = document.querySelector(
                '[data-task-id="gen_123"].nuclen-run-now'
            ) as HTMLButtonElement;

            runButton.click();
            await flushPromises();

            expect(fetchMock).not.toHaveBeenCalled();
        });
    });

    describe('Cancel Task Action', () => {
        it('shows a confirmation dialog before cancelling the legacy task action', async () => {
            const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);
            await createManager();

            const cancelButton = document.querySelector(
                '[data-task-id="gen_124"].nuclen-cancel'
            ) as HTMLButtonElement;

            cancelButton.click();
            await flushPromises();

            expect(confirmSpy).toHaveBeenCalledWith('Are you sure you want to cancel this task?');
            expect(fetchMock).not.toHaveBeenCalled();

            confirmSpy.mockRestore();
        });

        it('handles legacy cancel task success', async () => {
            await createManager();

            fetchMock.mockResolvedValueOnce(
                createFetchResponse({
                    success: true,
                    data: { message: 'Task gen_124 has been cancelled.' },
                })
            );

            const cancelButton = document.querySelector(
                '[data-task-id="gen_124"].nuclen-cancel'
            ) as HTMLButtonElement;

            cancelButton.click();

            expect(cancelButton.getAttribute('disabled')).toBe('disabled');
            expect(cancelButton.textContent).toBe('Cancelling...');

            await flushPromises();

            const body = getActionBody('nuclen_cancel_task');
            expect(body.get('task_id')).toBe('gen_124');

            const statusCell = document.querySelector('tr[data-task-id="gen_124"] .column-status');
            const actionsCell = document.querySelector('tr[data-task-id="gen_124"] .column-actions');

            expect(statusCell?.innerHTML).toContain('Cancelled');
            expect(actionsCell?.querySelector('.nuclen-no-actions')).not.toBeNull();
        });

        it('logs and shows the generic error when the legacy cancel request fails', async () => {
            await createManager();

            fetchMock.mockRejectedValueOnce(new Error('Network error'));

            const cancelButton = document.querySelector(
                '[data-task-id="gen_124"].nuclen-cancel'
            ) as HTMLButtonElement;
            const originalText = cancelButton.textContent;

            cancelButton.click();
            await flushPromises();

            expect(consoleErrorSpy).toHaveBeenCalledWith(
                '[Nuclear Engagement]',
                'Task action failed:',
                expect.any(Error)
            );
            expect(cancelButton.textContent).toBe(originalText);

            const notice = document.querySelector('.notice-error');
            expect(notice?.textContent).toContain('An error occurred');
        });
    });

    describe('Cancel Generation Action (server-coordinated)', () => {
        const injectCancelButton = (taskId: string, cell: Element) => {
            cell.innerHTML = `
                <button class="button button-small button-link-delete nuclen-cancel-task"
                        data-task-id="${taskId}"
                        data-generation-id="${taskId}"
                        data-nonce="test_nonce_123">Cancel</button>
            `;

            return cell.querySelector('.nuclen-cancel-task') as HTMLButtonElement;
        };

        it('posts to nuclen_cancel_generation and shows refunded credits', async () => {
            const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true);
            const actions = document.querySelector('tr[data-task-id="gen_124"] .column-actions') as Element;
            const button = injectCancelButton('gen_124', actions);

            await createManager();

            fetchMock.mockResolvedValueOnce(
                createFetchResponse({
                    success: true,
                    data: {
                        refunded_credits: 12,
                        status: 'cancelled',
                        message: 'Generation cancelled. 12 credits refunded.',
                    },
                })
            );

            button.click();
            await flushPromises();

            expect(confirmSpy).toHaveBeenCalledWith(
                'Cancel this generation? Unused credits will be refunded.'
            );

            const body = getActionBody('nuclen_cancel_generation');
            expect(body.get('generation_id')).toBe('gen_124');
            expect(body.get('nonce')).toBe('test_nonce_123');

            const statusCell = document.querySelector('tr[data-task-id="gen_124"] .column-status');
            const detailsCell = document.querySelector('tr[data-task-id="gen_124"] td:nth-child(7)');
            const actionsCell = document.querySelector('tr[data-task-id="gen_124"] .column-actions');

            expect(statusCell?.innerHTML).toContain('Cancelled');
            expect(detailsCell?.textContent).toContain('12 credits refunded');
            expect(actionsCell?.querySelector('.nuclen-retry')).not.toBeNull();

            confirmSpy.mockRestore();
        });

        it('aborts server-coordinated cancel when the user declines the prompt', async () => {
            const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);
            const actions = document.querySelector('tr[data-task-id="gen_123"] .column-actions') as Element;
            const button = injectCancelButton('gen_123', actions);

            await createManager();

            button.click();
            await flushPromises();

            expect(confirmSpy).toHaveBeenCalled();
            expect(getActionCalls('nuclen_cancel_generation')).toHaveLength(0);

            confirmSpy.mockRestore();
        });

        it('keeps the row active when the server reports cancelling', async () => {
            const actions = document.querySelector('tr[data-task-id="gen_124"] .column-actions') as Element;
            const button = injectCancelButton('gen_124', actions);

            await createManager();

            fetchMock.mockResolvedValueOnce(
                createFetchResponse({
                    success: true,
                    data: {
                        refunded_credits: 0,
                        status: 'cancelling',
                        message: 'Cancellation requested. Waiting for the remote worker to stop.',
                    },
                })
            );

            button.click();
            await flushPromises();

            const statusCell = document.querySelector('tr[data-task-id="gen_124"] .column-status');
            const actionsCell = document.querySelector('tr[data-task-id="gen_124"] .column-actions');
            const notice = document.querySelector('.notice-info');

            expect(statusCell?.innerHTML).toContain('Processing');
            expect(statusCell?.innerHTML).not.toContain('Cancelled');
            expect(actionsCell?.querySelector('.nuclen-cancel-task')).not.toBeNull();
            expect(actionsCell?.textContent).toContain('Cancelling...');
            expect(notice?.textContent).toContain(
                'Cancellation requested. Waiting for the remote worker to stop.'
            );
        });
    });

    describe('UI Updates', () => {
        it('renders the expected status badges', async () => {
            const manager = await createManager();

            const cases = [
                ['pending', 'nuclen-badge-warning', 'Pending'],
                ['processing', 'nuclen-badge-info', 'Processing'],
                ['completed', 'nuclen-badge-success', 'Completed'],
                ['completed_with_errors', 'nuclen-badge-warning', 'Completed with Errors'],
                ['failed', 'nuclen-badge-error', 'Failed'],
                ['cancelled', 'nuclen-badge-default', 'Cancelled'],
            ] as const;

            for (const [status, className, text] of cases) {
                const badge = (manager as any).getStatusBadge(status);
                expect(badge).toContain(className);
                expect(badge).toContain(text);
            }
        });

        it('updates action buttons based on task status', async () => {
            const manager = await createManager();
            const row = document.querySelector('tr[data-task-id="gen_123"]') as HTMLElement;
            const actionsCell = row.querySelector('.column-actions') as HTMLElement;

            (manager as any).updateActionButtons(row, 'pending');
            expect(actionsCell.querySelector('.nuclen-run-now')).not.toBeNull();
            expect(actionsCell.querySelector('.nuclen-cancel-task')).not.toBeNull();

            (manager as any).updateActionButtons(row, 'processing');
            expect(actionsCell.innerHTML).toContain('spinner is-active');
            expect(actionsCell.querySelector('.nuclen-cancel-task')).not.toBeNull();
            expect(actionsCell.querySelector('.nuclen-run-now')).toBeNull();

            (manager as any).updateActionButtons(row, 'failed');
            expect(actionsCell.querySelector('.nuclen-retry')).not.toBeNull();

            (manager as any).updateActionButtons(row, 'completed');
            expect(actionsCell.querySelector('.nuclen-no-actions')).not.toBeNull();
            expect(actionsCell.querySelector('button')).toBeNull();
        });
    });

    describe('Notifications', () => {
        it('shows dismissible notices and auto-dismisses them after five seconds', async () => {
            const manager = await createManager();

            (manager as any).showNotice('Test success message', 'success');

            const successNotice = document.querySelector('.notice-success');
            expect(successNotice?.textContent).toContain('Test success message');

            const dismissButton = successNotice?.querySelector('.notice-dismiss') as HTMLButtonElement;
            dismissButton.click();
            expect(document.querySelector('.notice-success')).toBeNull();

            vi.useFakeTimers();

            (manager as any).showNotice('Test auto-dismiss', 'info');
            expect(document.querySelector('.notice-info')).not.toBeNull();

            vi.advanceTimersByTime(5000);
            expect(document.querySelector('.notice-info')).toBeNull();

            vi.useRealTimers();
        });

        it('does not surface recent completion notices while that feature is disabled', async () => {
            fetchMock.mockResolvedValueOnce(
                createFetchResponse({
                    success: true,
                    data: [
                        { task_id: 'gen_200', status: 'completed' },
                        { task_id: 'gen_201', status: 'completed_with_errors', fail_count: 3 },
                        { task_id: 'gen_202', status: 'failed' },
                    ],
                })
            );

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();
            await flushPromises();

            expect(document.querySelector('.notice-success')).toBeNull();
            expect(document.querySelector('.notice-info')).toBeNull();
            expect(document.querySelector('.notice-error')).toBeNull();
            expect(consoleErrorSpy).not.toHaveBeenCalled();
        });
    });

    describe('Edge Cases', () => {
        it('ignores buttons that are missing a task id', async () => {
            const button = document.createElement('button');
            button.className = 'nuclen-run-now';
            document.body.appendChild(button);

            await createManager();

            button.click();
            await flushPromises();

            expect(fetchMock).not.toHaveBeenCalled();
        });

        it('ignores malformed recent-completion responses', async () => {
            fetchMock.mockResolvedValueOnce(createFetchResponse({ invalid: 'response' }));

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();
            await flushPromises();

            expect(consoleErrorSpy).not.toHaveBeenCalled();
        });

        it('logs JSON parse failures from recent-completion polling', async () => {
            fetchMock.mockResolvedValueOnce({
                json: () => Promise.reject(new Error('Invalid JSON')),
            });

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();
            await flushPromises();

            expect(consoleErrorSpy).toHaveBeenCalledWith(
                '[Nuclear Engagement]',
                'Failed to check recent completions:',
                expect.any(Error)
            );
        });
    });
});
