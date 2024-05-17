<?php

function godot_game_handle_upload() {
    
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    // ajax
    if (wp_doing_ajax()) {
        header('Content-Type: application/json');

        
        $uploadedfile = $_FILES['godot_game_zip'] ?? null;
        if (!$uploadedfile) {
            echo json_encode(['error' => 'No file selected.']);
            wp_die();
        }

        
        if ($uploadedfile['type'] != 'application/zip') {
            echo json_encode(['error' => 'Invalid file type. Only ZIP files are allowed.']);
            wp_die();
        }

        
        $upload_overrides = ['test_form' => false];
        $game_title = sanitize_title($_POST['game_title']);
        $game_dir = WP_PLUGIN_DIR . '/godot-game-embedder/godot-games/' . $game_title;

       
        if (!is_dir($game_dir) && !mkdir($game_dir, 0777, true) && !is_dir($game_dir)) {
            echo json_encode(['error' => 'Failed to create game directory.']);
            wp_die();
        }

        // zip
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        if ($movefile && !isset($movefile['error'])) {
            $zip = new ZipArchive;
            if ($zip->open($movefile['file']) === TRUE) {
                $zip->extractTo($game_dir);
                $zip->close();

                // verify
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
                    echo json_encode(['success' => 'Game uploaded and unpacked successfully.', 'path' => esc_html($game_dir)]);
                } else {
                    echo json_encode(['error' => 'Game is invalid. \'game.html\' not found in any subdirectories.']);
                }
            } else {
                echo json_encode(['error' => 'Failed to unzip the game.']);
            }
        } else {
            echo json_encode(['error' => $movefile['error'] ?? 'Unknown error occurred.']);
        }
        wp_die();
    }
}
add_action('wp_ajax_godot_game_upload', 'godot_game_handle_upload');