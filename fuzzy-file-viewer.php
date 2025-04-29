<?php
/**
 * Plugin Name:      KISS Plugins Fuzzy File Finder Viewer
 * Description:       Scans the /wp-content/uploads/coas/ directory, lists files on an admin page, and provides a shortcode [coas name="filename"] to link to files.
 * Version:           1.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            KISS Plugins / Neochrome Inc.
 * Author URI:        https://kissplugins.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       coas-directory-viewer
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Constants ---
define( 'COAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'COAS_TARGET_DIR_SLUG', 'coas' ); // The subdirectory within wp-content/uploads/
define( 'COAS_CACHE_KEY', 'coas_directory_files' );
define( 'COAS_CACHE_EXPIRATION', 12 * HOUR_IN_SECONDS ); // Cache for 12 hours
define( 'COAS_SIMILARITY_THRESHOLD', 80 ); // Minimum percentage similarity for fuzzy match

// --- Helper Functions ---

/**
 * Normalizes a filename for comparison.
 * Converts to lowercase, removes dashes, underscores, and spaces.
 *
 * @param string $filename The original filename.
 * @return string The normalized filename.
 */
function coas_normalize_filename( $filename ) {
    if ( ! is_string( $filename ) ) {
        return '';
    }
    $filename = strtolower( $filename );
    // Remove file extension for better matching
    $filename = pathinfo($filename, PATHINFO_FILENAME);
    $filename = str_replace( ['-', '_', ' '], '', $filename );
    return $filename;
}

/**
 * Scans the target directory for files.
 *
 * @return array|WP_Error Array of filenames on success, WP_Error on failure.
 */
function coas_scan_directory() {
    $upload_dir = wp_upload_dir();
    // Ensure the base directory path has a trailing slash
    $base_upload_dir = trailingslashit( $upload_dir['basedir'] );
    $target_path = $base_upload_dir . COAS_TARGET_DIR_SLUG;

    if ( ! is_dir( $target_path ) ) {
        // Directory doesn't exist, return empty array, not an error
        // because the shortcode/admin page should still function.
        return [];
        // Alternatively, return new WP_Error('dir_not_found', __('COAS directory does not exist.', 'coas-directory-viewer'));
    }

    if ( ! is_readable( $target_path ) ) {
        return new WP_Error('dir_not_readable', __('COAS directory is not readable.', 'coas-directory-viewer'));
    }

    $files = [];
    try {
        // Use DirectoryIterator for a more robust way to iterate and check for files
        $iterator = new DirectoryIterator($target_path);
        foreach ($iterator as $fileinfo) {
            // Skip dots and directories
            if ($fileinfo->isDot() || $fileinfo->isDir()) {
                continue;
            }
            // Add the filename to the list
            $files[] = $fileinfo->getFilename();
        }
        // Sort files alphabetically
        sort($files);
    } catch (Exception $e) {
        return new WP_Error('scan_failed', __('Failed to scan COAS directory: ', 'coas-directory-viewer') . $e->getMessage());
    }


    return $files;
}

/**
 * Gets the list of files from the cache or scans the directory if the cache is empty/expired.
 *
 * @param bool $force_refresh If true, bypass cache and rescan.
 * @return array|WP_Error Array of filenames or WP_Error.
 */
function get_coas_files( $force_refresh = false ) {
    $cached_files = false; // Default to false

    if ( ! $force_refresh ) {
         $cached_files = get_transient( COAS_CACHE_KEY );
    }

    // If cache is empty, expired, or force_refresh is true
    if ( false === $cached_files ) {
        $files = coas_scan_directory();

        // Only cache successful scans or empty arrays (not WP_Error)
        if ( ! is_wp_error( $files ) ) {
            set_transient( COAS_CACHE_KEY, $files, COAS_CACHE_EXPIRATION );
            return $files; // Return the freshly scanned files
        } else {
            // If scan resulted in an error, return the error but don't cache it.
            // Or, optionally, return the last known good cache if available, though that might be stale.
            // For simplicity, we return the error here.
             return $files;
        }
    }

    // If cache hit and no force_refresh
    return $cached_files;
}


/**
 * Clears the file list cache.
 *
 * @return bool True on success, false on failure.
 */
