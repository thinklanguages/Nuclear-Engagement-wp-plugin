// admin/ts/nuclen-globals.d.ts
export {};

declare global {
  const wp: any;
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
    tinymce?: any;
    wp?: any;
  }
}
