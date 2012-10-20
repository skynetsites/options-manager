<?php
/**
 * Plugin Name: Options Manager
 * Plugin URI: http://www.claudiosmweb.com/
 * Description: Import and export WordPress options using a json file
 * Author: claudiosanches
 * Author URI: http://www.claudiosmweb.com/
 * Version: 1.0
 * License: GPLv2 or later
 * Text Domain: opmanager
 * Domain Path: /languages/
 */

/**
 * Load languages.
 */
add_action( 'plugins_loaded', 'opmanager_load_languages', 0 );

function opmanager_load_languages() {
    load_plugin_textdomain( 'opmanager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

/**
 * Create plugin menu.
 */
function opmanager_add_menu_page() {
    add_options_page( __( 'Options Manager', 'opmanager' ), __( 'Options Manager', 'opmanager' ), 'manage_options', 'opmanager', 'opmanager_page' );
}

add_action( 'admin_menu', 'opmanager_add_menu_page' );

/**
 * Load scripts in back-end.
 */
add_action( 'admin_enqueue_scripts', 'opmanager_admin_scripts' );

function opmanager_admin_scripts() {
    wp_register_style( 'opmanager', plugins_url( 'css/styles.css' , __FILE__ ), array(), null, 'all' );
    wp_enqueue_style( 'opmanager' );
}

/**
 * Create plugin page.
 */
function opmanager_page() {
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
            if ( $current_tab == 'import' ) {
                // Import page.
                opmanager_import_page();
            } else {
                // Export page.
                opmanager_export_page();
            }
        ?>
    </div>
    <?php
}

/**
 * Create export json.
 * @param  array $options $_POST with options.
 */
function opmanager_export_json( $options ) {
    // Generate archive name
    if ( empty( $options['filename'] ) ) {
        $options['filename'] = get_option( 'blogname' );
    }
    $file_name = sanitize_user( $options['filename'] );
    $json_name = str_replace( ' ', '_', $file_name ) . '-' . date( __( 'm-d-Y', 'opmanager' ) );

    // Remove unnecessary items.
    unset( $options['exportop'] );
    unset( $options['filename'] );
    unset( $options['_wpnonce'] );
    unset( $options['_wp_http_referer'] );
    unset( $options['submit'] );

    // Takes values ​​in wp_options to save json.
    foreach ($options as $key => $value) {
        $save_options[$key] = get_option( $key );
    }

    // Create json.
    $json_file = json_encode($save_options);

    // Built the json file.
    ob_clean();
    echo $json_file;
    header( "Content-Type: text/json; charset=" . get_option( 'blog_charset' ) );
    header( "Content-Disposition: attachment; filename=$json_name.json" );
    exit();
}

/**
 * Export page view.
 */
function opmanager_export_page() {
    // Check referer and POST.
    if ( !empty( $_POST ) && $_POST['exportop'] == '1' && check_admin_referer( 'opmanager-export' ) ) {
            opmanager_export_json( $_POST );
        }
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
        <p class="submit">
            <input type="submit" class="button-primary" name="submit" value="<?php _e( 'Generate File', 'opmanager' ); ?>" />
        </p>
    </form>
    <?php
}

/**
 * Import page view and renders the json.
 */
function opmanager_import_page() {
    if ( isset($_FILES['import'] ) && check_admin_referer( 'opmanager-import' ) ) {
        if ( $_FILES['import']['error'] > 0 ) {
            wp_die( __( 'An error has occurred with the file!', 'opmanager' ) );
        } else {
            // Checks the json file.
            $file_name = $_FILES['import']['name'];
            $file_ext = strtolower( end( explode( '.', $file_name ) ) );
            $file_size = $_FILES['import']['size'];

            if ( $file_ext == 'json' && $file_size < 500000 ) {
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
            <?php wp_nonce_field('opmanager-import'); ?>
            <input type="file" name="import" />
            <input type="submit" name="submit" value="<?php _e( 'Restore', 'opmanager' ); ?>" />
        </p>
    </form>
    <?php
}
