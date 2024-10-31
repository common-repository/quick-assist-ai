<?php

class WPCO_Init {

    public static function init() {
        self::load_textdomain();
        self::register_hooks();
    }

    private static function load_textdomain() {
        load_plugin_textdomain('quick-assist-ai', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    private static function register_hooks() {
    //    add_action('admin_menu', ['WPCO_Init', 'add_admin_menu']);
        add_action('admin_enqueue_scripts', ['WPCO_Init', 'enqueue_scripts']);
        add_action('admin_bar_menu', ['WPCO_Init', 'add_admin_bar_menu'], 100);
        add_action('admin_footer', ['WPCO_Init', 'add_admin_footer']);
        add_action('wp_enqueue_scripts', ['WPCO_Init', 'enqueue_frontend_scripts']);
        add_action('wp_footer', ['WPCO_Init', 'add_frontend_footer']);
        add_action('init', ['WPCO_Init', 'register_shortcodes']);

    }
        
    
    /* public static function add_admin_menu() {
        add_menu_page(
            __('Quick Assist AI', 'quick-assist-ai'),
            __('Quick Assist AI', 'quick-assist-ai'),
            'manage_options',
            'quick-assist-ai',
            6
        );
    } */

    public static function enqueue_scripts($hook) {
        wp_enqueue_script('wpco-popup', WPCO_PLUGIN_URL . 'assets/js/popup.js', ['jquery'], WPCO_VERSION, true);

        $post_id = self::get_current_page_id();
        
        wp_localize_script('wpco-popup', 'wpcoChat', [
            'nonce' => wp_create_nonce('wpco_chat_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'post_id' => $post_id,
            'plugin_url' => WPCO_PLUGIN_URL
        ]);

        // Enqueue popup styles and scripts
        wp_enqueue_style('wpco-popup-style', WPCO_PLUGIN_URL . 'assets/css/popup.css', [], WPCO_VERSION);

    }

    public static function enqueue_frontend_scripts() {
        wp_enqueue_script('wpco-frontend-popup', WPCO_PLUGIN_URL . 'assets/js/popup.js', ['jquery'], WPCO_VERSION, true);
        
        // Initialize post ID variable
        $post_id = self::get_current_page_id();    

        wp_localize_script('wpco-frontend-popup', 'wpcoChat', [
            'nonce' => wp_create_nonce('wpco_chat_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'post_id' => $post_id,
            'plugin_url' => WPCO_PLUGIN_URL
        ]);
    
        wp_enqueue_style('wpco-popup-style', WPCO_PLUGIN_URL . 'assets/css/popup.css', [], WPCO_VERSION);
    }
    
    
    public static function add_admin_bar_menu($wp_admin_bar) {
        $args = array(
            'id'    => 'wpco_open_popup',
            'title' => 'Quick Assist AI',
            'href'  => '#',
            'meta'  => array(
                'class' => 'wpco-open-popup',
                'title' => 'Quick Assist AI'
            )
        );
        $wp_admin_bar->add_node($args);
    }

    public static function add_admin_footer() {
        include WPCO_PLUGIN_DIR . 'includes/popup.php';
    }

    public static function add_frontend_footer() {
        include WPCO_PLUGIN_DIR . 'includes/popup.php';
    }

    public static function display_admin_page() {
        // define admin page if required
    }

    public static function register_shortcodes() {
        add_shortcode('wpco_open_popup_button', ['WPCO_Init', 'render_open_popup_button']);
    }

    public static function render_open_popup_button() {
        return '<button class="button wpco-open-popup">' . __('Open WP Copilot Assistant', 'quick-assist-ai') . '</button>';
    }

    public static function get_current_page_id() {
        global $post;
        
    $post_id = null;

    
    if (is_front_page() || is_home()) {
        if (current_theme_supports('block-templates')) {
            $theme = wp_get_theme();
            $theme_slug = $theme->get_template();
            
            if (is_home()) $template_id = $theme_slug . '//home';
            else $template_id = $theme_slug . '//front-page';

            if (function_exists('get_block_template')) {
                $template = get_block_template($template_id, 'wp_template');

                if ($template) {
                    $post_id = $template->id;
                } else {
                    $template_id = $theme_slug . '//index';
                    $template = get_block_template($template_id, 'wp_template');
                    if ($template) $post_id = $template->id;
                    else $post_id = null; 
                }
            } else {
                $post_id = null;
            }
        } else {
            $front_page_id = get_option('page_on_front');
            if ($front_page_id == 0) {
                $post_id = 0; 
            } else {
                $post_id = $front_page_id;
            }
        }
    } elseif (is_home()) {
        $post_id = get_option('page_for_posts');
    } elseif (is_singular()) {
        global $post;
        $post_id = $post->ID;
    } elseif (is_archive()) {
        if (current_theme_supports('block-templates')) {
            $theme = wp_get_theme();
            $theme_slug = $theme->get_template();
            if (is_post_type_archive()) {
                $post_type = get_query_var('post_type');
                $template_id = $theme_slug . '//archive-' . $post_type;
            } elseif (is_category()) {
                $template_id = $theme_slug . '//archive-category';
            } elseif (is_tag()) {
                $template_id = $theme_slug . '//archive-tag';
            } elseif (is_author()) {
                $template_id = $theme_slug . '//archive-author';
            } elseif (is_date()) {
                $template_id = $theme_slug . '//archive-date';
            } elseif (is_tax()) {
                $taxonomy = get_query_var('taxonomy');
                $template_id = $theme_slug . '//archive-' . $taxonomy;
            } else {
                $template_id = $theme_slug . '//archive';
            }
            if (function_exists('get_block_template')) {
                $template = get_block_template($template_id, 'wp_template');
                
                if ($template) {
                    $post_id = $template->id;
                } else {
                    $all_templates = self::execute_rest_command1();
                    $search_id = str_replace($theme_slug.'//','',$template_id);
                    $found_template = self::search_template_by_id($all_templates, $search_id);
                    if ($found_template) {$template = get_block_template($found_template, 'wp_template');
                    if ($template) $post_id = $template->id;
                    }
                    else if ($post_id === null) {
                    $template_id = $theme_slug . '//index';
                    $template = get_block_template($template_id, 'wp_template');
                    if ($template) $post_id = $template->id;
                    else $post_id = null;
                }
            }
            } else {
                $post_id = null;
            }
        }
    }
   
    return $post_id;
        }

        private static function execute_rest_command1() {  
            $url = get_site_url() . '/wp-json/wp/v2/templates';    

            if (strpos($url, home_url()) === 0) {
                    $url = str_replace(home_url(), '', $url);
                }
                if (strpos($url,'/wp-json') === 0){
                    $url = str_replace('/wp-json', '', $url);
                }

                $parsed_url = wp_parse_url($url);
            $route = $parsed_url['path'];
            $query_params = [];
            if (isset($parsed_url['query'])) {
                parse_str($parsed_url['query'], $query_params);
            }
        

        try{
                $nonce = wp_create_nonce('wp_rest');
                $http_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
                $request = new WP_REST_Request('GET', $route);
                $request->set_header('X-WP-Nonce', $nonce);
                $request->set_header('User-Agent', $http_user_agent);
                $request->set_query_params($query_params);

                /*$cookies = [];
                foreach ($_COOKIE as $name => $value) {
                    $cookies[] = new WP_Http_Cookie(array('name' => $name, 'value' => $value));
                }
                
                $request->set_header('cookies', $cookies);*/
            
                $response = rest_do_request($request);
                if (strpos($url,'/wp') === 0) {
                $attributes = $request->get_attributes();
                if (!empty($attributes)) {
                $args_keys = array_keys($attributes['args']);
                $reflection = new ReflectionClass($attributes['callback'][0]);
                $prop = $reflection->getProperty('schema');
                $prop->setAccessible(true);
                $schema = $prop->getValue($attributes['callback'][0]);
                $properties_keys = array_keys($schema['properties']);
                $help_string = 'Args - '.wp_json_encode($args_keys).","."Fields -".wp_json_encode($properties_keys);
                }
            } else $help_string = '';
        
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                    return 'Error processing: ' . esc_html($error_message). ". Rest Help -".esc_html($help_string);
                }
            
                $response_data = $response->get_data();
        
                if ($response_data instanceof stdClass) {
                    $json = wp_json_encode($response);                    
                    $response_data = json_decode($json, true);
                }
        
                if ($response->is_error()) {
                $error_message = isset($response_data['message']) ? $response_data['message'] : $response_data;
                return 'Error processing: ' . esc_html($error_message). ". Rest Help -".esc_html($help_string);
                }
        
            if (empty($response_data)) {
                return 'Error processing: No data found in response'. ". Rest Help -".esc_html($help_string);
            }
                
                return $response_data;
            } catch (Exception $e) {
                error_log('Error Processing: ' . $e->getMessage());
                return 'Error Processing: ' . esc_html($e->getMessage());
            }
        
        }
                
        private static function search_template_by_id($templates, $search_id) {
            if ($templates==='' || $templates === null) return '';
            if (isset($templates) && isset($search_id)) {            
                foreach ($templates as $template) {
                if (isset($template) && isset($search_id)) {
                if (is_array($template) && (isset($template['id']) && $template['id'] === $search_id || isset($template['slug']) && $template['slug'] === $search_id)) {                    
                    return $template['id'];
                }
            }
            }
        }
            return null;
        }
        
    }

    add_action('plugins_loaded', ['WPCO_Init', 'init']);


?>
