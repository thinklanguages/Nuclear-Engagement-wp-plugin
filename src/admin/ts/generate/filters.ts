export interface NuclenFilterValues {
	postStatus: string;
	category: string;
	author: string;
	postType: string;
	workflow: string;
	allowRegenerate: boolean;
	regenerateProtected: boolean;
	summaryFormat: string;
	summaryLength: string;
	summaryNumberOfItems: string;
}

export function nuclenCollectFilters(): NuclenFilterValues {
	const postStatusEl = document.getElementById('nuclen_post_status') as HTMLSelectElement | null;
	const categoryEl = document.getElementById('nuclen_category') as HTMLSelectElement | null;
	const authorEl = document.getElementById('nuclen_author') as HTMLSelectElement | null;
	const postTypeEl = document.getElementById('nuclen_post_type') as HTMLSelectElement | null;
	const workflowEl = document.getElementById('nuclen_generate_workflow') as HTMLSelectElement | null;
	const allowRegenEl = document.getElementById('nuclen_allow_regenerate_data') as HTMLInputElement | null;
	const protectRegenEl = document.getElementById('nuclen_regenerate_protected_data') as HTMLInputElement | null;
	const summaryFormatEl = document.getElementById('nuclen_format') as HTMLSelectElement | null;
	const summaryLengthEl = document.getElementById('nuclen_length') as HTMLSelectElement | null;
	const summaryNumberEl = document.getElementById('nuclen_number_of_items') as HTMLSelectElement | null;
	return {
		postStatus: postStatusEl ? postStatusEl.value : '',
		category: categoryEl ? categoryEl.value : '',
		author: authorEl ? authorEl.value : '',
		postType: postTypeEl ? postTypeEl.value : '',
		workflow: workflowEl ? workflowEl.value : '',
		allowRegenerate: !!allowRegenEl && allowRegenEl.checked,
		regenerateProtected: !!protectRegenEl && protectRegenEl.checked,
		summaryFormat: summaryFormatEl ? summaryFormatEl.value : '',
		summaryLength: summaryLengthEl ? summaryLengthEl.value : '',
		summaryNumberOfItems: summaryNumberEl ? summaryNumberEl.value : '',
	};
}

export function nuclenAppendFilters(formData: FormData, filters: NuclenFilterValues): void {
	formData.append('nuclen_post_status', filters.postStatus);
	formData.append('nuclen_category', filters.category);
	formData.append('nuclen_author', filters.author);
	formData.append('nuclen_post_type', filters.postType);
	formData.append('nuclen_generate_workflow', filters.workflow);
	formData.append('nuclen_allow_regenerate_data', filters.allowRegenerate ? '1' : '0');
	formData.append('nuclen_regenerate_protected_data', filters.regenerateProtected ? '1' : '0');
	formData.append('nuclen_summary_format', filters.summaryFormat);
	formData.append('nuclen_summary_length', filters.summaryLength);
	formData.append('nuclen_summary_number_of_items', filters.summaryNumberOfItems);
}

export function nuclenStoreFilters(filters: NuclenFilterValues): void {
	const selectedWorkflowEl = document.getElementById('nuclen_selected_generate_workflow') as HTMLInputElement | null;
	const selectedSummaryFormatEl = document.getElementById('nuclen_selected_summary_format') as HTMLInputElement | null;
	const selectedSummaryLengthEl = document.getElementById('nuclen_selected_summary_length') as HTMLInputElement | null;
	const selectedSummaryNumberEl = document.getElementById('nuclen_selected_summary_number_of_items') as HTMLInputElement | null;
	const selectedPostStatusEl = document.getElementById('nuclen_selected_post_status') as HTMLInputElement | null;
	const selectedPostTypeEl = document.getElementById('nuclen_selected_post_type') as HTMLInputElement | null;

	if (selectedWorkflowEl) {
		selectedWorkflowEl.value = filters.workflow;
	}
	if (selectedSummaryFormatEl) {
		selectedSummaryFormatEl.value = filters.summaryFormat;
	}
	if (selectedSummaryLengthEl) {
		selectedSummaryLengthEl.value = filters.summaryLength;
	}
	if (selectedSummaryNumberEl) {
		selectedSummaryNumberEl.value = filters.summaryNumberOfItems;
	}
	if (selectedPostStatusEl) {
		selectedPostStatusEl.value = filters.postStatus;
	}
	if (selectedPostTypeEl) {
		selectedPostTypeEl.value = filters.postType;
	}
}

