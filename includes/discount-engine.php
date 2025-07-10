<?php
// SmartBOQ AI – Discount Engine

add_action('admin_menu', function () {
    add_submenu_page(
        'smartboq-upload',
        'Apply Discount',
        'Apply Discount',
        'manage_options',
        'smartboq-discount',
        'smartboq_discount_page'
    );
});

function smartboq_discount_page() {
    ?>
    <div class="wrap">
        <h2>Apply Global Discount</h2>
        <?php if (isset($_GET['discount_status'])): ?>
            <div class="notice notice-<?php echo esc_attr($_GET['discount_status']); ?> is-dismissible">
                <p><?php echo esc_html($_GET['message']); ?></p>
            </div>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field('smartboq_discount'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Discount Percentage</th>
                    <td><input type="number" name="discount_percent" value="10" min="0" max="100" required> %</td>
                </tr>
            </table>
            <?php submit_button('Apply Discount'); ?>
        </form>
    </div>
    <?php

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('smartboq_discount')) {
        smartboq_apply_discount((float)$_POST['discount_percent']);
    }
}

function smartboq_apply_discount($discount_percent) {
    $debug = get_option('smartboq_debug_discount', false);
    if (!current_user_can('manage_options')) return;

    if (smartboq_get_status() !== 'complete') {
        smartboq_redirect_notice('discount_status', 'error', 'BOQ must be processed before discount.');
        return;
    }

    if ($discount_percent < 0 || $discount_percent > 100) {
        smartboq_redirect_notice('discount_status', 'error', 'Invalid discount %');
        return;
    }

    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/smartboq-boqs/uploaded_boq.xlsx';

    if (!file_exists($file_path)) {
        smartboq_redirect_notice('discount_status', 'error', 'BOQ file not found.');
        return;
    }

    require_once SMBOQ_PATH . 'vendor/autoload.php';
    use PhpOffice\PhpSpreadsheet\IOFactory;

    $spreadsheet = IOFactory::load($file_path);
    $sheet = $spreadsheet->getActiveSheet();
    $highestRow = $sheet->getHighestDataRow();
    $count = 0;

    for ($row = 2; $row <= $highestRow; $row++) {
        $supply = floatval($sheet->getCell("E$row")->getValue());
        $install = floatval($sheet->getCell("F$row")->getValue());

        if ($supply > 0 || $install > 0) {
            $disc_supply = $supply - ($supply * $discount_percent / 100);
            $disc_install = $install - ($install * $discount_percent / 100);

            $sheet->setCellValue("G$row", round($disc_supply, 2)); // Discounted Supply
            $sheet->setCellValue("H$row", round($disc_install, 2)); // Discounted Install
            $count++;

            if ($debug) error_log("[SmartBOQ] Discounted Row $row: Supply=$disc_supply, Install=$disc_install");
        }
    }

    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($file_path);

    smartboq_redirect_notice('discount_status', 'success', "Discount applied to $count items.");
}

function smartboq_redirect_notice($param, $type, $message) {
    wp_redirect(admin_url("admin.php?page=smartboq-discount&{$param}={$type}&message=" . urlencode($message)));
    exit;
}
