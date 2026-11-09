<?php

$key = isset( $all_rules[$rule_id]['secret_key'] ) ? $all_rules[$rule_id]['secret_key'] : md5(uniqid(rand(), true));
if ( $force_generate_key ) {
    $key = md5(uniqid(rand(), true));
}

$reference          = isset( $all_rules[$rule_id]['reference'] ) ? $all_rules[$rule_id]['reference'] : '';
$max_site           = isset( $all_rules[$rule_id]['max_site'] ) ? $all_rules[$rule_id]['max_site'] : 1;
$template_select_by = isset( $all_rules[$rule_id]['template_select_by'] ) ? $all_rules[$rule_id]['template_select_by'] : 'all';
$categories         = isset( $all_rules[$rule_id]['categories'] ) ? $all_rules[$rule_id]['categories'] : array();
$templates          = isset( $all_rules[$rule_id]['templates'] ) ? $all_rules[$rule_id]['templates'] : array();
$category_style     = $template_select_by === 'categories' ? 'display: block;' : 'display: none;';
$template_style     = $template_select_by === 'templates' ? 'display: block;' : 'display: none;';
$currently_active   = $this->get_site_used( $key );
$usage_percent      = (int) $max_site > 0 ? min( 100, round( $currently_active / (int) $max_site * 100 ) ) : 0;
$field_name         = 'elements_share_rules[' . $rule_id . ']';

$scope_labels = array(
    'all'        => __( 'All templates', 'mme-elementor-kit' ),
    'categories' => __( 'Specific categories', 'mme-elementor-kit' ),
    'templates'  => __( 'Specific templates', 'mme-elementor-kit' ),
);

?>
<div class="ems-key-card <?php echo $force_generate_key ? 'is-new' : ''; ?>" id="rule-<?php echo esc_attr( $rule_id ); ?>">
    <div class="ems-key-card__head">
        <div class="ems-key-card__title">
            <span class="ems-key-card__icon dashicons dashicons-admin-network"></span>
            <div>
                <strong class="ems-key-card__name" data-placeholder="<?php echo esc_attr__( 'Untitled key', 'mme-elementor-kit' ); ?>"><?php echo strlen( $reference ) ? esc_html( $reference ) : esc_html__( 'Untitled key', 'mme-elementor-kit' ); ?></strong>
                <span class="ems-badge ems-key-card__scope"><?php echo esc_html( isset( $scope_labels[ $template_select_by ] ) ? $scope_labels[ $template_select_by ] : $scope_labels['all'] ); ?></span>
            </div>
        </div>
        <div class="ems-key-card__actions">
            <button type="button" class="ems-icon-btn ems-reset-usage" title="<?php echo esc_attr__( 'Reset connected site count', 'mme-elementor-kit' ); ?>">
                <span class="dashicons dashicons-image-rotate"></span>
            </button>
            <button type="button" class="ems-icon-btn ems-icon-btn--danger trash-btn" title="<?php echo esc_attr__( 'Delete key', 'mme-elementor-kit' ); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>

    <div class="ems-field">
        <label class="ems-label"><?php echo esc_html__( 'Secret Key', 'mme-elementor-kit' ); ?></label>
        <div class="ems-input-group">
            <input class="ems-input ems-input--mono ems-key-input" id="ems-key-<?php echo esc_attr( $rule_id ); ?>" type="text" name="<?php echo esc_attr( $field_name ); ?>[secret_key]" value="<?php echo esc_attr( $key ); ?>" readonly>
            <button type="button" class="ems-btn ems-btn--ghost" data-ems-copy="#ems-key-<?php echo esc_attr( $rule_id ); ?>">
                <span class="dashicons dashicons-admin-page"></span>
                <span class="ems-copy-label"><?php echo esc_html__( 'Copy', 'mme-elementor-kit' ); ?></span>
            </button>
        </div>
    </div>

    <div class="ems-usage">
        <div class="ems-usage__row">
            <span><?php echo esc_html__( 'Connected sites', 'mme-elementor-kit' ); ?></span>
            <span><b class="ems-usage__used"><?php echo esc_html( $currently_active ); ?></b> / <span class="ems-usage__max"><?php echo esc_html( $max_site ); ?></span></span>
        </div>
        <span class="ems-progress <?php echo (int) $max_site > 0 && $currently_active >= (int) $max_site ? 'is-full' : ''; ?>"><span style="width: <?php echo esc_attr( $usage_percent ); ?>%"></span></span>
    </div>

    <div class="ems-grid-2">
        <div class="ems-field">
            <label class="ems-label"><?php echo esc_html__( 'Reference (Optional)', 'mme-elementor-kit' ); ?></label>
            <input class="ems-input ems-reference-input" type="text" placeholder="<?php echo esc_attr__( 'e.g. Client ABC', 'mme-elementor-kit' ); ?>" name="<?php echo esc_attr( $field_name ); ?>[reference]" value="<?php echo esc_attr( $reference ); ?>">
        </div>
        <div class="ems-field">
            <label class="ems-label"><?php echo esc_html__( 'Number of sites this key is valid for', 'mme-elementor-kit' ); ?></label>
            <input class="ems-input ems-max-site-input" type="number" min="1" name="<?php echo esc_attr( $field_name ); ?>[max_site]" value="<?php echo esc_attr( $max_site ); ?>">
        </div>
    </div>

    <div class="ems-field">
        <label class="ems-label"><?php echo esc_html__( 'Templates', 'mme-elementor-kit' ); ?></label>
        <select class="ems-input template-picker-options" name="<?php echo esc_attr( $field_name ); ?>[template_select_by]">
            <option <?php selected( $template_select_by, 'all' ); ?> value="all"><?php echo esc_html__( 'All Templates', 'mme-elementor-kit' ); ?></option>
            <option <?php selected( $template_select_by, 'categories' ); ?> value="categories"><?php echo esc_html__( 'Specific Categories', 'mme-elementor-kit' ); ?></option>
            <option <?php selected( $template_select_by, 'templates' ); ?> value="templates"><?php echo esc_html__( 'Specific Templates', 'mme-elementor-kit' ); ?></option>
        </select>
    </div>
    <div class="ems-field single-picker category-picker" style="<?php echo esc_attr( $category_style ); ?>">
        <label class="ems-label"><?php echo esc_html__( 'Choose Categories', 'mme-elementor-kit' ); ?></label>
        <select name="<?php echo esc_attr( $field_name ); ?>[categories][]" multiple class="multi">
            <?php
            foreach($elementor_categories as $cat) {
                $id = $cat->term_id;
                $title = $cat->name;
                $selected = selected( in_array( $id, $categories ), true, false );
                echo '<option '.$selected.' value="'.esc_attr( $id ).'">'.esc_html( $title ).'</option>';
            }
            ?>
        </select>
    </div>
    <div class="ems-field single-picker template-picker" style="<?php echo esc_attr( $template_style ); ?>">
        <label class="ems-label"><?php echo esc_html__( 'Choose Templates', 'mme-elementor-kit' ); ?></label>
        <select name="<?php echo esc_attr( $field_name ); ?>[templates][]" multiple class="multi">
            <?php
            foreach ($elementor_posts as $post) {
                $id       = $post->ID;
                $title    = $post->post_title;
                $selected = selected( in_array( $id, $templates ), true, false );
                echo '<option '.$selected.' value="'.esc_attr( $id ).'">'.esc_html( $title ).'</option>';
            }
            ?>
        </select>
    </div>
</div>
