<?php
class WPCO_Chat {

    public static function init() {        
        add_action('wp_ajax_wpco_chat', ['WPCO_Chat', 'handle_chat']);
        add_action('wp_ajax_wpco_check_admin_status', ['WPCO_Chat', 'wpco_check_admin_status']);
        add_action('wp_ajax_nopriv_wpco_check_admin_status', ['WPCO_Chat', 'wpco_check_admin_status']);
        add_action('wp_ajax_wpco_sse', ['WPCO_Chat', 'wpco_sse_handler']);
        add_action('wp_ajax_nopriv_wpco_sse', ['WPCO_Chat', 'wpco_sse_handler']);        
        add_action('wp_ajax_wpco_long_polling', ['WPCO_Chat', 'wpco_long_polling']);
        add_action('wp_ajax_nopriv_wpco_long_polling', ['WPCO_Chat', 'wpco_long_polling']);
    }

    public static function handle_chat() {
        check_ajax_referer('wpco_chat_nonce', '_wpnonce');
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'wpco_chat_nonce')) {
            
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        try {
        
        $history = array();

        $query = sanitize_text_field($_POST['query']);
        $post_id = sanitize_text_field($_POST['post_id']);
        $history = isset($_POST['history']) ? json_decode(sanitize_text_field($_POST['history']), true) : array();
        $query_id = uniqid('query_', true); 
        $http_referer = sanitize_url($_SERVER['HTTP_REFERER']);
        
        if (strpos($post_id, '//') !== false){
            $post_id = self::get_post_id_by_path($post_id,'wp_template');
        }

        if ($post_id === '' || $post_id === null)
            {
            $parsed_url = wp_parse_url($http_referer);
            if (isset($parsed_url['query'])){
            $query_string = $parsed_url['query'];
            parse_str($query_string, $query_vars);

            if (isset($query_vars['postId'])) $post_id = $query_vars['postId'];
            }
            else $post_id = '';
            }

        if (json_last_error() !== JSON_ERROR_NONE) {
            $history = array();
        }
        
        if (empty($query)) {
            wp_send_json_error(array('message' => 'Empty query'));
            return;
        }
        
        set_transient('wpco_process_state_' . $query_id, array(
            'query' => $query,
            'post_id' => $post_id,
            'history' => $history,
            'query_id' => $query_id,
            'http_referer' => $http_referer
        ), 600);
        
        ob_clean();

wp_send_json_success(array('message' => 'Process started', 'query_id' => $query_id));

        wp_die();

    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'An error occurred. Please try again later.'));
    }

            if (ErrorHandler::$exceptionMessage !== null) {
                wp_send_json_error(array('message' => ErrorHandler::$exceptionMessage));
                wp_die();
            }
    
                
    }

    private static function process_query($query,$post_id, $history,$query_id,$http_referer) {
        
        
        if ($query === 'What is my wordpress health?'){
              return self::site_health();
           }

        if ($post_id !== '') {
        $page_details = self::get_page_details($post_id,'Yes',[],'No','Yes','No','No');        
        $page_id = $post_id;
        } else 
        {
            $page_details = [];
            $page_id = '';
        }
    
        $site_info = self::get_wordpress_site_info('No');
        
	    $response = '';
        $testing = false;
        $wp_info = [];

        if (!isset($wp_info['post_id'])&& isset($page_id)) 
        { $wp_info['post_id'] = $page_id;
          $wp_info['Post Meta Fields and Taxonomy Data'] = self::get_post_fields($page_id);
        }
        if (isset($page_details['Admin Edit URL'])) $wp_info['Admin Edit URL'] = $page_details['Admin Edit URL'];
        if (isset($page_details['Title']) && $page_details['Title'] !== null && $page_details['Title']!== '') $wp_info['Title'] = $page_details['Title'];
        if (isset($page_details['Post Content']) && $page_details['Post Content'] !== null && $page_details['Post Content']!== '') $wp_info['Post Content'] = "Page content stripped of unnecessary html tags - ".self::restrictToApprox1000Tokens(self::clean_content($page_details['Post Content']));
        if (isset($page_details['Template Parts (Output of /wp/v2/template-parts)']) && $page_details['Template Parts (Output of /wp/v2/template-parts)'] !== null && $page_details['Template Parts (Output of /wp/v2/template-parts)']!== '') $wp_info['Content of Template Parts used in current page'] = $page_details['Template Parts (Output of /wp/v2/template-parts)']; 
        if (isset($page_details['Widgets in Post']) && $page_details['Widgets in Post'] !== null && $page_details['Widgets in Post'] !== '') {
            $wp_info['Widgets in Post'] = $page_details['Widgets in Post']; 
            //$wp_info['All available Widgets'] = $page_details['All available Widgets']; 
        }
    
        if (isset($page_details['Status'])) $wp_info['Post Status'] = $page_details['Status'];
        if (isset($page_details['Type'])) $wp_info['Post Type'] = $page_details['Type'];
        if (isset($page_details['Template'])) $wp_info['Post Template'] = $page_details['Template'];
        if (isset($page_details['Elementor Edit'])) $wp_info['Theme'] = $page_details['Elementor Edit'];
        if (isset($page_details['Theme'])) $wp_info['Post Theme'] = $page_details['Theme'];
        if (isset($page_details['Does current theme support block templates'])) $wp_info['Does current theme support block templates'] = $page_details['Does current theme support block templates'];
        if (isset($page_details['Has Menu Items'])) $wp_info['Post has Menu Items'] = $page_details['Has Menu Items'];
        if (function_exists( 'WC' ) ) $wp_info['Woocommerce enabled'] = "WooCommerce is active";
        $wp_info['Site Info'] = $site_info;
        $wp_info['Referer URL'] = $http_referer;
        
        $availablerestroutes = self::available_routes();
        
        
        self::wpco_sendMessage('step', 1);
        ob_flush();
        flush();

        $refinedquery = self::call_plan_module($query_id,$query, $page_id, $history,$wp_info,$http_referer,[],'','',1,'',$availablerestroutes);
        
        if (isset($refinedquery['error']))
        {
            return "Sorry, we are experiencing a lot of traffic right now. Please try again later.";
        }

        $refinedquery = json_decode($refinedquery, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return "Sorry, we are experiencing a lot of traffic right now. Please try again later.";
        }

        if (!isset($refinedquery['query']))
        {
            return "Sorry, we are experiencing a lot of traffic right now. Please try again later.";
        }
        
        self::wpco_sendMessage('step', 2);
    ob_flush();
    flush();

        $plan = [
            'steps' => []
        ];

        $response = self::execute_plan($refinedquery['query'], $page_id, $history,$wp_info,$query_id,$plan,$page_details,$site_info,$availablerestroutes,$http_referer);
        
        $patterns = [
            '/<h1>(.*?)<\/h1>/is',
            '/<h2>(.*?)<\/h2>/is',
            '/<h3>(.*?)<\/h3>/is'
        ];
        $replacements = [
            '<h4>$1</h4>',
            '<h4>$1</h4>',
            '<h4>$1</h4>'
        ];


        if ($response === null || $response === '') $response = 'Sorry, we are experiencing a lot of traffic right now. Please try again later.';
        
        $response1 = preg_replace($patterns, $replacements, $response);
    
        self::wpco_sendMessage('step', 5);
        ob_flush();
        flush();
    
        $response1 = nl2br($response1);

        $response_data = array(
            'message' => $response1,
        );

        return $response_data;
    }

    public static function wpco_long_polling() {
        
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_key($_POST['_wpnonce']), 'wpco_chat_nonce')) {            
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
    
        $query_id = sanitize_text_field($_POST['query_id']);
        
        // Check if the result is available (using transients or other caching mechanisms)
        $result = get_transient('wpco_response_' . $query_id);
        
        if ($result) {
            echo wp_json_encode($result); // Return the result to the client
        } else {            
            echo wp_json_encode(array('status' => 'not ready')); // Indicate that the result is not ready
        }
    
        wp_die(); // Ensure no extra output is sent
    }
        
