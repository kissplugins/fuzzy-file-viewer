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


// Exit if accessed directly to prevent direct execution.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Plugin Constants ---

/** @var string Plugin directory path. */
define( 'COAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/** @var string Plugin directory URL. */
define( 'COAS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/** @var string The subdirectory within wp-content/uploads/ where files are stored. */
define( 'COAS_TARGET_DIR_SLUG', 'coas' );

/** @var string The key used for storing the file list in WordPress transients. */
define( 'COAS_CACHE_KEY', 'coas_directory_files' );

/** @var int Cache expiration time in seconds (12 hours). */
define( 'COAS_CACHE_EXPIRATION', 12 * HOUR_IN_SECONDS );

/** @var int Minimum percentage similarity required for a fuzzy match (lowered to 50%). */
define( 'COAS_SIMILARITY_THRESHOLD', 50 );

// --- Helper Functions ---

/**
 * Normalizes a filename for comparison purposes.
 *
 * Converts the filename to lowercase, removes the file extension,
 * and strips out dashes, underscores, and spaces to facilitate
 * more flexible matching.
 *
 * @since 1.0.0
 *
 * @param string $filename The original filename (e.g., "My File_1.pdf").
 * @return string The normalized filename (e.g., "myfile1"). Returns empty string if input is not a string.
 */
function coas_normalize_filename( $filename ) {
    // Ensure input is a string.
    if ( ! is_string( $filename ) ) {
        return '';
    }
    // Convert to lowercase.
    $filename = strtolower( $filename );
    // Remove file extension.
    $filename = pathinfo( $filename, PATHINFO_FILENAME );
    // Remove common separators.
    $filename = str_replace( ['-', '_', ' '], '', $filename );
    return $filename;
}

/**
 * Scans the target COAS directory (/wp-content/uploads/coas/) for files.
 *
 * It retrieves the uploads directory information, constructs the path
 * to the 'coas' subdirectory, and iterates through its contents,
 * returning a sorted list of filenames. It skips directories and dot files (., ..).
 *
 * @since 1.0.0
 *
 * @global array $wp_filesystem WordPress Filesystem object (not directly used here, but relevant context).
 *
 * @return string[]|WP_Error An array of filenames found in the directory, sorted alphabetically.
 * Returns an empty array if the directory doesn't exist.
 * Returns WP_Error if the directory is not readable or if scanning fails.
 */
function coas_scan_directory() {
    // Get WordPress upload directory info.
    $upload_dir = wp_upload_dir();
    // Ensure the base directory path has a trailing slash.
    $base_upload_dir = trailingslashit( $upload_dir['basedir'] );
    // Construct the full path to the target directory.
    $target_path = $base_upload_dir . COAS_TARGET_DIR_SLUG;

    // Check if the target directory exists. If not, return an empty array gracefully.
    if ( ! is_dir( $target_path ) ) {
        return [];
    }

    // Check if the target directory is readable.
    if ( ! is_readable( $target_path ) ) {
        return new WP_Error( 'dir_not_readable', __( 'COAS directory is not readable.', 'coas-directory-viewer' ) );
    }

    $files = [];
    try {
        // Use DirectoryIterator for reliable directory traversal.
        $iterator = new DirectoryIterator( $target_path );
        foreach ( $iterator as $fileinfo ) {
            // Skip dots ('.', '..') and subdirectories.
            if ( $fileinfo->isDot() || $fileinfo->isDir() ) {
                continue;
            }
            // Add the filename to the list.
            $files[] = $fileinfo->getFilename();
        }
        // Sort files alphabetically for consistent display.
        sort( $files );
    } catch ( Exception $e ) {
        // Catch potential exceptions during directory iteration.
        return new WP_Error( 'scan_failed', __( 'Failed to scan COAS directory: ', 'coas-directory-viewer' ) . $e->getMessage() );
    }

    return $files;
}

/**
 * Retrieves the list of files from the COAS directory, utilizing cache.
 *
 * Attempts to fetch the file list from the WordPress transient cache.
 * If the cache is empty, expired, or if $force_refresh is true, it
 * rescans the directory using coas_scan_directory() and updates the cache.
 *
 * @since 1.0.0
 *
 * @param bool $force_refresh Optional. If true, bypasses the cache and forces a directory rescan. Default false.
 * @return string[]|WP_Error An array of filenames or a WP_Error object if scanning failed.
 * The result (if not WP_Error) is cached.
 */
function get_coas_files( $force_refresh = false ) {
    $cached_files = false; // Initialize cache variable.

    // Try fetching from cache unless forcing refresh.
    if ( ! $force_refresh ) {
         $cached_files = get_transient( COAS_CACHE_KEY );
    }

    // If cache is empty/expired or refresh is forced, scan the directory.
    if ( false === $cached_files ) {
        $files = coas_scan_directory();

        // Only cache successful scans or empty arrays (not WP_Error objects).
        if ( ! is_wp_error( $files ) ) {
            set_transient( COAS_CACHE_KEY, $files, COAS_CACHE_EXPIRATION );
            return $files; // Return the freshly scanned files.
        } else {
            // If scanning resulted in an error, return the WP_Error object. Do not cache errors.
             return $files;
        }
    }

    // Return the valid data retrieved from the cache.
    return $cached_files;
}


/**
 * Clears the COAS file list cache (transient).
 *
 * @since 1.0.0
 *
 * @return bool True if the transient was deleted, false otherwise.
 */
function clear_coas_cache() {
    return delete_transient( COAS_CACHE_KEY );
}

// --- Admin Settings Page ---

/**
 * Registers the admin menu page under the 'Settings' menu.
 *
 * @since 1.0.0
 *
 * @action admin_menu
 */
function coas_add_admin_menu() {
    add_options_page(
        __( 'COAS Viewer Settings', 'coas-directory-viewer' ), // Page title displayed in the browser tab.
        __( 'COAS Viewer', 'coas-directory-viewer' ),          // Menu title displayed in the admin sidebar.
        'manage_options',                                      // Capability required to access the page.
        'coas-viewer-settings',                                // Unique menu slug for the page.
        'coas_settings_page_html'                              // Callback function to render the page content.
    );
}
add_action( 'admin_menu', 'coas_add_admin_menu' );

/**
 * Renders the HTML content for the COAS Viewer settings page.
 *
 * Displays the list of files found in the COAS directory, provides information
 * about the target directory and caching, and includes a button to clear the cache.
 * Handles the cache clearing logic via POST request.
 *
 * @since 1.0.0
 *
 * @internal This function is called by WordPress automatically via the add_options_page hook.
 * It outputs HTML directly.
 *
 * @global $_POST Used to check for cache clearing actions.
 */
function coas_settings_page_html() {
    // Security check: Ensure the current user has the required capability.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'coas-directory-viewer' ) );
        return; // Should not be reached due to wp_die.
    }

    // Get upload directory paths and URLs.
    $upload_dir = wp_upload_dir();
    // Ensure base paths/URLs have trailing slashes for correct concatenation.
    $base_upload_dir = trailingslashit( $upload_dir['basedir'] );
    $base_upload_url = trailingslashit( $upload_dir['baseurl'] );

    // Construct the full path and URL to the target COAS directory.
    $target_path = $base_upload_dir . COAS_TARGET_DIR_SLUG;
    $target_url = trailingslashit( $base_upload_url . COAS_TARGET_DIR_SLUG ); // Ensure trailing slash for links.

    $cache_cleared = false; // Flag for displaying cache cleared message.
    $cache_rebuilt = false; // Flag for displaying cache rebuilt message.

    // --- Handle Cache Clearing Action ---
    // Check if the clear cache button was pressed and the nonce is valid.
    if ( isset( $_POST['coas_clear_cache_nonce'], $_POST['coas_clear_cache'] ) &&
         wp_verify_nonce( sanitize_key( $_POST['coas_clear_cache_nonce'] ), 'coas_clear_cache_action' ) )
    {
        // Attempt to clear the cache.
        $cleared = clear_coas_cache();
        if ( $cleared ) {
            $cache_cleared = true;
            // Immediately rebuild the cache after clearing for up-to-date display.
            get_coas_files( true ); // Force refresh.
            $cache_rebuilt = true;
        }
        // Optional: Add an error message if clearing failed.
        // else { add_settings_error(...) }
    }

    // --- Get File List ---
    // Retrieve the list of files (possibly from the just-rebuilt cache).
    $files = get_coas_files();

    // --- Render Page HTML ---
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php // Display success message if cache was cleared. ?>
        <?php if ( $cache_cleared ) : ?>
            <div id="message" class="updated notice is-dismissible">
                <p>
                    <?php esc_html_e( 'COAS file cache cleared.', 'coas-directory-viewer' ); ?>
                    <?php if ( $cache_rebuilt ) echo ' ' . esc_html__( 'Cache rebuilt.', 'coas-directory-viewer' ); ?>
                </p>
            </div>
        <?php endif; ?>

        <p>
            <?php
            /* translators: %s: directory path relative to wp-content */
            printf(
                esc_html__( 'Listing files found in the %s directory.', 'coas-directory-viewer' ),
                '<code>' . esc_html( trailingslashit( 'uploads' ) . COAS_TARGET_DIR_SLUG ) . '</code>'
            );
            ?>
        </p>
        <p>
             <?php
             /* translators: %s: human-readable cache duration */
             printf(
                 esc_html__( 'This list is cached for %s. Use the button below to clear the cache and refresh the list immediately.', 'coas-directory-viewer' ),
                 esc_html( human_time_diff( time(), time() + COAS_CACHE_EXPIRATION ) )
             );
             ?>
        </p>

        <?php // Cache clearing form ?>
        <form method="post" action="">
            <?php // Nonce field for security ?>
            <?php wp_nonce_field( 'coas_clear_cache_action', 'coas_clear_cache_nonce' ); ?>
            <p>
                <button type="submit" name="coas_clear_cache" class="button button-secondary">
                    <?php esc_html_e( 'Clear Cache & Refresh List', 'coas-directory-viewer' ); ?>
                </button>
            </p>
        </form>

        <hr> <?php // Visual separator ?>

        <h2><?php esc_html_e( 'Files Found', 'coas-directory-viewer' ); ?></h2>

        <?php // Display error message if file scanning failed. ?>
        <?php if ( is_wp_error( $files ) ) : ?>
            <div class="error notice is-dismissible">
                <p><strong><?php esc_html_e( 'Error:', 'coas-directory-viewer' ); ?></strong> <?php echo esc_html( $files->get_error_message() ); ?></p>
            </div>
        <?php // Display message if no files were found or directory doesn't exist. ?>
        <?php elseif ( empty( $files ) ) : ?>
             <?php // Check specifically if the directory is missing. ?>
             <?php if ( ! is_dir( $target_path ) ) : ?>
                 <p><em><?php
                    /* translators: %s: directory path relative to wp-content */
                    printf(
                        esc_html__( 'The directory %s does not exist. Please create it and upload files.', 'coas-directory-viewer' ),
                        '<code>' . esc_html( trailingslashit( 'uploads' ) . COAS_TARGET_DIR_SLUG ) . '</code>'
                    );
                 ?></em></p>
             <?php // Directory exists but is empty. ?>
             <?php else : ?>
                 <p><em><?php esc_html_e( 'No files found in the directory.', 'coas-directory-viewer' ); ?></em></p>
             <?php endif; ?>
        <?php // Display the list of files as links. ?>
        <?php else : ?>
            <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ( $files as $file ) : ?>
                    <?php
                        // Construct the full, properly encoded URL for the file link.
                        $file_url = $target_url . rawurlencode( $file );
                    ?>
                    <li>
                        <a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html( $file ); // Display the original filename ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

    </div><?php
}

// --- Shortcode Functionality ---

/**
 * Handles the [coas] shortcode processing.
 *
 * Searches for a file within the COAS directory that fuzzily matches
 * the provided 'name' attribute. If a match meeting the similarity
 * threshold is found, it outputs an HTML link to that file. Otherwise,
 * it displays a "not found" message.
 *
 * @since 1.0.0
 * @since 1.0.2 Lowered similarity threshold to 50%.
 *
 * @param array|string $atts Shortcode attributes. Expected: ['name' => 'search term'].
 * If called without attributes, $atts will be an empty string.
 * @param string|null  $content The content enclosed within the shortcode (not used by this shortcode).
 * @param string       $tag     The shortcode tag name ('coas').
 *
 * @return string HTML output for the shortcode (either a link or a message).
 */
function coas_shortcode_handler( $atts, $content = null, $tag = '' ) {
    // Normalize attribute keys to lowercase for consistency.
    $atts = array_change_key_case( (array) $atts, CASE_LOWER );

    // Define default attributes and merge with user-provided ones.
    $atts = shortcode_atts(
        [
            'name' => '', // Default: empty name attribute.
        ],
        $atts,
        $tag // Pass the tag for potential filtering via 'shortcode_atts_{$tag}' hook.
    );

    // Sanitize the search term provided in the 'name' attribute.
    $search_name = sanitize_text_field( $atts['name'] );

    // --- Input Validation ---
    // Ensure the 'name' attribute was provided and is not empty.
    if ( empty( $search_name ) ) {
        // Return an error message inline if the attribute is missing.
        return '<em class="coas-error">' . esc_html__( 'Shortcode error: Missing "name" attribute.', 'coas-directory-viewer' ) . '</em>';
    }

    // --- File Retrieval ---
    // Get the list of files, preferably from cache.
    $files = get_coas_files();

    // Handle potential errors during file list retrieval (e.g., directory not readable).
    if ( is_wp_error( $files ) ) {
        // Log the error for administrators (optional).
        // error_log( 'COAS Plugin Shortcode Error: Failed to get files - ' . $files->get_error_message() );
        // Return a generic error message to the user.
        return '<em class="coas-error">' . esc_html__( 'Error retrieving file list.', 'coas-directory-viewer' ) . '</em>';
    }

    // Handle case where the COAS directory is empty or doesn't exist.
    if ( empty( $files ) ) {
         return '<em>' . esc_html__( 'No files available in the COAS directory.', 'coas-directory-viewer' ) . '</em>';
    }

    // --- Fuzzy Matching Logic ---
    // Normalize the search term for comparison.
    $normalized_search_name = coas_normalize_filename( $search_name );
    $best_match_file = null; // Stores the filename of the best match found so far.
    $highest_similarity = -1; // Initialize similarity score (-1 ensures any valid score is higher).

    // Get base upload URL for constructing file links.
    $upload_dir = wp_upload_dir();
    $base_upload_url = trailingslashit( $upload_dir['baseurl'] );
    $target_url = trailingslashit( $base_upload_url . COAS_TARGET_DIR_SLUG ); // Ensure trailing slash.

    // Iterate through each file found in the directory.
    foreach ( $files as $file ) {
        // Normalize the current filename for comparison.
        $normalized_file_name = coas_normalize_filename( $file );
        $similarity_percent = 0; // Initialize similarity for this file.

        // Calculate the similarity percentage between the search term and the filename.
        similar_text( $normalized_search_name, $normalized_file_name, $similarity_percent );

        // Check if this file's similarity meets the threshold AND is better than the previous best match.
        if ( $similarity_percent >= COAS_SIMILARITY_THRESHOLD && $similarity_percent > $highest_similarity ) {
            $highest_similarity = $similarity_percent; // Update the highest similarity score.
            $best_match_file = $file; // Update the best matching filename.
        }
    }

    // --- Output Generation ---
    // If a suitable match was found...
    if ( $best_match_file !== null ) {
        // Construct the full, encoded URL to the matched file.
        $file_url = $target_url . rawurlencode( $best_match_file );
        // Use the filename without extension as the link text for better readability.
        $link_text = pathinfo( $best_match_file, PATHINFO_FILENAME );
        // Fallback to the full filename if pathinfo fails (e.g., filename has no extension).
        if ( empty( $link_text ) ) {
            $link_text = $best_match_file;
        }

        // Return the formatted HTML link.
        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="coas-link">%s</a>',
            esc_url( $file_url ),
            esc_html( $link_text )
        );
    } else {
        // If no file met the similarity threshold, return a "not found" message.
        return '<em>' . esc_html__( 'No matching file found.', 'coas-directory-viewer' ) . '</em>';
    }
}
// Register the shortcode handler with WordPress.
add_shortcode( 'coas', 'coas_shortcode_handler' );

// --- Plugin Activation/Deactivation Hooks ---

/**
 * Executes actions when the plugin is activated.
 *
 * Currently used to pre-warm the file list cache by forcing a refresh.
 * This ensures the admin page shows files immediately after activation
 * if the COAS directory already exists and contains files.
 *
 * @since 1.0.0
 *
 * @see register_activation_hook()
 */
function coas_activate() {
    // Force a cache refresh/build on activation.
    get_coas_files( true );
}
register_activation_hook( __FILE__, 'coas_activate' );

/**
 * Executes actions when the plugin is deactivated.
 *
 * Currently used to clean up by clearing the file list cache (transient).
 *
 * @since 1.0.0
 *
 * @see register_deactivation_hook()
 */
function coas_deactivate() {
    // Clear the cache transient on deactivation.
    clear_coas_cache();
}
register_deactivation_hook( __FILE__, 'coas_deactivate' );
