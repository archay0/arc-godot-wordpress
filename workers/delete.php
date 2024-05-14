<?php

function godot_game_handle_delete($game_name) {
    $game_dir = GODOT_GAMES_DIR . '/' . sanitize_title($game_name);
    
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

        echo '<p style="color: green;">Game ' . esc_html($game_name) . ' deleted successfully.</p>';
        
        echo '<script>window.location.href = "' . esc_url(admin_url('admin.php?page=godot-game-embedder')) . '";</script>';
    } else {
        echo '<p style="color: red;">Failed to delete the game. Directory not found.</p>';
    }
}


