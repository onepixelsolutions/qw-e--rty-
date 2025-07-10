<?php
// ❌ Exit if accessed directly
if (!defined('ABSPATH')) exit;

// ✅ Add submenu for matcher
add_action('admin_menu', function () {
    add_submenu_page(
        'smartboq_ai_main',
        'Run Rate Matcher',
        'Run Matcher',
        'manage_options',
        'smartboq_run_matcher',
        'smartboq_render_run_matcher_page'
    );
});

// ✅ Table Creation Function (called during plugin activation)
if (!function_exists('smartboq_create_validation_table')) {
    function smartboq_create_rate_matcher_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'smartboq_rate_matches';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sheet_name VARCHAR(255),
        description TEXT,
        qty FLOAT,
        rate_supply DECIMAL(10,2),
        rate_install DECIMAL(10,2),
        matched_rate_id BIGINT,
        match_status ENUM('matched', 'unmatched') DEFAULT 'unmatched',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
}

// ✅ Normalize Description for Matching
function smartboq_normalize_description($desc) {
    $desc = strtolower(trim($desc));
    $desc = preg_replace('/\s+/', ' ', $desc);          // Collapse multiple spaces
    $desc = preg_replace('/[^\x20-\x7E]/', '', $desc);  // Remove non-ASCII chars
    return $desc;
}

