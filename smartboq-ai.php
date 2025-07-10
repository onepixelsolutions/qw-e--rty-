<?php
/**
 * Plugin Name: SmartBOQ AI
 * Description: AI-powered BOQ processor with Formidable integration.
 * Version: 1.0.0
 * Author: Asghar Shaikh
 */

// ✅ Constant should be first
if (!defined('SMARTBOQ_DEBUG')) define('SMARTBOQ_DEBUG', true);

require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
// ❌ Exit if accessed directly
if (!defined('ABSPATH')) exit;

// ✅ Load Rate Matcher settings page UI always (for admin)
add_action('plugins_loaded', function () {
    // ✅ Load core modules
    if (is_admin()) {
        include_once plugin_dir_path(__FILE__) . 'includes/modules/rate-matcher-settings.php';
        include_once plugin_dir_path(__FILE__) . 'includes/modules/token-map-settings.php';
        include_once plugin_dir_path(__FILE__) . 'includes/modules/rate-matcher.php';
    }

    // ✅ Register activation after functions are loaded
    if (function_exists('smartboq_create_rate_matcher_table')) {
        register_activation_hook(__FILE__, 'smartboq_create_rate_matcher_table');
    }
});


// ✅ Include helper files
include_once plugin_dir_path(__FILE__) . 'includes/helpers.php';

// ✅ Register activation for Validation Table
register_activation_hook(__FILE__, function () {
    if (function_exists('smartboq_create_validation_table')) {
        smartboq_create_validation_table();
    } else {
        // Manually include the file to load the function
        $validation_file = plugin_dir_path(__FILE__) . 'includes/modules/excel-upload.php';
        if (file_exists($validation_file)) {
            include_once $validation_file;
            if (function_exists('smartboq_create_validation_table')) {
                smartboq_create_validation_table();
            }
        }
    }
});

// ✅ Now it's safe to register the activation hook
// register_activation_hook(__FILE__, 'smartboq_create_rate_matcher_table');

// ---------------------------------------------------
// ✅ Core: Version-wise Module Definitions
// ---------------------------------------------------
function smartboq_get_all_versions_modules() {
    return [
        'lite' => [
            'excel_upload'      => 'Excel Upload + Validation',
            'boq_locking'       => 'BOQ Locking System',
            'status_tracker'    => 'BOQ Status Tracker',
            'rate_matcher'      => 'Rate Matcher (Formidable)',
            'discount_engine'   => 'Discount Engine',
            'excel_export'      => 'Excel Export',
        ],
        'advance' => [
            'ai_parser'         => 'AI NLP Parser',
            'voice_input'       => 'Voice + Roman Urdu Input',
            'prompt_ui'         => 'AI Prompt UI (Predefined/Custom)',
            'category_mapping'  => 'BOQ Category Mapping',
            'confidence_score'  => 'Rate Confidence Scoring',
            'item_discounts'    => 'Per-item Discount Logic',
            'basic_fallback'    => 'Fixed AI Fallback (non-DB)',
        ],
        'pro' => [
            'internet_rates'    => 'Internet Rate Fetch + Links',
            'image_suggestion'  => 'Rate-based Product Image + Link',
            'notifications'     => 'WhatsApp / Email Notifications',
            'webhooks'          => 'Webhook Integration',
            'dashboards'        => 'Project Dashboards',
            'audit_trails'      => 'Audit Logs & Revisions',
        ]
    ];
}

function smartboq_get_default_modules() {
    $all = smartboq_get_all_versions_modules();
    return array_keys($all['lite']);
}

function smartboq_initialize_modules() {
    if (!get_option('smartboq_enabled_modules')) {
        update_option('smartboq_enabled_modules', smartboq_get_default_modules());
    }
}
add_action('admin_init', 'smartboq_initialize_modules');

function smartboq_get_enabled_modules() {
    return get_option('smartboq_enabled_modules', []);
}

function smartboq_is_module_active($key) {
    return in_array($key, smartboq_get_enabled_modules());
}

function smartboq_save_enabled_modules($keys) {
    if (is_array($keys)) {
        update_option('smartboq_enabled_modules', $keys);
    }
}

function smartboq_reset_modules_to_default() {
    update_option('smartboq_enabled_modules', smartboq_get_default_modules());
}

