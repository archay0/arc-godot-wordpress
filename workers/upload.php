<?php



function godot_game_handle_upload() {
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploadedfile = $_FILES['godot_game_zip'] ?? null;
    if (!$uploadedfile) {
        echo '<p style="color: red;">Select a file buddy.</p>';
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


