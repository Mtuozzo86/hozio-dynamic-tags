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
        $tags[] = [$tag['value'], $tag['title'], strtoupper($tag['type'])];
    }

    // Register dynamic tags
    foreach ($tags as $tag) {
        $tag_slug = str_replace('-', '_', $tag[0]);
        $class_name = 'My_' . ucwords($tag_slug) . '_Tag';

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
                        return 'general';
                    }

                    public function get_categories() {
                        return [\$this->tag_type === 'URL' ? \\Elementor\\Modules\\DynamicTags\\Module::URL_CATEGORY : \\Elementor\\Modules\\DynamicTags\\Module::TEXT_CATEGORY];
                    }

                    protected function register_controls() {
                        // No controls needed for now
                    }

                    public function render() {
                        \$value = get_option('hozio_' . str_replace('-', '_', \$this->tag_name));
                        switch (\$this->tag_type) {
                            case 'URL':
                                echo esc_url(\$value);
                                break;
                            case 'TEXT':
                            default:
                                echo esc_html(\$value);
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
