<?php
/**
 * Theme.php - Part of the Nuclear Engagement plugin.
 *
 * @package NuclearEngagement_Models
 */

namespace NuclearEngagement\Models;

class Theme {
	public $id;
	public $name;
	public $type;
	public $config;
	public $css_hash;
	public $css_path;
	public $is_active;
	public $created_at;
	public $updated_at;

	const TYPE_PRESET = 'preset';
	const TYPE_CUSTOM = 'custom';

	public function __construct( $data = array() ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				if ( $key === 'config' ) {
					$this->config = is_string( $value ) ? wp_json_decode( $value, true ) : $value;
				} else {
					$this->$key = $value;
				}
			}
		}
	}

	public function to_array() {
		return array(
			'id'         => $this->id,
			'name'       => $this->name,
			'type'       => $this->type,
			'config'     => $this->config,
			'css_hash'   => $this->css_hash,
			'css_path'   => $this->css_path,
			'is_active'  => $this->is_active,
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
		);
	}

	public function get_config_json() {
		return wp_json_encode( $this->config );
	}

	public function generate_hash() {
		return hash( 'sha256', wp_json_encode( $this->config ) );
	}

	public function needs_css_update() {
		return $this->css_hash !== $this->generate_hash();
	}

	public function get_css_url() {
		if ( ! $this->css_path ) {
			return null;
		}

		$upload_dir = wp_upload_dir();
		$base_path  = 'nuclear-engagement/themes/';

		return $upload_dir['baseurl'] . '/' . $base_path . basename( $this->css_path );
	}

	public function get_css_file_path() {
		if ( ! $this->css_path ) {
			return null;
		}

		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/nuclear-engagement/themes/' . basename( $this->css_path );
	}
}
