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
        ['sitemap-xml', 'Sitemap', 'URL'],
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
        ['years-of-experience', 'Years of Experience', 'TEXT'], // Dynamic tag for calculated value
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
            eval("class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
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
                    return [\$this->tag_type === 'URL' ? \\Elementor\\Modules\\DynamicTags\\Module::URL_CATEGORY : \\Elementor\\Modules\\DynamicTags\\Module::TEXT_CATEGORY];
                }

                protected function register_controls() {}

                public function render() {
                    if (\$this->tag_name === 'years-of-experience') {
                        // Fetch the stored start year
                        \$start_year = get_option('hozio_start_year', 2010); // Default to 2010 if not set
                        \$current_year = (int) date('Y');
                        
                        // Calculate years of experience
                        \$years_of_experience = \$current_year - (int) \$start_year;

                        // Output the calculated value
                        echo esc_html(\$years_of_experience);
                        return;
                    }

                    // Fetch the option value for other tags
                    \$option_value = get_option('hozio_' . \$this->tag_name);
                    \$allowed_tags = [
                        'br' => [],
                        'a' => ['href' => [], 'title' => []],
                        'b' => [], 'i' => [],
                        'p' => [], 'ul' => [], 'ol' => [], 'li' => [],
                    ];

                    // Render the content based on the tag type
                    switch (\$this->tag_name) {
                        case 'phone-number-icon-box':
                            \$phone = esc_attr(get_option('hozio_company_phone_1'));
                            echo '<a href=\"tel:' . \$phone . '\">' . esc_html(\$phone) . '</a>';
                            break;

                        case 'sms-icon-box':
                            \$sms_phone = esc_attr(get_option('hozio_sms_phone'));
                            echo '<a href=\"sms:' . \$sms_phone . '\">' . esc_html(\$sms_phone) . '</a>';
                            break;

                        case 'email-icon-box':
                            \$email = esc_attr(get_option('hozio_company_email'));
                            echo '<a href=\"mailto:' . \$email . '\">' . esc_html(\$email) . '</a>';
                            break;

                        case 'company-address':
                            echo wp_kses(\$option_value, \$allowed_tags);
                            break;

                        case 'business-hours':
                            echo wp_kses(\$option_value, \$allowed_tags);
                            break;

                        case 'gmb-link':
                            echo esc_url(\$option_value);
                            break;

                        case 'company-phone-1':
                            echo esc_url('tel:' . esc_attr(\$option_value));
                            break;

                        case 'company-phone-2':
                            echo esc_url('tel:' . esc_attr(\$option_value));
                            break;

                        case 'sms-phone':
                            echo esc_url('sms:' . esc_attr(\$option_value));
                            break;

                        case 'company-email':
                            echo esc_url('mailto:' . esc_attr(\$option_value));
                            break;

                        default:
                            echo esc_html(\$option_value);
                            break;
                    }
                }
            }");
            $dynamic_tags->register(new $class_name());
        }
    }

    // Register the new Google Ads Thank You Page dynamic tag
    class My_Google_Ads_Thank_You_Page_Tag extends \Elementor\Core\DynamicTags\Tag {
        public function get_name() {
            return 'google_ads_thank_you_page';
        }

        public function get_title() {
            return __('Google Ads Thank You Page', 'elementor');
        }

        public function get_group() {
            return 'site';
        }

        public function get_categories() {
            return [\Elementor\Modules\DynamicTags\Module::URL_CATEGORY];
        }

        protected function register_controls() {
            // No controls needed for this tag
        }

        public function render() {
            // Get the current site URL
            $site_url = get_site_url();

            // Get the email value dynamically (assuming it's available in the form submission)
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

            // Construct the thank you page URL
            $thank_you_url = $site_url . '/thank-you/?email=' . urlencode($email);

            // Output the URL
            echo esc_url($thank_you_url);
        }
    }
	
    $dynamic_tags->register(new My_Google_Ads_Thank_You_Page_Tag());
    $class_name = 'My_Services_Children_Tag';
    if (!class_exists($class_name)) {
        eval("
            class $class_name extends \\Elementor\\Core\\DynamicTags\\Tag {
                public function get_name() {
                    return 'services_children';
                }
                public function get_title() {
                    return __('Query ID Service Child Pages', 'plugin-name');
                }
                public function get_group() {
                    return 'site';
                }
                public function get_categories() {
                    return [\\Elementor\\Modules\\DynamicTags\\Module::TEXT_CATEGORY];
                }
                protected function register_controls() {}
                public function render() {
                    // Directly output the fixed value for 'services_children'
                    echo 'services_children';
                }
            }
        ");
        $dynamic_tags->register(new $class_name());
    }

    // Register composite dynamic tag
    class Hozio_Composite_Tag extends \Elementor\Core\DynamicTags\Tag {
        public function get_name() {
            return 'hozio_composite_tag';
        }

        public function get_title() {
            return __('Composite Dynamic Tag', 'hozio');
        }

        public function get_group() {
            return 'site';
        }

        public function get_categories() {
            return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY];
        }

        protected function register_controls() {
            $this->add_control(
                'before_text',
                [
                    'label' => __('Before Text', 'hozio'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => '',
                ]
            );

            $this->add_control(
                'dynamic_tag_one',
                [
                    'label' => __('Dynamic Tag 1', 'hozio'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => $this->get_dynamic_tag_options(),
                    'default' => '',
                ]
            );

            $this->add_control(
                'between_text',
                [
                    'label' => __('Between Text', 'hozio'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => '',
                ]
            );

            $this->add_control(
                'dynamic_tag_two',
                [
                    'label' => __('Dynamic Tag 2', 'hozio'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => $this->get_dynamic_tag_options(),
                    'default' => '',
                ]
            );

            $this->add_control(
                'after_text',
                [
                    'label' => __('After Text', 'hozio'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => '',
                ]
            );
        }

        public function render() {
            $before_text = $this->get_settings('before_text');
            $dynamic_tag_one = $this->get_settings('dynamic_tag_one');
            $between_text = $this->get_settings('between_text');
            $dynamic_tag_two = $this->get_settings('dynamic_tag_two');
            $after_text = $this->get_settings('after_text');

            // Fetch values for dynamic tags
            $value_one = ($dynamic_tag_one === 'years-of-experience')
                ? $this->calculate_years_of_experience()
                : get_option('hozio_' . $dynamic_tag_one, '');
            $value_two = ($dynamic_tag_two === 'years-of-experience')
                ? $this->calculate_years_of_experience()
                : get_option('hozio_' . $dynamic_tag_two, '');

            echo wp_kses_post($before_text . '' . $value_one . '' . $between_text . '' . $value_two . '' . $after_text);
        }

        private function get_dynamic_tag_options() {
            $options = [];

            // Add built-in dynamic tags
            $options['years-of-experience'] = __('Years of Experience', 'hozio');
            $options['page-title'] = __('Page Title', 'hozio'); // Add Page Title option

            // Fetch custom tags from the options table
            $custom_tags = get_option('hozio_custom_tags', []);
            foreach ($custom_tags as $tag) {
                $options[$tag['value']] = $tag['title'];
            }

            return $options;
        }

        private function calculate_years_of_experience() {
            $start_year = get_option('hozio_start_year', 0);
            $current_year = (int) date('Y');
            return ($start_year > 0) ? $current_year - (int) $start_year : 0;
        }
    }

    $dynamic_tags->register(new Hozio_Composite_Tag());
}, 50);

function register_services_children_dynamic_tag($tags) {
    if (class_exists('Elementor\DynamicTags\Tag')) {
        class Services_Children_Dynamic_Tag extends \Elementor\DynamicTags\Tag {
            public function get_name() {
                return 'services_children';
            }
            public function get_title() {
                return 'Query ID Service Child Pages';
            }
            public function render() {
                // Replace the below logic with the actual code to fetch and display the services children dynamically.
                $post_id = get_the_ID(); // Get the current post ID
                $children = get_post_meta($post_id, '_services_children', true); // Assume services_children is stored as post meta
                
                if ($children) {
                    echo esc_html($children); // Output the value
                } else {
                    echo 'No services available';
                }
            }
        }
        // Register the custom dynamic tag
        $tags->register(new Services_Children_Dynamic_Tag());
    }
}
add_action('elementor/dynamic_tags/register', 'register_services_children_dynamic_tag');