private static function get_wordpress_site_info($q_widgetopts = '') {
    // Get WordPress version
    $wp_version = get_bloginfo('version');

    // Get site language
    $wp_language = get_bloginfo('language');

    // Get site name
    $site_name = get_bloginfo('name');

    // Get site description
    $site_description = get_bloginfo('description');

    // Get site URL
    $site_url = get_bloginfo('url');

    // Get admin email
    $admin_email = get_option('admin_email');

    // Get theme info
    $current_theme = wp_get_theme();
    $theme_name = $current_theme->get('Name');
    $theme_version = $current_theme->get('Version');
    $theme_supports_block_editor = current_theme_supports('editor-styles');

    // Get child theme info
    $child_theme = wp_get_theme(get_template());
    if ($child_theme->parent()) {
        $child_theme_name = $child_theme->get('Name');
        $child_theme_version = $child_theme->get('Version');
    } else {
        $child_theme_name = null;
        $child_theme_version = null;
    }

    // Get theme directory
    $theme_directory = get_template_directory();

    // Get plugin info
    $active_plugins = get_option('active_plugins');

    // Get all plugins status
    $all_plugins = get_plugins();
    $all_plugins_info = [];
    foreach ($all_plugins as $plugin_path => $plugin_data) {
        $all_plugins_info[] = [
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
            'active' => in_array($plugin_path, $active_plugins)
        ];
    }
    $all_plugins_info_json = wp_json_encode($all_plugins_info);

    // Get WordPress settings
    $wp_debug = defined('WP_DEBUG') ? WP_DEBUG : false;
    $wp_memory_limit = WP_MEMORY_LIMIT;

    // Get permalink structure
    $permalink_structure = get_option('permalink_structure');

    // Get installed WordPress features and capabilities
    $features = [
        ['features' =>'Posts', 'capabilities' => post_type_exists('post') ? 'Exists':'Does not Exists'],
        ['features' =>'Pages','capabilities' => post_type_exists('page')? 'Exists':'Does not Exists'],
        ['features' =>'Featured Images','capabilities' => current_theme_supports('post-thumbnails') ? 'Supported':'Not Supported'],
        ['features' =>'Custom Headers','capabilities' => current_theme_supports('custom-header')? 'Supported':'Not Supported'],
        ['features' =>'Custom Backgrounds','capabilities' => current_theme_supports('custom-background')? 'Supported':'Not Supported'],
        ['features' =>'Navigation menus','capabilities' => current_theme_supports('menus')? 'Supported':'Not Supported'],
        ['features' =>'Widgets','capabilities' => current_theme_supports('widgets')? 'Supported':'Not Supported']
    ];

    $features_json = wp_json_encode($features);
    if (post_type_exists('post')) $features[] = 'Posts';
    if (post_type_exists('page')) $features[] = 'Pages';
    if (current_theme_supports('post-thumbnails')) $features[] = 'Featured Images';
    if (current_theme_supports('custom-header')) $features[] = 'Custom Headers';
    if (current_theme_supports('custom-background')) $features[] = 'Custom Backgrounds';
    if (current_theme_supports('menus')) $features[] = 'Navigation menus';
    if (current_theme_supports('widgets')) $features[] = 'Widgets';

    // Get custom post types
    $post_types = get_post_types(['_builtin' => false], 'names');

    // Get custom taxonomies
    $taxonomies = get_taxonomies(['_builtin' => false], 'names');

    // Get site health info
    //$site_health = wp_remote_get(site_url('/wp-site-health/v1/tests'));

    // Get multisite information
    $is_multisite = is_multisite();
    $site_count = $is_multisite ? get_blog_count() : 1;
    $network_name = $is_multisite ? get_network()->site_name : null;

    // Get database table prefix
    global $wpdb;
    $table_prefix = $wpdb->prefix;

    // Get PHP version
    $php_version = phpversion();

    // Get MySQL version
    $mysql_version = $wpdb->db_version();

    // Get server software
    $server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';

    // Get upload directory info
    $upload_dir = wp_get_upload_dir();

    // Get active widgets
    $sidebars_widgets = wp_get_sidebars_widgets();

    // Get cron schedules
    //$cron_schedules = wp_get_schedules();

    // Get WordPress constants
    $constants = [
        'WP_DEBUG' => defined('WP_DEBUG') ? WP_DEBUG : null,
        'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : null,
        'WP_DEBUG_DISPLAY' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : null,
        'SCRIPT_DEBUG' => defined('SCRIPT_DEBUG') ? SCRIPT_DEBUG : null,
        'DISALLOW_FILE_EDIT' => defined('DISALLOW_FILE_EDIT') ? DISALLOW_FILE_EDIT : null,
        'DISALLOW_FILE_MODS' => defined('DISALLOW_FILE_MODS') ? DISALLOW_FILE_MODS : null,
        'AUTOMATIC_UPDATER_DISABLED' => defined('AUTOMATIC_UPDATER_DISABLED') ? AUTOMATIC_UPDATER_DISABLED : null
    ];
    $constants_json = wp_json_encode($constants);

    // Check for specific theme supports
    $theme_supports = [];
    $supports_list = [
        'custom-logo',
        'custom-header',
        'custom-background',
        'post-thumbnails',
        'automatic-feed-links',
        'title-tag',
        'html5',
        'responsive-embeds',
        'wp-block-styles',
        'align-wide',
        'editor-styles',
        'dark-editor-style',
        'editor-font-sizes',
        'editor-color-palette',
        'editor-gradient-presets',
        'disable-custom-colors',
        'disable-custom-font-sizes',
        'disable-custom-gradients',
        'editor-style',
        'custom-spacing',
        'block-nav-menus',
        'block-templates',
        'core-block-patterns',
        'wide-alignment'
    ];
    foreach ($supports_list as $feature) {
        if (current_theme_supports($feature)) {
            $theme_supports[] = $feature;
        }
    }
    $theme_supports_json = wp_json_encode($theme_supports);

    // Get installed languages
    $installed_languages = get_available_languages();
    $installed_languages_json = wp_json_encode($installed_languages);

    // Get environment type
    $environment_type = wp_get_environment_type();

    // Get WordPress options
    $options = [];
    $options['home'] = get_option('home');
    $options['siteurl'] = get_option('siteurl');
    $options['blogname'] = get_option('blogname');
    $options['blogdescription'] = get_option('blogdescription');
    $options['admin_email'] = get_option('admin_email');
    $options['users_can_register'] = get_option('users_can_register');
    $options['timezone_string'] = get_option('timezone_string');
    $options['date_format'] = get_option('date_format');
    $options['time_format'] = get_option('time_format');
    //if ($q_widgetopts === 'Yes') $options['widgetopts_global_all_pages'] = get_option('widgetopts_global_all_pages');
    $options_json = wp_json_encode($options);

    // Get disk space usage
    $disk_total_space = disk_total_space(ABSPATH);
    $disk_free_space = disk_free_space(ABSPATH);


    $siteinfo = [
'wp_version' => $wp_version,
'language' => $wp_language,
'site_name' => $site_name,
'site_description' => $site_description,
'site_url' => $site_url,
//'admin_email' => $admin_email,
'theme_name' => $theme_name,
'theme_version' => $theme_version,
'child_theme_name' => $child_theme_name,
'child_theme_version' => $child_theme_version,
//'theme_directory' => $theme_directory,
'theme_supports_block_editor' => ($theme_supports_block_editor ? 'yes' : 'no'),
//'wp_debug' => ($wp_debug ? 'enabled' : 'disabled'),
//'wp_memory_limit' => $wp_memory_limit,
'permalink_structure' => $permalink_structure,
'features' => $features_json,
//'roles' => $roles_info_json,
'custom_post_types' => implode(', ', $post_types),
'custom_taxonomies' => implode(', ', $taxonomies),
//'active_plugins' => $plugins_info_json,
'all_plugins' => $all_plugins_info_json,
//'active_plugins_directories' . $active_plugins_directories_json,
'is_multisite' => ($is_multisite ? 'yes' : 'no'),
'site_count' => $site_count,
'network_name' => $network_name,
//'cron_jobs' => $cron_jobs_info_json,
'table_prefix' => $table_prefix,
'php_version' => $php_version,
'mysql_version' => $mysql_version,
'server_software' => $server_software,
'upload_dir' => wp_json_encode($upload_dir),
'sidebars_widgets' => wp_json_encode($sidebars_widgets),
//'cron_schedules' => wp_json_encode($cron_schedules),
//'constants' => $constants_json,
'theme_supports' => $theme_supports_json,
//'rest_routes' => $rest_routes_json,
'installed_languages' => $installed_languages_json,
'environment_type' => $environment_type,
'options' => $options_json,
'customizer_settings' => wp_json_encode(self::get_customizer_settings()),
//'disk_total_space' => $disk_total_space,
//'disk_free_space' => $disk_free_space
//'security_settings' => $security_settings_json,
//'transients' => $transients_info_json,
//'site_health' => wp_remote_retrieve_body($site_health),
    ];

    //$siteinfo_json = wp_json_encode($siteinfo, JSON_PRETTY_PRINT);
    //$file_path = WPCO_PLUGIN_UPLOAD_DIR . 'page_details_2.json';// . $page_id . '.json';
    //file_put_contents($file_path, $siteinfo_json);

return $siteinfo;
}



