<?php
namespace NuclearEngagement\Modules\TOC {
    function update_post_meta($postId, $key, $value){
        $GLOBALS['wp_meta'][$postId][$key] = $value; return true;
    }
    function delete_post_meta($postId, $key){ unset($GLOBALS['wp_meta'][$postId][$key]); }
}

namespace {
    use PHPUnit\Framework\TestCase;
    use NuclearEngagement\Modules\TOC\Nuclen_TOC_Utils;
    use NuclearEngagement\Modules\TOC\Nuclen_TOC_Headings;
    if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }
    if (!defined('NUCLEN_TOC_DIR')) { define('NUCLEN_TOC_DIR', dirname(__DIR__).'/nuclear-engagement/inc/Modules/TOC/'); }
    if (!defined('NUCLEN_TOC_URL')) { define('NUCLEN_TOC_URL', 'http://example.com/'); }
    require_once NUCLEN_TOC_DIR.'includes/polyfills.php';
    require_once NUCLEN_TOC_DIR.'includes/class-nuclen-toc-utils.php';
    require_once NUCLEN_TOC_DIR.'includes/class-nuclen-toc-headings.php';

    class NuclenTOCMetaCacheTest extends TestCase {
        protected function setUp(): void {
            $GLOBALS['wp_posts'] = [];
            $GLOBALS['wp_meta'] = [];
            $GLOBALS['wp_cache'] = [];
            $GLOBALS['transients'] = [];
        }

        public function test_extract_returns_meta_when_available(): void {
            $post = (object)[ 'ID' => 1, 'post_content' => '<h2>One</h2>' ];
            $stored = [ [ 'tag'=>'h2','level'=>2,'text'=>'One','inner'=>'One','id'=>'one' ] ];
            $GLOBALS['wp_posts'][1] = $post;
            $GLOBALS['wp_meta'][1][Nuclen_TOC_Headings::META_KEY] = $stored;
            $result = Nuclen_TOC_Utils::extract($post->post_content, [2], 1);
            $this->assertSame($stored, $result);
        }

        public function test_cache_saved_on_save_post(): void {
            $post = (object)[ 'ID' => 2, 'post_content' => '<h2>Two</h2>' ];
            $handler = new Nuclen_TOC_Headings();
            $handler->cache_headings_on_save(2, $post, true);
            $this->assertArrayHasKey(Nuclen_TOC_Headings::META_KEY, $GLOBALS['wp_meta'][2]);
            $saved = $GLOBALS['wp_meta'][2][Nuclen_TOC_Headings::META_KEY];
            $this->assertSame('two', $saved[0]['id']);
        }
    }
}
