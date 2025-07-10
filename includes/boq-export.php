<?php
// SmartBOQ AI – BOQ Export Module

add_action('admin_menu', function () {
    add_submenu_page(
        'smartboq-upload',
        'Export Final BOQ',
        'Export BOQ',
        'manage_options',
        'smartboq-export',
        'smartboq_export_page'
    );
});

function smartboq_export_page() {
    ?>
    <div class="wrap">
        <h2>Download Final BOQ</h2>
        <?php if (!file_exists(smartboq_get_boq_path())): ?>
            <p><strong style="color:red;">BOQ not found. Please upload and process the BOQ first.</strong></p>
        <?php else: ?>
            <form method="post">
                <?php wp_nonce_field('smartboq_export'); ?>
                <p>Click below to download your processed BOQ with matched rates and discounts.</p>
                <?php submit_button('Download Final BOQ'); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('smartboq_export')) {
        smartboq_handle_export();
    }
}

function smartboq_handle_export() {
    $file_path = smartboq_get_boq_path();
    if (!file_exists($file_path)) {
        wp_die('BOQ file not found.');
    }

    $filename = 'SmartBOQ-Final-' . date('Ymd-His') . '.xlsx';

    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
}

function smartboq_get_boq_path() {
    $upload_dir = wp_upload_dir();
    return $upload_dir['basedir'] . '/smartboq-boqs/uploaded_boq.xlsx';
}
