<?php
/*
Plugin Name: Arc Godot-Embedder
Plugin URI: https://srujanlok.org/archay-products
Description: Simply upload Godot games to a website and display them via shortcode.
Version: 1.1
Author: Archay Inc.
Author URI: https://srujanlok.org/archay
*/

defined('ABSPATH') or die('Access denied.');

define('GODOT_GAMES_DIR', WP_CONTENT_DIR . '/godot_games/');

register_activation_hook(__FILE__, function() {
    if (!is_dir(GODOT_GAMES_DIR)) {
        mkdir(GODOT_GAMES_DIR, 0777, true);
    }
});

require_once(plugin_dir_path(__FILE__) . 'workers/upload.php');
require_once(plugin_dir_path(__FILE__) . 'workers/delete.php');
require_once(plugin_dir_path(__FILE__) . 'workers/shortcode.php');

add_action('wp_ajax_godot_game_upload', 'godot_game_handle_upload');
add_action('wp_ajax_godot_game_delete', 'godot_game_handle_delete');
add_shortcode('godot_game', 'godot_game_shortcode');


function godot_game_admin_page() {
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Godot Game Embedder</h1>
        <form id="upload-form" method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="game_title">Game Title</label></th>
                    <td><input type="text" id="game_title" name="game_title" required class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="godot_game_zip">Game File (.zip)</label></th>
                    <td><input type="file" id="godot_game_zip" name="godot_game_zip" required /></td>
                </tr>
            </table>
            <progress id="progress-bar" value="0" max="100" style="width: 100%;"></progress>
            <p class="submit">
                <button type="button" id="upload-button" class="button button-primary">Upload Game</button>
            </p>
        </form>
        
        <h2 class="title">Uploaded Games</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Validity</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="games-table">
                <?php
                $games = scandir(GODOT_GAMES_DIR);
                $games = array_diff($games, ['..', '.']);
                foreach ($games as $game) {
                    $game_dir = GODOT_GAMES_DIR . '/' . $game;
                    $index_exists = file_exists($game_dir . '/game.html') ? 'Valid' : 'Invalid';
                    echo "<tr><td>" . esc_html($game) . "</td><td>" . $index_exists . "</td>";
                    echo "<td><button class='button delete-button' data-game='" . esc_attr($game) . "'>Delete</button></td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
    <script>
    document.getElementById("upload-button").addEventListener("click", function() {
        var form = document.getElementById("upload-form");
        var formData = new FormData(form);
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "<?php echo admin_url('admin-ajax.php?action=godot_game_upload'); ?>", true);
        xhr.upload.onprogress = function(event) {
            if (event.lengthComputable) {
                var percentComplete = (event.loaded / event.total) * 100;
                document.getElementById("progress-bar").value = percentComplete;
            }
        };
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert("Upload successful!");
                    var newGameRow = document.createElement("tr");
                    newGameRow.innerHTML = "<td>" + response.game_title + "</td><td>Valid</td><td><button class='button delete-button' data-game='" + response.game_title + "'>Delete</button></td>";
                    document.getElementById("games-table").appendChild(newGameRow);
                    bindDeleteButtons(); // Rebind delete buttons to new elements
                } else {
                    alert("Error: " + response.error);
                }
            } else {
                alert("An error occurred during the upload. Please try again.");
            }
        };
        xhr.send(formData);
    });

    function bindDeleteButtons() {
        document.querySelectorAll('.delete-button').forEach(button => {
    button.addEventListener('click', function() {
        var gameName = this.getAttribute('data-game');
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "<?php echo admin_url('admin-ajax.php'); ?>", true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert("Game deleted successfully");
                    button.parentNode.parentNode.remove();
                } else {
                    alert("Error: " + response.error);
                }
            } else {
                alert("An error occurred while deleting the game.");
            }
        };
        xhr.send("action=godot_game_delete&game_name=" + encodeURIComponent(gameName) + "&_wpnonce=" + "<?php echo wp_create_nonce('godot_game_delete_action'); ?>");
    });
});
    }

    document.addEventListener("DOMContentLoaded", function() {
        bindDeleteButtons();
    });
    </script>
    <?php
}


add_action('admin_menu', function() {
    add_menu_page('Arc Godot', 'Godot Games', 'manage_options', 'godot-game-embedder', 'godot_game_admin_page', 'dashicons-games');
});

// Add headers to ensure proper policy enforcement when Godot games are embedded
add_action('template_redirect', 'add_godot_game_headers');
function add_godot_game_headers() {
    if (is_page() && has_shortcode(get_post()->post_content, 'godot_game')) {
        header("Cross-Origin-Embedder-Policy: require-corp");
        header("Cross-Origin-Opener-Policy: same-origin");
    }
}

// Register a REST API endpoint for serving game files with necessary policies
add_action('rest_api_init', function () {
    register_rest_route('godot/v1', '/game-proxy/', array(
        'methods' => 'GET',
        'callback' => 'godot_game_proxy',
    ));
});

// The callback function for the REST API endpoint to serve game files securely
function godot_game_proxy(WP_REST_Request $request) {
    $game_path = $request->get_param('game_path');
    if (strpos($game_path, '..') !== false) {
        return new WP_REST_Response('Invalid path', 400);
    }
    $full_path = WP_CONTENT_DIR . $game_path;
    if (!file_exists($full_path)) {
        return new WP_REST_Response('File not found', 404);
    }
    $mime_type = mime_content_type($full_path);
    header('Content-Type: ' . $mime_type);
    header("Cross-Origin-Embedder-Policy: require-corp");
    header("Cross-Origin-Opener-Policy: same-origin");
    header("Cross-Origin-Resource-Policy: same-origin");
    readfile($full_path);
    exit;
}