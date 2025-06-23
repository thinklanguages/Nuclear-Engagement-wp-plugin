export async function apiRequest(url: string, options: RequestInit): Promise<Response> {
  try {
    const response = await fetch(url, options);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    return response;
  } catch (err: any) {
    throw new Error(err.message || 'Network error');
  }
}
