<?php
// Register custom dynamic tags
add_action('elementor/dynamic_tags/register', function ($dynamic_tags) {
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
        ['company-address', 'Company Address', 'TEXT'], // Allow HTML
        ['yelp', 'Yelp', 'URL'],
        ['youtube', 'YouTube', 'URL'],
        ['angies-list', "Angi's List", 'URL'],
        ['home-advisor', 'Home Advisor', 'URL'],
        ['business-hours', 'Business Hours', 'TEXT'], // Allow HTML
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
        $tags[] = [$tag['value'], $tag['title'], strtoupper($tag['type'])];
    }

    // Register dynamic tags
    foreach ($tags as $tag) {
        $tag_slug = str_replace('-', '_', $tag[0]);
        $class_name = 'My_' . ucwords($tag_slug, '_') . '_Tag';

        if (!class_exists($class_name)) {
            eval("
                class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
                    private \$tag_name = '" . esc_attr($tag[0]) . "';
                    private \$tag_title = '" . esc_attr($tag[1]) . "';
                    private \$tag_type = '" . esc_attr($tag[2]) . "';

                    public function get_name() {
                        return \$this->tag_name;
                    }

                    public function get_title() {
                        return \$this->tag_title;
                    }

                    public function get_group() {
                        return 'site'; // Updated group for Elementor compatibility
                    }

                    public function get_categories() {
                        return [\$this->tag_type === 'URL' ? \\Elementor\\Modules\\DynamicTags\\Module::URL_CATEGORY : \\Elementor\\Modules\\DynamicTags\\Module::TEXT_CATEGORY];
                    }

                    protected function register_controls() {
                        // No controls needed for now
                    }

                    public function render() {
                        \$option_value = get_option('hozio_' . \$this->tag_name);
                        
                        // Define allowed HTML tags for rendering dynamic content
                        \$allowed_tags = array(
                            'br' => array(),
                            'a' => array(
                                'href' => array(),
                                'title' => array(),
                            ),
                            'b' => array(),
                            'i' => array(),
                            'p' => array(),
                            'ul' => array(),
                            'ol' => array(),
                            'li' => array(),
                            // Add other tags you want to allow here
                        );

                        switch (\$this->tag_name) {
                            case 'phone-number-icon-box':
                                // Display Phone Number Icon Box
                                \$phone = esc_attr(get_option('hozio_company_phone_1'));
                                echo '<a href=\"tel:' . \$phone . '\">' . esc_html(\$phone) . '</a>';
                                break;

                            case 'sms-icon-box':
                                // Display SMS Icon Box
                                \$sms_phone = esc_attr(get_option('hozio_sms_phone'));
                                echo '<a href=\"sms:' . \$sms_phone . '\">' . esc_html(\$sms_phone) . '</a>';
                                break;

                            case 'email-icon-box':
                                // Display Email Icon Box
                                \$email = esc_attr(get_option('hozio_company_email'));
                                echo '<a href=\"mailto:' . \$email . '\">' . esc_html(\$email) . '</a>';
                                break;

                            case 'company-address':
                                // Allow HTML for company address
                                echo wp_kses(\$option_value, \$allowed_tags); 
                                break;

                            case 'business-hours':
                                // Allow HTML for business hours
                                echo wp_kses(\$option_value, \$allowed_tags); 
                                break;

                            case 'gmb-link':
                                echo esc_url(\$option_value);  // GMB Link
                                break;

                            case 'company-phone-1':
                                echo esc_url('tel:' . esc_attr(\$option_value));  // Company Phone 1 (URL)
                                break;

                            case 'company-phone-2':
                                echo esc_url('tel:' . esc_attr(\$option_value));  // Company Phone 2 (URL)
                                break;

                            case 'sms-phone':
                                echo esc_url('sms:' . esc_attr(\$option_value));  // SMS phone (URL)
                                break;

                            case 'company-email':
                                echo esc_url('mailto:' . esc_attr(\$option_value));  // Company email (URL)
                                break;

                            default:
                                echo esc_html(\$option_value);  // Default to allow HTML output for all others
                                break;
                        }
                    }
                }
            ");
            $dynamic_tags->register(new $class_name());
        }
    }
}, 50);
?>
