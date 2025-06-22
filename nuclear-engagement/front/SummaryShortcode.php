<?php
declare(strict_types=1);
namespace NuclearEngagement\Front;

use NuclearEngagement\SettingsRepository;

if (!defined('ABSPATH')) {
    exit;
}

class SummaryShortcode {
    private SettingsRepository $settings;
    private SummaryView $view;

    public function __construct(SettingsRepository $settings) {
        $this->settings = $settings;
        $this->view = new SummaryView();
    }

    public function register(): void {
        add_shortcode('nuclear_engagement_summary', [$this, 'render']);
    }

    public function render(): string {
        $summary_data = $this->getSummaryData();
        if (!$this->isValidSummaryData($summary_data)) {
            return '';
        }

        $settings = $this->getSummarySettings();
        $html  = '<div class="nuclen-root">';
        $html .= $this->view->container($summary_data, $settings);
        $html .= $this->view->attribution($settings['show_attribution']);
        $html .= '</div>';
        return $html;
    }

    private function getSummaryData() {
        $post_id = get_the_ID();
        return get_post_meta($post_id, 'nuclen-summary-data', true);
    }

    private function isValidSummaryData($summary_data): bool {
        return !empty($summary_data) && !empty(trim($summary_data['summary'] ?? ''));
    }

    private function getSummarySettings(): array {
        return [
            'summary_title'    => $this->settings->get_string('summary_title', __('Key Facts', 'nuclear-engagement')),
            'show_attribution' => $this->settings->get_bool('show_attribution', false),
        ];
    }
}
