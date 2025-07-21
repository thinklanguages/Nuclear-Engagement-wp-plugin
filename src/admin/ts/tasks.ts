/**
 * Nuclear Engagement Tasks Page JavaScript
 */

import { error } from '../../shared/logger';

declare const ajaxurl: string;
declare const nuclen_tasks: {
    nonce: string;
    i18n: {
        running: string;
        cancelling: string;
        error: string;
        success: string;
    };
};

class TasksManager {
    private isProcessing = false;
    private isRefreshing = false;
    private currentPage = 1;

    constructor() {
        this.init();
    }

    private init(): void {
        // Check for recent completions on page load
        this.checkRecentCompletions();
        
        // Attach event listeners to action buttons
        this.attachActionHandlers();
        
        // Convert the refresh link to a button with AJAX functionality
        this.setupRefreshButton();
    }

    private attachActionHandlers(): void {
        // Handle run now buttons
        document.querySelectorAll('.nuclen-run-now').forEach(button => {
            button.addEventListener('click', (e) => this.handleRunTask(e));
        });

        // Handle cancel buttons
        document.querySelectorAll('.nuclen-cancel').forEach(button => {
            button.addEventListener('click', (e) => this.handleCancelTask(e));
        });
    }

    private setupRefreshButton(): void {
        // Find the refresh link
        const refreshLink = document.querySelector('a[href*="refresh=1"]');
        if (!refreshLink) return;

        // Get current page from URL if available
        const urlParams = new URLSearchParams(window.location.search);
        this.currentPage = parseInt(urlParams.get('paged') || '1', 10);

        // Convert to button
        const refreshButton = document.createElement('button');
        refreshButton.className = 'button button-small nuclen-refresh-button';
        refreshButton.innerHTML = '<span class="dashicons dashicons-update"></span> Refresh';
        refreshButton.title = 'Refresh task data';
        
        // Replace link with button
        refreshLink.parentNode?.replaceChild(refreshButton, refreshLink);
        
        // Add click handler
        refreshButton.addEventListener('click', (e) => {
            e.preventDefault();
            this.refreshTasksData();
        });
    }

    private refreshTasksData(): void {
        // Just reload the page to get fresh data
        window.location.reload();
    }

    private updateTasksTable(tasks: any[]): void {
        const tbody = document.querySelector('.nuclen-tasks-table tbody');
        if (!tbody || tasks.length === 0) return;

        // Update each task row
        tasks.forEach(task => {
            const row = tbody.querySelector(`tr[data-task-id="${task.id}"]`);
            if (!row) return;

            // Update status
            const statusCell = row.querySelector('.column-status');
            if (statusCell) {
                statusCell.innerHTML = this.getStatusBadge(task.status);
            }

            // Update progress
            const progressCell = row.querySelector('.column-progress');
            if (progressCell) {
                progressCell.innerHTML = `
                    <div class="nuclen-progress-container">
                        <div class="nuclen-progress-bar">
                            <div class="nuclen-progress-fill" style="width: ${task.progress}%"></div>
                        </div>
                        <span class="nuclen-progress-text">${task.progress}%</span>
                    </div>
                `;
            }

            // Update details
            const detailsCell = row.querySelector('td:nth-child(5)'); // Details column
            if (detailsCell) {
                let detailsHTML = task.details;
                if (task.failed > 0) {
                    detailsHTML += `<br><span class="nuclen-error-text">${task.failed} failed</span>`;
                }
                detailsCell.innerHTML = detailsHTML;
            }


            // Update actions based on status
            this.updateActionButtons(row as HTMLElement, task.status);
        });
    }

    private async handleRunTask(event: Event): Promise<void> {
        event.preventDefault();
        const button = event.currentTarget as HTMLElement;
        const taskId = button.getAttribute('data-task-id');
        
        if (!taskId || this.isProcessing) return;
        
        await this.executeTaskAction('run_task', taskId, button);
    }

    private async handleCancelTask(event: Event): Promise<void> {
        event.preventDefault();
        const button = event.currentTarget as HTMLElement;
        const taskId = button.getAttribute('data-task-id');
        
        if (!taskId || this.isProcessing) return;
        
        if (!window.confirm('Are you sure you want to cancel this task?')) {
            return;
        }
        
        await this.executeTaskAction('cancel_task', taskId, button);
    }

