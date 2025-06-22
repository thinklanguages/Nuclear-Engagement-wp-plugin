<?php
declare(strict_types=1);
/**
 * File: admin/OnboardingPointers.php
 *
 * Stores admin pointer definitions for the onboarding flow.
 *
 * @package NuclearEngagement\Admin
 */

namespace NuclearEngagement\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class OnboardingPointers {
    /**
     * Get pointer definitions grouped by admin page.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function get_pointers(): array {
        return [
            'toplevel_page_nuclear-engagement' => [
                [
                    'id'       => 'nuclen_dashboard_step1',
                    'target'   => '#overview-tab',
                    'title'    => esc_html__('Post Inventory, at a Glance', 'nuclear-engagement'),
                    'content'  => esc_html__('Keep track of which posts lack quizzes or summaries. Looks like many may need an upgrade!', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'top', 'align' => 'center' ],
                ],
                [
                    'id'       => 'nuclen_dashboard_step2',
                    'target'   => '#post-type-tab',
                    'title'    => esc_html__('Custom Post Types', 'nuclear-engagement'),
                    'content'  => esc_html__('Not only posts, but also pages and other custom types are supported.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'top', 'align' => 'center' ],
                ],
                [
                    'id'       => 'nuclen_dashboard_step3',
                    'target'   => 'li.toplevel_page_nuclear-engagement ul.wp-submenu a[href="admin.php?page=nuclear-engagement-generate"]',
                    'title'    => esc_html__('Generate Engaging Content', 'nuclear-engagement'),
                    'content'  => esc_html__('Open the Generate page to create or update your content at scale.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'left', 'align' => 'center' ],
                ],
            ],
            'nuclear-engagement_page_nuclear-engagement-generate' => [
                [
                    'id'       => 'nuclen_generate_step1',
                    'target'   => '#nuclen_generate_workflow',
                    'title'    => esc_html__('Bulk Upgrade', 'nuclear-engagement'),
                    'content'  => esc_html__('Generate content for all selected posts in one go.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'top', 'align' => 'center' ],
                ],
                [
                    'id'       => 'nuclen_generate_step2',
                    'target'   => '#nuclen-filters-form .form-table',
                    'title'    => esc_html__('Refine Your Selection', 'nuclear-engagement'),
                    'content'  => esc_html__('You can filter down to specific authors, categories, or post types.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'top', 'align' => 'center' ],
                ],
                [
                    'id'       => 'nuclen_generate_step3',
                    'target'   => 'li.toplevel_page_nuclear-engagement ul.wp-submenu a[href="admin.php?page=nuclear-engagement-settings"]',
                    'title'    => esc_html__('Customize Behavior', 'nuclear-engagement'),
                    'content'  => esc_html__('You can finuclen-tune how and where new content is displayed under Settings.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'left', 'align' => 'center' ],
                ],
            ],
            'nuclear-engagement_page_nuclear-engagement-settings' => [
                [
                    'id'       => 'nuclen_settings_step1',
                    'target'   => '#placement-tab',
                    'title'    => esc_html__('Display Sections as You Prefer', 'nuclear-engagement'),
                    'content'  => esc_html__('Use shortcodes or automatically insert quiz/summary before/after post content.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'top', 'align' => 'center' ],
                ],
                [
                    'id'       => 'nuclen_settings_step2',
                    'target'   => '#optin-tab',
                    'title'    => esc_html__('Generate Leads', 'nuclear-engagement'),
                    'content'  => esc_html__('Enable an email opt-in form at the end of the quiz, hooking into your marketing tools.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'top', 'align' => 'center' ],
                ],
                [
                    'id'       => 'nuclen_settings_step3',
                    'target'   => 'li.toplevel_page_nuclear-engagement ul.wp-submenu a[href="admin.php?page=nuclear-engagement-setup"]',
                    'title'    => esc_html__('Easy Setup', 'nuclear-engagement'),
                    'content'  => esc_html__('Authorize your site to generate content with NE in two steps.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'left', 'align' => 'center' ],
                ],
            ],
            'nuclear-engagement_page_nuclear-engagement-setup' => [
                [
                    'id'       => 'nuclen_setup_step1',
                    'target'   => '#nuclen-setup-step-1',
                    'title'    => esc_html__('Enter Your Gold Code', 'nuclear-engagement'),
                    'content'  => esc_html__('Paste your API key to connect your site with the NE service.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'top', 'align' => 'center' ],
                ],
                [
                    'id'       => 'nuclen_setup_step2',
                    'target'   => '#nuclen-setup-step-2',
                    'title'    => esc_html__('One More Click', 'nuclear-engagement'),
                    'content'  => esc_html__('Allow NE to push generated content directly into your site.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'top', 'align' => 'center' ],
                ],
                [
                    'id'       => 'nuclen_setup_step3',
                    'target'   => 'li.toplevel_page_nuclear-engagement ul.wp-submenu li.wp-first-item',
                    'title'    => esc_html__('Check Your Dashboard', 'nuclear-engagement'),
                    'content'  => esc_html__('See which posts need a quiz or summary the most.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'left', 'align' => 'center' ],
                ],
            ],
            'post.php' => [
                [
                    'id'       => 'nuclen_postedit_step1',
                    'target'   => '#nuclen-quiz-data-meta-box',
                    'title'    => esc_html__('Generate from Editor', 'nuclear-engagement'),
                    'content'  => esc_html__('Create or edit quiz content for this single post.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'top', 'align' => 'center' ],
                ],
                [
                    'id'       => 'nuclen_postedit_step2',
                    'target'   => '#nuclen-generate-quiz-single',
                    'title'    => esc_html__('One‑Click Generation', 'nuclear-engagement'),
                    'content'  => esc_html__('Immediately fetch new quiz data for this post.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'right', 'align' => 'center' ],
                ],
                [
                    'id'       => 'nuclen_postedit_step3',
                    'target'   => '#show-settings-link',
                    'title'    => esc_html__('Hide Metaboxes', 'nuclear-engagement'),
                    'content'  => esc_html__('You can hide plugin sections here if your editor is cluttered.', 'nuclear-engagement'),
                    'position' => [ 'edge' => 'bottom', 'align' => 'center' ],
                ],
            ],
        ];
    }
}
