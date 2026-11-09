<?php
/**
 * Template Item
 */
?>

<div class="elementor-template-library-template-body">
	<div class="elementor-template-library-template-screenshot">
		<div class="elementor-template-library-template-title">
            <span class="">{{ title }}</span>
        </div>
        <div class="emselementor-template--thumb">
            <div class="emselementor-template--label">
                <# if ( is_pro ) { #>
                <span class="emselementor-template--tag emselementor-template--pro"><?php echo esc_html__( 'Elementor Pro', 'mme-elementor-kit' ); ?></span><span class="emselementor-template--sep"></span>
                <# } #>
                <?php if (class_exists( 'SFWD_LMS' )) { ?>
                    <# if ( is_learndash ) { #>
                    <span class="emselementor-template--tag emselementor-template--ld"><?php echo esc_html__( 'LearnDash', 'mme-elementor-kit' ); ?></span><span class="emselementor-template--sep"></span>
                    <# } #>
                <?php } elseif (class_exists( 'LifterLMS' )) { ?>
                    <# if ( is_lifter ) { #>
                    <span class="emselementor-template--tag emselementor-template--llms"><?php echo esc_html__( 'LifterLMS', 'mme-elementor-kit' ); ?></span><span class="emselementor-template--sep"></span>
                    <# } #>
                <?php } else { ?>
                    <# if ( is_learndash ) { #>
                    <span class="emselementor-template--tag emselementor-template--ld"><?php echo esc_html__( 'LearnDash', 'mme-elementor-kit' ); ?></span><span class="emselementor-template--sep"></span>
                    <# } #>
                    <# if ( is_lifter ) { #>
                    <span class="emselementor-template--tag emselementor-template--llms"><?php echo esc_html__( 'LifterLMS', 'mme-elementor-kit' ); ?></span><span class="emselementor-template--sep"></span>
                    <# } #>
                <?php } ?>
                <# if ( is_woo ) { #>
                <span class="emselementor-template--tag emselementor-template--woo"><?php echo esc_html__( 'WooCommerce', 'mme-elementor-kit' ); ?></span><span class="emselementor-template--sep"></span>
                <# } #>
                <# if ( is_tec ) { #>
                <span class="emselementor-template--tag emselementor-template--tec"><?php echo esc_html__( 'The Events Calendar', 'mme-elementor-kit' ); ?></span><span class="emselementor-template--sep"></span>
                <# } #>
            </div>
            <img src="{{ thumbnail }}" alt="{{ title }}">
        </div>
	</div>
</div>
<div class="elementor-template-library-template-controls">
    <button class="elementor-template-library-template-action emselementor-template-insert elementor-button elementor-button-success">
        <i class="eicon-file-download"></i>
        <span class="elementor-button-title"><?php echo esc_html__( 'Insert', 'mme-elementor-kit' ); ?></span>
    </button>
</div>