    private async executeTaskAction(action: string, taskId: string, button: HTMLElement): Promise<void> {
        this.isProcessing = true;
        const originalText = button.textContent || '';
        const row = button.closest('tr');
        
        try {
            // Update button state
            button.classList.add('disabled');
            button.setAttribute('disabled', 'disabled');
            
            if (action === 'run_task') {
                button.textContent = nuclen_tasks.i18n.running || 'Running...';
            } else {
                button.textContent = nuclen_tasks.i18n.cancelling || 'Cancelling...';
            }

            // Make AJAX request
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: `nuclen_${action}`,
                    task_id: taskId,
                    nonce: nuclen_tasks.nonce
                })
            });

            const result = await response.json();

            if (result.success) {
                // Show success message
                this.showNotice(result.data.message || nuclen_tasks.i18n.success, 'success');
                
                // Show additional notice about refreshing
                if (action === 'run_task') {
                    setTimeout(() => {
                        this.showNotice('Task is now processing. Refresh later to see the latest progress.', 'info');
                    }, 500);
                }
                
                // Update the row status locally
                if (row) {
                    // For run_task, immediately show processing status
                    if (action === 'run_task') {
                        const statusCell = row.querySelector('.column-status');
                        if (statusCell) {
                            statusCell.innerHTML = this.getStatusBadge('processing');
                        }
                        // Update action buttons to show spinner
                        this.updateActionButtons(row as HTMLElement, 'processing');
                    } else if (action === 'cancel_task') {
                        // For cancel, show cancelled status
                        const statusCell = row.querySelector('.column-status');
                        if (statusCell) {
                            statusCell.innerHTML = this.getStatusBadge('cancelled');
                        }
                        // Remove action buttons
                        const actionsCell = row.querySelector('.column-actions');
                        if (actionsCell) {
                            actionsCell.innerHTML = '<span class="nuclen-no-actions">—</span>';
                        }
                    }
                }
            } else {
                throw new Error(result.data?.message || result.data || nuclen_tasks.i18n.error);
            }

        } catch (err) {
            error('Task action failed:', err);
            this.showNotice(err instanceof Error ? err.message : nuclen_tasks.i18n.error, 'error');
            
            // Restore button state
            button.textContent = originalText;
            button.classList.remove('disabled');
            button.removeAttribute('disabled');
        } finally {
            this.isProcessing = false;
        }
    }

    private getStatusBadge(status: string): string {
        const badges: Record<string, string> = {
            'pending': '<span class="nuclen-badge nuclen-badge-warning">Pending</span>',
            'processing': '<span class="nuclen-badge nuclen-badge-info">Processing</span>',
            'completed': '<span class="nuclen-badge nuclen-badge-success">Completed</span>',
            'completed_with_errors': '<span class="nuclen-badge nuclen-badge-warning">Completed with Errors</span>',
            'failed': '<span class="nuclen-badge nuclen-badge-error">Failed</span>',
            'cancelled': '<span class="nuclen-badge nuclen-badge-default">Cancelled</span>'
        };
        
        return badges[status] || `<span class="nuclen-badge nuclen-badge-default">${status}</span>`;
    }

    private updateActionButtons(row: HTMLElement, status: string): void {
        const actionsCell = row.querySelector('.column-actions');
        if (!actionsCell) return;

        // Clear existing actions
        actionsCell.innerHTML = '';

        if (status === 'pending') {
            const taskId = row.getAttribute('data-task-id');
            if (taskId) {
                actionsCell.innerHTML = `
                    <button class="button button-small nuclen-run-now" data-task-id="${taskId}">
                        Run Now
                    </button>
                    <button class="button button-small nuclen-cancel" data-task-id="${taskId}">
                        Cancel
                    </button>
                `;
                
                // Re-attach event handlers
                actionsCell.querySelector('.nuclen-run-now')?.addEventListener('click', (e) => this.handleRunTask(e));
                actionsCell.querySelector('.nuclen-cancel')?.addEventListener('click', (e) => this.handleCancelTask(e));
            }
        } else if (status === 'processing') {
            const taskId = row.getAttribute('data-task-id');
            if (taskId) {
                actionsCell.innerHTML = `
                    <span class="spinner is-active"></span>
                    <button class="button button-small nuclen-cancel" data-task-id="${taskId}">
                        Cancel
                    </button>
                `;
                // Re-attach cancel event handler
                actionsCell.querySelector('.nuclen-cancel')?.addEventListener('click', (e) => this.handleCancelTask(e));
            }
        } else {
            actionsCell.innerHTML = '<span class="nuclen-no-actions">—</span>';
        }
    }

    private showNotice(message: string, type: 'success' | 'error' | 'info' = 'info'): void {
        // Check if there's an existing notice container
        let noticeContainer = document.querySelector('.nuclen-tasks-notices');
        if (!noticeContainer) {
            // Create notice container
            noticeContainer = document.createElement('div');
            noticeContainer.className = 'nuclen-tasks-notices';
            const pageTitle = document.querySelector('.wrap h1');
            if (pageTitle) {
                pageTitle.insertAdjacentElement('afterend', noticeContainer);
            }
        }

        // Create notice element
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.innerHTML = `
            <p>${message}</p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
        `;

        // Add to container
        noticeContainer.appendChild(notice);

        // Handle dismiss
        notice.querySelector('.notice-dismiss')?.addEventListener('click', () => {
            notice.remove();
        });

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            notice.remove();
        }, 5000);
    }

    
    private async checkRecentCompletions(): Promise<void> {
        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'nuclen_get_recent_completions',
                    nonce: nuclen_tasks.nonce
                })
            });

            const result = await response.json();
            
            if (result.success && result.data && result.data.length > 0) {
                // Show notifications for recent completions
                result.data.forEach((completion: any) => {
                    let message: string;
                    let type: 'success' | 'error' | 'info';
                    
                    if (completion.status === 'completed') {
                        message = `Generation ${completion.task_id} completed successfully!`;
                        type = 'success';
                    } else if (completion.status === 'completed_with_errors') {
                        const failCount = completion.fail_count || 'some';
                        message = `Generation ${completion.task_id} completed with ${failCount} errors. Check individual posts for details.`;
                        type = 'info';
                    } else if (completion.status === 'failed') {
                        message = `Generation ${completion.task_id} failed.`;
                        type = 'error';
                    } else {
                        return; // Skip unknown statuses
                    }
                    
                    // Disabled for now - will be re-enabled later
                    // this.showNotice(message, type);
                });
            }
        } catch (err) {
            error('Failed to check recent completions:', err);
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new TasksManager();
});

// Export for testing
export default TasksManager;