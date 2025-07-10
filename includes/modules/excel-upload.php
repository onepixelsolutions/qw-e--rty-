<?php
// At the top of excel-upload.php
if (!defined('ABSPATH')) exit;

// ✅ Admin Page for Excel Upload
// 🔷 [Feature: Admin Menu Integration] START
add_action('admin_menu', function () {
    add_submenu_page(
        'smartboq_ai_main',
        'Upload BOQ Excel',
        'Upload Excel',
        'manage_options',
        'smartboq_excel_upload',
        'smartboq_render_excel_upload_page'
    );
});
// 🔷 [Feature: Admin Menu Integration] END

// 🔷 [Feature: Validation Table Creation] START
if (!function_exists('smartboq_create_validation_table')) {
    function smartboq_create_validation_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'smartboq_validations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT NOT NULL,
        file_name TEXT NOT NULL,
        validation_data LONGTEXT NOT NULL,
        uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
}
// 🔷 [Feature: Validation Table Creation] END

// 🔷 [Feature: Save Validation Result to DB] START
function smartboq_save_validation_result($user_id, $file_name, $validation_data) {
    global $wpdb;

    if (empty($validation_data)) {
        error_log("❌ Validation data is empty, nothing to save.");
        return false;
    }

    $table = $wpdb->prefix . 'smartboq_validations';

    $result = $wpdb->insert(
        $table,
        [
            'user_id'        => $user_id,
            'file_name'      => $file_name,
            'validation_data'=> maybe_serialize($validation_data),
            'uploaded_at'    => current_time('mysql'),
        ],
        [
            '%d', '%s', '%s', '%s'
        ]
    );
    error_log("🧪 Attempted Insert: user_id = $user_id, file = $file_name");
error_log("🧪 Table = $table");
error_log("🧪 Error: " . $wpdb->last_error);


    if ($result === false) {
        error_log("❌ DB Insert failed: " . $wpdb->last_error);
    } else {
        error_log("✅ Validation data saved for file: $file_name");
    }

    return $result;
}
// 🔷 [Feature: Save Validation Result to DB] END


