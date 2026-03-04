<?php
// Register custom dynamic tags (base set + user-created custom tags)
add_action('elementor/dynamic_tags/register', function ($dynamic_tags) {

    // ========================================
    // GENERIC DYNAMIC TAG CLASS (no eval needed)
    // Handles all tag types: URL, TEXT, icon-box, HTML, calculated
    // ========================================
    if (!class_exists('Hozio_Generic_Dynamic_Tag')) {
        class Hozio_Generic_Dynamic_Tag extends \Elementor\Core\DynamicTags\Tag {
            protected $_tag_name  = '';
            protected $_tag_title = '';
            protected $_tag_type  = 'TEXT';

            public function __construct( array $data = [], $args = null, $config = [] ) {
                if ( ! empty( $config['tag_name'] ) )  $this->_tag_name  = $config['tag_name'];
                if ( ! empty( $config['tag_title'] ) ) $this->_tag_title = $config['tag_title'];
                if ( ! empty( $config['tag_type'] ) )  $this->_tag_type  = $config['tag_type'];
                parent::__construct( $data, $args );
            }

            public function get_name()  { return $this->_tag_name; }
            public function get_title() { return $this->_tag_title; }
            public function get_group() { return 'site'; }

            public function get_categories() {
                return [ $this->_tag_type === 'URL'
                    ? \Elementor\Modules\DynamicTags\Module::URL_CATEGORY
                    : \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY
                ];
            }

            protected function register_controls() {}

            public function render() {
                $name = $this->_tag_name;

                // Calculated: years-of-experience
                if ($name === 'years-of-experience') {
                    $start_year = (int) get_option('hozio_start_year', 0);
                    $current_year = (int) date('Y');
                    echo esc_html($start_year > 0 ? $current_year - $start_year : 0);
                    return;
                }

                // Icon-box tags: phone, SMS, email with clickable link
                if ($name === 'phone-number-icon-box') {
                    $phone = esc_attr(get_option('hozio_company_phone_1'));
                    echo '<a href="tel:' . $phone . '">' . esc_html($phone) . '</a>';
                    return;
                }
                if ($name === 'sms-icon-box') {
                    $sms = esc_attr(get_option('hozio_sms_phone'));
                    echo '<a href="sms:' . $sms . '">' . esc_html($sms) . '</a>';
                    return;
                }
                if ($name === 'email-icon-box') {
                    $email = esc_attr(get_option('hozio_company_email'));
                    echo '<a href="mailto:' . $email . '">' . esc_html($email) . '</a>';
                    return;
                }

                // Fetch option value
                $option_value = get_option('hozio_' . $name, '');

                // HTML-allowed tags: address, business hours
                $html_tags = ['company-address', 'business-hours'];
                if (in_array($name, $html_tags, true)) {
                    $allowed = [
                        'br' => [], 'a' => ['href' => [], 'title' => []],
                        'b' => [], 'i' => [], 'p' => [],
                        'ul' => [], 'ol' => [], 'li' => [],
                    ];
                    echo wp_kses($option_value, $allowed);
                    return;
                }

                // URL tags: phone, SMS, email, plain URL
                switch ($name) {
                    case 'company-phone-1':
                    case 'company-phone-2':
                    case 'google-ads-phone':
                        echo esc_url('tel:' . esc_attr($option_value));
                        return;
                    case 'sms-phone':
                        echo esc_url('sms:' . esc_attr($option_value));
                        return;
                    case 'company-email':
                        echo esc_url('mailto:' . esc_attr($option_value));
                        return;
                    case 'gmb-link':
                        echo esc_url($option_value);
                        return;
                }

                // Default: escape output (never allow script tags)
                if (strpos($option_value, '<script') !== false) {
                    echo ''; // Block script injection
                } else {
                    echo esc_html($option_value);
                }
            }

            public static function create($tag_name, $tag_title, $tag_type) {
                return new self([], null, [
                    'tag_name'  => $tag_name,
                    'tag_title' => $tag_title,
                    'tag_type'  => $tag_type,
                ]);
            }
        }
    }

    // Base tags (default set)
    $tags = [
        ['company-phone-1', 'Company Phone Number 1', 'URL'],
        ['company-phone-1-name', 'Company Phone #1 Name', 'TEXT'],
        ['phone-number-icon-box', 'Phone Number (Icon Box desc.)', 'TEXT'],
        ['company-phone-2', 'Company Phone Number 2', 'URL'],
        ['company-phone-2-name', 'Company Phone #2 Name', 'TEXT'],
        ['google-ads-phone', 'Google Ads Phone Number', 'URL'],
        ['google-ads-phone', 'Google Ads Phone Number', 'TEXT'],
        ['sms-phone', 'SMS Phone Number', 'URL'],
        ['sms-phone-name', 'SMS Phone # Name', 'TEXT'],
        ['sms-icon-box', 'SMS (Icon Box desc.)', 'TEXT'],
        ['company-email', 'Company Email', 'URL'],
        ['email-icon-box', 'Email (Icon Box desc.)', 'TEXT'],
        ['to-email-contact-form', 'To Email(s) Contact Form', 'TEXT'],
        ['sitemap-xml', 'Sitemap', 'URL'],
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
        ['years-of-experience', 'Years of Experience', 'TEXT'],
    ];

    // Fetch custom tags from the options table
    $custom_tags = get_option('hozio_custom_tags', []);
    foreach ($custom_tags as $tag) {
        $tags[] = [$tag['value'], $tag['title'], strtoupper($tag['type'])];
    }

    // Register all tags using the factory (no eval)
    foreach ($tags as $tag) {
        $dynamic_tags->register(
            Hozio_Generic_Dynamic_Tag::create($tag[0], $tag[1], $tag[2])
        );
    }

    // Services Children (fixed value) — reuse class from main plugin if available
    if (!class_exists('Hozio_Services_Children_Tag')) {
        class Hozio_Services_Children_Tag extends \Elementor\Core\DynamicTags\Tag {
            public function get_name()       { return 'services_children'; }
            public function get_title()      { return __('Query ID Service Child Pages', 'plugin-name'); }
            public function get_group()      { return 'site'; }
            public function get_categories() { return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY]; }
            protected function register_controls() {}
            public function render()         { echo 'services_children'; }
        }
    }
    $dynamic_tags->register(new Hozio_Services_Children_Tag());

    // Register composite dynamic tag
    if (!class_exists('Hozio_Composite_Tag')) {
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
                $options['page-title'] = __('Page Title', 'hozio');

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
                $post_id = get_the_ID();
                $children = get_post_meta($post_id, '_services_children', true);

                if ($children) {
                    echo esc_html($children);
                } else {
                    echo 'No services available';
                }
            }
        }
        $tags->register(new Services_Children_Dynamic_Tag());
    }
}
add_action('elementor/dynamic_tags/register', 'register_services_children_dynamic_tag');
