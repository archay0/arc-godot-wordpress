<?php
/*
Plugin Name: Arc Godot-Embedder
Plugin URI: https://srujanlok.org/archay-products
Description: Simply upload Godot games to a website and display them via shortcode.
Version: 1.1
Author: Archay Inc.
Author URI: https://srujanlok.org/archay
*/

defined('ABSPATH') or die('Permission Issue, go back to coding.');

define('GODOT_GAMES_DIR', WP_CONTENT_DIR . '/godot_games/');



register_activation_hook(__FILE__, function() {
    if (!is_dir(GODOT_GAMES_DIR)) {
        mkdir(GODOT_GAMES_DIR, 0777, true);
    }
});

require_once(plugin_dir_path(__FILE__) . 'workers/upload.php');
require_once(plugin_dir_path(__FILE__) . 'workers/delete.php');
require_once(plugin_dir_path(__FILE__) . 'workers/shortcode.php');


// setup code for scripts and shit
add_action('wp_ajax_godot_game_upload', 'godot_game_handle_upload');
add_action('wp_ajax_godot_game_delete', 'godot_game_handle_delete');

// shortcode add to main

add_shortcode('godot_game', 'godot_game_shortcode');

function godot_game_admin_page() {
    echo '<div class="wrap" style="padding: 20px; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 960px; margin: 20px auto;">';
    echo '<h1 style="font-size: 24px; font-weight: bold; color: #333; margin-bottom: 20px;">Godot Game Embedder</h1>';

    echo '<style>
        .godot-form { margin-bottom: 40px; }
        .godot-input, .godot-button { width: 100%; padding: 10px; box-sizing: border-box; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; }
        th { background-color: #f4f4f4; }
    </style>';

    // form
    echo '<form id="upload-form" method="post" enctype="multipart/form-data">';
    echo '<input type="text" name="game_title" id="game_title" placeholder="Game Title" required class="godot-input">';
    echo '<input type="file" name="godot_game_zip" id="godot_game_zip" required class="godot-input">';
    echo '<progress id="progress-bar" value="0" max="100" style="width: 100%;"></progress>'; // Progress bar
    echo '<input type="button" id="upload-button" value="Upload .zip" class="godot-button button button-primary">';
    echo '</form>';

    // List 
    echo '<input type="hidden" id="godot_game_delete_nonce" value="' . wp_create_nonce('godot_game_delete_action') . '">';
    echo '<h2 style="font-size: 20px; color: #555; margin-bottom: 10px;">Uploaded Games</h2>';
    echo '<table id="games-table">';
    echo '<thead><tr><th>Name</th><th>Validity</th><th>Action</th></tr></thead>';
    echo '<tbody>';
    if ($games = scandir(GODOT_GAMES_DIR)) {
        $games = array_diff($games, ['..', '.']);
        if (!empty($games)) {
            echo '<h2 style="font-size: 20px; color: #555; margin-bottom: 10px;">Uploaded Games</h2>';
            echo '<table>';
            echo '<thead><tr><th>Name</th><th>Validity</th><th>Action</th></tr></thead>';
            echo '<tbody>';
            foreach ($games as $game) {
                $game_dir = GODOT_GAMES_DIR . '/' . $game;
                $index_exists = file_exists($game_dir . '/game.html') ? 'Valid' : 'Invalid';
                echo '<tr><td>' . esc_html($game) . '</td><td>' . $index_exists . '</td>';
                echo '<td><form method="post"><input type="hidden" name="game_name" value="' . esc_attr($game) . '">';
                echo '<input type="submit" name="action-del" value="Delete" class="button button-secondary"></form></td></tr>';
            }
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>No games available.</p>';
        }
    }
    echo '</div>';

    // js
    echo '<script>
    document.getElementById("upload-button").addEventListener("click", function() {
        var form = document.getElementById("upload-form");
        var formData = new FormData(form);
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "' . admin_url('admin-ajax.php?action=godot_game_upload') . '", true);

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
                    var table = document.getElementById("games-table").getElementsByTagName("tbody")[0];
                    var newRow = table.insertRow();
                    var nameCell = newRow.insertCell(0);
                    var validityCell = newRow.insertCell(1);
                    var actionCell = newRow.insertCell(2);
                    nameCell.textContent = response.game_title;
                    validityCell.textContent = "Valid";
                    actionCell.innerHTML = \'<button class="delete-button" data-game="\' + response.game_title + \'">Delete</button>\';
                    bindDeleteButtons();
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
        var deleteButtons = document.getElementsByClassName("delete-button");
        var nonce = document.getElementById("godot_game_delete_nonce").value; // Get nonce value
    
        for (var i = 0; i < deleteButtons.length; i++) {
            deleteButtons[i].addEventListener("click", function() {
                var gameName = this.getAttribute("data-game");
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "' . admin_url('admin-ajax.php') . '", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            alert(response.success);
                            var row = document.querySelector("button[data-game=\'" + gameName + "\']").parentNode.parentNode;
                            row.parentNode.removeChild(row);
                        } else {
                            alert("Error: " + response.error);
                        }
                    } else {
                        alert("An error occurred while deleting the game. Please try again.");
                    }
                };
    
                xhr.send("action=godot_game_delete&game_name=" + encodeURIComponent(gameName) + "&_wpnonce=" + encodeURIComponent(nonce));
            });
        }
    }
    
    

    document.addEventListener("DOMContentLoaded", function() {
        bindDeleteButtons();
    });
    </script>';
}



add_action('admin_menu', function() {
    add_menu_page('Arc Godot', 'Godot Games', 'manage_options', 'godot-game-embedder', 'godot_game_admin_page', 'dashicons-games');
});

function add_godot_game_headers() {
    if (is_page() && has_shortcode(get_post()->post_content, 'godot_game')) {
        header("Cross-Origin-Embedder-Policy: require-corp");
        header("Cross-Origin-Opener-Policy: same-origin");
    }
}
add_action('template_redirect', 'add_godot_game_headers');


add_action('rest_api_init', function () {
    register_rest_route('godot/v1', '/game-proxy/', array(
        'methods' => 'GET',
        'callback' => 'godot_game_proxy',
    ));
});

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
    header('Cross-Origin-Embedder-Policy: require-corp');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    readfile($full_path);
    exit;
}