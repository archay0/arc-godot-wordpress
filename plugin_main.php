<?php
/*
Plugin Name: Arc Godot-Embedder
Plugin URI: https://srujanlok.org/archay-products
Description: Simply upload Godot games to a website and display them via shortcode.
Version: 1.1
Author: Archay Inc.
Author URI: https://srujanlok.org/archay
*/

defined('ABSPATH') or die('Access denied!');

define('GODOT_GAMES_DIR', WP_CONTENT_DIR . '/godot_games/');



register_activation_hook(__FILE__, function() {
    if (!is_dir(GODOT_GAMES_DIR)) {
        mkdir(GODOT_GAMES_DIR, 0777, true);
    }
});

require_once(plugin_dir_path(__FILE__) . 'workers/upload.php');
require_once(plugin_dir_path(__FILE__) . 'workers/delete.php');

// setup code for scripts and shit
add_action('wp_ajax_godot_game_upload', 'godot_game_handle_upload');


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

    // Upload form
    echo '<form id="upload-form" method="post" enctype="multipart/form-data">';
    echo '<input type="text" name="game_title" id="game_title" placeholder="Game Title" required class="godot-input">';
    echo '<input type="file" name="godot_game_zip" id="godot_game_zip" required class="godot-input">';
    echo '<progress id="progress-bar" value="0" max="100" style="width: 100%;"></progress>'; // Progress bar
    echo '<input type="button" id="upload-button" value="Upload .zip" class="godot-button button button-primary">';
    echo '</form>';

    // List of games
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
                } else {
                    alert("Error: " + response.error);
                }
            } else {
                alert("An error occurred during the upload. Please try again.");
            }
        };

        xhr.send(formData);
    });
    </script>';
}


function godot_game_shortcode($atts) {
    $atts = shortcode_atts(array(
        'arc_embed' => ''
    ), $atts, 'godot_game');
    
    $gameSlug = sanitize_title($atts['arc_embed']);
    $gameDirectory = GODOT_GAMES_DIR . $gameSlug; 

    if (strpos($gameSlug, '..') !== false || strpos($gameSlug, '/') !== false) {
        return 'Invalid game path!';
    }

    $gameHTMLFound = false;
    if (is_dir($gameDirectory)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($gameDirectory, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (strtolower($file->getFilename()) === 'game.html') {
                $gameHTMLFound = true;
                $gamePath = str_replace(WP_CONTENT_DIR, '', $file->getPathname()); 
                break;
            }
        }
    }

    if (!$gameHTMLFound) {
        return 'Game does not exist!';
    }

    $proxy_url = home_url('/wp-json/godot/v1/game-proxy/?game_path=' . urlencode($gamePath));

    $output = '<div id="godot-game-container" style="width: 100%; height: 80vh; overflow: hidden;">';
    $output .= '<iframe id="godot-game-iframe" src="' . esc_url($proxy_url) . '" style="width: 100%; height: 100%; border: none;" allowfullscreen></iframe>';
    $output .= '<button onclick="toggleFullScreen();" style="position: absolute; bottom: 10px; right: 10px; z-index: 1000; padding: 5px 10px;">Toggle Fullscreen</button>';
    $output .= '</div>';
    $output .= '<script>
        function toggleFullScreen() {
            var iframe = document.getElementById("godot-game-iframe");
            if (!document.fullscreenElement) {
                iframe.requestFullscreen().catch(err => {
                    alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                });
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }
    </script>';
    return $output;
}
add_shortcode('godot_game', 'godot_game_shortcode');

add_action('admin_menu', function() {
    add_menu_page('Godot Game Embedder', 'Godot Games', 'manage_options', 'godot-game-embedder', 'godot_game_admin_page', 'dashicons-games');
});

function add_godot_game_headers() {
    if (is_page() && has_shortcode(get_post()->post_content, 'godot_game')) {
        header("Cross-Origin-Embedder-Policy: require-corp");
        header("Cross-Origin-Opener-Policy: same-origin");
    }
}
add_action('template_redirect', 'add_godot_game_headers');

function locate_godot_game_file($game_id) {
    $file_path = WP_CONTENT_DIR . '/godot_games/' . $game_id;
    if (!file_exists($file_path)) {
        exit('File not found');
    }
    return $file_path;
}

function godot_games_proxy() {
    $game_path = isset($_GET['game_path']) ? sanitize_text_field($_GET['game_path']) : exit('No game specified');
    $file_path = locate_godot_game_file($game_path);

    $file_info = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $file_info->file($file_path);

    header("Content-Type: $mime_type");
    header("Cross-Origin-Embedder-Policy: require-corp");
    header("Cross-Origin-Opener-Policy: same-origin");

    readfile($file_path);
    exit;
}
add_action('rest_api_init', function () {
    register_rest_route('godot/v1', '/game-proxy/', array(
        'methods' => 'GET',
        'callback' => 'godot_games_proxy',
    ));
});
