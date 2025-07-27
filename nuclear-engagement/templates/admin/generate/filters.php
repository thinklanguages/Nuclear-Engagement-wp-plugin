	<div id="nuclen-step-1" class="nuclen-section">
		<h2 class="nuclen-subheading"><?php esc_html_e( '1. Select filters for posts', 'nuclear-engagement' ); ?></h2>
		<form id="nuclen-filters-form" class="nuclen-section" method="post">
			<table class="form-table">
				<tr class="nuclen-form-group">
					<th><label for="nuclen_post_status" class="nuclen-label"><?php esc_html_e( 'Post Status', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_post_status" id="nuclen_post_status" class="nuclen-input">
							<option value="any"><?php esc_html_e( 'All', 'nuclear-engagement' ); ?></option>
							<?php
							foreach ( $statuses as $status_key => $st_obj ) :
								if ( ! in_array( $status_key, array( 'trash', 'private', 'inherit' ) ) ) :
									?>
									<option value="<?php echo esc_attr( $status_key ); ?>">
										<?php echo esc_html( $st_obj->label ); ?>
									</option>
									<?php
								endif;
					endforeach;
							?>
						</select>
					</td>
				</tr>

				<tr class="nuclen-form-group">
					<th><label for="nuclen_category" class="nuclen-label"><?php esc_html_e( 'Category', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_category" id="nuclen_category" class="nuclen-input">
							<option value=""><?php esc_html_e( 'All', 'nuclear-engagement' ); ?></option>
							<?php
							foreach ( $categories as $cat ) :
								?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>">
									<?php echo esc_html( $cat->name ); ?>
								</option>
								<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr class="nuclen-form-group">
					<th><label for="nuclen_author" class="nuclen-label"><?php esc_html_e( 'Author', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_author" id="nuclen_author" class="nuclen-input">
							<option value=""><?php esc_html_e( 'All', 'nuclear-engagement' ); ?></option>
							<?php
							foreach ( $authors as $author ) :
								?>
								<option value="<?php echo esc_attr( $author->ID ); ?>">
									<?php echo esc_html( $author->display_name ); ?>
								</option>
								<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<!-- Post Type row -->
				<tr class="nuclen-form-group">
					<th><label for="nuclen_post_type" class="nuclen-label"><?php esc_html_e( 'Post Type', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_post_type" id="nuclen_post_type" class="nuclen-input">
							<?php
							foreach ( $post_types as $pt_key => $pt_obj ) {
								if ( in_array( $pt_key, $allowed_post_types ) ) {
									echo '<option value="' . esc_attr( $pt_key ) . '">' . esc_html( $pt_obj->labels->name ) . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
				<!-- End Post Type row -->

				<tr class="nuclen-form-group">
					<th><label for="nuclen_generate_workflow" class="nuclen-label"><?php esc_html_e( 'Generate Type', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_generate_workflow" id="nuclen_generate_workflow" class="nuclen-input">
							<option value="quiz" selected><?php esc_html_e( 'Quiz', 'nuclear-engagement' ); ?></option>
							<option value="summary"><?php esc_html_e( 'Summary', 'nuclear-engagement' ); ?></option>
						</select>
					</td>
				</tr>

				<tr id="nuclen-summary-format-row" class="nuclen-form-group nuclen-hidden">
					<th><label for="nuclen_format" class="nuclen-label"><?php esc_html_e( 'Summary Format', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_format" id="nuclen_format" class="nuclen-input nuclen-summary-format-select">
							<option value="paragraph" selected><?php esc_html_e( 'Paragraph', 'nuclear-engagement' ); ?></option>
							<option value="bullet_list"><?php esc_html_e( 'Bullet List', 'nuclear-engagement' ); ?></option>
						</select>
					</td>
				</tr>

				<tr id="nuclen-summary-paragraph-row" class="nuclen-form-group nuclen-summary-paragraph-options">
					<th><label for="nuclen_length" class="nuclen-label"><?php esc_html_e( 'Paragraph length', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_length" id="nuclen_length" class="nuclen-input">
							<option value="20">20 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
							<option value="30" selected>30 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
							<option value="40">40 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
							<option value="50">50 <?php esc_html_e( 'words', 'nuclear-engagement' ); ?></option>
						</select>
					</td>
				</tr>

				<tr id="nuclen-summary-bullet-row" class="nuclen-form-group nuclen-summary-bullet-options nuclen-hidden">
					<th><label for="nuclen_number_of_items" class="nuclen-label"><?php esc_html_e( 'Number of bullet items', 'nuclear-engagement' ); ?></label></th>
					<td>
						<select name="nuclen_number_of_items" id="nuclen_number_of_items" class="nuclen-input">
							<option value="3">3 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
							<option value="4">4 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
							<option value="5" selected>5 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
							<option value="6">6 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
							<option value="7">7 <?php esc_html_e( 'items', 'nuclear-engagement' ); ?></option>
						</select>
					</td>
				</tr>

			</table>

			<div class="nuclen-form-row">
				<div class="nuclen-form-field">
					<input
					type="checkbox"
					class="nuclen-checkbox"
					name="nuclen_allow_regenerate_data"
					id="nuclen_allow_regenerate_data"
					value="1"
					/>
					<label class="nuclen-checkbox-label" for="nuclen_allow_regenerate_data">
					<?php esc_html_e( 'Allow bulk regeneration of existing data', 'nuclear-engagement' ); ?>
					</label>
					<span nuclen-tooltip="<?php esc_attr_e( 'By default, the plugin only generates content for posts lacking it. If checked, existing data is overwritten.', 'nuclear-engagement' ); ?>">🛈</span>
				</div>
			</div>

			<div class="nuclen-form-row">
				<div class="nuclen-form-field">
					<input
					type="checkbox"
					class="nuclen-checkbox"
					name="nuclen_regenerate_protected_data"
					id="nuclen_regenerate_protected_data"
					value="1"
					/>
					<label class="nuclen-checkbox-label" for="nuclen_regenerate_protected_data">
					<?php esc_html_e( 'Allow bulk regeneration of protected data', 'nuclear-engagement' ); ?>
					</label>
					<span nuclen-tooltip="<?php esc_attr_e( 'By default, content marked protected is skipped. If checked, protected data can be overwritten too.', 'nuclear-engagement' ); ?>">🛈</span>
				</div>
			</div>

			<button type="button" id="nuclen-get-posts-btn" class="button button-primary nuclen-button-primary">
				<?php esc_html_e( 'Get Posts to Process', 'nuclear-engagement' ); ?>
			</button>
		</form>
	</div>
