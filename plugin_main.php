<?php
/*
Plugin Name: Arc Godot-Embedder
Plugin URI: https://srujanlok.org/archay-products
Description: Simply upload Godot games to a website and display them via shortcode.
Version: 1.0
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

// end setup code
function godot_game_handle_upload() {
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploadedfile = $_FILES['godot_game_zip'] ?? null;
    if (!$uploadedfile) {
        echo '<p style="color: red;">No file selected.</p>';
        return;
    }

    if ($uploadedfile['type'] != 'application/zip') {
        echo '<p style="color: red;">Invalid file type. Only ZIP files are allowed.</p>';
        return;
    }

    $upload_overrides = ['test_form' => false];
    $game_title = sanitize_title($_POST['game_title']);
    $game_dir = GODOT_GAMES_DIR . '/' . $game_title;

    if (!is_dir($game_dir) && !mkdir($game_dir, 0777, true) && !is_dir($game_dir)) {
        echo '<p style="color: red;">Failed to create game directory.</p>';
        return;
    }

    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        $zip = new ZipArchive;
        if ($zip->open($movefile['file']) === TRUE) {
            $zip->extractTo($game_dir);
            $zip->close();
            echo "<p style='color: green;'>Game uploaded and unpacked successfully to " . esc_html($game_dir) . ".</p>";

            $index_found = false;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($game_dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (strtolower($file->getFilename()) === 'game.html') {
                    $index_found = true;
                    $index_path = $file->getPathname();
                    break;
                }
            }

            if ($index_found) {
                echo "<p style='color: green;'>Game is valid.</p>";
            } else {
                echo "<p style='color: red;'>Game is invalid. 'game.html' not found in any subdirectories.</p>";
            }
        } else {
            echo "<p style='color: red;'>Failed to unzip the game.</p>";
        }
    } else {
        echo "<p style='color: red;'>" . esc_html($movefile['error'] ?? 'Unknown error occurred.') . "</p>";
    }
}

// shortcode here
function godot_game_shortcode($atts) {
    $atts = shortcode_atts(array('arc_embed' => ''), $atts, 'godot_game');
    $gameSlug = sanitize_title($atts['arc_embed']);
    $gameDirectory = WP_CONTENT_DIR . '/godot_games/' . $gameSlug; // Correct file system path
    $gameURL = content_url('/godot_games/' . $gameSlug . '/game.html'); // URL to access via web

    // Security check to ensure directory traversal is not possible
    if (strpos($gameSlug, '..') !== false || strpos($gameSlug, '/') !== false) {
        return 'Invalid game path!';
    }

    // Ensure the file exists before trying to embed it
    if (file_exists($gameDirectory . '/game.html')) {
        $output = '<div id="godot-game-container" style="width: 100%; height: 80vh; overflow: hidden;">';
        $output .= '<iframe id="godot-game-iframe" src="' . esc_url($gameURL) . '" style="width: 100%; height: 100%; border: none;" allowfullscreen></iframe>';
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
    } else {
        return 'Game does not exist!';
    }
}
add_shortcode('godot_game', 'godot_game_shortcode');


function godot_game_admin_page() {
    echo '<div class="wrap" style="padding: 20px; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
    echo '<h2 style="font-size: 24px; font-weight: 400; color: #555;">Godot Game Embedder</h2>';

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['action'])) {
            godot_game_handle_upload();
        }
        if (!empty($_POST['action-del'])) {
            godot_game_handle_delete($_POST['game_name']);
        }
    }

    // Form for uploading games
    echo '<form method="post" action="" action-del="" enctype="multipart/form-data">';
    echo '<input type="text" name="game_title" id="game_title" placeholder="Game Title" required style="width: 100%; margin-bottom: 10px;">';
    echo '<input type="file" name="godot_game_zip" id="godot_game_zip" required style="margin-bottom: 10px;">';
    echo '<input type="submit" name="action" value="Upload .zip" class="button button-primary">';
    echo '</form>';

    // List uploaded games
    if ($games = scandir(GODOT_GAMES_DIR)) {
        $games = array_diff($games, ['..', '.']);
        if (!empty($games)) {
            echo '<h3>Uploaded Games</h3><table style="width: 100%; margin-top: 20px; text-align: left;">';
            echo '<tr><th>Name</th><th>Validity</th><th>Action</th></tr>';
            foreach ($games as $game) {
                $game_dir = GODOT_GAMES_DIR . '/' . $game;
                $index_exists = file_exists($game_dir . '/game.html') ? 'Found' : 'Not found';
                echo '<tr><td>' . esc_html($game) . '</td><td>' . $index_exists . '</td>';
                echo '<td><form method="post"><input type="hidden" name="game_name" value="' . esc_attr($game) . '">';
                echo '<button type="submit" name="action-del" value="delete_godot_game" class="button">Delete</button></form></td></tr>';
            }
            echo '</table>';
        } else {
            echo '<p>No games available.</p>';
        }
    }
    echo '</div>';
}

function godot_game_handle_delete($game_name) {
    $game_dir = GODOT_GAMES_DIR . '/' . sanitize_title($game_name);
    
    if (is_dir($game_dir)) {
        // Properly delete directories and files recursively
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($game_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($game_dir);

        echo '<p style="color: green;">Game ' . esc_html($game_name) . ' deleted successfully.</p>';
        // Refresh the page to show the updated list of games
        echo '<script>window.location.href = "' . esc_url(admin_url('admin.php?page=godot-game-embedder')) . '";</script>';
    } else {
        echo '<p style="color: red;">Failed to delete the game. Directory not found.</p>';
    }
}

add_action('admin_menu', function() {
    add_menu_page('Godot Game Embedder', 'Godot Games', 'manage_options', 'godot-game-embedder', 'godot_game_admin_page', 'dashicons-games');
});
