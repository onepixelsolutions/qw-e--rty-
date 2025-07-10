<?php
// SmartBOQ AI – Status Tracker

// Default status setter on plugin init
add_action('init', function () {
    if (!get_option('smartboq_status')) {
        update_option('smartboq_status', 'draft');
    }
});

// Admin notice to display current status
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;

    $status = get_option('smartboq_status', 'draft');
    $color = ($status === 'complete') ? 'green' : (($status === 'processing') ? 'orange' : 'gray');

    echo '<div class="notice notice-info is-dismissible">';
    echo '<p><strong>SmartBOQ Status:</strong> <span style="color:' . esc_attr($color) . '; text-transform:uppercase;">' . esc_html($status) . '</span></p>';
    echo '</div>';
});

// Set status programmatically (for other modules)
function smartboq_set_status($status) {
    $allowed = ['draft', 'processing', 'complete'];
    if (in_array($status, $allowed)) {
        update_option('smartboq_status', $status);
        if (get_option('smartboq_debug_status')) {
            error_log("[SmartBOQ] Status changed to: " . $status);
        }
    }
}

// Get current status (for checks in other modules)
function smartboq_get_status() {
    return get_option('smartboq_status', 'draft');
}

// Reset status button in BOQ Upload Page
add_action('admin_footer', function () {
    if (isset($_GET['page']) && $_GET['page'] === 'smartboq-upload' && current_user_can('manage_options')) {
        echo '<form method="post" style="margin-top:20px;">';
        echo '<input type="hidden" name="reset_status" value="1">';
        submit_button("Reset Status to Draft", 'secondary');
        echo '</form>';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_status'])) {
        smartboq_set_status('draft');
        wp_redirect(admin_url('admin.php?page=smartboq-upload'));
        exit;
    }
});