private static function get_page_details($page_id, $q_page_content = 'No',$q_template_parts = [],$q_template_parts_content = 'No',$q_widgets_info = 'No',$q_header = 'No',$q_footer = 'No') {
    // Initialize variables
    $details = [];

        $page = get_post($page_id);
        if (!$page) {
            return "Page not found.";
        }


        // Get page template
        $template = get_post_meta($page_id, '_wp_page_template', true);
        
        if (empty($template) || $template == 'default') {
            $template = 'default';
        } else {
            $template = basename($template, '.php'); // Clean up the template name
        }
        
        $template_parts = [];

        // Get the active theme
        $theme = wp_get_theme();

        // Get admin URL for page edit
        $admin_url = get_admin_url(null, "post.php?post=$page_id&action=edit");
        if (self::is_elementor_page($page_id)){
            $admin_url = $admin_url."&action=elementor";
        }

        // Get page author
        $author_id = $page->post_author;
        $author = get_userdata($author_id);

        // Get page publication date
        $published_date = get_the_date('Y-m-d H:i:s', $page);

        // Get page modification date
        $modified_date = get_the_modified_date('Y-m-d H:i:s', $page);

        // Get page slug
        $slug = $page->post_name;

        // Get page permalink
        $permalink = get_permalink($page_id);

        // Get page featured image
        $featured_image_id = get_post_thumbnail_id($page_id);
        $featured_image_url = $featured_image_id ? wp_get_attachment_url($featured_image_id) : '';
        
    
        // Output the details
        $details = [
            'Post id' => $page->ID ?? '',
            'Title' => $page->post_title ?? 'No Title',
            'Post Content' => $page->post_content ?? 'No Content',
            'Status' => $page->post_status ?? 'No Status',
            'Type' => $page->post_type ?? 'No Type',
            'Template' => $template ?? 'No Template',
            'Theme' => $theme->get('Name') ?? 'No Theme',
            'Admin Edit URL' => $admin_url ?? '#',
            //'Author' => $author->display_name ?? 'No Author',
            //'Published Date' => $published_date ?? 'No Published Date',
            //'Modified Date' => $modified_date ?? 'No Modified Date',
            'Elementor Edit' => self::is_elementor_page($page->ID) ? 'Page edited by Elementor' : 'Page not edited by Elementor',
            'Slug' => $slug ?? 'No Slug',
            'Permalink' => $permalink ?? '#',
            'Featured Image URL' => $featured_image_url ?? 'No Image URL',
            'Post URL' => get_permalink($page->ID),
            'Template Parts' => $template_parts ?? '',
            'Does current theme support block templates' => wp_is_block_theme() ? 'Yes': 'No',
            'Has Menu Items' => self::has_menu_items('primary') ? 'Yes' : 'No', // Check for 'primary' menu location

        ];
    //}

    $content = $details['Post Content'];
    

    if (!is_string($content)) {
    

        if (is_array($content)) {
            $content = wp_json_encode($content);
        }
        else $content = strval($content);
        
    }

    if (!is_string($content)) {
        $content = '';
    }

$q_template_parts = array_map(function($id) {
    return str_replace('///', '//', $id);
}, $q_template_parts);

    $templatepartsdata = self::execute_rest_command('/wp/v2/template-parts');
    $templatepartsdata = $templatepartsdata['message'];
    $extractedData = [];
    if (!is_string($templatepartsdata)) {
    
// Iterate through the array to extract specific fields
    foreach ($templatepartsdata as $item) {
        if (strpos($item['id'], '//') !== false){
            
            $wp_post_id = self::get_post_id_by_path($item['id'],'wp_template_part');
        }
        $extracted = [
            'id' => $item['id'],
            'theme' => $item['theme'],
            'Template Part Post id' => $wp_post_id
        ];
        $extractedData[] = $extracted;
    }

}
else if (strpos($templatepartsdata, "Error processing") === 0) 
{ $extractedData = [];
}

    $details['Template Parts (Output of /wp/v2/template-parts)'] = wp_json_encode($extractedData);

                // Get widgets from Gutenberg blocks
    $widgets_from_blocks = self::get_widgets_from_blocks($content);
    $widgets_from_classic = self::get_widgets_from_classic_content($content);
    $all_widgets = array_merge($widgets_from_blocks, $widgets_from_classic);
    // Add widgets details to the page details array
    $details['Widgets in Post'] = wp_json_encode($all_widgets) ?? '';
            
    
    // Retrieve sidebar widgets and details
    $widgets_details = self::get_all_widgets();

    // Add widgets details to the page details array
    $details['All available Widgets'] = wp_json_encode($widgets_details) ?? '';
    

    if ($q_page_content !== 'Yes') $details['Post Content']='';
    return $details;
}