// 🔷 [Feature: Render Upload Form + Drag-Drop + Preloader] START
function smartboq_render_excel_upload_page() {
    echo '<div class="wrap"><h1>Upload BOQ Excel File</h1>';
    flush();

    // ✅ Preloader HTML – Add this BEFORE the form
    echo '<div id="smartboq-preloader" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.8); z-index: 99999; display: flex; align-items: center; justify-content: center; flex-direction: column;">
        <div style="border: 6px solid #f3f3f3; border-top: 6px solid #3498db; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite;"></div>
        <p style="margin-top:15px; font-weight: bold; font-size: 16px;">Processing file... Please wait.</p>
    </div>
    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>';

// ✅ Handle Form Submission
    if (!empty($_POST['smartboq_excel_upload_nonce']) && wp_verify_nonce($_POST['smartboq_excel_upload_nonce'], 'smartboq_excel_upload')) {
        error_log('📤 Form submitted and nonce verified.');
        smartboq_handle_excel_upload();
        flush();
    }

    // ✅ Form
    echo '<div id="smartboq-dropzone" style="border: 2px dashed #ccc; padding: 30px; text-align: center; margin-bottom: 20px; background: #fdfdfd; cursor: pointer;">
    <p style="margin: 0;">📂 Drag & Drop your Excel (.xlsx) file here or use the form below</p>
</div>

<form method="post" enctype="multipart/form-data" id="smartboq-upload-form">';

    wp_nonce_field('smartboq_excel_upload', 'smartboq_excel_upload_nonce');
    echo '<table class="form-table"><tbody>';
    echo '<tr><th>Select Excel (.xlsx) File</th><td><input type="file" name="smartboq_excel_file" required></td></tr>';
    echo '</tbody></table>';
    echo '<p><input type="submit" class="button button-primary" value="Upload & Validate"></p>';
    echo '</form>';

    // ✅ JS: Show preloader only on form submit
    echo '<script>
        document.addEventListener("DOMContentLoaded", function () {
            const preloader = document.getElementById("smartboq-preloader");
            if (preloader) preloader.style.display = "none";

            const form = document.querySelector("form");
            if (form) {
                form.addEventListener("submit", function () {
                    if (preloader) preloader.style.display = "flex";
                });
            }
        });
        
        document.addEventListener("DOMContentLoaded", function () {
    const dropzone = document.getElementById("smartboq-dropzone");
    const fileInput = document.querySelector(\'input[type="file"]\');

    ["dragenter", "dragover"].forEach(evt => {
        dropzone.addEventListener(evt, e => {
            e.preventDefault();
            dropzone.style.background = "#e8f4ff";
        });
    });

    ["dragleave", "drop"].forEach(evt => {
        dropzone.addEventListener(evt, e => {
            e.preventDefault();
            dropzone.style.background = "#fdfdfd";
        });
    });

    dropzone.addEventListener("drop", function (e) {
        const files = e.dataTransfer.files;
        if (files.length) {
            fileInput.files = files;
        }
    });
});
    </script>';

    echo '</div>';
}
// 🔷 [Feature: Render Upload Form + Drag-Drop + Preloader] END

// 🔷 [Feature: Handle File Upload & Storage] START
function smartboq_handle_excel_upload() {
    if (!current_user_can('manage_options')) {
        error_log('❌ User lacks permission.');
        return;
    }

    error_log('📥 Upload handler started.');

    if (isset($_FILES['smartboq_excel_file']) && $_FILES['smartboq_excel_file']['error'] === 0) {
        $uploaded_file = $_FILES['smartboq_excel_file'];
        $original_name = $uploaded_file['name'] ?? ''; // ✅ moved here

        error_log('🧾 File detected: ' . $original_name);
        $filetype = wp_check_filetype($original_name);

        // ✅ File validation (.xlsx)
        if ($filetype['ext'] !== 'xlsx') {
            smartboq_add_admin_notice('❌ Only .xlsx files are allowed.', 'error');
            error_log('❌ Invalid file type.');
            return;
        }

        // ✅ Check file size (optional, as per your note)
        if ($uploaded_file['size'] > 10 * 1024 * 1024) {
            smartboq_add_admin_notice('❌ File too large. Max allowed size is 10MB.', 'error');
            error_log('❌ File size exceeds limit.');
            return;
        }

// ✅ Move file to /uploads/smartboq/
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/smartboq/';
        if (!file_exists($target_dir)) wp_mkdir_p($target_dir);

        $filename = 'boq_upload_' . time() . '.xlsx';
        $target_file = $target_dir . $filename;

    if (move_uploaded_file($uploaded_file['tmp_name'], $target_file)) {
    update_option('smartboq_last_uploaded_file', $target_file);
    smartboq_add_admin_notice('✅ File uploaded: ' . $filename);
    error_log('✅ File moved to: ' . $target_file);

    // ✅ Defensive check
    if (!empty($target_file) && is_string($target_file) && file_exists($target_file)) {
        error_log('📁 Valid file path. Proceeding to validate: ' . $target_file);
        smartboq_validate_excel_columns($target_file, $original_name);
    } else {
        smartboq_add_admin_notice('❌ File path invalid after upload.', 'error');
        error_log('❌ Invalid target_file passed to validator. Value: ' . print_r($target_file, true));
    }
} else {
    smartboq_add_admin_notice('❌ Failed to move uploaded file.', 'error');
    error_log('❌ move_uploaded_file FAILED. Temp path: ' . $uploaded_file['tmp_name'] . ' => Target: ' . $target_file);
}


    }
}
// 🔷 [Feature: Handle File Upload & Storage] END

// ✅ Validate Excel Columns
//$original_name = $_FILES['smartboq_excel_file']['name'] ?? ''; // or however you're retrieving the uploaded file

// 🔷 [Feature: Validate Excel Columns + Token Map Matching] START
function smartboq_validate_excel_columns($file_path, $original_filename = '') {
if (empty($file_path) || !is_string($file_path) || !file_exists($file_path)) {
    echo '<div class="notice notice-error"><p>❌ Uploaded file not found or path is invalid.</p></div>';
    error_log('❌ smartboq_validate_excel_columns    () aborted – invalid file path: ' . print_r($file_path, true));
    return;
}


    if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
    echo '<div class="notice notice-error"><p>❌ PhpSpreadsheet library not loaded. Please make sure it is installed via Composer.</p></div>';
    error_log('❌ PhpSpreadsheet class missing.');
    return;
}


    /*$field_variants = [
        'Sr No' => ['sr no', 'sr. no', 's. no', 'serial no', 'serial number', 'sr num', 's number', 'sr.num'],
        'Description' => ['description', 'item description', 'boq description', 'work description', 'desc'],
        'Unit' => ['unit', 'units', 'uom', 'unit of measure', 'measurement unit'],
        'Qty' => ['qty', 'quantity', 'boq qty', 'required qty'],
        'Rate in INR - Supply' => ['rate in INR - supply', 'rate - supply', 'rate supply', 'supply rate', 'inr rate supply'],
        'Amount in INR - Supply' => ['amount in INR - supply', 'amount - supply', 'amount supply', 'supply amount', 'inr amount supply'],
        'Rate in INR - Installation' => ['rate in INR - installation', 'rate - installation', 'rate installation', 'installation rate', 'inr rate installation'],
        'Amount in INR - Installation' => ['amount in INR - installation', 'amount - installation', 'amount installation', 'installation amount', 'inr amount installation']
    ];*/
  $field_variants = get_option('smartboq_token_map', []);
  if (!is_array($field_variants)) {
    error_log("❌ Token map not an array");
    $field_variants = [];
}
error_log("📄 Loaded token map: " . print_r($field_variants, true));
if (empty($field_variants) || !is_array($field_variants)) {
     error_log('⚠️ Using default fallback token map.');
    $field_variants = [
        'Sr No' => ['sr no', 'sr. no', 's. no', 'serial no', 'serial number', 'sr num', 's number', 'sr.num'],
        'Description' => ['description', 'item description', 'boq description', 'work description', 'desc'],
        'Unit' => ['unit', 'units', 'uom', 'unit of measure', 'measurement unit'],
        'Qty' => ['qty', 'quantity', 'boq qty', 'required qty'],
        'Rate in INR - Supply' => ['rate in inr - supply', 'rate - supply', 'rate supply', 'supply rate', 'inr rate supply'],
        'Amount in INR - Supply' => ['amount in inr - supply', 'amount - supply', 'amount supply', 'supply amount', 'inr amount supply'],
        'Rate in INR - Installation' => ['rate in inr - installation', 'rate - installation', 'rate installation', 'installation rate', 'inr rate installation'],
        'Amount in INR - Installation' => ['amount in inr - installation', 'amount - installation', 'amount installation', 'installation amount', 'inr amount installation']
    ];
}else {
    // ✅ Normalize all aliases for safe matching
    foreach ($field_variants as $label => &$aliases) {
        $aliases = array_map('smartboq_normalize_header', $aliases);
    }
    unset($aliases); // break reference
}

/*if (!isset($file_path) || !is_string($file_path) || !file_exists($file_path)) {
    error_log("❌ Excel file not found or invalid: " . print_r($file_path ?? 'NULL', true));
    return;
}*/

    try {
        $current_user_id = get_current_user_id();
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
        $sheets = $spreadsheet->getAllSheets();
        $results = [];
        

// ✅ Minimum required fields (can be updated later from admin or config)
   //     $minimum_required_fields = ['description', 'qua//'];
        
        // ✅ At least one of these should be present in the sheet
$rate_field_keys = array_map('smartboq_normalize_header', [
    'rate in inr - supply',
    'rate in inr - installation'
]);
$normalized_matched = $sheet_match['normalized'] ?? [];
error_log('✅ Normalized Matched: ' . print_r($normalized_matched, true));
error_log("✅ Rate Fields Available: " . json_encode($rate_field_keys));
error_log("✅ Normalized Matched: " . json_encode($normalized_matched));

foreach ($sheets as $sheet) {
    $sheet_name = $sheet->getTitle();
    error_log("🔎 Starting header check for sheet: {$sheet_name}");
    $sheet_data = $sheet->toArray(null, true, true, true);

    list($best_row_index, $flat_headers) = smartboq_detect_best_header_row($sheet_data, $field_variants, 100);
    error_log("✅ Header check completed for sheet: {$sheet_name}");
    $flat_headers = array_unique($flat_headers);

    list($matched, $missing) = smartboq_match_fields($flat_headers, $field_variants);


// ✅ Normalize matched headers to lowercase
$normalized_matched = array_map('smartboq_normalize_header', $matched);


$is_usable = true;

// ✅ Step 1: Required Fields Check
// ✅ New Required Field Logic using token_map
/*$required = ['serial_number','description', 'quantity','rate'];
$matched_required = [];

foreach ($required as $field) {
    $found = false;

    // ✅ Direct match check
    if (in_array($field, $normalized_matched)) {
        $found = true;
    }

    // ✅ Token match
    if (!$found && !empty($field_variants[$field])) {
        foreach ($field_variants[$field] as $token) {
            foreach ($normalized_matched as $header) {
                if (stripos($header, $token) !== false) {
                    $found = true;
                    break 2;
                }
            }
        }
    }

    if ($found) {
        $matched_required[] = $field;
    }
}

// ✅ Mark usable only if all 3 required fields are matched
$is_usable = count($matched_required) === 3;
*/
// ✅ Step 1: Required Fields Check using token map
// ✅ Define required fields (normalized expected labels)
$required_fields = ['serial_number','description', 'quantity','rate'];
$normalized_required = array_map('smartboq_normalize_header', $required_fields);

// ✅ Normalize all matched headers for the sheet
$normalized_matched = array_map('smartboq_normalize_header', $matched);

// ✅ Check if all required normalized fields exist
$required_fields_ok = true;
foreach ($normalized_required as $req_field) {
    if (!in_array($req_field, $normalized_matched)) {
        $required_fields_ok = false;
        break;
    }
}

$is_usable = $required_fields_ok;




// ✅ Debug Logs (optional but helpful)
error_log("🔎 Sheet: $sheet_name");
error_log("Matched: " . print_r($matched, true));
error_log("Normalized: " . print_r($normalized_matched, true));
error_log("Required Fields OK: " . ($is_usable ? '✅' : '❌'));


    $results[] = [
        'sheet' => $sheet_name,
        'matched' => $matched,
        'missing' => $missing,
        'status' => $is_usable ? (empty($missing) ? 'complete' : 'incomplete') : 'skipped'
    ];
}

// 🔷 [Feature: Extract Parsed Rows for Rate Matcher] START
// Called from within validate_excel_columns()
// ✅ Build structured data for Rate Matcher
$parsed_rows = [];

foreach ($sheets as $i => $sheet) {
    // ✅ Skip unusable sheets
    if (isset($results[$i]['status']) && $results[$i]['status'] === 'skipped') {

    continue;
}

    $sheet_name = $sheet->getTitle();
    $sheet_data = $sheet->toArray(null, true, true, true);
    
    // ✅ Build header map
    // Detect header row (first 3 rows max)
    $header_map = [];
    error_log('🔍 Header map: ' . print_r($header_map, true));

    $header_row = $sheet_data[$best_row_index] ?? [];
    error_log("📋 Raw Header Row: " . print_r($header_row, true));
    $normalized = array_map('smartboq_normalize_header', $header_row);
error_log("🧽 Normalized Header Row: " . print_r($normalized, true));
foreach ($header_row as $col => $val) {
    if (!$val || !is_string($val)) continue;
    $val = strtolower(trim(preg_replace('/\s+/', ' ', $val)));
    foreach ($field_variants as $expected => $aliases) {
        if (in_array($val, $aliases) && !isset($header_map[$expected])) {
            $header_map[$expected] = $col;
        }
    }
}


    // If headers found, start collecting rows
    // Find actual header row index
/*$header_row_index = 0;
foreach ($sheet_data as $i => $row) {
    $normalized_row = array_map(function($val) {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $val)));
    }, $row);

    foreach ($field_variants as $expected => $aliases) {
        foreach ($aliases as $alias) {
            if (in_array($alias, $normalized_row)) {
                $header_row_index = $i;
                break 2;
            }
        }
    }
}*/

// Start reading rows after header
$data_rows = array_slice($sheet_data, $header_row_index + 1);

$extracted_rows = smartboq_extract_parsed_rows($sheet_data, $best_row_index, $header_map);
foreach ($extracted_rows as $row) {
    $parsed_rows[] = array_merge(['sheet' => $sheet_name], $row);
}

    }
    // 🔷 [Feature: Extract Parsed Rows for Rate Matcher] END
    
    // 🔷 [Feature: Save Parsed Rows in WP Option + DB] START
    // ✅ Store in WP option for Lite Rate Matcher to consume
if (!empty($parsed_rows)) {
    update_option('smartboq_parsed_excel_rows', $parsed_rows);
    error_log('📄 Parsed rows: ' . print_r($parsed_rows, true));
    smartboq_save_validation_result($current_user_id, $original_filename, $parsed_rows);
}else {
    error_log('⚠️ No parsed rows to save.');
}
// 🔷 [Feature: Save Parsed Rows in WP Option + DB] END

// error_log('📄 Parsed rows: ' . print_r($parsed_rows, true));
// ✅ Save parsed rows to DB table as well
$current_user_id = get_current_user_id();
if (!empty($parsed_rows)) {
    error_log('📄 Parsed rows: ' . print_r($parsed_rows, true));
    smartboq_save_validation_result($current_user_id, $original_filename, $parsed_rows);
}

}catch (Exception $e) {
        echo '<div class="notice notice-error"><p>❌ Excel read error: ' . esc_html($e->getMessage()) . '</p></div>';
        return;
    }




        // --- UI Output ---
        ?>
        <style>
        .sheet-btn.skipped {
    background: #cccccc !important;
    color: #333;
    opacity: 0.7;
    font-style: italic;
}

        html.smartboq-lock, body.smartboq-lock {
    height: 100%;
    overflow: hidden !important;
    position: fixed;
    width: 100%;
}

        body.no-scroll, html.no-scroll {    overflow: hidden !important;}
.was{padding: 20px;background: #f7f7f7;  border-right: 1px solid #ddd;}
.wass{margin-top: 10px; text-align:center; font-weight:bold;position: absolute;bottom: 20px;}
            .smartboq-modal-overlay {
                position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                background: rgba(0, 0, 0, 0.5); display: none;
                align-items: center; justify-content: center;
                z-index: 9999;
            }
            .smartboq-modal {
                background: #fff; width: 850px; max-width: 95%;
                border-radius: 8px; overflow: hidden;
                display: flex; flex-direction: row;
                box-shadow: 0 8px 30px rgba(0,0,0,0.2);
                animation: fadeIn 0.3s ease-in-out;
                height: 90vh;
                position: relative;
            }
            .smartboq-modal-left {
                /*display: flex;  flex-direction: row;  flex-wrap: wrap;  gap: 5px;*/
                width: 350px;overflow-y: auto;height:100%;}
            .smartboq-modal-left button {
                /*width: 100%;display: block;*/
                margin-bottom: 5px;padding: 5px 10px;
                 text-align: left;
                background: #e2e2e2; border: none; border-radius: 4px;
                cursor: pointer;
            }
            .smartboq-modal-left button.active {
                background: #0073aa; color: #fff;
            }
            .smartboq-modal-right {
                flex: 1; padding: 20px;
                overflow-y: auto;
            }
            .smartboq-tag {display: inline-block; padding: 6px 14px;margin: 6px 6px 6px 0;border-radius: 20px; color: #fff;font-size: 13px;}
            .match { background: #28a745; }
            .missing { background: #dc3545; }
            .smartboq-modal-close {position: absolute; top: 12px; right: 20px;font-size: 22px; font-weight: bold;color: #333; cursor: pointer;}
            .button.modalbtn {position: fixed; right: 30px; bottom: 70px;z-index: 1000;}
            .smartboq-modal .file-name {  font-size: 15px;  font-weight: bold;  background: #f0f0f0;  border-radius: 5px;  padding: 5px 10px;  width: fit-content;  box-shadow: 0 0 5px #f0f0f0;  border: 1px solid white;}
.both-wrapper {  border-radius: 5px;  padding: 20px;  border: 1px solid #ececec;  box-shadow: 0 0 57px #f4f4f4;}
.missing-wrapper {  margin-top: 20px;  position: relative;}
.missing-wrapper::before {  content: '';  border: 1px solid #f4f4f4;  width: 100%;  display: inherit;}
.allsheets {  margin-bottom: 10px;  position: relative;  font-size: 20px;}
.allsheets::after {  content: '';  border: 1px solid #f0f0f0;  width: 100%;  display: inherit;  margin-top: 10px;}
.smartboq-modal-overlay {
    transition: all 0.3s ease-in-out;
}

#downloadReportBtn {  position: fixed;  right: 30px;  bottom: 30px;  z-index: 1000;}
@media (max-width: 768px) {
    .smartboq-modal {
        flex-direction: column;
        width: 95%;
        height: 90vh;
    }
.smartboq-modal-scroll-wrapper {
        overflow-x: auto;
        border-bottom: 1px solid #ddd;
    }
    
    .smartboq-modal-left {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        width: max-content;
        padding: 10px;
        height: auto;
        /*overflow-x: auto;
        overflow-y: hidden;
        max-height: none;
        border-bottom: 1px solid #ddd;*/
    }

    .smartboq-modal-left::-webkit-scrollbar {
        height: 6px;
    }
    .smartboq-modal-left::-webkit-scrollbar-thumb {
        background: #bbb;
        border-radius: 4px;
    }

    .smartboq-modal-left button {
        flex: 1 0 0%; /* 2 buttons per row approx */
        min-width: auto;
        padding: 6px 10px;
        font-size: 13px;
        white-space: nowrap;
    }

    .smartboq-modal-right {
        flex: 1;
        padding: 10px;
        overflow-y: auto;
        height: calc(100% - 150px);
    }
    .smartboq-modal-scroll-wrapper::-webkit-scrollbar {
        height: 6px;
    }

    .smartboq-modal-scroll-wrapper::-webkit-scrollbar-thumb {
        background: #aaa;
        border-radius: 3px;
    }
    .allsheets{width:100%;}
    .wass{position:relative;bottom:auto;}
}


            @keyframes fadeIn {                from { opacity: 0; transform: scale(0.9); }
                to { opacity: 1; transform: scale(1); }
            }
        </style>

        <button onclick="document.getElementById('smartboqModalOverlay').style.display='flex'; document.body.classList.add('smartboq-lock'); document.documentElement.classList.add('smartboq-lock');" class="button button-primary modalbtn">View Excel Report</button>
        <button id="downloadReportBtn" class="button">📥 Download Report (JSON)</button>

        <div class="smartboq-modal-overlay" id="smartboqModalOverlay" style="display:flex;">
            <div class="smartboq-modal">
                <div class="was">
                    <div class="allsheets">All Sheets</div>
                <div class="smartboq-modal-scroll-wrapper">
                <div class="smartboq-modal-left">
                    <!--<?php foreach ($results as $i => $result) {
    $icon = '❌'; // default
    $btnClass = '';
    if ($result['status'] === 'complete') {
        $icon = '✅';
    } elseif ($result['status'] === 'skipped') {
        $icon = '⏸️';
        $btnClass = 'skipped';
    }
    ?>
    <button type="button" class="sheet-btn <?php echo $btnClass; ?> <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo esc_attr($i); ?>">
        <?php echo $icon . ' ' . esc_html($result['sheet']); ?>
    </button>
    <?php
} ?>
-->
                    
                    
                    <?php
                    //this feature supposed in advanced version  
                     foreach ($results as $i => $result) {
    $icon = '❌'; // default
    $btnClass = '';
    if ($result['status'] === 'complete') {
        $icon = '✅';
    } elseif ($result['status'] === 'skipped') {
        $icon = '⏸️';
        $btnClass = 'skipped';
    }
    ?>
    <button type="button" class="sheet-btn <?php echo $btnClass; ?> <?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo esc_attr($i); ?>">
        <?php echo $icon . ' ' . esc_html($result['sheet']); ?>
    </button>
    <?php
} ?>


                </div>
                </div>
                <?php
        //this feature supposed in advanced version 
$total = count($results);
$complete = count(array_filter($results, fn($r) => $r['status'] === 'complete'));
$incomplete = $total - $complete;
$skipped = count(array_filter($results, fn($r) => $r['status'] === 'skipped'));
echo "<div class='wass'>🧾 Total: $total | ✅ Complete: $complete | ❌<br> Incomplete: $incomplete | ⏸️ Skipped: $skipped</div>";

?>
                </div>
                <div class="smartboq-modal-right" id="sheet-details">
                    <!-- Right panel content injected via JS -->
                </div>
                <div class="smartboq-modal-close" onclick="document.getElementById('smartboqModalOverlay').style.display='none'; document.body.classList.remove('smartboq-lock'); document.documentElement.classList.remove('smartboq-lock');">&times;</div>
            </div>
        </div>
        

        <?php
        //this feature supposed in advanced version 
$total = count($results);
$complete = count(array_filter($results, fn($r) => $r['status'] === 'complete'));
$incomplete = $total - $complete;
echo "<div class='wass'>🧾 Total Sheets: $total | ✅ Complete: $complete | ❌ Incomplete: $incomplete</div>";
?>


        <script>
            const overlay = document.getElementById('smartboqModalOverlay');
if (overlay && overlay.style.display === 'flex') {
    document.body.classList.add('smartboq-lock');
    document.documentElement.classList.add('smartboq-lock');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('smartboqModalOverlay').style.display = 'none';
        document.body.classList.remove('no-scroll');
        document.documentElement.classList.remove('no-scroll');
    }
});


            const sheetButtons = document.querySelectorAll('.sheet-btn');
            const sheetDetails = document.getElementById('sheet-details');
            const modalData = <?php echo json_encode($results); ?>;
            const fileName = <?php echo json_encode($original_filename); ?>;

function renderSheet(index) {
    const data = modalData[index];
    let html = `<p class="file-name"><strong>📁 File:</strong> ${fileName}</p>`;
    html += `<h2 class="summary">Sheet: ${data.sheet}</h2>`;
    if (data.status === 'skipped') {
    html += `<div class="both-wrapper"><div style="padding: 15px; border: 1px dashed #ccc; background: #fcfcfc; margin-bottom: 20px; color: #777;">
        ⏸️ This sheet was <strong>skipped</strong> because it did not contain the minimum required columns (Serial Number, Description, Quantity, and Rate).
    </div>`;
}
html += `<div class="both-wrapper"><div class="matched-wrapper"><p class="matched-headers"><strong>✅ Matched Headers:</strong></p>`;


    if (data.matched.length > 0) {
        data.matched.forEach(f => {
            const clean = f.replace(/_/g, ' ');
            html += `<span class="smartboq-tag match">${clean}</span>`;
        });
    } else {
        html += `<p style="margin: 10px 0; color: #555;">😕 Nothing matched. Maybe check column names?</p>`;
    }

    html += `</div>`;
    html += `<div class="missing-wrapper"><p class="missing-headers"><strong>❌ Missing Headers:</strong></p>`;

    if (data.missing.length > 0) {
        data.missing.forEach(f => {
            const clean = f.replace(/_/g, ' ');
            html += `<span class="smartboq-tag missing">${clean}</span>`;
        });
    } else {
        html += `<p style="margin: 10px 0; color: #555;">🎉 All expected headers found. Great job!</p>`;
    }

    html += `</div></div>`;
    sheetDetails.innerHTML = html;
}




            sheetButtons.forEach((btn, index) => {
                btn.addEventListener('click', () => {
                    sheetButtons.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    renderSheet(index);
                });
            });

            const firstValidIndex = modalData.findIndex(r => r.status !== 'skipped');
if (firstValidIndex !== -1) renderSheet(firstValidIndex);

            
            document.getElementById('downloadReportBtn').addEventListener('click', function () {
    const blob = new Blob([JSON.stringify(modalData, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `smartboq_validation_report_${Date.now()}.json`;
    a.click();
    URL.revokeObjectURL(url);
});

        </script>
        <?php
}
// 🔷 [Feature: Validate Excel Columns + Token Map Matching] END


       /* function normalize_header($header) {
    return strtolower(trim(preg_replace('/[^a-z0-9]/i', '_', $header))); // alphabets and digits only
}
function smartboq_normalize_header($header) {
    return strtolower(trim(preg_replace('/[^a-z0-9]+/', '_', $header)));
}*/





// 🔷 [Feature: Admin Notices Console Log Helper] START
add_action('admin_footer', function () {
    if (!isset($_GET['page']) || $_GET['page'] !== 'smartboq_excel_upload') return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const notices = document.querySelectorAll('.notice');
        notices.forEach(function (notice) {
            let type = 'log';
            if (notice.classList.contains('notice-error')) type = 'error';
            else if (notice.classList.contains('notice-warning')) type = 'warn';
            else if (notice.classList.contains('notice-success') || notice.classList.contains('notice-info')) type = 'info';

            const msg = notice.textContent.trim();
            console[type]('%cSmartBOQ Notice:', 'color: green; font-weight: bold;', msg);
        });
    });
    </script>
    <?php
});
// 🔷 [Feature: Admin Notices Console Log Helper] END

// 🔷 [Feature: Utility – Normalize Header String] START
// 🔧 Normalize Header
function smartboq_normalize_header($val) {
    return strtolower(trim(preg_replace('/\s+/', ' ', is_string($val) ? $val : '')));
}
// 🔷 [Feature: Utility – Normalize Header String] END

/*
function smartboq_normalize_header($val) {
    $val = strtolower(trim(preg_replace('/[^a-z0-9]+/', '_', (string)$val)));
    return trim($val, '_');
}
*/

// 🔷 [Feature: Utility – Detect Best Header Row] START
// 🔧 Detect Best Header Row (up to 100 rows) with improved matching
function smartboq_detect_best_header_row($sheet_data, $field_variants, $max_scan = 100) {
    $best_row_index = -1;
    $max_score = 0;
    $best_normalized_headers = [];

    for ($i = 0; $i < min($max_scan, count($sheet_data)); $i++) {
        $row = $sheet_data[$i];
        if (!is_array($row)) continue;

        $normalized_row = array_map('smartboq_normalize_header', $row);
        $normalized_row = array_filter($normalized_row); // Remove blanks

        $score = 0;
        $matched_aliases = [];

        foreach ($field_variants as $expected => $aliases) {
            foreach ($aliases as $alias) {
                $normalized_alias = smartboq_normalize_header($alias);
                if (in_array($normalized_alias, $normalized_row)) {
                    $score++;
                    $matched_aliases[] = $normalized_alias;
                    break; // ✅ Only one match needed per expected field
                }
            }
        }

        error_log("🔍 Checking row {$i}... matched {$score} tokens.");

        if ($score > $max_score) {
            $max_score = $score;
            $best_row_index = $i;
            $best_normalized_headers = $normalized_row;

            // ✅ Stop further checks if score is good enough (say >= 3)
            if ($score >= 3) {
                error_log("✅ Header found at row {$i} with score {$score} ✔️");
                break;
            }
        }
    }

    if ($best_row_index === -1) {
        error_log("❌ No suitable header row found after scanning {$max_scan} rows.");
    } else {
        error_log("✅ Final header row index: {$best_row_index}, max score: {$max_score}");
    }

    return [$best_row_index, array_unique($best_normalized_headers)];
}
// 🔷 [Feature: Utility – Detect Best Header Row] END


// 🔷 [Feature: Utility – Match Fields Against Token Map] START
// 🔧 Get Matched & Missing Fields
function smartboq_match_fields($flat_headers, $field_variants) {
    $matched = [];
    $missing = [];
$flat_headers_normalized = array_map('smartboq_normalize_header', $flat_headers);
    foreach ($field_variants as $expected => $aliases) {
    $found = false;
    foreach ($aliases as $alias) {
        $normalized_alias = smartboq_normalize_header($alias); // ✅ Normalize alias
        if (in_array($normalized_alias, $flat_headers_normalized)) {
            $found = true;
            break;
        }
    }


        if ($found) {
            $matched[] = $expected;
        } else {
            $missing[] = $expected;
        }
    }

    return [$matched, $missing];
}
// 🔷 [Feature: Utility – Match Fields Against Token Map] END


// 🔷 [Feature: Utility – Extract Parsed Rows From Sheet] START
// 🔧 Extract Parsed Rows From Sheet
//Map row data using token field mapping
function smartboq_extract_parsed_rows($sheet_data, $header_row_index, $header_map) {
    $parsed_rows = [];

    $data_rows = array_slice($sheet_data, $header_row_index + 1);

    foreach ($data_rows as $row) {
      //  $desc = isset($header_map['Description']) ? trim((string) $row[$header_map['Description']] ?? '') : '';
      //  $qty = isset($header_map['Qty']) ? floatval($row[$header_map['Qty']] ?? 0) : 0;
      //  $supply = isset($header_map['Rate in INR - Supply']) ? floatval($row[$header_map['Rate in INR - Supply']] ?? 0) : 0;
      //  $install = isset($header_map['Rate in INR - Installation']) ? floatval($row[$header_map['Rate in INR - Installation']] ?? 0) : 0;
      
      $desc_col = $header_map['Description'] ?? null;
$qty_col = $header_map['Qty'] ?? null;
$supply_col = $header_map['Rate in INR - Supply'] ?? null;
$install_col = $header_map['Rate in INR - Installation'] ?? null;

$desc = $desc_col ? trim((string) $row[$desc_col] ?? '') : '';
$qty = $qty_col ? floatval($row[$qty_col] ?? 0) : 0;
$supply = $supply_col ? floatval($row[$supply_col] ?? 0) : 0;
$install = $install_col ? floatval($row[$install_col] ?? 0) : 0;


        if ($desc !== '' && $qty > 0) {
            $parsed_rows[] = [
                'description' => $desc,
                'qty' => $qty,
                'rate_supply' => $supply,
                'rate_install' => $install
            ];
        }
    }

    return $parsed_rows;
}
// 🔷 [Feature: Utility – Extract Parsed Rows From Sheet] END