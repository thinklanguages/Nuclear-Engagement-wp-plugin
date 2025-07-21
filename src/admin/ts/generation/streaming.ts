/**
 * SSE Streaming client for real-time progress updates
 */

import * as logger from '../utils/logger';

export interface StreamProgressData {
    progress: number;
    processed: number;
    total: number;
    status: string;
    results?: Array<{
        post_id: number;
        success: boolean;
        title: string;
    }>;
}

export interface StreamOptions {
    onProgress: (data: StreamProgressData) => void;
    onComplete: (status: string) => void;
    onError: (error: string) => void;
}

export class StreamingClient {
    private eventSource: EventSource | null = null;
    private taskId: string;
    private options: StreamOptions;
    
    constructor(taskId: string, options: StreamOptions) {
        this.taskId = taskId;
        this.options = options;
    }
    
    /**
     * Start streaming progress updates
     */
    start(): void {
        if (this.eventSource) {
            this.stop();
        }
        
        // Build SSE URL with proper parameters
        const params = new URLSearchParams({
            action: 'nuclen_stream_progress',
            task_id: this.taskId,
            nonce: (window as any).nuclenAdminVars?.stream_nonce || ''
        });
        
        const ajaxUrl = (window as any).ajaxurl || (window as any).nuclenAdminVars?.ajax_url || '/wp-admin/admin-ajax.php';
        const url = `${ajaxUrl}?${params.toString()}`;
        
        logger.log('Starting SSE stream', { url, taskId: this.taskId });
        
        this.eventSource = new EventSource(url);
        
        // Handle progress events
        this.eventSource.addEventListener('progress', (event) => {
            try {
                const data = JSON.parse(event.data) as StreamProgressData;
                logger.log('Progress update received', data);
                this.options.onProgress(data);
            } catch (error) {
                logger.error('Failed to parse progress data', error);
            }
        });
        
        // Handle completion
        this.eventSource.addEventListener('complete', (event) => {
            try {
                const data = JSON.parse(event.data);
                logger.log('Stream completed', data);
                this.options.onComplete(data.status);
                this.stop();
            } catch (error) {
                logger.error('Failed to parse completion data', error);
            }
        });
        
        // Handle errors
        this.eventSource.addEventListener('error', (event) => {
            if (this.eventSource?.readyState === EventSource.CLOSED) {
                logger.error('SSE connection closed');
                this.options.onError('Connection lost');
                this.stop();
            } else {
                logger.error('SSE error occurred', event);
            }
        });
        
        // Handle timeout
        this.eventSource.addEventListener('timeout', (event) => {
            logger.warn('SSE stream timeout', event);
            this.options.onError('Stream timeout');
            this.stop();
        });
    }
    
    /**
     * Stop streaming
     */
    stop(): void {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            logger.log('SSE stream stopped');
        }
    }
    
    /**
     * Check if streaming is supported
     */
    static isSupported(): boolean {
        return typeof EventSource !== 'undefined';
    }
}

/**
 * Factory function to create streaming client with fallback
 */
export function createStreamingClient(
    taskId: string,
    options: StreamOptions
): StreamingClient | null {
    if (!StreamingClient.isSupported()) {
        logger.warn('SSE not supported, falling back to polling');
        return null;
    }
    
    return new StreamingClient(taskId, options);
}