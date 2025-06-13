import { nuclenFetchWithRetry } from "./nuclen-admin-generate";

export async function checkCreditsAjax(): Promise<number> {
  if (!(window as any).nuclenAjax || !(window as any).nuclenAjax.ajax_url) {
    throw new Error("Missing nuclenAjax configuration (ajax_url).");
  }
  if (!(window as any).nuclenAjax.fetch_action) {
    throw new Error("Missing fetch_action in nuclenAjax configuration.");
  }

  const formData = new FormData();
  formData.append("action", (window as any).nuclenAjax.fetch_action);
  if ((window as any).nuclenAjax.nonce) {
    formData.append("security", (window as any).nuclenAjax.nonce);
  }

  const response = await nuclenFetchWithRetry(
    (window as any).nuclenAjax.ajax_url,
    {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    }
  );

  const data = await response.json();
  if (!data.success) {
    throw new Error(data.data?.message || "Failed to fetch credits from SaaS");
  }
  if (typeof data.data.remaining_credits === "number") {
    return data.data.remaining_credits;
  }
  throw new Error("No 'remaining_credits' in response");
}
