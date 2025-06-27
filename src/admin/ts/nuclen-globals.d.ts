// admin/ts/nuclen-globals.d.ts
export {};

declare global {
	const wp: Record<string, unknown>;
	interface Window {
	nuclenAjax?: {
		ajax_url?: string;
		fetch_action?: string;
		nonce?: string;
	};
	nuclenAdminVars?: {
		ajax_url?: string;
		security?: string;
		rest_receive_content?: string;
		rest_nonce?: string;
	};
	tinymce?: Record<string, unknown>;
	wp?: Record<string, unknown>;
	}
}