private static function get_customizer_settings() {
    $customizer_settings = [];
    
    add_action('customize_register', function($wp_customize) use (&$customizer_settings) {
        foreach ($wp_customize->settings() as $setting) {
            $customizer_settings[$setting->id] = $setting->value();
        }
    });
    
    return $customizer_settings;
}

private static function display_site_health_recommendations($tests) {
    if (empty($tests) || $tests === null) {
        return '<p>No issues found.</p>';
    }

    $test_results = [];
    $results =[];
    $response = '';
    $response .= '<ul>';
    foreach ($tests as $test_key => $test_details) {
        if (empty($test_key) || $test_key === null) {continue;}
        $test_result = self::run_site_health_test($test_key);
        
        if ($test_result === null ||  $test_result['description']=== 'Test function not found.') {
            continue;
        }
        

        if ($test_result['status'] === 'critical') {
            $results['critical'][] = $test_result;
        } elseif ($test_result['status'] === 'recommended') {
            $results['recommended'][] = $test_result;
        } else {
            $results['other'][] = $test_result;
        }
    }

    $response = '';

    foreach (['critical', 'recommended', 'other'] as $status) {
        if (!empty($results[$status])) {
            if ($status === 'critical') $response .= ucfirst($status) . ' Issues';
            if ($status === 'recommended') $response .= ucfirst($status) . ' Improvements';
            if ($status === 'other') $response .= ucfirst($status) . ' Checks';
            $response .= '<ul>';
            foreach ($results[$status] as $test_result) {
                $response .= '<li>';
                $response .= '<p>' . esc_html($test_result['label']) . '</p>';
                $response .= '</li>';
            }
            $response .= '</ul>';
        }
    }

    if ($response === '') {
        return '<p>No issues found.</p>';
    }

    return $response;
}

private static function run_site_health_test($test_name) {
    if (!class_exists('WP_Site_Health')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
    }

    $site_health = new WP_Site_Health();    
    $function_name = 'get_test_' . $test_name;
    
    // Ensure the test function exists
    if (!method_exists($site_health, $function_name)) {

        return [
            'description' => 'Test function not found.',
            'actions' => []
        ];
    }

    // Run the test and capture the result
    $result = $site_health->$function_name();

    if (1===1){//$result['status'] === 'critical' || $result['status'] === 'recommended') {
        return $result;
//            'description' => $result['description'] ?? 'No description available',
            //'status' => $result['status'],
//            'label' => $result['label'] ?? 'No label available',
  //      ];
    } else {
        return null;
    }
    // Structure the result to match the expected format
}


