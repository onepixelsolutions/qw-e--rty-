<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('smartboq_add_admin_notice')) {
    function smartboq_add_admin_notice($message, $type = 'success') {
        if (!is_admin()) return;

        $notices = get_option('smartboq_admin_notices', []);
        $notices[] = [
            'message' => sanitize_text_field($message),
            'type'    => $type
        ];
        update_option('smartboq_admin_notices', $notices);

        if (defined('SMARTBOQ_DEBUG') && SMARTBOQ_DEBUG) {
            error_log('[SmartBOQ Notice] ' . $type . ': ' . $message);
        }
    }

    add_action('admin_notices', function () {
        $notices = get_option('smartboq_admin_notices', []);
        if (!empty($notices)) {
            foreach ($notices as $notice) {
                $class = ($notice['type'] === 'error') ? 'notice notice-error' :
                         (($notice['type'] === 'info') ? 'notice notice-info' : 'notice notice-success');
                echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($notice['message']) . '</p></div>';
            }
            delete_option('smartboq_admin_notices');
        }
    });
}
add_action('all_admin_notices', function () {
    do_action('admin_notices');
});
