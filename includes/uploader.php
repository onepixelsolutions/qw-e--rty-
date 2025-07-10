<?php
// SmartBOQ Excel Upload + Locking System

add_action('admin_menu', function () {
    add_menu_page(
        'BOQ Upload',
        'BOQ Upload',
        'manage_options',
        'smartboq-upload',
        'smartboq_upload_page',
        'dashicons-upload',
        56
    );
});

function smartboq_upload_page() {
    ?>
    <div class="wrap">
        <h2>Upload BOQ Excel File</h2>
        <?php if (isset($_GET['upload_status'])): ?>
            <div class="notice notice-<?php echo esc_attr($_GET['upload_status']); ?> is-dismissible">
                <p><?php echo esc_html($_GET['message']); ?></p>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('smartboq_upload'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">BOQ Excel File (.xlsx)</th>
                    <td><input type="file" name="smartboq_file" accept=".xlsx" required /></td>
                </tr>
            </table>
            <?php submit_button('Upload BOQ'); ?>
        </form>
    </div>
    <?php

    // Handle upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('smartboq_upload')) {
        smartboq_handle_file_upload();
    }
}

function smartboq_handle_file_upload() {
    $debug = get_option('smartboq_debug_upload', false);

    if (!current_user_can('manage_options')) return;

    if (!isset($_FILES['smartboq_file']) || $_FILES['smartboq_file']['error'] !== UPLOAD_ERR_OK) {
        smartboq_redirect_notice('error', 'File upload failed.');
        return;
    }

    $file = $_FILES['smartboq_file'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'xlsx') {
        smartboq_redirect_notice('error', 'Only .xlsx files are allowed.');
        return;
    }

    // Lock check
    $upload_dir = wp_upload_dir();
    $boq_folder = $upload_dir['basedir'] . '/smartboq-boqs';
    $lock_file = $boq_folder . '/boq.lock';

    if (!file_exists($boq_folder)) {
        wp_mkdir_p($boq_folder);
    }

    if (file_exists($lock_file)) {
        smartboq_redirect_notice('error', 'BOQ upload is locked. Please unlock first.');
        return;
    }

    $filename = $boq_folder . '/uploaded_boq.xlsx';
    if (move_uploaded_file($file['tmp_name'], $filename)) {
        // Create lock
        file_put_contents($lock_file, current_time('mysql'));
        if ($debug) error_log("[SmartBOQ] File uploaded and lock created at " . current_time('mysql'));
        smartboq_redirect_notice('success', 'BOQ uploaded and locked successfully.');
    } else {
        smartboq_redirect_notice('error', 'Failed to move uploaded file.');
    }
}

function smartboq_redirect_notice($status, $message) {
    wp_redirect(admin_url('admin.php?page=smartboq-upload&upload_status=' . $status . '&message=' . urlencode($message)));
    exit;
}
