<?php 

function godot_game_admin_page() {
    echo '<div class="wrap" style="padding: 20px; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
    echo '<h2 style="font-size: 24px; font-weight: 400; color: #555;">Godot Game Embedder</h2>';

    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['action'])) {
            godot_game_handle_upload();
        }
        if (!empty($_POST['action-del'])) {
            godot_game_handle_delete($_POST['game_name']);
        }
    }

    
    echo '<form method="post" action="" action-del="" enctype="multipart/form-data">';
    echo '<input type="text" name="game_title" id="game_title" placeholder="Game Title" required style="width: 100%; margin-bottom: 10px;">';
    echo '<input type="file" name="godot_game_zip" id="godot_game_zip" required style="margin-bottom: 10px;">';
    echo '<input type="submit" name="action" value="Upload .zip" class="button button-primary">';
    echo '</form>';

    
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