function clear_coas_cache() {
    return delete_transient( COAS_CACHE_KEY );
}

// --- Admin Settings Page ---

/**
 * Adds the admin menu item.
 */
function coas_add_admin_menu() {
    add_options_page(
        __( 'COAS Viewer Settings', 'coas-directory-viewer' ), // Page title
        __( 'COAS Viewer', 'coas-directory-viewer' ),          // Menu title
        'manage_options',                                      // Capability required
        'coas-viewer-settings',                                // Menu slug
        'coas_settings_page_html'                              // Callback function
    );
}
add_action( 'admin_menu', 'coas_add_admin_menu' );

/**
 * Renders the admin settings page HTML.
 */
function coas_settings_page_html() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $upload_dir = wp_upload_dir();
    // Ensure base paths/URLs have trailing slashes
    $base_upload_dir = trailingslashit( $upload_dir['basedir'] );
    $base_upload_url = trailingslashit( $upload_dir['baseurl'] );

    // Construct the full path and URL to the target directory
    $target_path = $base_upload_dir . COAS_TARGET_DIR_SLUG;
    // *** FIX: Ensure the target URL for linking also has a trailing slash ***
    $target_url = trailingslashit( $base_upload_url . COAS_TARGET_DIR_SLUG );

    $cache_cleared = false;
    $cache_rebuilt = false;

    // Handle cache clearing
    if ( isset( $_POST['coas_clear_cache_nonce'], $_POST['coas_clear_cache'] ) &&
         wp_verify_nonce( sanitize_key( $_POST['coas_clear_cache_nonce'] ), 'coas_clear_cache_action' ) )
    {
        $cleared = clear_coas_cache();
        if ( $cleared ) {
            $cache_cleared = true;
            // Optionally rebuild cache immediately
            get_coas_files( true ); // Force refresh
            $cache_rebuilt = true; // Assume rebuild was triggered
        }
    }

    // Get the list of files (potentially from the just-rebuilt cache)
    $files = get_coas_files();

    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php if ( $cache_cleared ) : ?>
            <div id="message" class="updated notice is-dismissible">
                <p><?php esc_html_e( 'COAS file cache cleared.', 'coas-directory-viewer' ); ?>
                   <?php if ($cache_rebuilt) echo esc_html__( ' Cache rebuilt.', 'coas-directory-viewer' ); ?>
                </p>
            </div>
        <?php endif; ?>

        <p>
            <?php
            /* translators: %s: directory path */
            printf( esc_html__( 'Listing files found in the %s directory.', 'coas-directory-viewer' ), '<code>' . esc_html( trailingslashit( 'wp-content/uploads' ) . COAS_TARGET_DIR_SLUG ) . '</code>' );
            ?>
        </p>
        <p>
             <?php
             /* translators: %s: cache duration */
             printf( esc_html__( 'This list is cached for %s. Use the button below to clear the cache and refresh the list immediately.', 'coas-directory-viewer' ), esc_html( human_time_diff( time(), time() + COAS_CACHE_EXPIRATION ) ) );
             ?>
        </p>

        <form method="post" action="">
            <?php wp_nonce_field( 'coas_clear_cache_action', 'coas_clear_cache_nonce' ); ?>
            <p>
                <button type="submit" name="coas_clear_cache" class="button button-secondary">
                    <?php esc_html_e( 'Clear Cache & Refresh List', 'coas-directory-viewer' ); ?>
                </button>
            </p>
        </form>

        <hr>

        <h2><?php esc_html_e( 'Files Found', 'coas-directory-viewer' ); ?></h2>

        <?php if ( is_wp_error( $files ) ) : ?>
            <div class="error">
                <p><?php echo esc_html( $files->get_error_message() ); ?></p>
            </div>
        <?php elseif ( empty( $files ) ) : ?>
             <?php if ( ! is_dir( $target_path ) ) : ?>
                 <p><em><?php
                    /* translators: %s: directory path */
                    printf( esc_html__( 'The directory %s does not exist. Please create it and upload files.', 'coas-directory-viewer' ), '<code>' . esc_html( trailingslashit( 'wp-content/uploads' ) . COAS_TARGET_DIR_SLUG ) . '</code>' );
                 ?></em></p>
             <?php else : ?>
                 <p><em><?php esc_html_e( 'No files found in the directory.', 'coas-directory-viewer' ); ?></em></p>
             <?php endif; ?>
        <?php else : ?>
            <ul>
                <?php foreach ( $files as $file ) : ?>
                    <?php
                        // *** FIX: $target_url now correctly includes the trailing slash ***
                        $file_url = $target_url . rawurlencode( $file );
                    ?>
                    <li>
                        <a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html( $file ); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

    </div><?php
}

