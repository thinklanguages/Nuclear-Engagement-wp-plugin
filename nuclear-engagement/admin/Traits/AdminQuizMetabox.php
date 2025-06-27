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
       private function create_quiz_admin(): Quiz_Admin {
               $service = new Quiz_Service();
               return new Quiz_Admin( $this->nuclen_get_settings_repository(), $service );
       }

       public function nuclen_add_quiz_data_meta_box() {
               $this->create_quiz_admin()->add_meta_box();
       }

       public function nuclen_render_quiz_data_meta_box( $post ) {
               $this->create_quiz_admin()->render_meta_box( $post );
       }

       public function nuclen_save_quiz_data_meta( $post_id ) {
               $this->create_quiz_admin()->save_meta( (int) $post_id );
       }
}