public static function wpco_check_admin_status() {
    if (current_user_can('administrator')) {
        wp_send_json_success(true);
    } else {
        wp_send_json_success(false);
    }
}

private static function is_elementor_page($post_id) {
    // Check if Elementor is active
    if (did_action('elementor/loaded')) {


        // Check if the page has Elementor data
        $meta = get_post_meta($post_id, '_elementor_data', true);


        // Check if the page uses an Elementor template
        $template = get_page_template_slug($post_id);


        $elementor_templates = array(
            'elementor_canvas',
            'elementor_header_footer',
            'elementor_full_width'
        );


        // Check for Elementor widgets in the post content
        $content = get_post_field('post_content', $post_id);
        $is_elementor_content = strpos($content, '<!-- wp:elementor') !== false || strpos($content, 'elementor-widget') !== false;


        // Additional Elementor-specific checks
        $elementor_settings = get_post_meta($post_id, '_elementor_settings', true);
        $is_elementor_page = get_post_meta($post_id, '_elementor_edit_mode', true);

        if (!empty($meta) || in_array($template, $elementor_templates) || $is_elementor_content || !empty($elementor_settings) || $is_elementor_page === 'builder') {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
    return false;
}


private static function execute_rest_command($url = null) {

    $parsed_url = wp_parse_url($url);
    $route = $parsed_url['path'];
    $query_params = [];
    if (isset($parsed_url['query'])) {
        parse_str($parsed_url['query'], $query_params);
    }

    $searchTerm = $query_params['contentsearch'] ?? null;
    if ($searchTerm !== null) {
        unset($query_params['contentsearch']);
    }


try{

        $nonce = wp_create_nonce('wp_rest');
    
        $http_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        $request = new WP_REST_Request('GET', $route);
        $request->set_header('X-WP-Nonce', $nonce);
        $request->set_header('User-Agent', $http_user_agent);
        $request->set_query_params($query_params);
        // Add cookies to the request headers
        /*$cookies = [];
        foreach ($_COOKIE as $name => $value) {
            $cookies[] = new WP_Http_Cookie(array('name' => $name, 'value' => $value));
        }
        
        $request->set_header('cookies', $cookies);
        */
        $help_string = '';
        $args_keys = []; 
        $properties_keys = [];
        
        // Dispatch the request using the REST server
        $response = rest_do_request($request);
        
        if (($url !== null)  && strpos($url,'/wp') === 0) {
        $attributes = $request->get_attributes();
        if (!empty($attributes)) {
        if (isset($attributes['args'])) $args_keys = array_keys($attributes['args']);
        else $args_keys = []; 
        if (isset($attributes['callback'][0])) {
        $reflection = new ReflectionClass($attributes['callback'][0]);
        $prop = $reflection->getProperty('schema');
        $prop->setAccessible(true);
        $schema = $prop->getValue($attributes['callback'][0]);
        if (isset($schema['properties'])) $properties_keys = array_keys($schema['properties']);
        else $property_keys = [];
        }
        $help_string = 'Arguments - '.wp_json_encode($args_keys).","."Schema -".wp_json_encode($properties_keys);
        }
    } 
        // Check if the response is a WP_Error
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            if (strpos($route,'/wp-site-health/') !== false || strpos($route,'/site-health/') !== false) {$error_message = self::site_health();}
            return ['message' => $error_message, 'Route Parameters' => $help_string];
        }
    
            // Extract the response data
        $response_data = $response->get_data();

        if ($response_data instanceof stdClass) {
            // Convert the stdClass object to JSON
            $json = wp_json_encode($response);
            
            // Decode the JSON back to an associative array
            $response_data = json_decode($json, true);
        }

            // Check if the response contains an error
        if ($response->is_error()) {
        $error_message = isset($response_data['message']) ? $response_data['message'] : $response_data;
        if (strpos($route,'/wp-site-health/') !== false || strpos($route,'/site-health/') !== false) {$error_message = self::site_health();}
        return ['message' => $error_message, 'Route Parameters' => $help_string];
        }
    
    if (empty($response_data)) {
        $message = 'No data found in response';
        if (strpos($route,'/wp-site-health/') !== false || strpos($route,'/site-health/') !== false) {$message = self::site_health();}
        return ['message' => $message, 'Route Parameters' => $help_string];
    }
            
        
    if (strpos($url, '/wp/v2/plugins') !== false && isset($query_params['_fields'])) 
    { 
        $fields = $query_params['_fields'];
        $fieldsArray = explode(',', $fields);
        $response_data = array_map(function($plugin) use ($fieldsArray) {
            return array_intersect_key($plugin, array_flip($fieldsArray));
        }, $response_data);
    }

    if ($searchTerm !== null)
    {   $content = null;
        $searchResults=[];
        
        if (is_array($response_data) && count($response_data) > 0) {
            
            if (isset($response_data[0]['content']['rendered'])) $content = $response_data[0]['content']['rendered'];
            if (isset($response_data[0]['content']['raw']) && $content === null) $content = $response_data[0]['content']['raw'];
        }
        else {
            if (isset($response_data['content']['rendered'])) $content = $response_data['content']['rendered'];
            if (isset($response_data['content']['raw']) && $content === null) $content = $response_data['content']['raw'];
        }
        

        if ($content !== null) $searchResults = self::searchInContent($content, $searchTerm);
        

        if ($searchResults !== []) 
        {
            $newMessage = [];
            foreach ($response_data as $key => $value) {
                
                if ($key === 'content') {
                    $newMessage['search_results'] =  $searchResults;
                }
                if ($key !== 'content') $newMessage[$key] = $value;
            }
            
            $response_data = $newMessage;

        }
    }

        return ['message' => $response_data, 'Route Parameters' => $help_string];
        
    } catch (Exception $e) {
        return ['message' => $e->getMessage(), 'Route Parameters' => $help_string];
        ;
    }

}



private static function get_all_widgets() {
    global $wp_registered_widgets;

    $sidebars_widgets = wp_get_sidebars_widgets();
    $all_widgets = [];

    // Gather sidebar widgets
    foreach ($sidebars_widgets as $sidebar_id => $widgets) {
        foreach ($widgets as $widget_id) {
            if (!isset($wp_registered_widgets[$widget_id])) {
                continue;
            }

            $widget_data = $wp_registered_widgets[$widget_id];
            $widget_instance = get_option($widget_data['callback'][0]->option_name);
            $widget_settings = isset($widget_instance[$widget_data['params'][0]['number']]) ? $widget_instance[$widget_data['params'][0]['number']] : [];

            $all_widgets[$widget_id] = [
                'id' => $widget_id,
                'name' => $widget_data['name'],
                'settings' => $widget_settings,
                'sidebar' => $sidebar_id,
            ];
        }
    }

    // Gather non-sidebar widgets
    foreach ($wp_registered_widgets as $widget_id => $widget_data) {
        if (array_key_exists($widget_id, $all_widgets)) {
            continue; // Already processed as a sidebar widget
        }

        $widget_instance = get_option($widget_data['callback'][0]->option_name);
        $widget_settings = isset($widget_instance[$widget_data['params'][0]['number']]) ? $widget_instance[$widget_data['params'][0]['number']] : [];

        $all_widgets[$widget_id] = [
            'id' => $widget_id,
            'name' => $widget_data['name'],
            'settings' => $widget_settings,
            'sidebar' => 'none',
        ];
    }

    return $all_widgets;
}

private static function get_widgets_from_blocks($blocks) {
    $widgets = [];

    // If content is provided as a string, parse the blocks
    if (is_string($blocks)) {
        $blocks = parse_blocks($blocks);
    }

    foreach ($blocks as $block) {
        if (isset($block['blockName']) && (strpos($block['blockName'], 'widget') !== false || $block['blockName'] === 'core/widget-area' || $block['blockName'] === 'core/legacy-widget')) {
            // Example: Get widget block details
            $widgets[] = [
                'blockName' => $block['blockName'],
                'attrs' => $block['attrs'],
                'innerHTML' => $block['innerHTML'],
                'innerContent' => $block['innerContent'],
            ];
        }

        if (!empty($block['innerBlocks'])) {
            $widgets = array_merge($widgets, self::get_widgets_from_blocks($block['innerBlocks']));
        }
    }

    return $widgets;
}

private static function get_widgets_from_classic_content($content) {
    $shortcode_widgets = [];
    
    // Example shortcode pattern for widgets
    $pattern = get_shortcode_regex();
    
    if (preg_match_all('/' . $pattern . '/s', $content, $matches) && array_key_exists(2, $matches)) {
        foreach ($matches[2] as $key => $shortcode) {
            // Assuming the shortcode is a widget, you can adjust this logic as needed
            $shortcode_widgets[] = [
                'shortcode' => $shortcode,
                'attributes' => shortcode_parse_atts($matches[3][$key]),
                'content' => $matches[5][$key],
            ];
        }
    }

    return $shortcode_widgets;
}


private static function has_menu_items($menu_location) {
    if (has_nav_menu($menu_location)) {
        $locations = get_nav_menu_locations();
        $menu = wp_get_nav_menu_object($locations[$menu_location]);
        $menu_items = wp_get_nav_menu_items($menu->term_id);
        return !empty($menu_items);
    }
    return false;
}



private static function execute_plan($query, $page_id, $history,$wp_info,$query_id,$plan,$page_details,$site_info,$availablerestroutes,$http_referer)
{
    
    $loop = true;
    $i = 1;
    $last_iteration = 2;
    
    while ($loop) {
    
    $plan1 = self::call_plan_module($query_id,$query, $page_id, $history,$wp_info,$http_referer,$plan,$i,$last_iteration,2,$availablerestroutes);
    
    if (isset($plan1['error']))
    {
        $i = $i+1;
        if ($i > $last_iteration) 
    {
        $loop = false;
        break;
    }
    else continue;
    }


    if (is_array($plan1)) $plan = $plan1;
    else  $plan = json_decode($plan1,true);


    self::wpco_sendMessage('step', $i+2);
    ob_flush();
    flush();

    foreach ($plan['steps'] as $index => $step) {
        
        if (!isset($step['command'])) continue;

        $command = $step['command'];

        if (isset($step['output'])) $step['output'] = trim($step['output']);
        if (isset($step['output']) && $step['output'] !== null && $step['output'] !== '') 
        {   
            continue;
        }

        
        $restapi_command = $step['command'];
        $help_string = '';

        
        
        $response = self::execute_rest_command($restapi_command);
        $help_string = isset($response['Route Parameters']) ? $response['Route Parameters'] : '';
        $response = $response['message'];
        if ($response === '' || $response === null) $response = "No output";
        
        if (is_array($response)) $response = wp_json_encode($response);
        $response = self::restrictToApprox1000Tokens($response);
        if ($help_string !== '') $help_string = self::restrictToApprox1000Tokens($help_string);
        $plan['steps'][$index]['output'] = $response;
        if (strpos($response,'Error processing') !== false && $help_string !== '') $plan['steps'][$index]['Rest API Parameters'] = $help_string;
    
    }
    
    //if ($plan['status'] === 'stop' || $plan['status'] === 'success' || $i >= $last_iteration) 
    if ($i >= $last_iteration) 
    {
        $loop = false;
        break;
    }
    
    $i = $i +1;    
}   
    

    $plan1 = self::call_plan_module($query_id,$query, $page_id,  $history,$wp_info,$http_referer,$plan,$i,$last_iteration,3,$availablerestroutes);
    
    if (isset($plan1['error']))
    {
        return "Sorry, we are experiencing a lot of traffic right now. Please try again later.";// but here is the update so far".$plan['analysis'];
    }

    if (!is_array($plan1)) $plan1 = json_decode($plan1, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return $plan1;
    }

    $plan = $plan1;
    
    return $plan['response'];

}



public static function wpco_sse_handler() {
    
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), 'wpco_chat_nonce')) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }

    @ini_set('zlib.output_compression', 0); // Disable PHP output compression
    @ini_set('implicit_flush', 1); // Force PHP to flush the output buffer immediately

    if (!headers_sent()) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache'); // Legacy compatibility for HTTP/1.0
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable server-side buffering
            }
    
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    ob_implicit_flush(true);

     
    $query_id = isset($_GET['query_id']) ? sanitize_text_field($_GET['query_id']) : '';

    if (empty($query_id)) {
        self::wpco_sendMessage('error', 'Invalid query ID');
        ob_flush();
        flush();
        return;
    }
 
    ob_start();
 
    while (true) {

    $process_state = get_transient('wpco_process_state_' . $query_id);
    
    $query = $process_state['query'];
    $post_id = $process_state['post_id'];
    $history = $process_state['history'];
    $http_referer = $process_state['http_referer'];

    try {
        $response = '';
        self::wpco_sendMessage('update', 'Analyzing your request...');
        $response = self::process_query($query, $post_id, $history, $query_id,$http_referer);                
    } catch (Exception $e) {
        self::wpco_sendMessage('error', 'Error processing query: ' . $e->getMessage());

        if (isset($response['message'])) $finalresponse = $response['message'];
        else if (isset($response['response'])) $finalresponse = $response['response'];
        else if (is_string($response)) $finalresponse = $response;
        else $finalresponse = 'Completed';

        set_transient('wpco_response_' . $query_id, esc_html($finalresponse), 600);

        self::wpco_sendMessage('complete', $finalresponse);

        ob_flush();
        flush();
        
    } finally {
        

        if (isset($response['message'])) $finalresponse = $response['message'];
        else if (isset($response['response'])) $finalresponse = $response['response'];
        else if (is_string($response)) $finalresponse = $response;
        else $finalresponse = 'Completed';

        set_transient('wpco_response_' . $query_id, esc_html($finalresponse), 600);

        self::wpco_sendMessage('complete', $finalresponse);
        
        delete_transient('wpco_process_state_' . $query_id);
        ob_flush();
        flush();     
        ob_end_flush();
        
    }

    break;
}
   // ob_end_flush();
}

