<?php
if ( is_user_logged_in() && (int) get_user_meta( get_current_user_id(), '_mpg_dismiss_subscribe_notice', true ) ) {
	return;
}
?>
<div class="mpg-free-seo-guide">
	<div class="mpg-header">
		<div class="mpg-title" data-success_title="<?php esc_attr_e( 'You\'re in!', 'multiple-pages-generator-by-porthas' ); ?>"><?php esc_html_e( 'Master Programmatic SEO', 'multiple-pages-generator-by-porthas' ); ?></div>
		<div class="mpg-close">
			<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'mpg_dismiss_subscribe_notice', '_nonce' => wp_create_nonce( MPG_BASENAME ) ), admin_url( 'admin.php' ) ) ); ?>"><span class="dashicons dashicons-no-alt"></span></a>
		</div>
	</div>
	<div class="mpg-image">
		<img src="<?php echo esc_url( MPG_BASE_IMG_PATH . '/sidebar-subscribe.jpg' ); ?>">
		<img src="<?php echo esc_url( MPG_BASE_IMG_PATH . '/subscribe-thank-you.jpg' ); ?>" class="d-none">
	</div>
	<div class="mpg-form-wrapper">
		<div class="mpg-form-message" data-success_message="<?php esc_attr_e( 'Thanks for joining! We\'ll send you programmatic SEO tips to help you create pages at scale. Get ready to boost your search rankings!', 'multiple-pages-generator-by-porthas' ); ?>"><?php esc_html_e( 'Learn how to scale your SEO with automation. Get tips on creating content that ranks across multiple pages.', 'multiple-pages-generator-by-porthas' ); ?></div>
		<div class="mpg-form">
			<form method="post" id="subscribe-form">
				<input type="hidden" name="action" value="mpg_ti_subscribe">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( MPG_BASENAME ) ); ?>">
				<input type="email" class="mpg-input" name="email" placeholder="<?php esc_attr_e( 'Enter your email', 'multiple-pages-generator-by-porthas' ); ?>" required >
				<input type="submit" class="mpg-submit" value="<?php esc_attr_e( 'Sign Up', 'multiple-pages-generator-by-porthas' ); ?>">
			</form>
		</div>
	</div>
</div>