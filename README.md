# arc-godot-wordpress
A plugin that allows you to play Godot games on Wordpress as shortcode. Aww no need to thank me!

# .htaccess changes and enabling the Game to use computer resources

Currently, you need to modify the server configuration on Apache or Ngnix to be able to use device resources to run the games.
There are currently 3 ways to make the plugin run your uploaded godot games. The steps required are listed below.

Apache - modify the .htaccess file if you have access to your server files
<IfModule mod_headers.c>
    Header always set Cross-Origin-Embedder-Policy "require-corp"
    Header always set Cross-Origin-Opener-Policy "same-origin"
</IfModule>
