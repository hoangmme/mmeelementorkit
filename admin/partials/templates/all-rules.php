<div class="ems-keys-list">
    <?php
        if ( is_array( $all_rules ) && count( $all_rules ) ) {
            foreach ($all_rules as $rule_id => $rule) {
                $this->single_rule_section( $rule_id, $all_rules, $elementor_posts, $elementor_categories );
            }
        }
    ?>
</div>
<div class="ems-empty <?php echo is_array( $all_rules ) && count( $all_rules ) ? 'is-hidden' : ''; ?>">
    <span class="dashicons dashicons-admin-network"></span>
    <p><?php echo esc_html__( 'No secret keys yet. Add one to start sharing templates.', 'mme-elementor-kit' ); ?></p>
</div>
