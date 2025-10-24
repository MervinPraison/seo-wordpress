<?php

class ZEO_Taxonomy {
	
	function __construct() {
		$options = get_mervin_options();
		$taxonomy = isset($_GET['taxonomy']) ? sanitize_text_field( wp_unslash( $_GET['taxonomy'] ) ) : '';
		
		if (is_admin() && $taxonomy && 
			( !isset($options['tax-hideeditbox-'.$taxonomy]) || !$options['tax-hideeditbox-'.$taxonomy]) )
			add_action($taxonomy . '_edit_form', array(&$this,'term_additions_form'), 10, 2 );
		
		add_action('edit_term', array(&$this,'update_term'), 10, 3 );
	}
	
	function form_row( $id, $label, $desc, $tax_meta, $type = 'text', $options = '' ) {
		$val = '';
		if ( isset($tax_meta[$id]) )
			$val = wp_unslash($tax_meta[$id]);
		
		echo '<tr class="form-field">'."\n";
		echo "\t".'<th scope="row" valign="top"><label for="'.esc_attr($id).'">'.esc_html($label).':</label></th>'."\n";
		echo "\t".'<td>'."\n";
		if ($type == 'text') {
?>
			<input name="<?php echo esc_attr($id); ?>" id="<?php echo esc_attr($id); ?>" type="text" value="<?php echo esc_attr($val); ?>" size="40"/>
			<p class="description"><?php echo esc_html($desc); ?></p>
<?php	
		} else if ($type == 'checkbox') {
?>
			<input name="<?php echo $id; ?>" id="<?php echo $id; ?>" type="checkbox" <?php checked($val); ?>/>
<?php
		} else if ($type == 'select') {
?>
			<select name="<?php echo $id; ?>" id="<?php echo $id; ?>">
				<?php foreach ($options as $option => $label) {
					$sel = '';
					if ($option == $val)
						$sel = " selected='selected'";
					echo "<option".$sel." value='".$option."'>".$label."</option>";
				}?>
			</select>
<?php
		}
		echo "\t".'</td>'."\n";
		echo '</tr>'."\n";
	
	}
	
	function term_additions_form( $term, $taxonomy ) {
		$tax_meta = get_option('zeo_taxonomy_meta');
		$options = get_mervin_options();
		
		if ( isset($tax_meta[$taxonomy][$term->term_id]) )
			$tax_meta = $tax_meta[$taxonomy][$term->term_id];

		echo '<h3>'.__( 'Mervin Praison WordPress SEO Settings', 'seo-wordpress' ).'</h3>';
		echo '<table class="form-table">';

		$this->form_row( 'zeo_title', __( 'SEO Title', 'seo-wordpress' ), __( 'The SEO title is used on the archive page for this term.', 'seo-wordpress' ), $tax_meta );
		$this->form_row( 'zeo_desc', __( 'SEO Description', 'seo-wordpress' ), __( 'The SEO description is used for the meta description on the archive page for this term.', 'seo-wordpress' ), $tax_meta );
		if ( isset($options['usemetakeywords']) && $options['usemetakeywords'] )
			$this->form_row( 'zeo_metakey', __( 'Meta Keywords', 'seo-wordpress' ), __( 'Meta keywords used on the archive page for this term.', 'seo-wordpress' ), $tax_meta );
		$this->form_row( 'zeo_canonical', __( 'Canonical', 'seo-wordpress' ), __( 'The canonical link is shown on the archive page for this term.', 'seo-wordpress' ), $tax_meta );
		$this->form_row( 'zeo_bctitle', __( 'Breadcrumbs Title', 'seo-wordpress' ), sprintf(__( 'The Breadcrumbs title is used in the breadcrumbs where this %s appears.', 'seo-wordpress' ), $taxonomy), $tax_meta );

		$this->form_row( 'zeo_noindex', sprintf(__( 'Noindex this %s', 'seo-wordpress' ), $taxonomy), '', $tax_meta, 'checkbox' );
		$this->form_row( 'zeo_nofollow', sprintf(__( 'Nofollow this %s', 'seo-wordpress' ),$taxonomy), '', $tax_meta, 'checkbox' );
/*
		$this->form_row( 'zeo_sitemap_include', __( 'Include in sitemap?', 'seo-wordpress' ), '', $tax_meta, 'select', array(
			"-" => __("Auto detect", 'seo-wordpress' ),
			"always" => __("Always include", 'seo-wordpress' ),
			"never" => __("Never include", 'seo-wordpress' ),
		) );
		
		*/

		echo '</table>';
	}
	
	function update_term( $term_id, $tt_id, $taxonomy ) {
		$tax_meta = get_option( 'zeo_taxonomy_meta' );

		foreach (array('title', 'desc', 'metakey', 'bctitle', 'canonical', 'sitemap_include') as $key) {
			if ( isset($_POST['zeo_'.$key]) )
				$tax_meta[$taxonomy][$term_id]['zeo_'.$key] 	= sanitize_text_field($_POST['zeo_'.$key]);
		}

		foreach (array('noindex', 'nofollow') as $key) {
			if ( isset($_POST['zeo_'.$key]) )
				$tax_meta[$taxonomy][$term_id]['zeo_'.$key] = true;
			else
				$tax_meta[$taxonomy][$term_id]['zeo_'.$key] = false;			
		}

		update_option( 'zeo_taxonomy_meta', $tax_meta );

		if ( defined('W3TC_DIR') ) {
			require_once W3TC_DIR . '/lib/W3/ObjectCache.php';
		    $w3_objectcache = & W3_ObjectCache::instance();

		    $w3_objectcache->flush();			
		}
	}
}
// $zeo_taxonomy = new ZEO_Taxonomy();