public static function wpco_sendMessage($event, $data) {    
    if ($data === null) {
        return; // If data is null, do not send anything
    }
    if (!headers_sent()) {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache'); // Legacy compatibility for HTTP/1.0
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable server-side buffering
            }

    echo "event: ".esc_html($event)."\n";
    
    // Replace double newlines with <br/><br/> first
    $data = str_replace("\n\n", '<br/><br/>', $data);
    // Then replace single newlines with <br/>
    $data = str_replace("\n", '<br/>', $data);


        // Send the entire content as a single SSE data event
        echo "data: " . esc_html($data) . "\n\n";
        ob_flush();
        flush();
    
}


private static function get_post_id_by_path($path, $post_type = 'page') {
    global $wpdb;

    // Split the path to get the slugs, handling double forward slashes
    $parts = explode('//', $path);
    $post_slug = array_pop($parts); // Get the last element as post slug
    $parent_slug = array_pop($parts); // Get the second last element as parent slug if exists

    // Initialize parent ID
    $parent_id = 0;

    // If there is a parent slug, find its ID first
    if ($parent_slug) {
        $parent_query = $wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_parent = 0",
            $parent_slug,
            $post_type
        );
        $parent_id = $wpdb->get_var($parent_query);
        if (!$parent_id) {
            $parent_id = 0; // If parent is not found, set parent_id to 0
        }
    }

    // Query to find the post with the given slug, post type, and parent ID
    $query = $wpdb->prepare(
        "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_parent = %d ORDER BY post_date DESC LIMIT 1",
        $post_slug,
        $post_type,
        $parent_id
    );

    // Get the post ID
    $post_id = $wpdb->get_var($query);

    // Return the post ID if found, or null if not found
    return $post_id ? $post_id : null;
}

