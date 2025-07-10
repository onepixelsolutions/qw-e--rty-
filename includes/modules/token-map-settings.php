<?php
// ❌ Direct access block
if (!defined('ABSPATH')) exit;

// ✅ Submenu for Token Map Settings
add_action('admin_menu', function () {
    add_submenu_page(
        'smartboq_ai_main',
        'Token Map Settings',
        'Token Mapping',
        'manage_options',
        'smartboq_token_map_settings',
        'smartboq_render_token_map_settings_page'
    );
});

// ✅ Render Function
function smartboq_render_token_map_settings_page() {
    $option_key = 'smartboq_token_map';

    // ✅ Handle Save
    if (isset($_POST['smartboq_save_token_map'])) {
        check_admin_referer('smartboq_save_token_map');

        $submitted = $_POST['token_map'] ?? [];
        $sanitized = [];

foreach ($submitted as $field => $tokens) {
    $field_key = smartboq_normalize_header($field);
    $token_array = [];

    // ✅ Check: is field key single word or not
    $is_single_word = (str_word_count(str_replace('_', ' ', $field_key)) === 1);

    foreach ($tokens as $token) {
        $raw_token = trim($token);
        if ($raw_token === '') continue;

        $normalized_token = smartboq_normalize_header($raw_token);

        // ✅ Logic: remove token same as field key only if field key has multiple words
        if (!$is_single_word && $normalized_token === $field_key) {
            continue;
        }

        $token_array[] = $normalized_token;
    }

    if (!empty($token_array)) {
        $sanitized[$field_key] = array_unique($token_array);
    }
}








        update_option($option_key, $sanitized);
        echo '<div class="updated"><p>✅ Token map saved successfully.</p></div>';
    }

    $token_map = get_option($option_key, []);

    echo '<div class="wrap">';
echo '<h1>🧠 Token Map Settings</h1>';
echo '<p>Define token variants for each field (e.g. <code>sqmm</code> → <code>sq mm</code>, <code>sq. mm.</code>, <code>mm2</code> etc.)</p>';

echo '<form method="post">';
wp_nonce_field('smartboq_save_token_map');

echo '<table class="form-table" id="token-map-table">';
foreach ($token_map as $field => $tokens) {
    echo '<tr class="token-row">';
    $display_field = ucwords(str_replace('_', ' ', $field));
  // $display_field = ucwords(preg_replace('/[_\-]+/', ' ', $field));
    echo '<th><input type="text" name="token_map[' . esc_attr($field) . '][]" value="' . esc_attr($field) . '" readonly class="regular-text field-key-input"><br><small>' . esc_html($display_field) . '</small></th>';

    echo '<td>';
    
    // Skip the first token if it matches field key (case insensitive match)
    foreach ($tokens as $token) {
    echo '<input type="text" name="token_map[' . esc_attr($field) . '][]" value="' . esc_attr($token) . '" class="regular-text" style="margin-bottom:5px;">';
}


    echo '<button type="button" class="button add-token">➕</button>';
    echo '<button type="button" class="button remove-row">❌</button>';
    echo '</td></tr>';
}


// ✅ Template row for JS to clone
echo '<tr class="token-row new-row">';
echo '<th><input type="text" name="token_map[__new_field__][]" placeholder="Field Key (e.g. sqmm)" class="regular-text field-key-input"></th>';
echo '<td>';
echo '<input type="text" name="token_map[__new_field__][]" placeholder="e.g. sq mm" class="regular-text" style="margin-bottom:5px;">';
echo '<button type="button" class="button add-token">➕</button>';
echo '<button type="button" class="button remove-row">❌</button>';
echo '</td></tr>';

echo '</table>';

echo '<p><input type="submit" name="smartboq_save_token_map" class="button button-primary" value="💾 Save Token Map"></p>';
echo '</form>';
echo '</div>';


    // ✅ JS for dynamic UI
    echo '<script>
document.addEventListener("DOMContentLoaded", function () {
    const table = document.getElementById("token-map-table");

    // ✅ Rename logic on input event (field key update)
    table.addEventListener("input", function (e) {
        const fieldKeyInput = e.target.closest(".field-key-input");
        if (!fieldKeyInput) return;

        const row = fieldKeyInput.closest("tr");
        const newKey = fieldKeyInput.value.trim().toLowerCase().replace(/\s+/g, "_");

        if (!newKey || !row) return;

        // Update all input names in that row
        const inputs = row.querySelectorAll("input");
        inputs.forEach((input, index) => {
            input.name = `token_map[${newKey}][]`;
        });
    });

    // ✅ Click event for Add/Delete buttons
    table.addEventListener("click", function (e) {
        const removeBtn = e.target.closest(".remove-row");
        const addBtn = e.target.closest(".add-token");

        // ❌ Delete button logic
        if (removeBtn) {
            const row = removeBtn.closest("tr");
            if (row && !row.classList.contains("token-row-initial")) {
                row.remove();
            }
            return;
        }

        // ➕ Add token logic
        if (addBtn) {
            const row = addBtn.closest("tr");
            const fieldKeyInput = row.querySelector(".field-key-input");
            const newKey = fieldKeyInput?.value.trim().toLowerCase().replace(/\s+/g, "_") || "__new_field__";

            const newInput = document.createElement("input");
            newInput.type = "text";
            newInput.className = "regular-text";
            newInput.name = `token_map[${newKey}][]`;
            newInput.style.marginBottom = "5px";

            // Add before the Add/Remove buttons
            const td = addBtn.closest("td");
            td.insertBefore(newInput, addBtn);
        }
    });
});




</script>';
}
