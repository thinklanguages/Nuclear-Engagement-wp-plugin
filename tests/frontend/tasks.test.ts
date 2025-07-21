/**
 * tasks.test.ts - Frontend tests for tasks page functionality
 */

import { describe, it, expect, beforeEach, afterEach, vi, Mock } from 'vitest';
import { JSDOM } from 'jsdom';

// Mock global variables
declare global {
    var ajaxurl: string;
    var nuclen_tasks: {
        nonce: string;
        i18n: {
            running: string;
            cancelling: string;
            error: string;
            success: string;
        };
    };
}

// Setup DOM and globals before importing the module
const setupDOM = () => {
    const dom = new JSDOM(`
        <!DOCTYPE html>
        <html>
        <body>
            <div class="wrap">
                <h1>Nuclear Engagement - Tasks</h1>
                <table class="wp-list-table">
                    <tbody>
                        <tr data-task-id="gen_123">
                            <td class="column-id">gen_123</td>
                            <td class="column-type">Summary</td>
                            <td class="column-status">
                                <span class="nuclen-badge nuclen-badge-warning">Pending</span>
                            </td>
                            <td class="column-progress">0%</td>
                            <td class="column-actions">
                                <button class="button button-small nuclen-run-now" data-task-id="gen_123">Run Now</button>
                                <button class="button button-small nuclen-cancel" data-task-id="gen_123">Cancel</button>
                            </td>
                        </tr>
                        <tr data-task-id="gen_124">
                            <td class="column-id">gen_124</td>
                            <td class="column-type">Quiz</td>
                            <td class="column-status">
                                <span class="nuclen-badge nuclen-badge-info">Processing</span>
                            </td>
                            <td class="column-progress">50%</td>
                            <td class="column-actions">
                                <span class="spinner is-active"></span>
                                <button class="button button-small nuclen-cancel" data-task-id="gen_124">Cancel</button>
                            </td>
                        </tr>
                        <tr data-task-id="gen_125">
                            <td class="column-id">gen_125</td>
                            <td class="column-type">Summary</td>
                            <td class="column-status">
                                <span class="nuclen-badge nuclen-badge-success">Completed</span>
                            </td>
                            <td class="column-progress">100%</td>
                            <td class="column-actions">
                                <span class="nuclen-no-actions">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </body>
        </html>
    `, { url: 'http://localhost' });

    // Set up globals
    global.window = dom.window as any;
    global.document = dom.window.document;
    global.Element = dom.window.Element;
    global.HTMLElement = dom.window.HTMLElement;
    global.Event = dom.window.Event;
    global.fetch = vi.fn();
    
    // Mock WordPress globals
    global.ajaxurl = 'http://localhost/wp-admin/admin-ajax.php';
    global.nuclen_tasks = {
        nonce: 'test_nonce_123',
        i18n: {
            running: 'Running...',
            cancelling: 'Cancelling...',
            error: 'An error occurred',
            success: 'Operation successful'
        }
    };
};

