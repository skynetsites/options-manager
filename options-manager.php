<?php
/**
 * Plugin Name: Options Manager
 * Plugin URI: https://github.com/claudiosmweb/options-manager
 * Description: Import and export WordPress options using a json file.
 * Author: claudiosanches
 * Author URI: http://claudiosmweb.com/
 * Version: 1.1
 * License: GPLv2 or later
 * Text Domain: opmanager
 * Domain Path: /languages/
 */

class Options_Manager {

    /**
     * Class construct.
     */
    public function __construct() {

        // Translations.
        add_action( 'plugins_loaded', array( &$this, 'languages' ), 0 );

        // Load export method.
        if ( isset( $_GET['page'] ) && 'opmanager' == $_GET['page'] ) {
            add_action( 'admin_init', array( &$this, 'export_json' ) );
        }

        // Settings page.
        add_action( 'admin_menu', array( &$this, 'add_menu_page' ) );

        // Load scripts.
        add_action( 'admin_enqueue_scripts', array( &$this, 'scripts' ) );
    }

    /**
     * Load languages.
     */
    public function languages() {
        load_plugin_textdomain( 'opmanager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Create plugin menu.
     */
    public function add_menu_page() {
        add_options_page( __( 'Options Manager', 'opmanager' ), __( 'Options Manager', 'opmanager' ), 'manage_options', 'opmanager', array( &$this, 'settings_page' ) );
    }

    /**
     * Load scripts in back-end.
     */
    public function scripts() {
        wp_register_style( 'opmanager', plugins_url( 'css/styles.css' , __FILE__ ), array(), null, 'all' );
        wp_enqueue_style( 'opmanager' );
    }

    /**
     * Create plugin page.
     */
    public function settings_page() {
        // Create tabs current class.
        $current_tab = '';
        if ( isset($_GET['tab'] ) ) {
            $current_tab = $_GET['tab'];
        } else {
            $current_tab = 'options';
        }

        ?>
        <div class="wrap">
            <div class="icon32" id="icon-options-general"><br /></div>
            <h2 class="nav-tab-wrapper">
                <a href="?page=opmanager&amp;tab=options" class="nav-tab <?php echo $current_tab == 'options' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Export', 'opmanager' ); ?></a><a href="?page=opmanager&amp;tab=import" class="nav-tab <?php echo $current_tab == 'import' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Import', 'opmanager' ); ?></a>
            </h2>
            <?php settings_errors(); ?>
            <?php
                if ( 'import' == $current_tab ) {
                    // Import page.
                    $this->import_page();
                } else {
                    // Export page.
                    $this->export_page();
                }
            ?>
        </div>
        <?php
    }

    /**
     * Create export json.
     *
     * @param  array $options $_POST with options.
     *
     * @return json file.
     */
    public function export_json() {

        // Checks tab refer.
        if ( isset( $_GET['tab'] ) && 'import' == $_GET['tab'] ) {
            return false;
        }

        // Checks POST.
        if ( empty( $_POST ) ) {
            return false;
        }

        // Checks the action.
        if ( isset( $_POST['exportop'] ) && 1 != $_POST['exportop'] ) {
            return false;
        }

        // Checks refer.
        if ( ! check_admin_referer( 'opmanager-export' ) ) {
            return false;
        }

        // Generate archive name
        if ( empty( $_POST['filename'] ) ) {
            $_POST['filename'] = get_option( 'blogname' );
        }
        $file_name = sanitize_user( $_POST['filename'] );
        $json_name = str_replace( ' ', '_', $file_name ) . '-' . date( __( 'm-d-Y', 'opmanager' ) );

        // Remove unnecessary items.
        unset( $_POST['exportop'] );
        unset( $_POST['filename'] );
        unset( $_POST['_wpnonce'] );
        unset( $_POST['_wp_http_referer'] );
        unset( $_POST['submit'] );

        // Takes values ​​in wp_options to save json.
        foreach ( $_POST as $key => $value ) {
            $save_options[$key] = get_option( $key );
        }

        // Create json.
        $json_file = json_encode( $save_options );

        // Built the json file.
        @ob_clean();
        echo $json_file;
        header( "Content-Type: text/json; charset=" . get_option( 'blog_charset' ) );
        header( "Content-Disposition: attachment; filename=$json_name.json" );
        exit();
    }

    /**
     * Export page view.
     */
    protected function export_page() {
        ?>
        <form id="opmanager-export" action="#" method="post">
            <p><?php _e( 'Select the options you want to save:', 'opmanager' ) ?></p>
            <div class="available-options">
                <?php
                    // Load all wp_options.
                    $options = wp_load_alloptions();
                    // Built the checkboxs.
                    foreach ( $options as $key => $value ) {
                        echo '<label><input type="checkbox" name="' . $key . '" value="1" /> ' . $key .'</label>';
                    }
                ?>
            </div>
            <p>
                <label for="filename"><?php _e( 'File name:', 'opmanager' ); ?></label>
                <input type="text" id="filename" class="regular-text" name="filename" />
            </p>
            <input type="hidden" name="exportop" value="1" />
            <?php wp_nonce_field( 'opmanager-export' ); ?>

            <?php submit_button( __( 'Generate File', 'opmanager' ), 'primary' ); ?>
        </form>
        <?php
    }

    /**
     * Import page view and renders the json.
     */
    protected function import_page() {
        if ( isset( $_FILES['import'] ) && check_admin_referer( 'opmanager-import' ) ) {
            if ( $_FILES['import']['error'] > 0 ) {
                wp_die( __( 'An error has occurred with the file!', 'opmanager' ) );
            } else {
                // Checks the json file.
                $file_name = $_FILES['import']['name'];
                $file_ext = strtolower( end( explode( '.', $file_name ) ) );
                $file_size = $_FILES['import']['size'];

                if ( 'json' == $file_ext && $file_size < 500000 ) {
                    $encode_options = file_get_contents( $_FILES['import']['tmp_name'] );
                    // Decodes the information.
                    $options = json_decode( $encode_options, true );

                    // Saves the options in wp_options.
                    foreach ( $options as $key => $value ) {
                        update_option( $key, $value );
                    }
                    echo '<div class="updated"><p>' . __( 'All options are restored successfully.', 'opmanager' ) . '</p></div>';
                } else {
                    echo '<div class="error"><p>' . __( 'Invalid file or file size too big.', 'opmanager' ) . '</p></div>';
                }
            }
        }
        ?>
        <form method="post" enctype="multipart/form-data" action="#">
            <p><?php _e( 'Select a json backup file valid:', 'opmanager' ); ?></p>
            <p class="submit">
                <?php wp_nonce_field( 'opmanager-import' ); ?>
                <input type="file" name="import" />
                <?php submit_button( __( 'Restore', 'opmanager' ), 'secondary', 'submit', false ); ?>
            </p>
        </form>
        <?php
    }

} // close Options_Manager class.

$opmanager = new Options_Manager;
