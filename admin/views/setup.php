<?php
/**
 * Setup wizard view.
 *
 * @package AISEO
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('aiseo_render_setup_view')) {
function aiseo_render_setup_view($step) {
    $step = absint($step);
    $steps = array(
        1 => __('Welcome', 'aiseo'),
        2 => __('Site', 'aiseo'),
        3 => __('Features', 'aiseo'),
        4 => __('Homepage', 'aiseo'),
        5 => __('Finish', 'aiseo'),
    );
    $details = get_option('aiseo_organization_details', array());
    if (!is_array($details)) {
        $details = array();
    }
    $org_name = isset($details['name']) ? $details['name'] : get_bloginfo('name');
    $represents = get_option('aiseo_site_represents', 'organization');
    $home = class_exists('AISEO_Homepage_SEO') ? (new AISEO_Homepage_SEO())->get_settings() : array();
    $feature_on = static function ($option) {
        return !in_array(get_option($option, '1'), array(false, 0, '0', ''), true);
    };
    $site_is_public = (get_option('blog_public') !== '0');
    ?>
    <div class="wrap aiseo-setup-wrap">
        <h1><?php esc_html_e('Praison AI SEO setup', 'aiseo'); ?></h1>
        <ol class="aiseo-setup-steps">
            <?php foreach ($steps as $number => $label) : ?>
                <li class="<?php echo esc_attr($number === $step ? 'is-current' : ($number < $step ? 'is-done' : '')); ?>">
                    <span class="aiseo-setup-step-num"><?php echo esc_html((string) $number); ?></span>
                    <?php echo esc_html($label); ?>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="aiseo-setup-card">
            <form method="post">
                <?php wp_nonce_field('aiseo_setup', 'aiseo_setup_nonce'); ?>
                <input type="hidden" name="aiseo_setup_step" value="<?php echo esc_attr((string) $step); ?>" />

                <?php if ($step === 1) : ?>
                    <h2><?php esc_html_e('Set up search appearance in a few steps', 'aiseo'); ?></h2>
                    <p><?php esc_html_e('This wizard writes the same settings you can change later. It does not invent rankings or search volume.', 'aiseo'); ?></p>
                    <p>
                        <button type="submit" name="aiseo_setup_action" value="next" class="button button-primary button-hero"><?php esc_html_e('Start setup', 'aiseo'); ?></button>
                    </p>
                <?php elseif ($step === 2) : ?>
                    <h2><?php esc_html_e('Who does this site represent?', 'aiseo'); ?></h2>
                    <p><?php esc_html_e('Search engines use this for Knowledge Graph markup.', 'aiseo'); ?></p>
                    <fieldset class="aiseo-setup-fieldset">
                        <legend class="screen-reader-text"><?php esc_html_e('Site representation', 'aiseo'); ?></legend>
                        <label>
                            <input type="radio" name="aiseo_site_represents" value="organization" <?php checked($represents, 'organization'); ?> />
                            <?php esc_html_e('An organisation or brand', 'aiseo'); ?>
                        </label>
                        <label>
                            <input type="radio" name="aiseo_site_represents" value="person" <?php checked($represents, 'person'); ?> />
                            <?php esc_html_e('A person', 'aiseo'); ?>
                        </label>
                    </fieldset>
                    <p>
                        <label for="aiseo_org_name"><?php esc_html_e('Name', 'aiseo'); ?></label><br />
                        <input type="text" class="regular-text" id="aiseo_org_name" name="aiseo_org_name" value="<?php echo esc_attr(is_string($org_name) ? $org_name : ''); ?>" />
                    </p>
                    <p>
                        <button type="submit" name="aiseo_setup_action" value="save_representation" class="button button-primary"><?php esc_html_e('Continue', 'aiseo'); ?></button>
                    </p>
                <?php elseif ($step === 3) : ?>
                    <h2><?php esc_html_e('Turn on the essentials', 'aiseo'); ?></h2>
                    <p><?php esc_html_e('Recommended features are on. You can change them later in Settings.', 'aiseo'); ?></p>
                    <label class="aiseo-setup-check">
                        <input type="checkbox" name="aiseo_enable_schema" value="1" <?php checked($feature_on('aiseo_enable_schema')); ?> />
                        <?php esc_html_e('Schema markup (JSON-LD)', 'aiseo'); ?>
                    </label>
                    <label class="aiseo-setup-check">
                        <input type="checkbox" name="aiseo_enable_sitemap" value="1" <?php checked($feature_on('aiseo_enable_sitemap')); ?> />
                        <?php esc_html_e('XML sitemap', 'aiseo'); ?>
                    </label>
                    <label class="aiseo-setup-check">
                        <input type="checkbox" name="aiseo_enable_llms" value="1" <?php checked($feature_on('aiseo_enable_llms')); ?> />
                        <?php esc_html_e('llms.txt for AI crawlers', 'aiseo'); ?>
                    </label>
                    <label class="aiseo-setup-check">
                        <input type="checkbox" name="aiseo_enable_indexnow" value="1" <?php checked($feature_on('aiseo_enable_indexnow')); ?> />
                        <?php esc_html_e('IndexNow', 'aiseo'); ?>
                    </label>
                    <p>
                        <button type="submit" name="aiseo_setup_action" value="save_features" class="button button-primary"><?php esc_html_e('Continue', 'aiseo'); ?></button>
                    </p>
                <?php elseif ($step === 4) : ?>
                    <h2><?php esc_html_e('Homepage search snippet', 'aiseo'); ?></h2>
                    <p>
                        <label for="aiseo_home_title"><?php esc_html_e('SEO title', 'aiseo'); ?></label><br />
                        <input type="text" class="large-text" id="aiseo_home_title" name="aiseo_home_title" value="<?php echo esc_attr(isset($home['home_title']) ? $home['home_title'] : ''); ?>" />
                    </p>
                    <p>
                        <label for="aiseo_home_description"><?php esc_html_e('Meta description', 'aiseo'); ?></label><br />
                        <textarea class="large-text" rows="3" id="aiseo_home_description" name="aiseo_home_description"><?php echo esc_textarea(isset($home['home_description']) ? $home['home_description'] : ''); ?></textarea>
                    </p>
                    <p>
                        <button type="submit" name="aiseo_setup_action" value="save_homepage" class="button button-primary"><?php esc_html_e('Continue', 'aiseo'); ?></button>
                    </p>
                <?php else : ?>
                    <h2><?php esc_html_e('Setup is ready to finish', 'aiseo'); ?></h2>
                    <?php if (!$site_is_public) : ?>
                        <div class="notice notice-error inline">
                            <p>
                                <?php esc_html_e('WordPress is set not to let search engines index this site. Allow them in Reading settings when you are ready to go live.', 'aiseo'); ?>
                                <a href="<?php echo esc_url(admin_url('options-reading.php')); ?>"><?php esc_html_e('Reading settings', 'aiseo'); ?></a>
                            </p>
                        </div>
                    <?php else : ?>
                        <p><?php esc_html_e('Search engines are allowed. Connect Google Search Console in Settings if you want clicks and impressions from Google, not estimates.', 'aiseo'); ?></p>
                    <?php endif; ?>
                    <p>
                        <button type="submit" name="aiseo_setup_action" value="finish" class="button button-primary button-hero"><?php esc_html_e('Go to the dashboard', 'aiseo'); ?></button>
                    </p>
                <?php endif; ?>

                <?php if ($step < 5) : ?>
                    <p class="aiseo-setup-skip">
                        <button type="submit" name="aiseo_setup_action" value="skip" class="button-link"><?php esc_html_e('Skip for now', 'aiseo'); ?></button>
                    </p>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php
}
}

aiseo_render_setup_view($step);
