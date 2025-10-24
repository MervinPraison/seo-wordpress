<div class="wrap">
<h1>Import Settings</h1>
<?php
	$message = null;
		$message_updated ="All in one SEO Pack migrated.";

		// update options
		$action = isset($_POST['action']) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		if ( $action && $action == 'aioseop-migrate') {

			/*NONCE Verification*/

		$nonce = isset($_POST['aioseop-migrate-nonce']) ? sanitize_text_field( wp_unslash( $_POST['aioseop-migrate-nonce'] ) ) : '';

			if (!wp_verify_nonce($nonce, 'aioseop-migrate-nonce')) die ( 'Security Check - If you receive this in error, log out and back in to WordPress');

$aioseop_options = get_option('aioseop_options');

update_option('zeo_common_home_title',$aioseop_options['aiosp_home_title']);
update_option('zeo_home_description',$aioseop_options['aiosp_home_keywords']);
update_option('zeo_home_keywords',$aioseop_options['aiosp_home_description']);

update_option('zeo_canonical_url', $aioseop_options['aiosp_can']);
update_option('zeo_activate_title', $aioseop_options['aiosp_rewrite_titles']);
update_option('zeo_common_post_title', $aioseop_options['aiosp_post_title_format']);
update_option('zeo_common_page_title', $aioseop_options['aiosp_page_title_format']);
update_option('zeo_common_category_title', $aioseop_options['aiosp_category_title_format']);
update_option('zeo_common_archive_title', $aioseop_options['aiosp_archive_title_format']);
update_option('zeo_common_tag_title', $aioseop_options['aiosp_tag_title_format']);
update_option('zeo_common_search_title', $aioseop_options['aiosp_search_title_format']);
update_option('zeo_description_format', $aioseop_options['aiosp_description_format']);
update_option('zeo_common_error_title', $aioseop_options['aiosp_404_title_format']);
update_option('aiosp_paged_format', $aioseop_options['aiosp_paged_format']);

update_option('psp_category_noindex', $aioseop_options['aiosp_category_noindex']);
update_option('psp_archive_noindex', $aioseop_options['aiosp_archive_noindex']);
update_option('psp_tags_noindex', $aioseop_options['aiosp_tags_noindex']);

update_option('aiosp_generate_descriptions', $aioseop_options['aiosp_generate_descriptions']);
update_option('aiosp_post_meta_tags', $aioseop_options['aiosp_post_meta_tags']);
update_option('aiosp_page_meta_tags', $aioseop_options['aiosp_page_meta_tags']);
update_option('aiosp_home_meta_tags', $aioseop_options['aiosp_home_meta_tags']);
update_option('zeo_do_log', $aioseop_options['aiosp_do_log']);

global $wpdb;
$wpdb->query("UPDATE $wpdb->postmeta SET meta_key = 'keywords' WHERE meta_key = '_aioseop_keywords'");
$wpdb->query("UPDATE $wpdb->postmeta SET meta_key = 'title' WHERE meta_key = '_aioseop_title'");	
$wpdb->query("UPDATE $wpdb->postmeta SET meta_key = 'description' WHERE meta_key = '_aioseop_description'");
$wpdb->query("UPDATE $wpdb->postmeta SET meta_key = 'psp_disable' WHERE meta_key in ('_aioseop_disable', 'aioseop_disable')" );
echo "<div class='updated fade' style='background-color:green;border-color:green;'><p><strong>Migrated All in One SEO to SEO Wordpress Plugin.</strong></p></div";
}
?>

<p><?php _e('Click the button to migrate All in one SEO options to SEO Wordpress Plugin.<br>(It is recommended to back up your database before updating.)', 'platinum_seo_pack') ?></em></p></div>
<form name="aioseop-migrate" action="" method="post">
<p class="submit">
<input type="hidden" name="action" value="aioseop-migrate" />
<input type="hidden" name="aioseop-migrate-nonce" value="<?php echo esc_attr( wp_create_nonce('aioseop-migrate-nonce') ); ?>" />
<input type="submit" name="Submit" value="<?php _e('Migrate from All in One SEO', 'platinum_seo_pack')?> &raquo;" />
</p>

</form>



</div>