// ---------------------------------------------------
// ✅ Admin Menu: Parent + Settings Page
// ---------------------------------------------------
add_action('admin_menu', function () {
    add_menu_page(
        'SmartBOQ AI',
        'SmartBOQ AI',
        'manage_options',
        'smartboq_ai_main',
        'smartboq_render_dashboard_page',
        'dashicons-clipboard',
        60
    );

    add_submenu_page(
        'smartboq_ai_main',
        'Module Settings',
        'Module Settings',
        'manage_options',
        'smartboq_module_settings',
        'smartboq_render_module_settings_page'
    );
    
    add_submenu_page(
        'smartboq_ai_main',
        'Rate Matcher Settings',
        'Field Mapping',
        'manage_options',
        'smartboq_rate_matcher_settings',
        'smartboq_render_rate_matcher_settings_page'
    );
    add_submenu_page(
        'smartboq_ai_main',
        'Token Map Settings',
        'Token Mapping',
        'manage_options',
        'smartboq_token_map_settings',
        'smartboq_render_token_map_settings_page'
    );
});

function smartboq_render_dashboard_page() {
    echo '<div class="wrap"><h1>Welcome to SmartBOQ AI</h1><p>This will be your project dashboard in the future.</p></div>';
}

function smartboq_render_module_settings_page() {
    if (isset($_POST['smartboq_save_settings'])) {
        check_admin_referer('smartboq_save_modules');
        $selected = isset($_POST['smartboq_modules']) ? array_map('sanitize_text_field', $_POST['smartboq_modules']) : [];
        smartboq_save_enabled_modules($selected);
        echo '<div class="updated"><p>✅ Settings saved successfully.</p></div>';
    }

    if (isset($_POST['smartboq_reset_defaults'])) {
        check_admin_referer('smartboq_save_modules');
        smartboq_reset_modules_to_default();
        echo '<div class="notice notice-warning"><p>✅ Reset to default modules complete.</p></div>';
    }

    $enabled_modules = smartboq_get_enabled_modules();
    $all_versions = smartboq_get_all_versions_modules();

    echo '<div class="wrap"><h1>SmartBOQ AI – Module Settings</h1><form method="post">';
    wp_nonce_field('smartboq_save_modules');

    echo '<style>
        .smartboq-box {border:1px solid #ccc; padding:20px; margin-bottom:30px; background:#fefefe;}
        .smartboq-box h2 {margin-top:0; border-bottom:1px solid #ddd; padding-bottom:5px;}
        .smartboq-module {margin:10px 0;}
    </style>';

    foreach ($all_versions as $version => $modules) {
        echo "<div class='smartboq-box'><h2>" . ucfirst($version) . " Version</h2>";
        foreach ($modules as $key => $label) {
            $checked = in_array($key, $enabled_modules) ? 'checked' : '';
            echo "<div class='smartboq-module'><label><input type='checkbox' name='smartboq_modules[]' value='$key' $checked> $label</label></div>";
        }
        echo '</div>';
    }

    echo '<p><input type="submit" name="smartboq_save_settings" class="button button-primary" value="💾 Save Changes"> ';
    echo '<input type="submit" name="smartboq_reset_defaults" class="button button-secondary" value="♻️ Reset to Default"></p>';
    echo '</form></div>';
}

// ---------------------------------------------------
// ✅ Load Enabled Modules
// ---------------------------------------------------
// ✅ Load Enabled Modules Only in Admin Area
add_action('admin_menu', function () {
    foreach (smartboq_get_enabled_modules() as $module_key) {
        $file_name = str_replace('_', '-', $module_key) . '.php';
        $filepath = plugin_dir_path(__FILE__) . 'includes/modules/' . $file_name;

        if (file_exists($filepath)) {
            include_once $filepath;
        } else {
            if (SMARTBOQ_DEBUG) {
                error_log("❌ Module file not found: $filepath");
                add_action('admin_notices', function () use ($file_name) {
                    echo '<div class="notice notice-warning"><p>⚠️ Module file not found: <code>' . esc_html($file_name) . '</code></p></div>';
                });
            }
        }
    }
}, 5); // 🎯 Priority low rakhein taake submenu register hone se pehle module load ho jaye




// ---------------------------------------------------
// ✅ Debug Active Modules in Admin Notice
// ---------------------------------------------------
function smartboq_debug_active_modules() {
    if (SMARTBOQ_DEBUG && current_user_can('administrator')) {
        echo '<div class="notice notice-info"><strong>SmartBOQ Active Modules:</strong><br>';
        foreach (smartboq_get_enabled_modules() as $key) {
            echo '<code>' . esc_html($key) . '</code> ';
        }
        echo '</div>';
    }
}
add_action('admin_notices', 'smartboq_debug_active_modules');


add_action('admin_init', function () {
    if (isset($_GET['smartboq_test_notice'])) {
        smartboq_add_admin_notice('🧪 Test message from SmartBOQ plugin.');
    }
});
add_action('admin_init', function () {
    if (!current_user_can('manage_options')) {
        wp_die('🚫 Current user cannot manage options.');
    }
});
