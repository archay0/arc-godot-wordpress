<?php

function godot_game_handle_delete() {
    // Check for the game_name parameter in the AJAX request
    $game_name = isset($_POST['game_name']) ? sanitize_title($_POST['game_name']) : '';

    if (empty($game_name)) {
        echo json_encode(['error' => 'Game name is required.']);
        wp_die();
    }

    $game_dir = GODOT_GAMES_DIR . '/' . $game_name;

    if (is_dir($game_dir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($game_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($game_dir);

        echo json_encode(['success' => 'Game ' . esc_html($game_name) . ' deleted successfully.']);
    } else {
        echo json_encode(['error' => 'Failed to delete the game. Directory not found.']);
    }
    wp_die();
}

