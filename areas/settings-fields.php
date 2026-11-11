<?php
/**
 * Area settings, shown in the Post Area widget's Settings dialog on the Widgets page.
 *
 * @var array            $config     From Ems_Areas_Manager::get_config().
 * @var \WP_Post_Type[]  $post_types From Ems_Areas_Manager::get_available_post_types().
 */
?>
<div class="ems-field">
    <label class="ems-label"><?php echo esc_html__( 'Post types', 'mme-elementor-kit' ); ?></label>
    <div class="ems-checks">
        <?php foreach ( $post_types as $type ) : ?>
            <label>
                <input type="checkbox" name="mme_kit_area_post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $config['post_types'], true ) ); ?>>
                <?php echo esc_html( $type->labels->name ); ?>
            </label>
        <?php endforeach; ?>
    </div>
</div>

<div class="ems-field">
    <label class="ems-label"><?php echo esc_html__( 'Areas', 'mme-elementor-kit' ); ?></label>
    <div class="ems-area-rows">
        <div class="ems-area-row ems-area-row--head">
            <span><?php echo esc_html__( 'Name', 'mme-elementor-kit' ); ?></span>
            <span><?php echo esc_html__( 'Label', 'mme-elementor-kit' ); ?></span>
            <span><?php echo esc_html__( 'Display', 'mme-elementor-kit' ); ?></span>
            <span></span>
        </div>
        <?php
        $index = 0;
        foreach ( $config['areas'] as $name => $area ) :
            ?>
            <div class="ems-area-row">
                <input class="ems-input ems-input--mono" type="text" name="mme_kit_areas[<?php echo (int) $index; ?>][name]" value="<?php echo esc_attr( $name ); ?>" pattern="[a-z0-9_\-]+" maxlength="20" required>
                <input class="ems-input" type="text" name="mme_kit_areas[<?php echo (int) $index; ?>][label]" value="<?php echo esc_attr( $area['label'] ); ?>">
                <select class="ems-input" name="mme_kit_areas[<?php echo (int) $index; ?>][mode]">
                    <option value="after_content" <?php selected( $area['mode'], 'after_content' ); ?>><?php echo esc_html__( 'After the content (automatic)', 'mme-elementor-kit' ); ?></option>
                    <option value="manual" <?php selected( $area['mode'], 'manual' ); ?>><?php echo esc_html__( 'Manual (widget / shortcode)', 'mme-elementor-kit' ); ?></option>
                </select>
                <button type="button" class="ems-icon-btn ems-icon-btn--danger ems-remove-area" title="<?php echo esc_attr__( 'Remove', 'mme-elementor-kit' ); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
            <?php
            $index++;
        endforeach;
        ?>
    </div>
    <template class="ems-area-row-template">
        <div class="ems-area-row">
            <input class="ems-input ems-input--mono" type="text" name="mme_kit_areas[__i__][name]" placeholder="faq" pattern="[a-z0-9_\-]+" maxlength="20" required>
            <input class="ems-input" type="text" name="mme_kit_areas[__i__][label]" placeholder="FAQ">
            <select class="ems-input" name="mme_kit_areas[__i__][mode]">
                <option value="after_content"><?php echo esc_html__( 'After the content (automatic)', 'mme-elementor-kit' ); ?></option>
                <option value="manual" selected><?php echo esc_html__( 'Manual (widget / shortcode)', 'mme-elementor-kit' ); ?></option>
            </select>
            <button type="button" class="ems-icon-btn ems-icon-btn--danger ems-remove-area" title="<?php echo esc_attr__( 'Remove', 'mme-elementor-kit' ); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </template>
    <button type="button" class="ems-btn ems-btn--secondary ems-add-area ems-field">
        <span class="dashicons dashicons-plus-alt2"></span>
        <?php echo esc_html__( 'Add area', 'mme-elementor-kit' ); ?>
    </button>
</div>

<ul class="ems-meta">
    <li><span><?php echo esc_html__( 'Manual area in Elementor', 'mme-elementor-kit' ); ?></span><strong><?php echo esc_html__( 'Post Area widget', 'mme-elementor-kit' ); ?></strong></li>
    <li><span><?php echo esc_html__( 'Shortcode', 'mme-elementor-kit' ); ?></span><code>[mme_area name="bottom"]</code></li>
    <li><span><?php echo esc_html__( 'Theme PHP', 'mme-elementor-kit' ); ?></span><code>&lt;?php mme_kit_area( 'bottom' ); ?&gt;</code></li>
</ul>

<p class="ems-note">
    <span class="dashicons dashicons-warning"></span>
    <?php echo esc_html__( 'Renaming or removing an area hides the content already designed for it; the content itself is kept until the post is deleted.', 'mme-elementor-kit' ); ?>
</p>

<?php wp_nonce_field( 'mme_kit_save_areas', '_wp_nonce_areas' ); ?>
<input type="hidden" name="action" value="mme_kit_save_areas">
