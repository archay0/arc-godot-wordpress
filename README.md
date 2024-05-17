# arc-godot-wordpress
A plugin that allows you to play Godot games on Wordpress as shortcode. Aww no need to thank me!

# .htaccess changes and enabling the Game to use computer resources

Currently, you need to modify the server configuration on Apache or Ngnix to be able to use device resources to run the games.

# Apache - modify the .htaccess file if you have access to your server files

```
<IfModule mod_headers.c>
    Header set Cross-Origin-Embedder-Policy "require-corp"
    Header set Cross-Origin-Opener-Policy "same-origin"
    Header set Cross-Origin-Resource-Policy "same-origin"
</IfModule>
AddType application/javascript .js
AddType application/wasm .wasm
```

# Ngnix - modify your server block

```
location /wp-content/plugins/godot-game-embedder/godot-games/ {
    add_header Cross-Origin-Embedder-Policy "require-corp";
    add_header Cross-Origin-Opener-Policy "same-origin";
    add_header Cross-Origin-Resource-Policy "same-origin";
    types {
        application/javascript js;
        application/wasm wasm;
    }
}
```

If you have unexpected results during the use of plugin, please revert back to your backup. The software does not attempt to change anything within the site and tries to run in a self contained environment. If you think you have a suggestion, please feel free to reach out.