<?php
/**
 * ThemeSettingsService.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Services
 */

namespace NuclearEngagement\Services;

use NuclearEngagement\Models\Theme;
use NuclearEngagement\Repositories\ThemeRepository;

class ThemeSettingsService {
	private $repository;
	private $css_generator;
	private $validator;
	private $config_converter;
	private $event_manager;

	public function __construct(
		ThemeRepository $repository = null,
		ThemeCssGenerator $css_generator = null,
		ThemeValidator $validator = null,
		ThemeConfigConverter $config_converter = null,
		ThemeEventManager $event_manager = null
	) {
		$this->repository       = $repository ?: new ThemeRepository();
		$this->event_manager    = $event_manager ?: ThemeEventManager::instance();
		$this->css_generator    = $css_generator ?: new ThemeCssGenerator( $this->repository, $this->event_manager );
		$this->validator        = $validator ?: new ThemeValidator();
		$this->config_converter = $config_converter ?: new ThemeConfigConverter();
	}

	public function get_available_themes() {
		return $this->repository->get_all();
	}

	public function get_preset_themes() {
		return $this->repository->get_all( Theme::TYPE_PRESET );
	}

	public function get_custom_themes() {
		return $this->repository->get_all( Theme::TYPE_CUSTOM );
	}

	public function get_active_theme() {
		return $this->repository->get_active();
	}

	public function save_theme_selection( $theme_identifier ) {
		if ( is_numeric( $theme_identifier ) ) {
			return $this->set_active_theme_by_id( (int) $theme_identifier );
		}

		return $this->set_active_theme_by_name( $theme_identifier );
	}

	public function save_custom_theme( $config, $name = 'custom' ) {
		// Validate theme name.
		$name_validation = $this->validator->validate_theme_name( $name );
		if ( ! $name_validation['valid'] ) {
			return array(
				'success' => false,
				'errors'  => $name_validation['errors'],
			);
		}

		// Sanitize and validate config.
		$sanitized_config  = $this->validator->sanitize_config( $config );
		$config_validation = $this->validator->validate_theme_config( $sanitized_config );
		if ( ! $config_validation['valid'] ) {
			return array(
				'success' => false,
				'errors'  => $config_validation['errors'],
			);
		}

		// Merge with defaults to ensure complete config.
		$complete_config = $this->config_converter->merge_with_defaults( $sanitized_config );

		// Apply filters before saving.
		$filtered_config = $this->event_manager->apply_config_filters( $complete_config, null );

		$existing_theme = $this->repository->find_by_name( $name );
		$is_new         = ! $existing_theme;

		if ( $existing_theme && $existing_theme->type === Theme::TYPE_CUSTOM ) {
			$existing_theme->config = $filtered_config;
			$theme                  = $this->repository->save( $existing_theme );
		} else {
			$theme = new Theme(
				array(
					'name'   => $name,
					'type'   => Theme::TYPE_CUSTOM,
					'config' => $filtered_config,
				)
			);

			$theme = $this->repository->save( $theme );
		}

		if ( $theme ) {
			$this->css_generator->generate_css( $theme );
			$this->repository->save( $theme );
			$this->repository->set_active( $theme->id );

			$this->event_manager->trigger_theme_saved( $theme, $is_new );
			$this->event_manager->trigger_theme_activated( $theme->id, $theme );
		}

		return array(
			'success' => true,
			'theme'   => $theme,
		);
	}

	public function delete_theme( $theme_id ) {
		$theme = $this->repository->find( $theme_id );

		if ( ! $theme || $theme->type === Theme::TYPE_PRESET ) {
			return array(
				'success' => false,
				'error'   => 'Cannot delete preset themes or theme not found',
			);
		}

		if ( $theme->is_active ) {
			$light_theme = $this->repository->find_by_name( 'light' );
			if ( $light_theme ) {
				$this->repository->set_active( $light_theme->id );
				$this->event_manager->trigger_theme_deactivated( $theme_id, $theme );
				$this->event_manager->trigger_theme_activated( $light_theme->id, $light_theme );
			}
		}

		if ( $theme->css_path && file_exists( $theme->get_css_file_path() ) ) {
			unlink( $theme->get_css_file_path() );
		}

		$result = $this->repository->delete( $theme_id );

		if ( $result ) {
			$this->event_manager->trigger_theme_deleted( $theme_id );
		}

		return array(
			'success' => $result,
			'error'   => $result ? null : 'Failed to delete theme',
		);
	}

	public function duplicate_theme( $theme_id, $new_name ) {
		$original = $this->repository->find( $theme_id );

		if ( ! $original ) {
			return false;
		}

		if ( $this->repository->find_by_name( $new_name ) ) {
			return false;
		}

		$new_theme = new Theme(
			array(
				'name'   => $new_name,
				'type'   => Theme::TYPE_CUSTOM,
				'config' => $original->config,
			)
		);

		$theme = $this->repository->save( $new_theme );

		if ( $theme ) {
			$this->css_generator->generate_css( $theme );
			$this->repository->save( $theme );
		}

		return $theme;
	}

	public function regenerate_css( $theme_id = null ) {
		if ( $theme_id ) {
			$theme = $this->repository->find( $theme_id );
			if ( $theme && $theme->type === Theme::TYPE_CUSTOM ) {
				$this->css_generator->generate_css( $theme );
				$this->repository->save( $theme );
			}
		} else {
			$custom_themes = $this->get_custom_themes();
			foreach ( $custom_themes as $theme ) {
				$this->css_generator->generate_css( $theme );
				$this->repository->save( $theme );
			}
		}

		return true;
	}

	private function set_active_theme_by_id( $theme_id ) {
		$theme = $this->repository->find( $theme_id );

		if ( ! $theme ) {
			return false;
		}

		return $this->repository->set_active( $theme_id );
	}

	private function set_active_theme_by_name( $name ) {
		$theme = $this->repository->find_by_name( $name );

		if ( ! $theme ) {
			return false;
		}

		return $this->repository->set_active( $theme->id );
	}

	public function export_theme( $theme_id ) {
		$theme = $this->repository->find( $theme_id );

		if ( ! $theme ) {
			return false;
		}

		return array(
			'name'        => $theme->name,
			'type'        => $theme->type,
			'config'      => $theme->config,
			'exported_at' => current_time( 'mysql' ),
		);
	}

	public function import_theme( $theme_data, $new_name = null ) {
		if ( ! isset( $theme_data['config'] ) || ! is_array( $theme_data['config'] ) ) {
			return false;
		}

		$name = $new_name ?: ( $theme_data['name'] ?? 'imported-theme' );

		if ( $this->repository->find_by_name( $name ) ) {
			$name .= '-' . time();
		}

		$theme = new Theme(
			array(
				'name'   => $name,
				'type'   => Theme::TYPE_CUSTOM,
				'config' => $theme_data['config'],
			)
		);

		$theme = $this->repository->save( $theme );

		if ( $theme ) {
			$this->css_generator->generate_css( $theme );
			$this->repository->save( $theme );
		}

		return $theme;
	}

	public function get_theme_config_for_legacy( $theme_name ) {
		$theme = $this->repository->find_by_name( $theme_name );

		if ( ! $theme ) {
			return array();
		}

		return $this->config_converter->convert_new_to_legacy( $theme->config );
	}
}
