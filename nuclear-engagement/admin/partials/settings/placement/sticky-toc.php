<!-- Sticky TOC settings -->
<!-- Sticky toggle -->
<div class="nuclen-form-group nuclen-row">
        <div class="nuclen-column nuclen-label-col">
                <label for="nuclen_toc_sticky" class="nuclen-label"><?php esc_html_e( 'Sticky TOC', 'nuclear-engagement' ); ?></label>
        </div>
        <div class="nuclen-column nuclen-input-col">
                <label class="nuclen-checkbox-label">
                        <input type="checkbox" name="toc_sticky" id="nuclen_toc_sticky" value="1" <?php checked( '1', $settings['toc_sticky'] ?? '0' ); ?> />
                        <?php esc_html_e( 'Make Table of Contents sticky when scrolling', 'nuclear-engagement' ); ?>
                </label>
        </div>
</div>

<!-- Z-index -->
<div class="nuclen-form-group nuclen-row">
        <div class="nuclen-column nuclen-label-col">
                <label for="nuclen_toc_zindex" class="nuclen-label"><?php esc_html_e( 'TOC Z-Index', 'nuclear-engagement' ); ?></label>
        </div>
        <div class="nuclen-column nuclen-input-col">
                <input type="number"
                       name="toc_zindex"
                       id="nuclen_toc_zindex"
                       class="small-text"
                       min="0"
                       step="1"
                       value="<?php echo esc_attr( $settings['toc_z_index'] ?? '100' ); ?>" />
                <p class="description"><?php esc_html_e( 'Higher numbers keep the sticky TOC above other elements.', 'nuclear-engagement' ); ?></p>
        </div>
</div>

<!-- Offset X -->
<div class="nuclen-form-group nuclen-row">
        <div class="nuclen-column nuclen-label-col">
                <label for="nuclen_toc_sticky_offset_x" class="nuclen-label"><?php esc_html_e( 'Sticky Offset X (px)', 'nuclear-engagement' ); ?></label>
        </div>
        <div class="nuclen-column nuclen-input-col">
                <input type="number"
                       name="toc_sticky_offset_x"
                       id="nuclen_toc_sticky_offset_x"
                       class="small-text"
                       min="0"
                       step="1"
                       value="<?php echo esc_attr( $settings['toc_sticky_offset_x'] ?? '20' ); ?>" />
        </div>
</div>

<!-- Offset Y -->
<div class="nuclen-form-group nuclen-row">
        <div class="nuclen-column nuclen-label-col">
                <label for="nuclen_toc_sticky_offset_y" class="nuclen-label"><?php esc_html_e( 'Sticky Offset Y (px)', 'nuclear-engagement' ); ?></label>
        </div>
        <div class="nuclen-column nuclen-input-col">
                <input type="number"
                       name="toc_sticky_offset_y"
                       id="nuclen_toc_sticky_offset_y"
                       class="small-text"
                       min="0"
                       step="1"
                       value="<?php echo esc_attr( $settings['toc_sticky_offset_y'] ?? '20' ); ?>" />
        </div>
</div>

<!-- Max width -->
<div class="nuclen-form-group nuclen-row">
        <div class="nuclen-column nuclen-label-col">
                <label for="nuclen_toc_sticky_max_width" class="nuclen-label"><?php esc_html_e( 'Sticky Max-width (px)', 'nuclear-engagement' ); ?></label>
        </div>
        <div class="nuclen-column nuclen-input-col">
                <input type="number"
                       name="toc_sticky_max_width"
                       id="nuclen_toc_sticky_max_width"
                       class="small-text"
                       min="200"
                       step="1"
                       value="<?php echo esc_attr( $settings['toc_sticky_max_width'] ?? '300' ); ?>" />
        </div>
</div>
