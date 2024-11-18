<?php
// Register custom dynamic tags
add_action('elementor/dynamic_tags/register', function ($dynamic_tags) {
    // Base tags (default set of tags)
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

    // Fetch custom tags from the options table
    $custom_tags = get_option('hozio_custom_tags', []);
    
    // Add custom tags from the database
    foreach ($custom_tags as $tag) {
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
                        return 'site'; // Group for Elementor compatibility
                    }

                    public function get_categories() {
                        // Return category based on type: URL or TEXT
                        return [\$this->tag_type === 'URL' ? \\Elementor\\Modules\\DynamicTags\\Module::URL_CATEGORY : \\Elementor\\Modules\\DynamicTags\\Module::TEXT_CATEGORY];
                    }

                    protected function register_controls() {
                        // No controls needed for now
                    }

                    public function render() {
                        // Get the tag value from the options table
                        \$option_value = get_option('hozio_' . \$this->tag_name);
                        
                        // Define allowed HTML tags for rendering dynamic content (HTML content)
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

                        // Render the tag based on its name and type
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
                                // Display the GMB link
                                echo esc_url(\$option_value);
                                break;

                            case 'company-phone-1':
                                // Display Company Phone 1 (URL)
                                echo esc_url('tel:' . esc_attr(\$option_value));  
                                break;

                            case 'company-phone-2':
                                // Display Company Phone 2 (URL)
                                echo esc_url('tel:' . esc_attr(\$option_value));  
                                break;

                            case 'sms-phone':
                                // Display SMS phone (URL)
                                echo esc_url('sms:' . esc_attr(\$option_value));  
                                break;

                            case 'company-email':
                                // Display Company email (URL)
                                echo esc_url('mailto:' . esc_attr(\$option_value));  
                                break;

                            default:
                                // Default: display the value as text (with sanitization for HTML)
                                echo esc_html(\$option_value);  
                                break;
                        }
                    }
                }
            ");
            // Register the dynamic tag
            $dynamic_tags->register(new $class_name());
        }
    }
}, 50);