// --- Shortcode ---

/**
 * Handles the [coas] shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML output for the shortcode.
 */
function coas_shortcode_handler( $atts ) {
    // Normalize attribute keys to lowercase
    $atts = array_change_key_case( (array) $atts, CASE_LOWER );

    // Set default attributes and override with user input
    $atts = shortcode_atts(
        [
            'name' => '', // Default value for the 'name' attribute
        ],
        $atts,
        'coas' // Shortcode tag used for filtering
    );

    $search_name = sanitize_text_field( $atts['name'] );

    // Basic validation: Ensure name attribute is provided
    if ( empty( $search_name ) ) {
        return '<em>' . esc_html__( 'Shortcode error: Missing "name" attribute.', 'coas-directory-viewer' ) . '</em>';
    }

    // Get the list of files (use cached version)
    $files = get_coas_files();

    // Handle potential errors during file retrieval
    if ( is_wp_error( $files ) ) {
        // Optionally log the error for admins: error_log('COAS Plugin Error: ' . $files->get_error_message());
        return '<em>' . esc_html__( 'Error retrieving file list.', 'coas-directory-viewer' ) . '</em>';
    }

    // Handle case where directory doesn't exist or is empty
    if ( empty( $files ) ) {
         return '<em>' . esc_html__( 'No files available in the COAS directory.', 'coas-directory-viewer' ) . '</em>';
    }

    $normalized_search_name = coas_normalize_filename( $search_name );
    $best_match_file = null;
    $highest_similarity = 0;

    $upload_dir = wp_upload_dir();
    // Ensure base URL has trailing slash
    $base_upload_url = trailingslashit( $upload_dir['baseurl'] );
    // *** FIX: Ensure the target URL for linking also has a trailing slash ***
    $target_url = trailingslashit( $base_upload_url . COAS_TARGET_DIR_SLUG );

    foreach ( $files as $file ) {
        $normalized_file_name = coas_normalize_filename( $file );
        $similarity_percent = 0;

        // Calculate similarity
        similar_text( $normalized_search_name, $normalized_file_name, $similarity_percent );

        // Check if this is a better match than the current best match
        if ( $similarity_percent >= COAS_SIMILARITY_THRESHOLD && $similarity_percent > $highest_similarity ) {
            $highest_similarity = $similarity_percent;
            $best_match_file = $file;
        }
    }

    // Output the link if a suitable match was found
    if ( $best_match_file !== null ) {
        // *** FIX: $target_url now correctly includes the trailing slash ***
        $file_url = $target_url . rawurlencode( $best_match_file );
        // Use the original filename without extension for the link text for better readability
        $link_text = esc_html( pathinfo( $best_match_file, PATHINFO_FILENAME ) );
        // Fallback if filename extraction fails (e.g., filename has no extension)
        if(empty($link_text)) {
            $link_text = esc_html($best_match_file);
        }

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener">%s</a>',
            esc_url( $file_url ),
            $link_text
        );
    } else {
        // No matching file found
        return '<em>' . esc_html__( 'No matching file found.', 'coas-directory-viewer' ) . '</em>';
    }
}
add_shortcode( 'coas', 'coas_shortcode_handler' );

// --- Plugin Activation/Deactivation (Optional but good practice) ---

/**
 * Runs on plugin activation.
 * Can be used to pre-warm the cache or check directory existence.
 */
function coas_activate() {
    // Optional: Pre-warm the cache on activation
    get_coas_files( true ); // Force refresh on activation
}
register_activation_hook( __FILE__, 'coas_activate' );

/**
 * Runs on plugin deactivation.
 * Clean up options or transients if necessary.
 */
function coas_deactivate() {
    // Clear the cache on deactivation
    clear_coas_cache();
}
register_deactivation_hook( __FILE__, 'coas_deactivate' );

