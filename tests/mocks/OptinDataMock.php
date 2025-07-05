<?php
namespace NuclearEngagement;

class OptinData {
	public static function table_name() {
		return 'wp_nuclen_optins';
	}
	
	public static function escape_csv_field($value) {
		if (preg_match('/^[=+\-@]/', $value)) {
			return "'" . $value;
		}
		return $value;
	}
}