<?php
// Register custom dynamic tags
add_action('elementor/dynamic_tags/register', function($dynamic_tags) {
    // Base tags
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

    // Add custom tags from options
    $custom_tags = get_option('hozio_custom_tags', []);
    foreach ($custom_tags as $tag) {
        // Ensure the tag type is set correctly
        $tags[] = [$tag['value'], $tag['title'], 'hozio_' . $tag['value'], strtoupper($tag['type'])];
    }

    // Register dynamic tags
    foreach ($tags as $tag) {
        $class_name = 'My_' . str_replace('-', '_', ucwords($tag[0], '-')) . '_Tag';

        if (!class_exists($class_name)) {
            eval("
                class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
                    public function get_name() { return '" . esc_attr($tag[0]) . "'; }
                    public function get_title() { return __('" . esc_attr($tag[1]) . "', 'plugin-name'); }
                    public function get_group() { return 'site'; }
                    public function get_categories() { return [\\Elementor\\Modules\\DynamicTags\\Module::" . ($tag[2] === 'URL' ? 'URL_CATEGORY' : 'TEXT_CATEGORY') . "]; }
                    protected function register_controls() {}

                    public function render() {
                        // Render logic based on tag name
                        switch ('" . esc_attr($tag[0]) . "') {
                            case 'company-address':
                                echo wp_kses_post(get_option('hozio_company_address'));
                                break;
                            case 'business-hours':
                                echo wp_kses_post(get_option('hozio_business_hours'));
                                break;
                            case 'gmb-link':
                                echo esc_url(get_option('hozio_gmb_link'));
                                break;
                            case 'company-phone-1':
                                echo esc_url('tel:' . esc_attr(get_option('hozio_company_phone_1')));
                                break;
                            case 'company-phone-1-name':
                                echo esc_html(get_option('hozio_company_phone_1'));
                                break;
                            case 'company-phone-2':
                                echo esc_url('tel:' . esc_attr(get_option('hozio_company_phone_2')));
                                break;
                            case 'sms-phone':
                                echo esc_url('sms:' . esc_attr(get_option('hozio_sms_phone')));
                                break;
                            case 'company-email':
                                echo esc_url('mailto:' . esc_attr(get_option('hozio_company_email')));
                                break;
                            // Add other cases for existing tags as necessary...
                            default:
                                echo wp_kses_post(get_option('hozio_' . str_replace('-', '_', $tag[0])));
                        }
                    }
                }
            ");
            $dynamic_tags->register(new $class_name());
        }
    }
});
?>
