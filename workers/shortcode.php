<?php

function godot_game_shortcode($atts) {
    $atts = shortcode_atts(array(
        'arc_embed' => ''
    ), $atts, 'godot_game');

    $gameSlug = sanitize_title($atts['arc_embed']);
    $gameDirectory = WP_PLUGIN_DIR . '/godot-game-embedder/godot-games/' . $gameSlug;

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
                $gamePath = plugins_url('godot-games/' . $gameSlug . '/' . $file->getFilename(), __FILE__);
                break;
            }
        }
    }

    if (!$gameHTMLFound) {
        return 'Game does not exist!';
    }

    $output = '<div id="godot-game-container" style="width: 100%; height: 80vh; overflow: hidden;">';
    $output .= '<iframe id="godot-game-iframe" src="' . esc_url($gamePath) . '" style="width: 100%; height: 100%; border: none;" allowfullscreen></iframe>';
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
    </script>';
    return $output;
}