// ✅ Render UI Page
function smartboq_render_run_matcher_page() {
    echo '<div class="wrap"><h1>🚀 Run Rate Matcher</h1>';

    if (!current_user_can('manage_options')) {
        echo '<div class="notice notice-error"><p>❌ Unauthorized</p></div>';
        return;
    }

    /* if (isset($_POST['run_matcher']) && check_admin_referer('smartboq_run_matcher')) {
        smartboq_run_rate_matcher_logic();
        echo '<div class="updated"><p>✅ Matcher executed. Check stored results below.</p></div>';
    }*/
    if (isset($_POST['run_matcher'])) {
    error_log('✅ Run Matcher button clicked.');

    if (check_admin_referer('smartboq_run_matcher')) {
        error_log('✅ Nonce verified.');
        smartboq_run_rate_matcher_logic();
        echo '<div class="updated"><p>✅ Matcher executed. Check stored results below.</p></div>';
    } else {
        error_log('❌ Nonce verification failed.');
    }
}


    if (isset($_POST['reset_boq_data']) && check_admin_referer('smartboq_reset_boq')) {
        delete_option('smartboq_parsed_excel_rows');
        delete_option('smartboq_matched_items');
        echo '<div class="notice notice-warning"><p>⚠️ Parsed Excel data and matched items deleted.</p></div>';
    }

    echo '<form method="post">';
    wp_nonce_field('smartboq_reset_boq');
    echo '<p><input type="submit" name="reset_boq_data" class="button button-secondary" value="🗑️ Clear Uploaded Data & Matches" onclick="return confirm(\'Delete all BOQ data?\')"></p>';
    echo '</form>';

    echo '<form method="post">';
    wp_nonce_field('smartboq_run_matcher');
    echo '<p><input type="submit" name="run_matcher" class="button button-primary" value="🔍 Run Matching Engine"></p>';
    echo '</form>';

    // ✅ Display Results
    $results = get_option('smartboq_matched_items', []);
    error_log('📦 Displaying Matched Items: ' . count($results));
    if (!empty($results)) {
        echo '<h2>📊 Match Results by Sheet</h2>';

        $grouped = [];
        foreach ($results as $row) {
            $sheet = $row['sheet'] ?: 'Unknown';
            $grouped[$sheet][] = $row;
        }

        echo '<style>
            .smartboq-accordion { border: 1px solid #ccd0d4; border-radius: 5px; margin-bottom: 12px; }
            .smartboq-accordion h3 { margin: 0; padding: 10px 15px; background: #f1f1f1; cursor: pointer; }
            .smartboq-panel { display: none; padding: 10px 15px; border-top: 1px solid #ccd0d4; }
            .smartboq-panel table { width: 100%; }
            .smartboq-panel td, .smartboq-panel th { padding: 6px; }
        </style>
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".smartboq-accordion h3").forEach(header => {
                header.addEventListener("click", function () {
                    this.classList.toggle("open");
                    const panel = this.nextElementSibling;
                    panel.style.display = (panel.style.display === "block") ? "none" : "block";
                });
            });
        });
        </script>';

        $sheet_index = 1;
        foreach ($grouped as $sheet_name => $rows) {
            echo '<div class="smartboq-accordion">';
            echo '<h3>📄 Sheet #' . $sheet_index++ . ': ' . esc_html($sheet_name) . '</h3>';
            echo '<div class="smartboq-panel">';
            echo '<table class="widefat striped"><thead><tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Supply Rate</th>
                    <th>Install Rate</th>
                    <th>Item Type</th>
                    <th>Status</th>
                    <th>Matched Entry ID</th>
                  </tr></thead><tbody>';
            foreach ($rows as $row) {
                $status_color = $row['status'] === 'matched' ? 'green' : 'red';
                echo '<tr>';
                echo '<td>' . esc_html($row['description']) . '</td>';
                echo '<td>' . esc_html($row['qty']) . '</td>';
                echo '<td>' . esc_html($row['rate_supply']) . '</td>';
                echo '<td>' . esc_html($row['rate_install']) . '</td>';
                echo '<td>' . esc_html($row['item_type']) . '</td>';
                echo '<td style="color:' . $status_color . ';">' . esc_html($row['status']) . '</td>';
                echo '<td>' . esc_html($row['matched_entry'] ?? '-') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div></div>';
        }
    } else {
        echo '<p><em>ℹ️ No results to display yet. Click the button above to run matching.</em></p>';
    }

    echo '</div>';
}

// ✅ Core Matching Logic
function smartboq_run_rate_matcher_logic() {
    global $wpdb;

    $parsed_rows = get_option('smartboq_parsed_excel_rows', []);
    $field_mappings = get_option('smartboq_rate_match_fields', []);
    $form_id = 15;

    error_log('🟡 Parsed Rows Count: ' . count($parsed_rows));
    error_log('🟡 Field Mappings: ' . print_r($field_mappings, true));

    if (empty($parsed_rows) || empty($field_mappings)) {
        error_log('🔴 Aborting Matcher: Parsed rows ya field mappings missing hain.');
        return;
    }

    $formidable_entries = FrmEntry::getAll(['it.form_id' => $form_id], '', '', true);
    error_log('🟢 Fetched ' . count($formidable_entries) . ' Formidable entries.');

    $results = [];

    foreach ($parsed_rows as $row_index => $row) {
        $desc = strtolower($row['description']);
        $item_type = smartboq_detect_item_type($desc, array_keys($field_mappings));
        if (!$item_type) {
    error_log("⚠️ No item type detected for: $desc");
}

        $matched_entry = null;

        error_log("🔎 Row #$row_index Description: " . $desc);
        error_log("🔍 Detected Item Type: " . $item_type);

        if ($item_type && isset($field_mappings[$item_type])) {
            $fields = $field_mappings[$item_type];
            foreach ($formidable_entries as $entry) {
                $entry_vals = $entry->metas;
                $all_match = true;

                foreach ($fields as $field_key) {
                  //  $token = smartboq_extract_token($desc, $field_key);
                  //  $entry_val = strtolower(trim($entry_vals[$field_key] ?? ''));
                  $token = smartboq_normalize_description(smartboq_extract_token($desc, $field_key));
$entry_val = smartboq_normalize_description($entry_vals[$field_key] ?? '');
if (!isset($entry_vals[$field_key])) {
    error_log("❌ Entry ID {$entry->id} does not have field key: $field_key");
}


                    if ($token === '' || $entry_val === '' || !str_contains($entry_val, $token)) {
                        $all_match = false;
                        break;
                    }
                }

                if ($all_match) {
                    $matched_entry = $entry->id;
                    break;
                }
            }
        }else {
    error_log("⚠️ No field mappings found for item type: " . $item_type);
}

        $results[] = [
            'sheet'         => $row['sheet'],
            'description'   => $row['description'],
            'qty'           => $row['qty'],
            'rate_supply'   => $row['rate_supply'],
            'rate_install'  => $row['rate_install'],
            'item_type'     => $item_type ?? 'unknown',
            'matched_entry' => $matched_entry,
            'status'        => $matched_entry ? 'matched' : 'unmatched'
        ];
    }

    error_log('✅ Final Results: ' . print_r($results, true));
    update_option('smartboq_matched_items', $results);
}


// ✅ Detect item type based on description tokens
function smartboq_detect_item_type($desc, $item_types) {
    foreach ($item_types as $type) {
        if (str_contains($desc, strtolower($type))) return $type;
    }
    return null;
}

// ✅ Token extractor based on field key
function smartboq_extract_token($desc, $field_key) {
    $desc = strtolower($desc);
   /* $token_map = [
        'make' => ['polycab', 'finolex', 'rr kabel', 'havells'],
        'core' => ['1 core', '2 core', '3 core', '3.5 core', '4 core'],
        'sq. mm.' => ['1.5', '2.5', '4', '6', '10', '16', '25', '35', '50', '70', '95', '120', '150'],
        'material' => ['copper', 'aluminum', 'aluminium'],
        'wire / cable type' => ['armoured', 'unarmoured', 'perforated', 'solid']
    ];*/
$token_map = get_option('smartboq_token_map', []);
if (!isset($token_map[$field_key])) {
    error_log("⚠️ No token map defined for field: $field_key");
    return '';
}

    foreach ($token_map[$field_key] as $token) {
        if (str_contains($desc, strtolower($token))) return strtolower($token);
    }
    return '';
}
