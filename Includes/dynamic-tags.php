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
        ['gmb-link', 'GMB Link', 'URL'],
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
                        if ('company-phone-1' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url('tel:' . esc_attr(get_option('hozio_company_phone_1')));
                        } elseif ('phone-number-icon-box' === '" . esc_attr($tag[0]) . "') {
                            echo '<a href=\"tel:' . esc_attr(get_option('hozio_company_phone_1')) . '\">' . esc_html(get_option('hozio_company_phone_1')) . '</a>';
                        } elseif ('company-phone-1-name' === '" . esc_attr($tag[0]) . "') {
                            echo esc_html(get_option('hozio_company_phone_1'));
                        } elseif ('company-phone-2' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url('tel:' . esc_attr(get_option('hozio_company_phone_2')));
                        } elseif ('company-phone-2-name' === '" . esc_attr($tag[0]) . "') {
                            echo esc_html(get_option('hozio_company_phone_2'));
                        } elseif ('sms-phone' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url('sms:' . esc_attr(get_option('hozio_sms_phone')));
                        } elseif ('sms-icon-box' === '" . esc_attr($tag[0]) . "') {
                            echo '<a href=\"sms:' . esc_attr(get_option('hozio_sms_phone')) . '\">' . esc_html(get_option('hozio_sms_phone')) . '</a>';
                        } elseif ('sms-phone-name' === '" . esc_attr($tag[0]) . "') {
                            echo esc_html(get_option('hozio_sms_phone'));
                        } elseif ('company-email' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url('mailto:' . esc_attr(get_option('hozio_company_email')));
                        } elseif ('email-icon-box' === '" . esc_attr($tag[0]) . "') {
                            echo '<a href=\"mailto:' . esc_attr(get_option('hozio_company_email')) . '\">' . esc_html(get_option('hozio_company_email')) . '</a>';
                        } elseif ('to-email-contact-form' === '" . esc_attr($tag[0]) . "') {
                            echo esc_html(get_option('hozio_company_email'));
                        } elseif ('sitemap-xml' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('home_url') . '/sitemap.xml');
                        } elseif ('company-address' === '" . esc_attr($tag[0]) . "') {
                            echo esc_html(get_option('hozio_company_address'));
                        } elseif ('business-hours' === '" . esc_attr($tag[0]) . "') {
                            echo esc_html(get_option('hozio_business_hours'));
                        } elseif ('yelp' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_yelp_url'));
                        } elseif ('youtube' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_youtube_url'));
                        } elseif ('angies-list' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_angies_list_url'));
                        } elseif ('home-advisor' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_home_advisor_url'));
                        } elseif ('bbb' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_bbb_url'));
                        } elseif ('facebook' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_facebook_url'));
                        } elseif ('instagram' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_instagram_url'));
                        } elseif ('twitter' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_twitter_url'));
                        } elseif ('tiktok' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_tiktok_url'));
                        } elseif ('linkedin' === '" . esc_attr($tag[0]) . "') {
                            echo esc_url(get_option('hozio_linkedin_url'));
                        } else {
                            echo esc_html(get_option('hozio_" . str_replace('-', '_', $tag[0]) . "'));
                        }
                    }
                }
            ");
        }
        $dynamic_tags->register(new $class_name());
    }
});
?>
