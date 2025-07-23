/**
 * Nuclear Engagement Tasks Page JavaScript
 */

import { error, log } from '../../shared/logger';

declare const ajaxurl: string;
declare const nuclen_tasks: {
    nonce: string;
    i18n: {
        processing: string;
        cancelling: string;
        error: string;
        success: string;
    };
};

interface TaskData {
    id: string;
    workflow_type: string;
    status: string;
    progress: number;
    details: string;
    created_at: string;
    scheduled_at?: string;
    failed?: number;
}

interface PollingConfig {
    initialInterval: number;
    intervals: Array<{ after: number; interval: number }>;
    maxInterval: number;
}

class TasksManager {
    private isProcessing = false;
    private isRefreshing = false;
    private currentPage = 1;
    
    // Polling configuration
    private pollingTimer: number | null = null;
    private pollingStartTime: number = 0;
    private isPageVisible = true;
    private lastTasksData: Map<string, TaskData> = new Map();
    private pollingConfig: PollingConfig = {
        initialInterval: 15000, // 15 seconds
        intervals: [
            { after: 60000, interval: 30000 },    // After 1 minute, poll every 30s
            { after: 300000, interval: 60000 },   // After 5 minutes, poll every 60s
            { after: 600000, interval: 120000 },  // After 10 minutes, poll every 2 minutes
        ],
        maxInterval: 300000, // Max 5 minutes
    };
    
    // Track active tasks for smart polling
    private activeTasks = new Set<string>();
    
    // Auto-refresh UI elements
    private pollingIndicator: HTMLElement | null = null;
    private autoRefreshBanner: HTMLElement | null = null;
    private nextPollTime: number = 0;
    private countdownInterval: number | null = null;
    
    // Bound event handlers for cleanup
    private handleVisibilityChange = this.onVisibilityChange.bind(this);
    private handleWindowBlur = this.onWindowBlur.bind(this);
    private handleWindowFocus = this.onWindowFocus.bind(this);