private static function call_plan_module($query_id, $query, $page_id, $history, $wp_info, $url, $plan, $i, $last_iteration, $question, $availablerestroutes) {
    $api_url = 'http://quickassistai.com//planmodule.php';
    $homepage = home_url();

    // Prepare the data to send in the POST request
    $data = array(
        'query' => $query,
        'page_id' => $page_id,
        'history' => $history,
        'wp_info' => $wp_info,
        'url' => $url,
        'plan' => $plan,
        'i' => $i,
        'last_iteration' => $last_iteration,
        'question' => $question,
        'Home Page' => $homepage,
        'query_id' => $query_id,
        'Available Rest Routes' => $availablerestroutes
    );

    // Convert the data to JSON format
    $json_data = wp_json_encode($data);

        // Set up the arguments for wp_remote_post
        $args = array(
            'body'        => $json_data,
            'headers'     => array(
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($json_data),
            ),
            'timeout'     => 120,  // Timeout in seconds
            'method'      => 'POST',
        );
    
        // Make the POST request to the API
        $response = wp_remote_post($api_url, $args);
    
        // Check for errors
        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            $data['result']['error'] = 'Error calling plan_module: ' . $error_msg;
            return $data['result'];
        }
    
        // Retrieve the response body
        $response_body = wp_remote_retrieve_body($response);
    
  /*   // Initialize cURL
    $ch = curl_init($api_url);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
    curl_setopt($ch, CURLOPT_POST, true); // Set the request method to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data); // Set the POST fields
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json', // Set the content type to JSON
        'Content-Length: ' . strlen($json_data) // Set the content length
    ));
    //curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 50); // Start keep-alive after 50 seconds
    //curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 10); // Interval between keep-alive packets
    //curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 seconds to establish a connection
    curl_setopt($ch, CURLOPT_TIMEOUT, 150); // Set the timeout to 240 seconds

    // Execute the cURL request
    $response = curl_exec($ch);

    // Check if there was an error
    if ($response === false) {
        $error_msg = curl_error($ch);
        $data['result']['error'] = 'Error calling plan_module';
        curl_close($ch);
        return $data['result'];
    }

    // Close the cURL session
    curl_close($ch);
 */
    // Decode the response
    $response_data = json_decode($response_body, true);

    // Handle the response
    if (isset($response_data['result'])) {
        return $response_data['result'];
    } else {
        $data['result']['error'] = 'Unexpected response from API';
        return $data['result'];
    }
}

