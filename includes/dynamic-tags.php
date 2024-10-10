<?php
// Register custom dynamic tags
add_action('elementor/dynamic_tags/register', function($dynamic_tags) {
    $tags = [
        ['company-phone-1', 'Company Phone Number 1', 'URL'],
        ['company-phone-1-name', 'Company Phone #1 Name', 'TEXT'],
        ['phone-number-icon-box', 'Phone Number (Icon Box desc.)', 'TEXT'],
        ['company-phone-2', 'Company Phone Number 2', 'URL'],
        ['company-phone-2-name', 'Company Phone #2 Name', 'TEXT'],
        ['sms-phone', 'SMS Phone Number', 'URL'],
        ['sms-phone-name', 'SMS Phone # Name', 'TEXT'],
        ['sms-icon-box', 'SMS (Icon Box desc.)', 'TEXT'],
        ['company-email', 'Company Email', 'URL'],
        ['email-icon-box', 'Email (Icon Box desc.)', 'TEXT'],
        ['to-email-contact-form', 'To Email(s) Contact Form', 'TEXT'],
        ['sitemap-xml', 'sitemap.xml', 'URL'],
        ['company-address', 'Company Address', 'TEXT'],
        ['yelp', 'Yelp', 'URL'],
        ['youtube', 'YouTube', 'URL'],
        ['angies-list', "Angi's List", 'URL'],
        ['home-advisor', 'Home Advisor', 'URL'],
        ['business-hours', 'Business Hours', 'TEXT'],
        ['gmb-link', 'GMB Link', 'URL'],  // GMB Link added here
        ['facebook', 'Facebook', 'URL'],
        ['instagram', 'Instagram', 'URL'],
        ['twitter', 'Twitter', 'URL'],
        ['tiktok', 'TikTok', 'URL'],
        ['linkedin', 'LinkedIn', 'URL'],
        ['bbb', 'BBB', 'URL'],
    ];

    foreach ($tags as $tag) {
        $class_name = 'My_' . str_replace('-', '_', ucwords($tag[0], '-')) . '_Tag';
        // Check if the class already exists
        if (!class_exists($class_name)) {
            eval("
                class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
                    public function get_name() { return '" . esc_attr($tag[0]) . "'; }
                    public function get_title() { return __('" . esc_attr($tag[1]) . "', 'plugin-name'); }
                    public function get_group() { return 'site'; }
                    public function get_categories() { return [\\Elementor\\Modules\\DynamicTags\\Module::" . ($tag[2] === 'URL' ? 'URL_CATEGORY' : 'TEXT_CATEGORY') . "]; }
                    protected function register_controls() {}
                    public function render() {
                        if ('company-address' === '" . esc_attr($tag[0]) . "') {
                            echo wp_kses_post(get_option('hozio_company_address'));  // Allow HTML for company address
                        } elseif ('business-hours' === '" . esc_attr($tag[0]) . "') {
                            echo wp_kses_post(get_option('hozio_business_hours'));  // Allow HTML for business hours
                        } elseif ('gmb-link' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_gmb_link'));  // GMB Link
                        } elseif ('company-phone-1' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url('tel:' . esc_attr(get_option('hozio_company_phone_1')));  // Company Phone 1 (URL)
                        } elseif ('phone-number-icon-box' === '" . esc_attr($tag[0]) . "') {
                            echo '<a href=\"tel:' . esc_attr(get_option('hozio_company_phone_1')) . '\">' . esc_html(get_option('hozio_company_phone_1')) . '</a>';  // Phone number icon box
                        } elseif ('company-phone-1-name' === '" . esc_attr($tag[0]) . "') {
                            echo esc_html(get_option('hozio_company_phone_1'));  // Company phone 1 name
                        } elseif ('company-phone-2' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url('tel:' . esc_attr(get_option('hozio_company_phone_2')));  // Company Phone 2 (URL)
                        } elseif ('company-phone-2-name' === '" . esc_attr($tag[0]) . "') {
                            echo esc_html(get_option('hozio_company_phone_2'));  // Company phone 2 name
                        } elseif ('sms-phone' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url('sms:' . esc_attr(get_option('hozio_sms_phone')));  // SMS phone (URL)
                        } elseif ('sms-icon-box' === '" . esc_attr($tag[0]) . "') {
                            echo '<a href=\"sms:' . esc_attr(get_option('hozio_sms_phone')) . '\">' . esc_html(get_option('hozio_sms_phone')) . '</a>';  // SMS phone icon box
                        } elseif ('sms-phone-name' === '" . esc_attr($tag[0]) . "') {
                            echo esc_html(get_option('hozio_sms_phone'));  // SMS phone name
                        } elseif ('company-email' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url('mailto:' . esc_attr(get_option('hozio_company_email')));  // Company email (URL)
                        } elseif ('email-icon-box' === '" . esc_attr($tag[0]) . "') {
                            echo '<a href=\"mailto:' . esc_attr(get_option('hozio_company_email')) . '\">' . esc_html(get_option('hozio_company_email')) . '</a>';  // Email icon box
                        } elseif ('to-email-contact-form' === '" . esc_attr($tag[0]) . "') {
                            echo esc_html(get_option('hozio_to_email_contact_form'));  // To email contact form
                        } elseif ('sitemap-xml' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('home_url') . '/sitemap.xml');  // Sitemap
                        } elseif ('yelp' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_yelp_url'));  // Yelp URL
                        } elseif ('youtube' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_youtube_url'));  // YouTube URL
                        } elseif ('angies-list' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_angies_list_url'));  // Angie's List URL
                        } elseif ('home-advisor' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_home_advisor_url'));  // Home Advisor URL
                        } elseif ('bbb' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_bbb_url'));  // BBB URL
                        } elseif ('facebook' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_facebook_url'));  // Facebook URL
                        } elseif ('instagram' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_instagram_url'));  // Instagram URL
                        } elseif ('twitter' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_twitter_url'));  // Twitter URL
                        } elseif ('tiktok' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_tiktok_url'));  // TikTok URL
                        } elseif ('linkedin' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_linkedin_url'));  // LinkedIn URL
                        } else {
                            echo wp_kses_post(get_option('hozio_" . str_replace('-', '_', $tag[0]) . "'));  // Default to allow HTML output for all others
                        }
                    }
                }
            ");
        }
        $dynamic_tags->register(new $class_name());
    }
});
?>
