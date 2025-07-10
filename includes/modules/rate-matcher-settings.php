<?php
// ❌ Direct access block
if (!defined('ABSPATH')) exit;

// ✅ Submenu for Rate Matcher Settings
add_action('admin_menu', function () {
    add_submenu_page(
        'smartboq_ai_main',
        'Rate Matcher Settings',
        'Field Mapping',
        'manage_options',
        'smartboq_rate_matcher_settings',
        'smartboq_render_rate_matcher_settings_page'
    );
}); 

// ✅ Render Function (Single Declaration)
function smartboq_render_rate_matcher_settings_page() {
    $option_key = 'smartboq_rate_match_fields';

    // ✅ Handle save
    if (isset($_POST['smartboq_save_match_fields'])) {
        check_admin_referer('smartboq_save_match_fields');
        $item_type = sanitize_text_field($_POST['item_type'] ?? '');
        $fields = array_filter(array_map('sanitize_text_field', $_POST['match_fields'] ?? []));

        $existing = get_option($option_key, []);
        $existing[$item_type] = $fields;
        update_option($option_key, $existing);

        echo '<div class="updated"><p>✅ Mapping saved for <strong>' . esc_html($item_type) . '</strong></p></div>';
    }

    // ❌ Handle delete
    if (isset($_GET['delete_item_type'])) {
        $delete = sanitize_text_field($_GET['delete_item_type']);
        $existing = get_option($option_key, []);
        unset($existing[$delete]);
        update_option($option_key, $existing);
        echo '<div class="notice notice-warning"><p>❌ Mapping deleted for <strong>' . esc_html($delete) . '</strong></p></div>';
    }

    // 🔧 UI
    echo '<div class="wrap">';
    echo '<h1>🧩 Rate Matcher Field Configuration</h1>';
    echo '<p>Define which fields should be used for rate matching based on the item type.</p>';

    echo '<form method="post">';
    wp_nonce_field('smartboq_save_match_fields');

    echo '<table class="form-table"><tr>';
    echo '<th scope="row"><label for="item_type">Item Type</label></th>';
    echo '<td><input type="text" id="item_type" name="item_type" class="regular-text" placeholder="e.g. wire / cable" required></td>';
    echo '</tr><tr>';
    echo '<th scope="row"><label for="match_fields">Matching Fields</label></th>';
    echo '<td><div id="match-fields-wrapper">';
    echo '<input type="text" name="match_fields[]" class="regular-text" placeholder="e.g. make">';
    echo '</div>';
    echo '<p><button type="button" class="button" id="add-match-field">➕ Add Field</button></p>';
    echo '</td>';
    echo '</tr></table>';

    echo '<p><input type="submit" name="smartboq_save_match_fields" class="button button-primary" value="💾 Save Mapping"></p>';
    echo '</form>';

    // 📋 Existing mappings
    $mappings = get_option($option_key, []);
    if (!empty($mappings)) {
        echo '<h2>📚 Existing Item Type Mappings</h2>';
        echo '<table class="widefat fixed striped"><thead><tr><th>Item Type</th><th>Matching Fields</th><th>Action</th></tr></thead><tbody>';
        foreach ($mappings as $type => $fields) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($type) . '</strong></td>';
            echo '<td>' . implode(', ', array_map('esc_html', $fields)) . '</td>';
            echo '<td><a href="' . esc_url(admin_url('admin.php?page=smartboq_rate_matcher_settings&delete_item_type=' . urlencode($type))) . '" class="button button-small" onclick="return confirm(\'Delete this mapping?\')">❌ Delete</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div>';

    // 🧠 JS for dynamic field addition
    echo '<script>
        document.getElementById("add-match-field").addEventListener("click", function() {
            const wrapper = document.getElementById("match-fields-wrapper");
            const input = document.createElement("input");
            input.type = "text";
            input.name = "match_fields[]";
            input.className = "regular-text";
            input.placeholder = "e.g. material";
            wrapper.appendChild(input);
        });
    </script>';
}
