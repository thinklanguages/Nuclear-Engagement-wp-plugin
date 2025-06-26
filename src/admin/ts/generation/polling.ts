import { nuclenFetchUpdates } from './api';
import type { PollingUpdateData, PollingUpdateResponse } from './api';

export function NuclenPollAndPullUpdates({
  intervalMs = 5000,
  generationId,
  onProgress = (() => {}) as (processed: number, total: number) => void,
  onComplete = (_finalData: PollingUpdateData) => {},
  onError = (_errMsg: string) => {},
}: {
  intervalMs?: number;
  generationId: string;
  onProgress?: (processed: number, total: number) => void;
  onComplete?: (finalData: PollingUpdateData) => void;
  onError?: (errMsg: string) => void;
}) {
  const pollInterval = setInterval(async () => {
    try {
      const pollResults: PollingUpdateResponse = await nuclenFetchUpdates(generationId);
      if (!pollResults.success) {
        const errMsg = pollResults.message || 'Polling error';
        throw new Error(errMsg);
      }

      const {
        processed,
        total,
        successCount = processed,
        failCount,
        finalReport,
        results,
        workflow,
      } = pollResults.data;

      onProgress(processed, total);

      if (processed >= total) {
        clearInterval(pollInterval);
        onComplete({
          processed,
          total,
          successCount,
          failCount,
          finalReport,
          results,
          workflow,
        });
      }
    } catch (err: unknown) {
      clearInterval(pollInterval);
      const message = err instanceof Error ? err.message : 'Unknown error';
      onError(message);
    }
  }, intervalMs);
}