private static function get_post_fields($post_id) {
    // Get the post object
    $post = get_post($post_id);
    if (!$post) {
        return wp_json_encode([]);
    }

    // Get standard fields
    $standard_fields = get_object_vars($post);

    // Get meta fields
    $meta_fields = get_post_meta($post_id);

        // Get taxonomies
        $taxonomies = [];
        $post_taxonomies = get_object_taxonomies($post->post_type, 'names');
        foreach ($post_taxonomies as $taxonomy) {
            $taxonomies[$taxonomy] = wp_get_post_terms($post_id, $taxonomy, array('fields' => 'all'));
        }
    
    
    // Combine standard fields and meta fields into a structured array
    $fields = [
    //    'post_fields' => $standard_fields,
        'meta_fields' => $meta_fields,
        'taxonomies' => $taxonomies,
    ];

    // Convert the array to JSON
    return wp_json_encode($fields, JSON_PRETTY_PRINT);
}

private static function restrictToApprox1000Tokens($text) {
    // Approximation based on your estimation
    if ($text === null) return $text;

    $maxWords = 1000 * 3/4;
    $maxChars = 1000 * 4; //Changed to 1500 tokens
    $truncated = false;

    // First, truncate based on character count
    if (strlen($text) > $maxChars) {
        $text = substr($text, 0, $maxChars);
        $truncated = true;
    }

    // Split the text into words
    $words = preg_split('/\s+/', $text);
    
    // Restrict to the approximate number of words
    if (count($words) > $maxWords) {
        $words = array_slice($words, 0, $maxWords);
        $truncated = true;
    }
    
    // Join the words back into a string
    $restrictedText = implode(' ', $words);

    if ($truncated) $restrictedText = "(Truncated)".$restrictedText;
    
    
    return $restrictedText;
}


// Function to clean content
private static function clean_content($content) {
    $allowed_tags = '<u><p><h1><h2><h3><h4><h5><h6><span><div><a><img><strong><em><br><ul><ol><li><blockquote><code>';
    $allowed_attributes = ['class', 'style'];

    // Remove WordPress block comments
    $content = preg_replace('/<!-- wp:[^>]* -->/', '', $content);
    $content = preg_replace('/<!-- \/wp:[^>]* -->/', '', $content);

    // Remove unnecessary HTML attributes but keep allowed attributes
    $content = preg_replace_callback('/<([a-z][a-z0-9]*)\b[^>]*>/i', function ($matches) use ($allowed_attributes) {
        $tag = $matches[1];
        $attributes = '';

        preg_match_all('/(\w+)=("[^"]*"|\'[^\']*\')/', $matches[0], $attr_matches, PREG_SET_ORDER);
        foreach ($attr_matches as $attr) {
            if (in_array($attr[1], $allowed_attributes)) {
                $attributes .= ' ' . $attr[1] . '=' . $attr[2];
            }
        }

        return '<' . $tag . $attributes . '>';
    }, $content);

    // Remove empty tags
    $content = preg_replace('/<(\w+)\s*><\/\1>/', '', $content);

    // Remove any remaining HTML tags not in the allowed list
    $content = strip_tags($content, $allowed_tags);

        // Remove extra white spaces and new lines
        $content = preg_replace('/\s+/', ' ', $content);
        $content = trim($content);

    return $content;
}


private static function site_health()
{
    if (!class_exists('WP_Site_Health')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
    }

    $site_health = new WP_Site_Health();
    $status = $site_health->get_tests();
    
    $site_health_data = '';
    if (!empty($status)) {
        $all_tests = [];
        foreach ($status as $test_group => $tests) {
            $all_tests = array_merge($all_tests, $tests);
        }
        $site_health_data .= self::display_site_health_recommendations($all_tests);
    } else {
        $site_health_data .= '<p>No site health data found.</p>';
    }
      $site_health_data = 'Here are some site health recommendations:<br>' . $site_health_data;
      return $site_health_data;
}

private static function searchInContent($content, $searchTerm) {
    $lines = explode("\n", $content);
    $results = [];
    

    foreach ($lines as $line) {
        if (stripos($line, $searchTerm) !== false) {
            $results[] = trim($line);
        }
    }

    return $results;
}

private static function available_routes()
{

    $routes = rest_get_server()->get_routes();

    $extracted_data = [];
    $base_route_data = [];
    
    foreach ($routes as $route => $endpoints) {
        foreach ($endpoints as $endpoint) {
            if (isset($endpoint['methods']['GET']) && $endpoint['methods']['GET']) {
                // Split the route into its components
                $route_parts = explode('/', trim($route, '/'));
                
                // Determine the base route (first two segments)
                $route1 = isset($route_parts[0]) ? '/'.$route_parts[0] : '';
                $route2 = isset($route_parts[1]) ? '/'.$route_parts[1] : '';
                $base_route = $route1.$route2;
                
                // Initialize the base route in the array if not already done
                if (!isset($base_route_data[$base_route])) {
                    $base_route_data[$base_route] = [
                        'base_route' => $base_route,
                      //  'description' => $endpoint['args']['context']['description'] ?? '', // Get the description if available
                      //  'args' => isset($endpoint['args']) ? $endpoint['args'] : [],
                        'sub_routes' => []
                    ];
                }
                
                // If there are more than two segments, group them accordingly
                if (count($route_parts) > 2) {
                    $second_level_route = '/' . $route_parts[2];
                    
                    if (!isset($base_route_data[$base_route]['sub_routes'][$second_level_route])) {
                        $base_route_data[$base_route]['sub_routes'][$second_level_route] = [];
                    }
                    
                    if (count($route_parts) > 3) {
                        $third_level_route = implode('/', array_slice($route_parts, 3));
                        $base_route_data[$base_route]['sub_routes'][$second_level_route][] = '/' . $third_level_route;
                    }
                }
            }
        }
    }
    
    // Convert the result to a simple list
    foreach ($base_route_data as $data) {
        $extracted_data[] = $data;
    }
    
            
    $routes_json = wp_json_encode($extracted_data);

    return $routes_json;
}

}
add_action('plugins_loaded', ['WPCO_Chat', 'init']);
?>
