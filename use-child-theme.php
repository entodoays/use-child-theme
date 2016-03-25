<?php
/*
 * Use Child Theme
 * A drop-in to make it easy to use WordPress child themes
 * @version 0.2
 */

defined( 'ABSPATH' ) or exit;

if ( ! class_exists( 'Use_Child_Theme' ) ) {

    class Use_Child_Theme
    {

        public $theme;
        public $child_slug;


        function __construct() {
            add_action( 'admin_init', array( $this, 'admin_init' ) );
        }


        function admin_init() {

            // Exit if unauthorized
            if ( ! current_user_can( 'switch_themes' ) ) {
                return;
            }

            // Exit if dismissed
            if ( false !== get_transient( 'uct_dismiss_notice' ) ) {
                return;
            }

            $this->theme = wp_get_theme();

            // Exit if child theme
            if ( false !== $this->theme->parent() ) {
                return;
            }

            add_action( 'wp_ajax_uct_activate', array( $this, 'activate_child_theme' ) );
            add_action( 'wp_ajax_uct_dismiss', array( $this, 'dismiss_notice' ) );
            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        }


        function admin_notices() {
?>
        <script>
        (function($) {
            $(function() {
                $(document).on('click', '.uct-activate', function() {
                    $.post(ajaxurl, {
                        action: 'uct_activate'
                    }, function(response) {
                        $('.uct-notice p').html(response);
                    });
                });

                $(document).on('click', '.uct-notice .notice-dismiss', function() {
                    $.post(ajaxurl, { action: 'uct_dismiss' });
                });
            });
        })(jQuery);
        </script>

        <div class="notice notice-error uct-notice is-dismissible">
            <p>Please use the <?php echo $this->theme->get( 'Name' ); ?> child theme <a class="uct-activate" href="javascript:;">Activate now &raquo;</a></p>
        </div>
<?php
        }


        function dismiss_notice() {
            set_transient( 'uct_dismiss_notice', 'yes', 86400 );
            exit;
        }


        function has_child_theme() {
            $themes = wp_get_themes();
            $folder_name = $this->theme->get_stylesheet();

            foreach ( $themes as $theme ) {
                if ( $folder_name == $theme->get( 'Template' ) ) {
                    $this->child_slug = $theme->get_stylesheet();
                    return true;
                }
            }

            return false;
        }


        function activate_child_theme() {
            $parent_slug = $this->theme->get_stylesheet();

            // Create child theme
            if ( ! $this->has_child_theme() ) {
                $this->create_child_theme();
                $this->child_slug = $parent_slug . '-child';
            }

            switch_theme( $this->child_slug );

            // Copy customizer settings, widgets, etc.
            $settings = get_option( 'theme_mods_' . $this->child_slug );

            if ( false === $settings ) {
                $parent_settings = get_option( 'theme_mods_' . $parent_slug );
                update_option( 'theme_mods_' . $this->child_slug, $parent_settings );
            }

            wp_die( 'All done!' );
        }


        function create_child_theme() {
            $parent_dir = $this->theme->get_stylesheet_directory();
            $child_dir = $parent_dir . '-child';

            $replacements = array(
                'theme_name' => $this->theme->get( 'Name' ) . ' Child',
                'theme_uri' => $this->theme->get( 'ThemeURI' ),
                'template' => $this->theme->get_stylesheet(),
                'version' => '1.0',
            );

            $css = $this->style_css();
            foreach ( $replacements as $key => $str ) {
                $css = str_replace( '{' . $key . '}', $str, $css );
            }

            if ( wp_mkdir_p( $child_dir ) ) {
                file_put_contents( $child_dir . '/style.css', $css );
                file_put_contents( $child_dir . '/functions.php', $this->functions_php() );

                if ( false !== ( $img = $this->theme->get_screenshot( 'relative' ) ) ) {
                    copy( "$parent_dir/$img", "$child_dir/$img" );
                }
            }
            else {
                wp_die( 'Error: theme folder not writable' );
            }
        }


        /**
         * Generate the style.css contents
         */
        function style_css() {
            ob_start();
?>
/*
Theme Name:     {theme_name}
Theme URI:      {theme_uri}
Template:       {template}
Version:        {version}
*/
<?php
            return ob_get_clean();
        }


        /**
         * Generate the functions.php contents
         */
        function functions_php() {
            ob_start();
?>
<?php echo '<'; ?>?php

function child_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles' );
<?php
            return ob_get_clean();
        }
    }

    new Use_Child_Theme();
}
