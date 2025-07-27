// admin/ts/nuclen-globals.d.ts
export {};

declare global {
	const wp: {
		editor?: {
			initialize?: (id: string, settings: any) => void;
			remove?: (id: string) => void;
		};
		data?: {
			select?: (store: string) => any;
		};
		[key: string]: any;
	};
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
	tinymce?: {
		get?: (id: string) => any;
		[key: string]: any;
	};
	wp?: {
		editor?: {
			initialize?: (id: string, settings: any) => void;
			remove?: (id: string) => void;
		};
		data?: {
			select?: (store: string) => any;
		};
		[key: string]: any;
	};
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