describe('TasksManager', () => {
    let fetchMock: Mock;
    let consoleErrorSpy: any;

    beforeEach(() => {
        setupDOM();
        fetchMock = global.fetch as Mock;
        consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        // Mock window.confirm to avoid jsdom not implemented error
        global.window.confirm = vi.fn(() => true);
    });

    afterEach(() => {
        vi.clearAllMocks();
        consoleErrorSpy.mockRestore();
    });

    describe('Initialization', () => {
        it('should check for recent completions on page load', async () => {
            // Mock successful response with completions
            fetchMock.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    data: [
                        {
                            task_id: 'gen_100',
                            status: 'completed'
                        }
                    ]
                })
            });

            // Import after setup
            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            // Wait for async operations
            await new Promise(resolve => setTimeout(resolve, 0));

            // Verify fetch was called with correct parameters
            expect(fetchMock).toHaveBeenCalledWith(
                'http://localhost/wp-admin/admin-ajax.php',
                expect.objectContaining({
                    method: 'POST',
                    body: expect.any(URLSearchParams)
                })
            );

            const fetchCall = fetchMock.mock.calls[0];
            const body = fetchCall[1].body as URLSearchParams;
            expect(body.get('action')).toBe('nuclen_get_recent_completions');
            expect(body.get('nonce')).toBe('test_nonce_123');
        });

        it('should attach event handlers to action buttons', async () => {
            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            // Check run now button
            const runButton = document.querySelector('.nuclen-run-now') as HTMLButtonElement;
            expect(runButton).toBeDefined();
            expect(runButton.onclick).toBeDefined();

            // Check cancel button
            const cancelButton = document.querySelector('.nuclen-cancel') as HTMLButtonElement;
            expect(cancelButton).toBeDefined();
            expect(cancelButton.onclick).toBeDefined();
        });
    });

    describe('Run Task Action', () => {
        it('should handle run task successfully', async () => {
            // Mock successful response
            fetchMock.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    data: { message: 'Task gen_123 has been queued for processing.' }
                })
            });

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            // Trigger run task
            const runButton = document.querySelector('[data-task-id="gen_123"].nuclen-run-now') as HTMLButtonElement;
            runButton.click();

            // Wait for async operations
            await new Promise(resolve => setTimeout(resolve, 100));

            // Verify button was disabled during processing
            expect(runButton.getAttribute('disabled')).toBe('disabled');
            expect(runButton.classList.contains('disabled')).toBe(true);

            // Verify fetch was called correctly
            expect(fetchMock).toHaveBeenCalledWith(
                'http://localhost/wp-admin/admin-ajax.php',
                expect.objectContaining({
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                })
            );

            const body = fetchMock.mock.calls[0][1].body as URLSearchParams;
            expect(body.get('action')).toBe('nuclen_run_task');
            expect(body.get('task_id')).toBe('gen_123');
            expect(body.get('nonce')).toBe('test_nonce_123');

            // Verify status was updated
            const statusCell = document.querySelector('tr[data-task-id="gen_123"] .column-status');
            expect(statusCell?.innerHTML).toContain('Processing');
        });

        it('should handle run task error', async () => {
            // Mock error response
            fetchMock.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: false,
                    data: 'Task is already processing'
                })
            });

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            const runButton = document.querySelector('[data-task-id="gen_123"].nuclen-run-now') as HTMLButtonElement;
            const originalText = runButton.textContent;
            runButton.click();

            await new Promise(resolve => setTimeout(resolve, 100));

            // Verify button was restored
            expect(runButton.textContent).toBe(originalText);
            expect(runButton.hasAttribute('disabled')).toBe(false);
            expect(runButton.classList.contains('disabled')).toBe(false);

            // Verify error notice was shown
            const notice = document.querySelector('.notice-error');
            expect(notice).toBeDefined();
            expect(notice?.textContent).toContain('Task is already processing');
        });

        it('should not process if already processing', async () => {
            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            const manager = new TasksManager();
            
            // Set processing flag
            (manager as any).isProcessing = true;

            const runButton = document.querySelector('[data-task-id="gen_123"].nuclen-run-now') as HTMLButtonElement;
            runButton.click();

            // Verify fetch was not called
            expect(fetchMock).not.toHaveBeenCalled();
        });
    });

    describe('Cancel Task Action', () => {
        it('should show confirmation dialog before cancelling', async () => {
            const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(false);

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            const cancelButton = document.querySelector('[data-task-id="gen_124"].nuclen-cancel') as HTMLButtonElement;
            cancelButton.click();

            expect(confirmSpy).toHaveBeenCalledWith('Are you sure you want to cancel this task?');
            expect(fetchMock).not.toHaveBeenCalled();

            confirmSpy.mockRestore();
        });

        it('should handle cancel task successfully', async () => {
            vi.spyOn(window, 'confirm').mockReturnValue(true);

            // Mock successful response
            fetchMock.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    data: { message: 'Task gen_124 has been cancelled.' }
                })
            });

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            const cancelButton = document.querySelector('[data-task-id="gen_124"].nuclen-cancel') as HTMLButtonElement;
            cancelButton.click();

            await new Promise(resolve => setTimeout(resolve, 100));

            // Verify fetch was called
            const body = fetchMock.mock.calls[0][1].body as URLSearchParams;
            expect(body.get('action')).toBe('nuclen_cancel_task');
            expect(body.get('task_id')).toBe('gen_124');

            // Verify status was updated
            const statusCell = document.querySelector('tr[data-task-id="gen_124"] .column-status');
            expect(statusCell?.innerHTML).toContain('Cancelled');

            // Verify actions were removed
            const actionsCell = document.querySelector('tr[data-task-id="gen_124"] .column-actions');
            expect(actionsCell?.innerHTML).toContain('—');
        });

        it('should handle network errors gracefully', async () => {
            vi.spyOn(window, 'confirm').mockReturnValue(true);
            fetchMock.mockRejectedValueOnce(new Error('Network error'));

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            const cancelButton = document.querySelector('[data-task-id="gen_124"].nuclen-cancel') as HTMLButtonElement;
            const originalText = cancelButton.textContent;
            cancelButton.click();

            await new Promise(resolve => setTimeout(resolve, 100));

            // Verify error was logged
            expect(consoleErrorSpy).toHaveBeenCalledWith('Task action failed:', expect.any(Error));

            // Verify button was restored
            expect(cancelButton.textContent).toBe(originalText);

            // Verify error notice
            const notice = document.querySelector('.notice-error');
            expect(notice).toBeDefined();
            expect(notice?.textContent).toContain('Network error');
        });
    });

    describe('UI Updates', () => {
        it('should update status badge correctly', async () => {
            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            const manager = new TasksManager();

            const badges = {
                'pending': 'nuclen-badge-warning',
                'processing': 'nuclen-badge-info',
                'completed': 'nuclen-badge-success',
                'failed': 'nuclen-badge-error',
                'cancelled': 'nuclen-badge-default'
            };

            for (const [status, className] of Object.entries(badges)) {
                const badge = (manager as any).getStatusBadge(status);
                expect(badge).toContain(className);
                expect(badge).toContain(status.charAt(0).toUpperCase() + status.slice(1));
            }
        });

        it('should update action buttons based on status', async () => {
            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            const manager = new TasksManager();

            const row = document.querySelector('tr[data-task-id="gen_123"]') as HTMLElement;
            const actionsCell = row.querySelector('.column-actions') as HTMLElement;

            // Test pending status
            (manager as any).updateActionButtons(row, 'pending');
            expect(actionsCell.innerHTML).toContain('Run Now');
            expect(actionsCell.innerHTML).toContain('Cancel');

            // Test processing status
            (manager as any).updateActionButtons(row, 'processing');
            expect(actionsCell.innerHTML).toContain('spinner is-active');
            expect(actionsCell.innerHTML).toContain('Cancel');
            expect(actionsCell.innerHTML).not.toContain('Run Now');

            // Test completed status
            (manager as any).updateActionButtons(row, 'completed');
            expect(actionsCell.innerHTML).toContain('—');
            expect(actionsCell.innerHTML).not.toContain('button');
        });
    });

    describe('Notifications', () => {
        it('should show and auto-dismiss notices', async () => {
            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            const manager = new TasksManager();

            // Show success notice
            (manager as any).showNotice('Test success message', 'success');

            const notice = document.querySelector('.notice-success');
            expect(notice).toBeDefined();
            expect(notice?.textContent).toContain('Test success message');

            // Test dismiss button
            const dismissButton = notice?.querySelector('.notice-dismiss') as HTMLButtonElement;
            dismissButton.click();
            expect(document.querySelector('.notice-success')).toBeNull();

            // Test auto-dismiss
            (manager as any).showNotice('Test auto-dismiss', 'info');
            const autoNotice = document.querySelector('.notice-info');
            expect(autoNotice).toBeDefined();

            // Wait for auto-dismiss (using shorter timeout for tests)
            vi.useFakeTimers();
            vi.advanceTimersByTime(5001);
            expect(document.querySelector('.notice-info')).toBeNull();
            vi.useRealTimers();
        });

        it('should handle recent completions notifications', async () => {
            // Mock response with various completion statuses
            fetchMock.mockResolvedValueOnce({
                json: () => Promise.resolve({
                    success: true,
                    data: [
                        {
                            task_id: 'gen_200',
                            status: 'completed'
                        },
                        {
                            task_id: 'gen_201',
                            status: 'completed_with_errors',
                            fail_count: 3
                        },
                        {
                            task_id: 'gen_202',
                            status: 'failed'
                        }
                    ]
                })
            });

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            await new Promise(resolve => setTimeout(resolve, 100));

            // Check notices were created
            const successNotice = document.querySelector('.notice-success');
            expect(successNotice?.textContent).toContain('gen_200 completed successfully');

            const infoNotice = document.querySelector('.notice-info');
            expect(infoNotice?.textContent).toContain('gen_201 completed with 3 errors');

            const errorNotice = document.querySelector('.notice-error');
            expect(errorNotice?.textContent).toContain('gen_202 failed');
        });
    });

    describe('Edge Cases', () => {
        it('should handle missing task ID gracefully', async () => {
            // Create button without task ID
            const button = document.createElement('button');
            button.className = 'nuclen-run-now';
            document.body.appendChild(button);

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            button.click();

            // Should not make any API calls
            expect(fetchMock).not.toHaveBeenCalled();
        });

        it('should handle malformed API responses', async () => {
            fetchMock.mockResolvedValueOnce({
                json: () => Promise.resolve({ invalid: 'response' })
            });

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            // Call checkRecentCompletions on the manager instance
            // Note: We need to wait for the initial call to complete first
            await new Promise(resolve => setTimeout(resolve, 100));

            // Should handle gracefully without throwing
            expect(consoleErrorSpy).not.toHaveBeenCalled();
        });

        it('should handle JSON parse errors', async () => {
            fetchMock.mockResolvedValueOnce({
                json: () => Promise.reject(new Error('Invalid JSON'))
            });

            const { default: TasksManager } = await import('../../src/admin/ts/tasks');
            new TasksManager();

            // Call checkRecentCompletions on the manager instance
            // Note: We need to wait for the initial call to complete first
            await new Promise(resolve => setTimeout(resolve, 100));

            expect(consoleErrorSpy).toHaveBeenCalledWith(
                'Failed to check recent completions:',
                expect.any(Error)
            );
        });
    });
});