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


require_once('./workers/upload.php');
require_once('./workers/delete.php');
require_once('./pages/adminpage.php');



function godot_game_shortcode($atts) {
    $atts = shortcode_atts([
        'arc_embed' => ''
    ], $atts, 'godot_game');
    
    $gameSlug = sanitize_title($atts['arc_embed']);
    $gameDirectory = WP_CONTENT_DIR . '/godot_games/' . $gameSlug; 

    
    if (strpos($gameSlug, '..') !== false || strpos($gameSlug, '/') !== false) {
        return 'Invalid game path!';
    }

    
    $gameHTMLFound = false;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($gameDirectory, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (strtolower($file->getFilename()) === 'game.html') {
            $gameHTMLFound = true;
            $gamePath = str_replace(WP_CONTENT_DIR, '', $file->getPathname()); 
            break;
        }
    }

    
    if (!$gameHTMLFound) {
        return 'Game does not exist!';
    }

    // reverse-proxy implementation
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


// header mod for coop and coerp

function add_godot_game_headers() {
    if (is_page() && has_shortcode(get_post()->post_content, 'godot_game')) {
        header("Cross-Origin-Embedder-Policy: require-corp");
        header("Cross-Origin-Opener-Policy: same-origin");
    }
}
add_action('template_redirect', 'add_godot_game_headers');


// PHP reverse proxy to circumvent coep

function godot_games_proxy() {
    
    $game_id = isset($_GET['game']) ? sanitize_text_field($_GET['game']) : exit('No game specified');

    
    $file_path = locate_godot_game_file($game_id);

    
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
