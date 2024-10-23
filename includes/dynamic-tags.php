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
                        if ('company-address' === \$this->tag_name) {
                            echo wp_kses_post(get_option('hozio_company_address'));  // Allow HTML for company address
                        } elseif ('business-hours' === \$this->tag_name) {
                            echo wp_kses_post(get_option('hozio_business_hours'));  // Allow HTML for business hours
                        } elseif ('gmb-link' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_gmb_link'));  // GMB Link
                        } elseif ('company-phone-1' === \$this->tag_name) {
                            echo esc_url('tel:' . esc_attr(get_option('hozio_company_phone_1')));  // Company Phone 1 (URL)
                        } elseif ('phone-number-icon-box' === \$this->tag_name) {
                            echo '<a href=\"tel:' . esc_attr(get_option('hozio_company_phone_1')) . '\">' . esc_html(get_option('hozio_company_phone_1')) . '</a>';  // Phone number icon box
                        } elseif ('company-phone-1-name' === \$this->tag_name) {
                            echo esc_html(get_option('hozio_company_phone_1'));  // Company phone 1 name
                        } elseif ('company-phone-2' === \$this->tag_name) {
                            echo esc_url('tel:' . esc_attr(get_option('hozio_company_phone_2')));  // Company Phone 2 (URL)
                        } elseif ('company-phone-2-name' === \$this->tag_name) {
                            echo esc_html(get_option('hozio_company_phone_2'));  // Company phone 2 name
                        } elseif ('sms-phone' === \$this->tag_name) {
                            echo esc_url('sms:' . esc_attr(get_option('hozio_sms_phone')));  // SMS phone (URL)
                        } elseif ('sms-icon-box' === \$this->tag_name) {
                            echo '<a href=\"sms:' . esc_attr(get_option('hozio_sms_phone')) . '\">' . esc_html(get_option('hozio_sms_phone')) . '</a>';  // SMS phone icon box
                        } elseif ('sms-phone-name' === \$this->tag_name) {
                            echo esc_html(get_option('hozio_sms_phone'));  // SMS phone name
                        } elseif ('company-email' === \$this->tag_name) {
                            echo esc_url('mailto:' . esc_attr(get_option('hozio_company_email')));  // Company email (URL)
                        } elseif ('email-icon-box' === \$this->tag_name) {
                            echo '<a href=\"mailto:' . esc_attr(get_option('hozio_company_email')) . '\">' . esc_html(get_option('hozio_company_email')) . '</a>';  // Email icon box
                        } elseif ('to-email-contact-form' === \$this->tag_name) {
                            echo esc_html(get_option('hozio_to_email_contact_form'));  // To email contact form
                        } elseif ('sitemap-xml' === \$this->tag_name) {
                            echo esc_url(home_url('/sitemap.xml'));  // Sitemap
                        } elseif ('yelp' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_yelp_url'));  // Yelp URL
                        } elseif ('youtube' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_youtube_url'));  // YouTube URL
                        } elseif ('angies-list' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_angies_list_url'));  // Angie's List URL
                        } elseif ('home-advisor' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_home_advisor_url'));  // Home Advisor URL
                        } elseif ('bbb' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_bbb_url'));  // BBB URL
                        } elseif ('facebook' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_facebook_url'));  // Facebook URL
                        } elseif ('instagram' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_instagram_url'));  // Instagram URL
                        } elseif ('twitter' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_twitter_url'));  // Twitter URL
                        } elseif ('tiktok' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_tiktok_url'));  // TikTok URL
                        } elseif ('linkedin' === \$this->tag_name) {
                            echo esc_url(get_option('hozio_linkedin_url'));  // LinkedIn URL
                        } else {
                            echo esc_html(get_option('hozio_' . str_replace('-', '_', \$this->tag_name)));  // Default to allow HTML output for all others
                        }
                    }
                }
            ");
            $dynamic_tags->register(new $class_name());
        }
    }
}, 50);
?>
