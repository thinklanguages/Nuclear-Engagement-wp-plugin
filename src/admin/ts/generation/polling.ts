import { nuclenFetchUpdates } from './api';

export function NuclenPollAndPullUpdates({
  intervalMs = 5000,
  generationId,
  onProgress = (_processed: number, _total: number) => {},
  onComplete = (_finalData: any) => {},
  onError = (_errMsg: string) => {},
}: {
  intervalMs?: number;
  generationId: string;
  onProgress?: (processed: number, total: number) => void;
  onComplete?: (finalData: any) => void;
  onError?: (errMsg: string) => void;
}) {
  const pollInterval = setInterval(async () => {
    try {
      const pollResults = await nuclenFetchUpdates(generationId);
      if (!pollResults.success) {
        const errMsg =
          pollResults.message || pollResults.data?.message || 'Polling error';
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
    } catch (err: any) {
      clearInterval(pollInterval);
      onError(err.message);
    }
  }, intervalMs);
}
