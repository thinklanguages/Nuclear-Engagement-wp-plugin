<?php
/**
 * Wrapper trait delegating quiz meta box logic to the new module.
 */

declare(strict_types=1);

namespace NuclearEngagement\Admin\Traits;

use NuclearEngagement\Modules\Quiz\Quiz_Admin;
use NuclearEngagement\Modules\Quiz\Quiz_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait AdminQuizMetabox {
    private ?Quiz_Admin $quiz_admin = null;

    private function get_quiz_admin(): Quiz_Admin {
        if ( $this->quiz_admin === null ) {
            $service       = new Quiz_Service();
            $this->quiz_admin = new Quiz_Admin( $this->nuclen_get_settings_repository(), $service );
        }
        return $this->quiz_admin;
    }

    public function nuclen_add_quiz_data_meta_box() {
        $this->get_quiz_admin()->add_meta_box();
    }

    public function nuclen_render_quiz_data_meta_box( $post ) {
        $this->get_quiz_admin()->render_meta_box( $post );
    }

    public function nuclen_save_quiz_data_meta( $post_id ) {
        $this->get_quiz_admin()->save_meta( (int) $post_id );
    }
}