    constructor() {
        console.log('[Nuclear Engagement] TasksManager initializing...');
        this.init();
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });
    }

    private init(): void {
        console.log('[Nuclear Engagement] Starting initialization...');
        
        // Check for recent completions on page load
        this.checkRecentCompletions();
        
        // Attach event listeners to action buttons
        this.attachActionHandlers();
        
        // Convert the refresh link to a button with AJAX functionality
        this.setupRefreshButton();
        
        // Setup page visibility handling
        this.setupPageVisibilityHandling();
        
        // Initialize task tracking
        this.initializeTaskTracking();
        
        console.log('[Nuclear Engagement] Creating polling indicator...');
        // Create polling indicator
        this.createPollingIndicator();
        
        console.log('[Nuclear Engagement] Starting smart polling...');
        // Start smart polling
        this.startSmartPolling();
    }

    private setupPageVisibilityHandling(): void {
        // Use Page Visibility API to pause polling when tab is hidden
        document.addEventListener('visibilitychange', this.handleVisibilityChange);
        
        // Also handle window focus/blur as backup
        window.addEventListener('blur', this.handleWindowBlur);
        window.addEventListener('focus', this.handleWindowFocus);
    }
    
    private onVisibilityChange(): void {
        this.isPageVisible = !document.hidden;
        log(`Page visibility changed: ${this.isPageVisible ? 'visible' : 'hidden'}`);
        
        if (this.isPageVisible) {
            // Resume polling when page becomes visible
            if (this.activeTasks.size > 0) {
                this.startSmartPolling();
            }
        } else {
            // Pause polling when page is hidden
            this.stopPolling();
        }
    }
    
    private onWindowBlur(): void {
        this.isPageVisible = false;
        this.stopPolling();
    }
    
    private onWindowFocus(): void {
        this.isPageVisible = true;
        if (this.activeTasks.size > 0) {
            // Immediately refresh when window regains focus
            this.refreshTasksData().catch(err => {
                error('Failed to refresh on focus:', err);
            });
            this.startSmartPolling();
        }
    }
    
    private initializeTaskTracking(): void {
        // Track all current tasks and their states
        const rows = document.querySelectorAll('.nuclen-tasks-table tbody tr');
        rows.forEach(row => {
            const taskId = row.getAttribute('data-task-id');
            const statusCell = row.querySelector('.column-status');
            if (taskId && statusCell) {
                const statusText = statusCell.textContent?.toLowerCase() || '';
                
                // Track as active if processing, pending, or scheduled
                if (statusText.includes('processing') || statusText.includes('pending') || statusText.includes('scheduled')) {
                    this.activeTasks.add(taskId);
                }
                
                // Store initial task data
                this.lastTasksData.set(taskId, this.extractTaskDataFromRow(row as HTMLElement));
            }
        });
        
        log(`Initialized task tracking: ${this.activeTasks.size} active tasks`);
    }
    
    private extractTaskDataFromRow(row: HTMLElement): TaskData {
        const taskId = row.getAttribute('data-task-id') || '';
        const statusCell = row.querySelector('.column-status');
        const progressCell = row.querySelector('.column-progress');
        const detailsCell = row.querySelector('td:nth-child(7)'); // Details column is now 7th
        
        // Extract progress number from progress bar
        let progress = 0;
        const progressText = progressCell?.querySelector('.nuclen-progress-text')?.textContent;
        if (progressText) {
            const match = progressText.match(/(\d+)%/);
            if (match) {
                progress = parseInt(match[1], 10);
            }
        }
        
        return {
            id: taskId,
            workflow_type: row.querySelector('td:nth-child(4)')?.textContent || '', // Type column is now 4th
            status: this.extractStatusFromBadge(statusCell?.innerHTML || ''),
            progress: progress,
            details: detailsCell?.innerHTML || '',
            created_at: row.querySelector('td:nth-child(1)')?.textContent || '',
            scheduled_at: row.querySelector('td:nth-child(2)')?.textContent || '',
            failed: this.extractFailedCount(detailsCell?.innerHTML || ''),
        };
    }
    
    private extractStatusFromBadge(badgeHtml: string): string {
        // Extract status from badge class
        const match = badgeHtml.match(/nuclen-badge-(\w+)/);
        if (match) {
            const badgeClass = match[1];
            switch (badgeClass) {
                case 'warning':
                    return badgeHtml.includes('Pending') ? 'pending' : 'completed_with_errors';
                case 'info':
                    return 'processing';
                case 'success':
                    return 'completed';
                case 'error':
                    return 'failed';
                default:
                    return 'cancelled';
            }
        }
        return 'unknown';
    }
    
    private extractFailedCount(detailsHtml: string): number {
        const match = detailsHtml.match(/(\d+)\s+failed/);
        return match ? parseInt(match[1], 10) : 0;
    }
    
    private cleanup(): void {
        // Stop polling
        this.stopPolling();
        
        // Clear countdown
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
        
        // Remove polling indicator
        if (this.pollingIndicator) {
            this.pollingIndicator.remove();
        }
        
        // Remove auto-refresh banner
        if (this.autoRefreshBanner) {
            this.autoRefreshBanner.remove();
        }
        
        // Clear stored data to free memory
        this.lastTasksData.clear();
        this.activeTasks.clear();
        
        // Remove event listeners
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        window.removeEventListener('blur', this.handleWindowBlur);
        window.removeEventListener('focus', this.handleWindowFocus);
    }
    
    private startSmartPolling(): void {
        // Don't start if page is not visible or no active tasks
        if (!this.isPageVisible || this.activeTasks.size === 0) {
            this.updatePollingIndicator(false);
            return;
        }
        
        // Clear any existing timer
        this.stopPolling();
        
        // Record polling start time
        if (this.pollingStartTime === 0) {
            this.pollingStartTime = Date.now();
        }
        
        // Calculate appropriate interval based on elapsed time
        const elapsedTime = Date.now() - this.pollingStartTime;
        let interval = this.pollingConfig.initialInterval;
        
        for (const config of this.pollingConfig.intervals) {
            if (elapsedTime > config.after) {
                interval = config.interval;
            }
        }
        
        // Cap at max interval
        interval = Math.min(interval, this.pollingConfig.maxInterval);
        
        log(`Starting smart polling with interval: ${interval / 1000}s, active tasks: ${this.activeTasks.size}`);
        
        // Update UI
        this.nextPollTime = Date.now() + interval;
        this.updatePollingIndicator(true, interval);
        this.startCountdown();
        
        // Schedule next poll
        this.pollingTimer = window.setTimeout(() => {
            this.pollForUpdates();
        }, interval);
    }
    
    private stopPolling(): void {
        if (this.pollingTimer) {
            clearTimeout(this.pollingTimer);
            this.pollingTimer = null;
            log('Polling stopped');
        }
        
        // Stop countdown
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
        
        // Update UI
        this.updatePollingIndicator(false);
    }
    
    private async pollForUpdates(): Promise<void> {
        if (!this.isPageVisible || this.isRefreshing) {
            return;
        }
        
        try {
            log('Polling for task updates...');
            
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'nuclen_refresh_tasks_data',
                    nonce: nuclen_tasks.nonce,
                    page: this.currentPage.toString(),
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result && result.success && result.data && Array.isArray(result.data.tasks)) {
                this.updateTasksFromPolling(result.data.tasks);
                
                // Only show update indicator if tasks were actually updated
                if (result.data.tasks.length > 0) {
                    this.showUpdateIndicator();
                }
            }
        } catch (err) {
            error('Failed to poll for updates:', err);
            // Don't stop polling on error, just skip this iteration
        } finally {
            // Continue polling if there are still active tasks
            if (this.activeTasks.size > 0 && this.isPageVisible) {
                this.startSmartPolling();
            } else if (this.activeTasks.size === 0) {
                log('No active tasks remaining, stopping polling');
                this.pollingStartTime = 0;
            }
        }
    }
    
    private updateTasksFromPolling(newTasks: TaskData[]): void {
        const updatedTasks = new Set<string>();
        
        // Limit stored tasks to prevent memory issues
        const MAX_STORED_TASKS = 100;
        if (this.lastTasksData.size > MAX_STORED_TASKS) {
            // Remove old completed/cancelled tasks first
            const tasksToRemove: string[] = [];
            this.lastTasksData.forEach((task, id) => {
                if (!this.activeTasks.has(id) && 
                    (task.status === 'completed' || task.status === 'cancelled' || task.status === 'failed')) {
                    tasksToRemove.push(id);
                }
            });
            
            // Remove enough tasks to get under the limit
            const removeCount = Math.min(tasksToRemove.length, this.lastTasksData.size - MAX_STORED_TASKS + 10); // +10 for buffer
            tasksToRemove.slice(0, removeCount).forEach(id => this.lastTasksData.delete(id));
        }
        
        newTasks.forEach(newTask => {
            const oldTask = this.lastTasksData.get(newTask.id);
            
            // Check if task has changed
            if (!oldTask || this.hasTaskChanged(oldTask, newTask)) {
                // Update the DOM for this specific task
                this.updateSingleTaskRow(newTask);
                updatedTasks.add(newTask.id);
                
                // Update our stored data
                this.lastTasksData.set(newTask.id, newTask);
            }
            
            // Update active tasks tracking
            if (newTask.status === 'processing' || newTask.status === 'pending' || newTask.status === 'scheduled') {
                this.activeTasks.add(newTask.id);
            } else {
                this.activeTasks.delete(newTask.id);
            }
        });
        
        if (updatedTasks.size > 0) {
            log(`Updated ${updatedTasks.size} tasks: ${Array.from(updatedTasks).join(', ')}`);
        }
    }
    
    private hasTaskChanged(oldTask: TaskData, newTask: TaskData): boolean {
        return (
            oldTask.status !== newTask.status ||
            oldTask.progress !== newTask.progress ||
            oldTask.failed !== newTask.failed ||
            oldTask.details !== newTask.details
        );
    }
    
    private updateSingleTaskRow(task: TaskData): void {
        const row = document.querySelector(`tr[data-task-id="${task.id}"]`);
        if (!row) return;
        
        // Update only the changed cells
        this.updateTasksTable([task]);
    }
    
    private showUpdateIndicator(): void {
        // Find or create update indicator
        let indicator = document.querySelector('.nuclen-update-indicator') as HTMLElement;
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'nuclen-update-indicator';
            indicator.innerHTML = '<span class="dashicons dashicons-update spin"></span> Updated';
            
            const pageTitle = document.querySelector('.wrap h1');
            if (pageTitle) {
                pageTitle.appendChild(indicator);
            } else {
                // Fallback: add to body if page title not found
                document.body.appendChild(indicator);
            }
        }
        
        // Show indicator
        indicator.classList.add('visible');
        
        // Hide after 2 seconds
        setTimeout(() => {
            if (indicator && indicator.parentNode) {
                indicator.classList.remove('visible');
            }
        }, 2000);
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

    private async refreshTasksData(): Promise<void> {
        if (this.isRefreshing) return;
        
        this.isRefreshing = true;
        
        try {
            // Add visual feedback
            const refreshButton = document.querySelector('.nuclen-refresh-button');
            if (refreshButton) {
                refreshButton.classList.add('is-busy');
                refreshButton.setAttribute('disabled', 'disabled');
            }
            
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'nuclen_refresh_tasks_data',
                    nonce: nuclen_tasks.nonce,
                    page: this.currentPage.toString(),
                })
            });
            
            const result = await response.json();
            
            if (result.success && result.data && result.data.tasks) {
                // Update all tasks
                this.updateTasksFromPolling(result.data.tasks);
                this.showNotice('Tasks refreshed successfully', 'success');
                
                // Reset polling timer since we just refreshed
                this.pollingStartTime = Date.now();
                if (this.activeTasks.size > 0) {
                    this.startSmartPolling();
                }
            } else {
                throw new Error(result.data?.message || 'Failed to refresh tasks');
            }
        } catch (err) {
            error('Failed to refresh tasks:', err);
            this.showNotice('Failed to refresh tasks. Please try again.', 'error');
        } finally {
            this.isRefreshing = false;
            
            // Remove visual feedback
            const refreshButton = document.querySelector('.nuclen-refresh-button');
            if (refreshButton) {
                refreshButton.classList.remove('is-busy');
                refreshButton.removeAttribute('disabled');
            }
        }
    }

    private updateTasksTable(tasks: TaskData[]): void {
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
            const detailsCell = row.querySelector('td:nth-child(7)'); // Details column is now 7th
            if (detailsCell) {
                let detailsHTML = task.details;
                if (task.failed && task.failed > 0) {
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
                button.textContent = nuclen_tasks.i18n.processing || 'Processing...';
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
                        
                        // Track as active task and start polling
                        this.activeTasks.add(taskId);
                        this.startSmartPolling();
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
                        
                        // Remove from active tasks
                        this.activeTasks.delete(taskId);
                    }
                }
            } else {
                // Use generic error message for user display
                const errorMessage = nuclen_tasks.i18n.error || 'An error occurred. Please try again.';
                throw new Error(errorMessage);
            }

        } catch (err) {
            error('Task action failed:', err);
            // Always show generic error message to users
            const userMessage = nuclen_tasks.i18n.error || 'An error occurred. Please try again.';
            this.showNotice(userMessage, 'error');
            
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
            'scheduled': '<span class="nuclen-badge nuclen-badge-warning">Scheduled</span>',
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

        if (status === 'pending' || status === 'scheduled') {
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
                    // let message: string;
                    // let type: 'success' | 'error' | 'info';
                    
                    if (completion.status === 'completed') {
                        // message = `Generation ${completion.task_id} completed successfully!`;
                        // type = 'success';
                    } else if (completion.status === 'completed_with_errors') {
                        // const failCount = completion.fail_count || 'some';
                        // message = `Generation ${completion.task_id} completed with ${failCount} errors. Check individual posts for details.`;
                        // type = 'info';
                    } else if (completion.status === 'failed') {
                        // message = `Generation ${completion.task_id} failed.`;
                        // type = 'error';
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
    
    private createPollingIndicator(): void {
        // Create the polling status indicator
        const indicator = document.createElement('div');
        indicator.className = 'nuclen-polling-indicator';
        indicator.innerHTML = `
            <span class="nuclen-polling-icon">
                <span class="dashicons dashicons-update"></span>
            </span>
            <span class="nuclen-polling-text">Auto-refresh: <span class="status">Inactive</span></span>
            <span class="nuclen-polling-countdown"></span>
        `;
        
        // Find the refresh button and insert indicator next to it
        const refreshButton = document.querySelector('.nuclen-refresh-button');
        console.log('[Nuclear Engagement] Refresh button found:', refreshButton);
        
        if (refreshButton && refreshButton.parentNode) {
            refreshButton.parentNode.insertBefore(indicator, refreshButton.nextSibling);
            this.pollingIndicator = indicator;
            console.log('[Nuclear Engagement] Polling indicator added next to refresh button');
        } else {
            // Fallback: insert after the page title
            const pageTitle = document.querySelector('.wrap h1');
            console.log('[Nuclear Engagement] Page title found:', pageTitle);
            if (pageTitle) {
                pageTitle.appendChild(indicator);
                this.pollingIndicator = indicator;
                console.log('[Nuclear Engagement] Polling indicator added to page title');
            } else {
                console.error('[Nuclear Engagement] Could not find location to insert polling indicator');
            }
        }
        
        // Create notification banner for auto-refresh
        this.createAutoRefreshBanner();
    }
    
    private createAutoRefreshBanner(): void {
        const banner = document.createElement('div');
        banner.className = 'nuclen-auto-refresh-banner';
        banner.innerHTML = `
            <span class="dashicons dashicons-update"></span>
            <span>Auto-refresh active</span>
        `;
        document.body.appendChild(banner);
        this.autoRefreshBanner = banner;
    }
    
    private updatePollingIndicator(active: boolean, interval?: number): void {
        if (!this.pollingIndicator) return;
        
        const statusEl = this.pollingIndicator.querySelector('.status') as HTMLElement;
        const iconEl = this.pollingIndicator.querySelector('.nuclen-polling-icon') as HTMLElement;
        const countdownEl = this.pollingIndicator.querySelector('.nuclen-polling-countdown') as HTMLElement;
        
        if (active) {
            this.pollingIndicator.classList.add('active');
            statusEl.textContent = `Active (${this.activeTasks.size} task${this.activeTasks.size !== 1 ? 's' : ''})`;
            iconEl.classList.add('spin');
            
            if (interval) {
                const seconds = Math.floor(interval / 1000);
                countdownEl.textContent = `Next refresh in ${seconds}s`;
            }
            
            // Show the auto-refresh banner briefly when first activated
            if (this.autoRefreshBanner && !this.pollingIndicator.classList.contains('active')) {
                this.autoRefreshBanner.classList.add('show');
                // Auto-hide after 3 seconds
                setTimeout(() => {
                    if (this.autoRefreshBanner) {
                        this.autoRefreshBanner.classList.remove('show');
                    }
                }, 3000);
            }
        } else {
            this.pollingIndicator.classList.remove('active');
            statusEl.textContent = 'Inactive';
            iconEl.classList.remove('spin');
            countdownEl.textContent = '';
            
            // Hide the auto-refresh banner
            if (this.autoRefreshBanner) {
                this.autoRefreshBanner.classList.remove('show');
            }
        }
    }
    
    private startCountdown(): void {
        // Clear any existing countdown
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        this.countdownInterval = window.setInterval(() => {
            const remaining = Math.max(0, Math.floor((this.nextPollTime - Date.now()) / 1000));
            const countdownEl = this.pollingIndicator?.querySelector('.nuclen-polling-countdown') as HTMLElement;
            
            if (countdownEl) {
                if (remaining > 0) {
                    countdownEl.textContent = `Next refresh in ${remaining}s`;
                } else {
                    countdownEl.textContent = 'Refreshing...';
                }
            }
            
            if (remaining <= 0 && this.countdownInterval) {
                clearInterval(this.countdownInterval);
                this.countdownInterval = null;
            }
        }, 1000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('[Nuclear Engagement] DOM ready, initializing TasksManager...');
    try {
        new TasksManager();
    } catch (error) {
        console.error('[Nuclear Engagement] Failed to initialize TasksManager:', error);
    }
});

// Export for testing
export default TasksManager;