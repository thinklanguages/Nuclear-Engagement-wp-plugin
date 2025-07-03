<?php
declare(strict_types=1);

namespace NuclearEngagement\Modules\TOC;

use NuclearEngagement\Core\Module\AbstractModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Table of Contents module.
 * 
 * @package NuclearEngagement\Modules\TOC
 */
final class TocModule extends AbstractModule {
	protected string $version = '1.0.0';
	protected array $dependencies = [
		'NuclearEngagement\Core\SettingsRepository',
	];
	
	public function __construct() {
		parent::__construct('toc', [
			'auto_insert' => true,
			'sticky_enabled' => true,
			'min_headings' => 3,
		]);
	}
	
	protected function registerHooks(): void {
		// Register shortcode
		add_shortcode('nuclen_toc', [$this, 'renderShortcode']);
		
		// Auto-insert TOC if enabled
		if ($this->getConfigValue('auto_insert', true)) {
			add_filter('the_content', [$this, 'autoInsertToc'], 20);
		}
		
		// Enqueue assets
		add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
		add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
		
		// Register admin metabox
		add_action('add_meta_boxes', [$this, 'addMetabox']);
		add_action('save_post', [$this, 'saveMetabox']);
		
		// Register settings
		add_action('admin_init', [$this, 'registerSettings']);
		
		$this->log('TOC module hooks registered');
	}
	
	/**
	 * Render TOC shortcode.
	 */
	public function renderShortcode(array $atts = []): string {
		if (!class_exists('NuclearEngagement\Modules\TOC\Nuclen_TOC_Render')) {
			return '';
		}
		
		$renderer = new \NuclearEngagement\Modules\TOC\Nuclen_TOC_Render();
		return $renderer->render_toc_shortcode($atts);
	}
	
	/**
	 * Auto-insert TOC into content.
	 */
	public function autoInsertToc(string $content): string {
		if (!is_singular() || is_admin()) {
			return $content;
		}
		
		// Check if post has enough headings
		$heading_count = substr_count($content, '<h') + substr_count($content, '<H');
		$min_headings = $this->getConfigValue('min_headings', 3);
		
		if ($heading_count < $min_headings) {
			return $content;
		}
		
		// Check if TOC is disabled for this post
		if (get_post_meta(get_the_ID(), 'nuclen_toc_disabled', true)) {
			return $content;
		}
		
		// Insert TOC after first paragraph
		$toc = $this->renderShortcode();
		if (empty($toc)) {
			return $content;
		}
		
		// Find first paragraph end
		$first_p_end = strpos($content, '</p>');
		if ($first_p_end !== false) {
			$content = substr_replace($content, '</p>' . $toc, $first_p_end, 4);
		}
		
		return $content;
	}
	
	/**
	 * Enqueue frontend assets.
	 */
	public function enqueueAssets(): void {
		if (!is_singular()) {
			return;
		}
		
		$asset_path = plugin_dir_url(__FILE__) . 'assets/';
		
		wp_enqueue_style(
			'nuclen-toc-front',
			$asset_path . 'css/nuclen-toc-front.css',
			[],
			$this->version
		);
		
		wp_enqueue_script(
			'nuclen-toc-front',
			$asset_path . 'js/nuclen-toc-front.js',
			['jquery'],
			$this->version,
			true
		);
		
		// Add sticky TOC configuration
		if ($this->getConfigValue('sticky_enabled', true)) {
			wp_localize_script('nuclen-toc-front', 'nuclenTocConfig', [
				'sticky' => true,
				'offset' => 50,
			]);
		}
	}
	
	/**
	 * Enqueue admin assets.
	 */
	public function enqueueAdminAssets(): void {
		$screen = get_current_screen();
		if (!$screen || !in_array($screen->post_type, ['post', 'page'], true)) {
			return;
		}
		
		$asset_path = plugin_dir_url(__FILE__) . 'assets/';
		
		wp_enqueue_style(
			'nuclen-toc-admin',
			$asset_path . 'css/nuclen-toc-admin.css',
			[],
			$this->version
		);
		
		wp_enqueue_script(
			'nuclen-toc-admin',
			$asset_path . 'js/nuclen-toc-admin.js',
			['jquery'],
			$this->version,
			true
		);
	}
	
	/**
	 * Add admin metabox.
	 */
	public function addMetabox(): void {
		add_meta_box(
			'nuclen-toc-settings',
			__('Table of Contents', 'nuclear-engagement'),
			[$this, 'renderMetabox'],
			['post', 'page'],
			'side',
			'default'
		);
	}
	
	/**
	 * Render admin metabox.
	 */
	public function renderMetabox(\WP_Post $post): void {
		wp_nonce_field('nuclen_toc_metabox', 'nuclen_toc_nonce');
		
		$disabled = get_post_meta($post->ID, 'nuclen_toc_disabled', true);
		
		echo '<p>';
		echo '<label for="nuclen_toc_disabled">';
		echo '<input type="checkbox" id="nuclen_toc_disabled" name="nuclen_toc_disabled" value="1"' . checked($disabled, '1', false) . '>';
		echo ' ' . __('Disable TOC for this post', 'nuclear-engagement');
		echo '</label>';
		echo '</p>';
	}
	
	/**
	 * Save metabox data.
	 */
	public function saveMetabox(int $post_id): void {
		if (!isset($_POST['nuclen_toc_nonce']) || 
			!wp_verify_nonce($_POST['nuclen_toc_nonce'], 'nuclen_toc_metabox')) {
			return;
		}
		
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		
		$disabled = isset($_POST['nuclen_toc_disabled']) ? '1' : '';
		update_post_meta($post_id, 'nuclen_toc_disabled', $disabled);
	}
	
	/**
	 * Register module settings.
	 */
	public function registerSettings(): void {
		register_setting('nuclen_toc_settings', 'nuclen_toc_auto_insert');
		register_setting('nuclen_toc_settings', 'nuclen_toc_sticky_enabled');
		register_setting('nuclen_toc_settings', 'nuclen_toc_min_headings');
	}
}