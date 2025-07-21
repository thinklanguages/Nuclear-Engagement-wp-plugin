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
		admin_url?: string;
	};
	tinymce?: Record<string, unknown>;
	wp?: Record<string, unknown>;
	nePointerData?: {
		pointers: Array<{
			id: string;
			target: string;
			title: string;
			content: string;
			position: {
				edge: 'top' | 'bottom' | 'left' | 'right';
				align: 'center' | 'left' | 'right' | 'top' | 'bottom';
			};
		}>;
		ajaxurl: string;
		nonce?: string;
	};
	}
}
