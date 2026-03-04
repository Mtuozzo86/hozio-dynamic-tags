<?php
/**
 * Hozio Dynamic Tags — Elementor tag registration.
 *
 * All eval() blocks replaced with explicit class definitions.
 * Option keys match those saved by includes/admin-settings.php.
 */

add_action('elementor/dynamic_tags/register', function ($dynamic_tags) {

    // ================================================================
    // BASE CLASSES — shared render logic, grouped by output type
    // ================================================================

    if (!class_exists('Hozio_URL_Base_Tag')) {
        class Hozio_URL_Base_Tag extends \Elementor\Core\DynamicTags\Tag {
            protected $tag_name   = '';
            protected $tag_title  = '';
            protected $option_key = '';
            protected $scheme     = 'url';

            public function get_name()       { return $this->tag_name; }
            public function get_title()      { return $this->tag_title; }
            public function get_group()      { return 'site'; }
            public function get_categories() { return [\Elementor\Modules\DynamicTags\Module::URL_CATEGORY]; }
            protected function register_controls() {}

            public function render() {
                $value = get_option($this->option_key, '');
                switch ($this->scheme) {
                    case 'tel':
                        echo esc_url('tel:' . esc_attr($value));
                        break;
                    case 'sms':
                        echo esc_url('sms:' . esc_attr($value));
                        break;
                    case 'mailto':
                        echo esc_url('mailto:' . esc_attr($value));
                        break;
                    default:
                        echo esc_url($value);
                        break;
                }
            }
        }
    }

    if (!class_exists('Hozio_Text_Base_Tag')) {
        class Hozio_Text_Base_Tag extends \Elementor\Core\DynamicTags\Tag {
            protected $tag_name   = '';
            protected $tag_title  = '';
            protected $option_key = '';
            protected $render_as  = 'text'; // 'text', 'html', 'years'

            public function get_name()       { return $this->tag_name; }
            public function get_title()      { return $this->tag_title; }
            public function get_group()      { return 'site'; }
            public function get_categories() { return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY]; }
            protected function register_controls() {}

            public function render() {
                if ($this->render_as === 'years') {
                    $start_year = get_option('hozio_start_year', 0);
                    $current_year = (int) date('Y');
                    $years = ($start_year > 0) ? $current_year - (int) $start_year : 0;
                    echo esc_html($years);
                    return;
                }

                $value = get_option($this->option_key, '');

                if ($this->render_as === 'html') {
                    $allowed = [
                        'br' => [], 'a' => ['href' => [], 'title' => []],
                        'b'  => [], 'i' => [], 'p' => [],
                        'ul' => [], 'ol' => [], 'li' => [],
                    ];
                    echo wp_kses($value, $allowed);
                } else {
                    // Allow admin-entered scripts for custom tracking tags
                    if (strpos($value, '<script') !== false) {
                        echo $value;
                    } else {
                        echo esc_html($value);
                    }
                }
            }
        }
    }

    if (!class_exists('Hozio_IconBox_Base_Tag')) {
        class Hozio_IconBox_Base_Tag extends \Elementor\Core\DynamicTags\Tag {
            protected $tag_name   = '';
            protected $tag_title  = '';
            protected $option_key = '';
            protected $scheme     = 'tel';

            public function get_name()       { return $this->tag_name; }
            public function get_title()      { return $this->tag_title; }
            public function get_group()      { return 'site'; }
            public function get_categories() { return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY]; }
            protected function register_controls() {}

            public function render() {
                $val = esc_attr(get_option($this->option_key, ''));
                echo '<a href="' . $this->scheme . ':' . $val . '">' . esc_html($val) . '</a>';
            }
        }
    }

    // ================================================================
    // URL TAG CLASSES
    // Option keys match admin-settings.php exactly
    // ================================================================

    if (!class_exists('My_Company_Phone_1_Tag')) {
        class My_Company_Phone_1_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'company-phone-1';
            protected $tag_title  = 'Company Phone Number 1';
            protected $option_key = 'hozio_company_phone_1';
            protected $scheme     = 'tel';
        }
    }

    if (!class_exists('My_Company_Phone_2_Tag')) {
        class My_Company_Phone_2_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'company-phone-2';
            protected $tag_title  = 'Company Phone Number 2';
            protected $option_key = 'hozio_company_phone_2';
            protected $scheme     = 'tel';
        }
    }

    if (!class_exists('My_Google_Ads_Phone_Tag')) {
        class My_Google_Ads_Phone_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'google-ads-phone';
            protected $tag_title  = 'Google Ads Phone Number';
            protected $option_key = 'hozio_google_ads_phone';
            protected $scheme     = 'tel';
        }
    }

    if (!class_exists('My_Sms_Phone_Tag')) {
        class My_Sms_Phone_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'sms-phone';
            protected $tag_title  = 'SMS Phone Number';
            protected $option_key = 'hozio_sms_phone';
            protected $scheme     = 'sms';
        }
    }

    if (!class_exists('My_Company_Email_Tag')) {
        class My_Company_Email_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'company-email';
            protected $tag_title  = 'Company Email';
            protected $option_key = 'hozio_company_email';
            protected $scheme     = 'mailto';
        }
    }

    if (!class_exists('My_Gmb_Link_Tag')) {
        class My_Gmb_Link_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'gmb-link';
            protected $tag_title  = 'GMB Link';
            protected $option_key = 'hozio_gmb_link';
        }
    }

    if (!class_exists('My_Facebook_Tag')) {
        class My_Facebook_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'facebook';
            protected $tag_title  = 'Facebook';
            protected $option_key = 'hozio_facebook_url';
        }
    }

    if (!class_exists('My_Instagram_Tag')) {
        class My_Instagram_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'instagram';
            protected $tag_title  = 'Instagram';
            protected $option_key = 'hozio_instagram_url';
        }
    }

    if (!class_exists('My_Twitter_Tag')) {
        class My_Twitter_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'twitter';
            protected $tag_title  = 'Twitter';
            protected $option_key = 'hozio_twitter_url';
        }
    }

    if (!class_exists('My_Tiktok_Tag')) {
        class My_Tiktok_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'tiktok';
            protected $tag_title  = 'TikTok';
            protected $option_key = 'hozio_tiktok_url';
        }
    }

    if (!class_exists('My_Linkedin_Tag')) {
        class My_Linkedin_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'linkedin';
            protected $tag_title  = 'LinkedIn';
            protected $option_key = 'hozio_linkedin_url';
        }
    }

    if (!class_exists('My_Bbb_Tag')) {
        class My_Bbb_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'bbb';
            protected $tag_title  = 'BBB';
            protected $option_key = 'hozio_bbb_url';
        }
    }

    if (!class_exists('My_Sitemap_Xml_Tag')) {
        class My_Sitemap_Xml_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'sitemap-xml';
            protected $tag_title  = 'Sitemap';
            protected $option_key = 'sitemap_url';

            public function render() {
                echo esc_url(get_option($this->option_key, '') ?: home_url('/sitemap.xml'));
            }
        }
    }

    if (!class_exists('My_Yelp_Tag')) {
        class My_Yelp_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'yelp';
            protected $tag_title  = 'Yelp';
            protected $option_key = 'hozio_yelp_url';
        }
    }

    if (!class_exists('My_Youtube_Tag')) {
        class My_Youtube_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'youtube';
            protected $tag_title  = 'YouTube';
            protected $option_key = 'hozio_youtube_url';
        }
    }

    if (!class_exists('My_Angies_List_Tag')) {
        class My_Angies_List_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'angies-list';
            protected $tag_title  = "Angi's List";
            protected $option_key = 'hozio_angies_list_url';
        }
    }

    if (!class_exists('My_Home_Advisor_Tag')) {
        class My_Home_Advisor_Tag extends Hozio_URL_Base_Tag {
            protected $tag_name   = 'home-advisor';
            protected $tag_title  = 'Home Advisor';
            protected $option_key = 'hozio_home_advisor_url';
        }
    }

    // ================================================================
    // TEXT TAG CLASSES
    // ================================================================

    if (!class_exists('My_Company_Phone_1_Name_Tag')) {
        class My_Company_Phone_1_Name_Tag extends Hozio_Text_Base_Tag {
            protected $tag_name   = 'company-phone-1-name';
            protected $tag_title  = 'Company Phone #1 Name';
            protected $option_key = 'hozio_company_phone_1';
        }
    }

    if (!class_exists('My_Company_Phone_2_Name_Tag')) {
        class My_Company_Phone_2_Name_Tag extends Hozio_Text_Base_Tag {
            protected $tag_name   = 'company-phone-2-name';
            protected $tag_title  = 'Company Phone #2 Name';
            protected $option_key = 'hozio_company_phone_2';
        }
    }

    if (!class_exists('My_Sms_Phone_Name_Tag')) {
        class My_Sms_Phone_Name_Tag extends Hozio_Text_Base_Tag {
            protected $tag_name   = 'sms-phone-name';
            protected $tag_title  = 'SMS Phone # Name';
            protected $option_key = 'hozio_sms_phone';
        }
    }

    if (!class_exists('My_Company_Address_Tag')) {
        class My_Company_Address_Tag extends Hozio_Text_Base_Tag {
            protected $tag_name   = 'company-address';
            protected $tag_title  = 'Company Address';
            protected $option_key = 'hozio_company_address';
            protected $render_as  = 'html';
        }
    }

    if (!class_exists('My_Business_Hours_Tag')) {
        class My_Business_Hours_Tag extends Hozio_Text_Base_Tag {
            protected $tag_name   = 'business-hours';
            protected $tag_title  = 'Business Hours';
            protected $option_key = 'hozio_business_hours';
            protected $render_as  = 'html';
        }
    }

    if (!class_exists('My_To_Email_Contact_Form_Tag')) {
        class My_To_Email_Contact_Form_Tag extends Hozio_Text_Base_Tag {
            protected $tag_name   = 'to-email-contact-form';
            protected $tag_title  = 'To Email(s) Contact Form';
            protected $option_key = 'hozio_to_email_contact_form';
        }
    }

    if (!class_exists('My_Years_Of_Experience_Tag')) {
        class My_Years_Of_Experience_Tag extends Hozio_Text_Base_Tag {
            protected $tag_name   = 'years-of-experience';
            protected $tag_title  = 'Years of Experience';
            protected $option_key = 'hozio_start_year';
            protected $render_as  = 'years';
        }
    }

    // ================================================================
    // ICON BOX TAG CLASSES — render as clickable <a> links
    // ================================================================

    if (!class_exists('My_Phone_Number_Icon_Box_Tag')) {
        class My_Phone_Number_Icon_Box_Tag extends Hozio_IconBox_Base_Tag {
            protected $tag_name   = 'phone-number-icon-box';
            protected $tag_title  = 'Phone Number (Icon Box desc.)';
            protected $option_key = 'hozio_company_phone_1';
            protected $scheme     = 'tel';
        }
    }

    if (!class_exists('My_Sms_Icon_Box_Tag')) {
        class My_Sms_Icon_Box_Tag extends Hozio_IconBox_Base_Tag {
            protected $tag_name   = 'sms-icon-box';
            protected $tag_title  = 'SMS (Icon Box desc.)';
            protected $option_key = 'hozio_sms_phone';
            protected $scheme     = 'sms';
        }
    }

    if (!class_exists('My_Email_Icon_Box_Tag')) {
        class My_Email_Icon_Box_Tag extends Hozio_IconBox_Base_Tag {
            protected $tag_name   = 'email-icon-box';
            protected $tag_title  = 'Email (Icon Box desc.)';
            protected $option_key = 'hozio_company_email';
            protected $scheme     = 'mailto';
        }
    }

    // ================================================================
    // REGISTER ALL BASE TAGS
    // ================================================================

    $base_classes = [
        'My_Company_Phone_1_Tag',
        'My_Company_Phone_2_Tag',
        'My_Google_Ads_Phone_Tag',
        'My_Sms_Phone_Tag',
        'My_Company_Email_Tag',
        'My_Gmb_Link_Tag',
        'My_Facebook_Tag',
        'My_Instagram_Tag',
        'My_Twitter_Tag',
        'My_Tiktok_Tag',
        'My_Linkedin_Tag',
        'My_Bbb_Tag',
        'My_Sitemap_Xml_Tag',
        'My_Yelp_Tag',
        'My_Youtube_Tag',
        'My_Angies_List_Tag',
        'My_Home_Advisor_Tag',
        'My_Company_Phone_1_Name_Tag',
        'My_Company_Phone_2_Name_Tag',
        'My_Sms_Phone_Name_Tag',
        'My_Company_Address_Tag',
        'My_Business_Hours_Tag',
        'My_To_Email_Contact_Form_Tag',
        'My_Years_Of_Experience_Tag',
        'My_Phone_Number_Icon_Box_Tag',
        'My_Sms_Icon_Box_Tag',
        'My_Email_Icon_Box_Tag',
    ];

    foreach ($base_classes as $cls) {
        if (class_exists($cls)) {
            $dynamic_tags->register(new $cls());
        }
    }

    // ================================================================
    // CUSTOM TAGS — user-created via Add/Remove page
    // Generated as cached PHP file to create unique classes without eval()
    // ================================================================

    $custom_tags = get_option('hozio_custom_tags', []);
    if (!empty($custom_tags) && is_array($custom_tags)) {
        $upload_dir = wp_upload_dir();
        $cache_dir  = $upload_dir['basedir'] . '/hozio-cache';
        $cache_file = $cache_dir . '/custom-tag-classes.php';
        // Include a version key so cache is regenerated when plugin code changes
        $cache_version = '3';
        $cache_hash = md5($cache_version . serialize($custom_tags));
        $stored_hash = get_transient('hozio_custom_tags_hash');

        // Regenerate cached file when custom tags or plugin version changes
        if (!file_exists($cache_file) || $cache_hash !== $stored_hash) {
            wp_mkdir_p($cache_dir);

            // Protect cache directory from direct web access
            $index_file = $cache_dir . '/index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, "<?php\n// Silence is golden.\n");
            }

            $code = "<?php\n// Auto-generated by Hozio Pro — do not edit manually\n\n";

            foreach ($custom_tags as $ct) {
                $slug = preg_replace('/[^a-z0-9_]/i', '', str_replace('-', '_', $ct['value']));
                // _Custom_Tag suffix prevents collision with built-in tag class names
                $safe_class = 'My_' . ucwords($slug, '_') . '_Custom_Tag';
                $val   = addslashes($ct['value']);
                $ttl   = addslashes($ct['title']);
                $is_url = strtoupper($ct['type']) === 'URL';

                $base  = $is_url ? 'Hozio_URL_Base_Tag' : 'Hozio_Text_Base_Tag';
                $okey  = addslashes('hozio_' . $ct['value']);

                $code .= "if (!class_exists('{$safe_class}')) {\n";
                $code .= "    class {$safe_class} extends {$base} {\n";
                $code .= "        protected \$tag_name   = '{$val}';\n";
                $code .= "        protected \$tag_title  = '{$ttl}';\n";
                $code .= "        protected \$option_key = '{$okey}';\n";
                $code .= "    }\n}\n\n";
            }

            // Atomic write: write to temp file then rename to avoid partial reads
            $tmp_file = $cache_file . '.' . uniqid('', true) . '.tmp';
            file_put_contents($tmp_file, $code, LOCK_EX);
            rename($tmp_file, $cache_file);
            set_transient('hozio_custom_tags_hash', $cache_hash);

            // Clean up any orphaned .tmp files from failed writes
            foreach (glob($cache_dir . '/*.tmp') as $stale) {
                @unlink($stale);
            }
        }

        if (file_exists($cache_file)) {
            require_once $cache_file;
        }

        // Register each custom tag
        foreach ($custom_tags as $ct) {
            $slug = preg_replace('/[^a-z0-9_]/i', '', str_replace('-', '_', $ct['value']));
            $safe_class = 'My_' . ucwords($slug, '_') . '_Custom_Tag';
            if (class_exists($safe_class)) {
                $dynamic_tags->register(new $safe_class());
            }
        }
    }

    // ================================================================
    // SERVICES CHILDREN — fixed-value tag for query ID
    // ================================================================

    if (!class_exists('My_Services_Children_Tag')) {
        class My_Services_Children_Tag extends \Elementor\Core\DynamicTags\Tag {
            public function get_name()       { return 'services_children'; }
            public function get_title()      { return __('Query ID Service Child Pages', 'plugin-name'); }
            public function get_group()      { return 'site'; }
            public function get_categories() { return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY]; }
            protected function register_controls() {}
            public function render()         { echo 'services_children'; }
        }
    }
    $dynamic_tags->register(new My_Services_Children_Tag());

    // ================================================================
    // COMPOSITE TAG — combines two dynamic tags with text
    // ================================================================

    if (!class_exists('Hozio_Composite_Tag')) {
        class Hozio_Composite_Tag extends \Elementor\Core\DynamicTags\Tag {
            public function get_name()       { return 'hozio_composite_tag'; }
            public function get_title()      { return __('Composite Dynamic Tag', 'hozio'); }
            public function get_group()      { return 'site'; }
            public function get_categories() { return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY]; }

            protected function register_controls() {
                $this->add_control('before_text', [
                    'label'   => __('Before Text', 'hozio'),
                    'type'    => \Elementor\Controls_Manager::TEXT,
                    'default' => '',
                ]);
                $this->add_control('dynamic_tag_one', [
                    'label'   => __('Dynamic Tag 1', 'hozio'),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'options' => $this->get_dynamic_tag_options(),
                    'default' => '',
                ]);
                $this->add_control('between_text', [
                    'label'   => __('Between Text', 'hozio'),
                    'type'    => \Elementor\Controls_Manager::TEXT,
                    'default' => '',
                ]);
                $this->add_control('dynamic_tag_two', [
                    'label'   => __('Dynamic Tag 2', 'hozio'),
                    'type'    => \Elementor\Controls_Manager::SELECT,
                    'options' => $this->get_dynamic_tag_options(),
                    'default' => '',
                ]);
                $this->add_control('after_text', [
                    'label'   => __('After Text', 'hozio'),
                    'type'    => \Elementor\Controls_Manager::TEXT,
                    'default' => '',
                ]);
            }

            public function render() {
                $before  = $this->get_settings('before_text');
                $tag_one = $this->get_settings('dynamic_tag_one');
                $between = $this->get_settings('between_text');
                $tag_two = $this->get_settings('dynamic_tag_two');
                $after   = $this->get_settings('after_text');

                $val_one = ($tag_one === 'years-of-experience')
                    ? $this->calculate_years_of_experience()
                    : get_option('hozio_' . $tag_one, '');
                $val_two = ($tag_two === 'years-of-experience')
                    ? $this->calculate_years_of_experience()
                    : get_option('hozio_' . $tag_two, '');

                echo wp_kses_post($before . $val_one . $between . $val_two . $after);
            }

            private function get_dynamic_tag_options() {
                $options = [];
                $options['years-of-experience'] = __('Years of Experience', 'hozio');
                $options['page-title'] = __('Page Title', 'hozio');

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
