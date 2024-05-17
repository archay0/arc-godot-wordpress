<?php

function godot_game_shortcode($atts) {
    $atts = shortcode_atts(array(
        'arc_embed' => ''
    ), $atts, 'godot_game');

    $gameSlug = sanitize_title($atts['arc_embed']);
    $gameDirectory = GODOT_GAMES_DIR . '/' . $gameSlug;

    // check dir
    if (strpos($gameSlug, '..') !== false || strpos($gameSlug, '/') !== false) {
        return 'Invalid game path!';
    }

    $gameHTMLFound = false;
    $gamePath = '';

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

    $output = '<div id="godot-game-container" style="width: 100%; height: 80vh; overflow: hidden; position: relative;">';
    $output .= '<iframe id="godot-game-iframe" src="' . esc_url($proxy_url) . '" style="width: 100%; height: 100%; border: none; display: none;" allowfullscreen onload="hideLoadingSpinner();"></iframe>';
    $output .= '<div id="loading-spinner" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; position: absolute; top: 0; left: 0; background-color: #fff;">Loading...</div>';
    $output .= '<button onclick="toggleFullScreen();" style="position: absolute; bottom: 10px; right: 10px; z-index: 1000; padding: 5px 10px;" aria-label="Toggle Fullscreen">Toggle Fullscreen</button>';
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

        function hideLoadingSpinner() {
            document.getElementById("godot-game-iframe").style.display = "block";
            document.getElementById("loading-spinner").style.display = "none";
        }
    </script>';
    return $output;
}