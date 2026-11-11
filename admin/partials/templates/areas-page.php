<?php
/**
 * Areas page: which post types get extra Elementor areas, and the areas themselves.
 *
 * @var Ems_Elements_Share\Areas\Ems_Areas_Manager $areas_manager
 */

$config     = $areas_manager->get_config();
$post_types = $areas_manager->get_available_post_types();
$rows       = $config['areas'];
?>
<div class="wrap ems-app ems-app--areas">
    <?php require __DIR__ . '/page-header.php'; ?>

    <div class="ems-layout">
        <main class="ems-layout__main">
            <form action="" method="POST" class="ems-card">
                <div class="ems-card__header">
                    <div>
                        <h2 class="ems-card__title"><?php echo esc_html__( 'Extra Elementor areas', 'mme-elementor-kit' ); ?></h2>
                        <p class="ems-card__desc"><?php echo esc_html__( 'Besides the main content, each post gets these areas, each with its own "Edit with Elementor" button.', 'mme-elementor-kit' ); ?></p>
                    </div>
                </div>

                <?php do_action( 'ems_elements_share_areas_notice' ); ?>

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
                        foreach ( $rows as $name => $area ) :
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
                    <template id="ems-area-row-template">
                        <div class="ems-area-row">
                            <input class="ems-input ems-input--mono" type="text" name="mme_kit_areas[__i__][name]" placeholder="sidebar" pattern="[a-z0-9_\-]+" maxlength="20" required>
                            <input class="ems-input" type="text" name="mme_kit_areas[__i__][label]" placeholder="<?php echo esc_attr__( 'Sidebar', 'mme-elementor-kit' ); ?>">
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
                    <p class="ems-note">
                        <span class="dashicons dashicons-warning"></span>
                        <?php echo esc_html__( 'Renaming or removing an area hides the content already designed for it; the content itself is kept until the post is deleted.', 'mme-elementor-kit' ); ?>
                    </p>
                </div>

                <?php wp_nonce_field( 'mme_kit_save_areas', '_wp_nonce_areas' ); ?>
                <input type="hidden" name="action" value="mme_kit_save_areas">
                <div class="ems-form-actions">
                    <button type="submit" class="ems-btn ems-btn--primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php echo esc_html__( 'Save Changes', 'mme-elementor-kit' ); ?>
                    </button>
                </div>
            </form>
        </main>

        <aside class="ems-layout__aside">
            <div class="ems-card">
                <h2 class="ems-card__title"><?php echo esc_html__( 'How to use', 'mme-elementor-kit' ); ?></h2>
                <ol class="ems-steps">
                    <li><?php echo esc_html__( 'Open a post and click "Edit Bottom with Elementor" in the MMe Elementor areas box (also in the posts list and the admin bar).', 'mme-elementor-kit' ); ?></li>
                    <li><?php echo esc_html__( 'Design the area and click Update. The main content keeps its own "Edit with Elementor".', 'mme-elementor-kit' ); ?></li>
                    <li><?php echo esc_html__( '"After the content" areas appear under the_content() automatically. "Manual" areas appear where you place them:', 'mme-elementor-kit' ); ?></li>
                </ol>
                <ul class="ems-meta">
                    <li><span><?php echo esc_html__( 'Elementor', 'mme-elementor-kit' ); ?></span><strong><?php echo esc_html__( 'Post Area widget', 'mme-elementor-kit' ); ?></strong></li>
                    <li><span><?php echo esc_html__( 'Shortcode', 'mme-elementor-kit' ); ?></span><code>[mme_area name="bottom"]</code></li>
                    <li><span><?php echo esc_html__( 'Theme PHP', 'mme-elementor-kit' ); ?></span><code>&lt;?php mme_kit_area( 'bottom' ); ?&gt;</code></li>
                </ul>
            </div>
        </aside>
    </div>
</div>
