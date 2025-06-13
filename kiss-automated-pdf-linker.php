<?php
/**
 * Plugin Name:       KISS Automated PDF Linker
 * Plugin URI:        https://example.com/plugins/kiss-automated-pdf-linker/
 * Description:       Scans selected upload directories for PDF files and provides a shortcode [kiss_pdf name="filename"] to link to them using fuzzy matching.
 * Version:           2.1.1
 * Requires at least: 5.2
 * Requires PHP:      7.4  // Increased requirement due to RecursiveDirectoryIterator usage
 * Author:            KISS / Neochrome, Inc.
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kiss-automated-pdf-linker
 * Domain Path:       /languages
 */

// Exit if accessed directly to prevent direct execution.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ==========================================================================
// 1. Plugin Constants
// ==========================================================================

/** @var string Plugin version. */
define( 'KAPL_VERSION', '2.1.1' );
/** @var string Plugin directory path. */
define( 'KAPL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
/** @var string Plugin directory URL. */
define( 'KAPL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
/** @var string WordPress option name for storing plugin settings. */
define( 'KAPL_SETTINGS_OPTION_NAME', 'kapl_settings' );
/** @var string WordPress option name for storing the PDF index cache. */
define( 'KAPL_INDEX_OPTION_NAME', 'kapl_pdf_index' );
/** @var int Minimum percentage similarity required for a fuzzy match. */
define( 'KAPL_SIMILARITY_THRESHOLD', 50 );
/** @var string The shortcode tag. */
define( 'KAPL_SHORTCODE_TAG', 'kiss_pdf' );
/** @var string The slug for the settings page. */
define( 'KAPL_SETTINGS_SLUG', 'kiss-pdf-linker-settings' );


// ==========================================================================
// 2. Initialization (Hooks)
// ==========================================================================

/**
 * Initializes the plugin, setting up hooks.
 *
 * @since 2.0.0
 */
function kapl_init() {
	// Register Settings API hooks
	add_action( 'admin_init', 'kapl_register_settings' );
	// Add admin menu page
	add_action( 'admin_menu', 'kapl_add_admin_menu' );
	// Register the shortcode
	add_shortcode( KAPL_SHORTCODE_TAG, 'kapl_shortcode_handler' );
    // Add settings link on plugin page
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'kapl_add_settings_link' );
    // Add frontend styles for PDF links
    add_action( 'wp_enqueue_scripts', 'kapl_enqueue_frontend_styles' );
    // Enqueue color picker assets on admin
    add_action( 'admin_enqueue_scripts', 'kapl_enqueue_admin_scripts' );
}
add_action( 'plugins_loaded', 'kapl_init' );


// ==========================================================================
// 3. Settings API Registration & Handling
// ==========================================================================

/**
 * Registers plugin settings using the WordPress Settings API.
 *
 * Defines the setting group, the setting name, and the fields.
 *
 * @since 2.0.0
 * @action admin_init
 */
function kapl_register_settings() {
	// Register the main setting group and option name
	register_setting(
		'kapl_settings_group',              // Option group name
		KAPL_SETTINGS_OPTION_NAME,          // Option name
		'kapl_sanitize_settings'            // Sanitization callback
	);

	// Add settings section for directory selection
	add_settings_section(
		'kapl_directory_selection_section', // Section ID
		__( 'Select Directories to Scan', 'kiss-automated-pdf-linker' ), // Section title
		'kapl_directory_selection_section_callback', // Callback for section description
		KAPL_SETTINGS_SLUG                  // Page slug where section appears
	);

	// Add the field for selecting directories
	add_settings_field(
		'kapl_selected_directories',        // Field ID
		__( 'Scan these folders:', 'kiss-automated-pdf-linker' ), // Field label
		'kapl_selected_directories_field_callback', // Callback to render the field
		KAPL_SETTINGS_SLUG,                 // Page slug
		'kapl_directory_selection_section'  // Section ID where field appears
	);
    
    // Add settings section for appearance customization
    add_settings_section(
        'kapl_appearance_section',          // Section ID
        __( 'PDF Link Appearance', 'kiss-automated-pdf-linker' ), // Section title
        'kapl_appearance_section_callback', // Callback for section description
        KAPL_SETTINGS_SLUG                  // Page slug where section appears
    );
    
    // Add the field for PDF link color
    add_settings_field(
        'kapl_link_color',                  // Field ID
        __( 'PDF Link Color:', 'kiss-automated-pdf-linker' ), // Field label
        'kapl_link_color_field_callback',   // Callback to render the field
        KAPL_SETTINGS_SLUG,                 // Page slug
        'kapl_appearance_section'           // Section ID where field appears
    );

	add_settings_section(
        'kapl_pdf_matching_section',       // Section ID
        __( 'PDF Matching Settings', 'kiss-automated-pdf-linker' ), // Section title
        '', // No callback for section description
        KAPL_SETTINGS_SLUG                  // Page slug where section appears
    );

    // Add the field for using product title for file match
    add_settings_field(
        'kapl_use_product_title_match',     // Field ID
        __( 'Use product title for file match:', 'kiss-automated-pdf-linker' ), // Field label
        'kapl_use_product_title_field_callback', // Callback to render the field
        KAPL_SETTINGS_SLUG,                 // Page slug
        'kapl_pdf_matching_section'           // Section ID where field appears
    );

	
}

/**
 * Sanitizes the plugin settings before saving.
 *
 * Ensures that the selected directories are saved as an array of strings
 * and the link color is a valid hex color.
 *
 * @since 2.0.0
 *
 * @param array|mixed $input The raw input data from the settings form.
 * @return array The sanitized settings array.
 */
function kapl_sanitize_settings( $input ) {
	$sanitized_input = [];

	// Sanitize the selected directories array
	if ( isset( $input['selected_directories'] ) && is_array( $input['selected_directories'] ) ) {
		$sanitized_input['selected_directories'] = array_map( 'sanitize_text_field', $input['selected_directories'] );
        // Further validation could be added here to ensure they are valid directory names within uploads.
	} else {
		$sanitized_input['selected_directories'] = []; // Default to empty array if not set or not an array
	}
    
    // Sanitize the link color (ensure it's a valid hex color)
    if ( isset( $input['link_color'] ) ) {
        // If the value doesn't start with #, add it
        $color = sanitize_hex_color( $input['link_color'] );
        $sanitized_input['link_color'] = $color ? $color : '#0000FF'; // Default to blue if invalid
    } else {
        $sanitized_input['link_color'] = '#0000FF'; // Default color
    }

    // Sanitize the use_product_title_match checkbox
    $sanitized_input['use_product_title_match'] = isset( $input['use_product_title_match'] ) ? true : false;

	// Add sanitization for future settings here...

	return $sanitized_input;
}

/**
 * Callback function to render the description for the directory selection section.
 *
 * @since 2.0.0
 */
function kapl_directory_selection_section_callback() {
	echo '<p>' . esc_html__( 'Check the top-level folders within your uploads directory that you want to scan recursively for PDF files.', 'kiss-automated-pdf-linker' ) . '</p>';
}

/**
 * Callback function to render the checkboxes for directory selection.
 *
 * Scans the root uploads directory for immediate subdirectories and displays them as checkboxes.
 *
 * @since 2.0.0
 */
function kapl_selected_directories_field_callback() {
    $settings      = get_option( KAPL_SETTINGS_OPTION_NAME, [ 'selected_directories' => [] ] );
    $selected_dirs = isset( $settings['selected_directories'] ) && is_array( $settings['selected_directories'] ) ? $settings['selected_directories'] : [];

	$upload_dir_info = wp_upload_dir();
	$uploads_path = $upload_dir_info['basedir'];
	$available_dirs = [];

	if ( is_dir( $uploads_path ) && is_readable( $uploads_path ) ) {
		try {
			$iterator = new DirectoryIterator( $uploads_path );
			foreach ( $iterator as $fileinfo ) {
				// Only include immediate subdirectories, skip dots and files.
				if ( $fileinfo->isDir() && ! $fileinfo->isDot() ) {
					$available_dirs[] = $fileinfo->getFilename();
				}
			}
			sort( $available_dirs ); // Sort alphabetically
		} catch ( Exception $e ) {
			echo '<p class="error">' . esc_html__( 'Error scanning uploads directory: ', 'kiss-automated-pdf-linker' ) . esc_html( $e->getMessage() ) . '</p>';
			return;
		}
	} else {
        echo '<p class="error">' . esc_html__( 'Uploads directory is not readable or does not exist.', 'kiss-automated-pdf-linker' ) . '</p>';
        return;
    }

	if ( empty( $available_dirs ) ) {
		echo '<p>' . esc_html__( 'No subdirectories found directly within the uploads folder.', 'kiss-automated-pdf-linker' ) . '</p>';
		return;
	}

	// Render checkboxes
	echo '<fieldset>';
	foreach ( $available_dirs as $dir_name ) {
		$field_id = 'kapl_dir_' . esc_attr( $dir_name );
		$checked = in_array( $dir_name, $selected_dirs, true ) ? 'checked' : '';
		?>
        <label for="<?php echo esc_attr( $field_id ); ?>">
            <input
                type="checkbox"
                id="<?php echo esc_attr( $field_id ); ?>"
                name="<?php echo esc_attr( KAPL_SETTINGS_OPTION_NAME ); ?>[selected_directories][]"
                value="<?php echo esc_attr( $dir_name ); ?>"
                <?php echo esc_attr( $checked ); ?>
            />
            <code><?php echo esc_html( $dir_name ); ?>/</code>
        </label><br>
        <?php
	}
	echo '</fieldset>';
	echo '<p class="description">' . esc_html__( 'Checking a folder will include all PDFs within it and its subfolders in the index.', 'kiss-automated-pdf-linker' ) . '</p>';
}

/**
 * Callback function to render the description for the appearance section.
 *
 * @since 2.0.0
 */
function kapl_appearance_section_callback() {
    echo '<p>' . esc_html__( 'Customize the appearance of PDF links generated by the shortcode.', 'kiss-automated-pdf-linker' ) . '</p>';
}

/**
 * Callback function to render the color picker field for PDF link color.
 *
 * @since 2.0.0
 */
function kapl_link_color_field_callback() {
    $settings = get_option( KAPL_SETTINGS_OPTION_NAME, ['link_color' => '#0000FF'] );
    $link_color = isset( $settings['link_color'] ) ? $settings['link_color'] : '#0000FF';
    
    ?>
    <input 
        type="text" 
        name="<?php echo esc_attr( KAPL_SETTINGS_OPTION_NAME ); ?>[link_color]" 
        id="kapl_link_color" 
        value="<?php echo esc_attr( $link_color ); ?>" 
        class="kapl-color-picker" 
    />
    <p class="description">
        <?php esc_html_e( 'Choose the color for PDF links. This will be applied to all links with the kapl-pdf-link class.', 'kiss-automated-pdf-linker' ); ?>
    </p>
    <?php
}

/**
 * Callback function to render the checkbox for using product title for file match.
 *
 * @since 2.0.1
 */
function kapl_use_product_title_field_callback() {
    $settings = get_option( KAPL_SETTINGS_OPTION_NAME, ['use_product_title_match' => false] );
    $use_product_title_match = isset( $settings['use_product_title_match'] ) ? (bool) $settings['use_product_title_match'] : false;
    
    ?>
    <label for="kapl_use_product_title_match">
        <input 
            type="checkbox" 
            name="<?php echo esc_attr( KAPL_SETTINGS_OPTION_NAME ); ?>[use_product_title_match]" 
            id="kapl_use_product_title_match" 
            value="1" 
            <?php checked( $use_product_title_match, true ); ?>
        />
        <?php esc_html_e( 'Enable automatic PDF linking for strains in the product tab.', 'kiss-automated-pdf-linker' ); ?>
    </label>
    <p class="description">
        <?php esc_html_e( 'When enabled, this will attempt to automatically link strain names listed in the \'Strains\' product tab to matching PDF files.', 'kiss-automated-pdf-linker' ); ?>
    </p>
    <?php
}

/**
 * Enqueues scripts needed for the color picker on the admin side.
 *
 * @since 2.0.0
 * @param string $hook The current admin page hook suffix.
 */
function kapl_enqueue_admin_scripts( $hook ) {
    // Only load on our settings page
    if ( 'settings_page_' . KAPL_SETTINGS_SLUG !== $hook ) {
        return;
    }
    
    // Enqueue color picker
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    
    // Enqueue our custom script to initialize color picker
    wp_enqueue_script(
        'kapl-admin-script',
        false, // We'll use inline script instead of a separate file
        array( 'wp-color-picker' ),
        KAPL_VERSION,
        true
    );
    
    // Initialize color picker
    wp_add_inline_script( 'kapl-admin-script', '
        jQuery(document).ready(function($) {
            $(".kapl-color-picker").wpColorPicker();
        });
    ' );
}

/**
 * Enqueues the frontend styles for PDF links.
 *
 * @since 2.0.0
 */
function kapl_enqueue_frontend_styles() {
    $settings = get_option( KAPL_SETTINGS_OPTION_NAME, ['link_color' => '#0000FF'] );
    $link_color = isset( $settings['link_color'] ) ? $settings['link_color'] : '#0000FF';
    
    // Add inline CSS for the PDF links
    $custom_css = "
			  .single-product .woocommerce-tabs.accordion-type ul.tabs > li .woocommerce-Tabs-panel a.kapl-pdf-link,
			  .kapl-pdf-link {
            color: {$link_color} !important;
            text-decoration: underline;
        }
				
        .kapl-pdf-link:hover {
            opacity: 0.8;
        }
    ";
    
    wp_register_style( 'kapl-frontend-styles', false ); // Register an empty handle
    wp_enqueue_style( 'kapl-frontend-styles' );
    wp_add_inline_style( 'kapl-frontend-styles', $custom_css );
}


// ==========================================================================
// 4. Admin Settings Page Rendering
// ==========================================================================

/**
 * Adds the admin menu item under the 'Settings' menu.
 *
 * @since 2.0.0
 * @action admin_menu
 */
function kapl_add_admin_menu() {
	add_options_page(
		__( 'KISS PDF Linker Settings', 'kiss-automated-pdf-linker' ), // Page title
		__( 'KISS PDF Linker', 'kiss-automated-pdf-linker' ),          // Menu title
		'manage_options',                                              // Capability required
		KAPL_SETTINGS_SLUG,                                            // Menu slug
		'kapl_settings_page_html'                                      // Callback function
	);
}

/**
 * Renders the HTML content for the plugin settings page.
 *
 * Uses the Settings API functions (settings_fields, do_settings_sections)
 * and includes a manual "Rebuild Index" button.
 *
 * @since 2.0.0
 */
function kapl_settings_page_html() {
	// Check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kiss-automated-pdf-linker' ) );
		return;
	}

    // Handle index rebuilding action
    $index_rebuilt = false;
    if ( isset( $_POST['kapl_rebuild_index_nonce'], $_POST['kapl_rebuild_index'] ) &&
         wp_verify_nonce( sanitize_key( $_POST['kapl_rebuild_index_nonce'] ), 'kapl_rebuild_index_action' ) )
    {
        $result = kapl_build_pdf_index(); // Rebuild the index
        if ( ! is_wp_error( $result ) ) {
            $index_rebuilt = true;
            // $result here is the count of indexed files
            $rebuild_message = sprintf(
                /* translators: %d: number of PDF files indexed */
                esc_html__( 'PDF index rebuilt successfully. Found %d PDF files.', 'kiss-automated-pdf-linker' ),
                (int) $result
            );
            add_settings_error( 'kapl_rebuild_status', 'rebuild_success', $rebuild_message, 'updated' );
        } else {
            // Display error message from WP_Error
            $indexed_count = 0;
            $data = $result->get_error_data();
            if ( is_numeric( $data ) ) {
                $indexed_count = (int) $data;
            } elseif ( is_array( $data ) && isset( $data['count'] ) ) {
                $indexed_count = (int) $data['count'];
            }
            $message = $result->get_error_message();
            if ( $indexed_count > 0 ) {
                $message = sprintf( esc_html__( 'Indexed %1$d files, but some issues occurred: %2$s', 'kiss-automated-pdf-linker' ), $indexed_count, $message );
            } else {
                $message = esc_html__( 'Error rebuilding index: ', 'kiss-automated-pdf-linker' ) . $message;
            }
            add_settings_error( 'kapl_rebuild_status', 'rebuild_error', $message, 'error' );
        }
    }

	?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <?php settings_errors( 'kapl_rebuild_status' ); // Display rebuild status messages ?>

        <form method="post" action="options.php">
            <?php
            // Output necessary hidden fields for the Settings API (nonce, action, etc.)
            settings_fields( 'kapl_settings_group' );
            // Output the settings sections and fields for this page
            do_settings_sections( KAPL_SETTINGS_SLUG );
            // Submit button for saving directory selections
            submit_button( __( 'Save Settings', 'kiss-automated-pdf-linker' ) );
            ?>
        </form>

        <hr> <?php // Visual separator ?>

        <h2><?php esc_html_e( 'PDF Index Management', 'kiss-automated-pdf-linker' ); ?></h2>
        <p><?php esc_html_e( 'After saving directory selections, click the button below to scan the selected folders and build the PDF index used by the shortcode.', 'kiss-automated-pdf-linker' ); ?></p>
        <p><?php esc_html_e( 'You should rebuild the index whenever you add, remove, or rename PDF files in the selected directories.', 'kiss-automated-pdf-linker' ); ?></p>

        <?php // Index rebuilding form (separate from settings form) ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'kapl_rebuild_index_action', 'kapl_rebuild_index_nonce' ); ?>
            <p>
                <button type="submit" name="kapl_rebuild_index" class="button button-primary">
                    <?php esc_html_e( 'Rebuild PDF Index Now', 'kiss-automated-pdf-linker' ); ?>
                </button>
            </p>
        </form>

         <?php // Display current index status (optional) ?>
         <?php
            $index_data = kapl_get_pdf_index();
            if ( is_array( $index_data ) ) {
                echo '<p><em>' . sprintf(
                    /* translators: %d: number of files in the current index */
                    esc_html__( 'Current index contains %d PDF files.', 'kiss-automated-pdf-linker' ),
                    count( $index_data )
                ) . '</em></p>';
            } else {
                 echo '<p><em>' . esc_html__( 'No index found. Please rebuild the index.', 'kiss-automated-pdf-linker' ) . '</em></p>';
            }
         ?>

    </div><?php
}


// ==========================================================================
// 5. Directory Scanning & Indexing Logic
// ==========================================================================

/**
 * Scans selected directories recursively for PDF files and builds the index.
 *
 * Reads the selected directories from settings, iterates through them,
 * finds all .pdf files (case-insensitive), normalizes names, and saves
 * the index data (path, filename, normalized name) to the options table.
 *
 * @since 2.0.0
 *
 * @return int|WP_Error The number of PDF files indexed on success, or WP_Error on failure.
 */
function kapl_build_pdf_index() {
	$settings = get_option( KAPL_SETTINGS_OPTION_NAME, ['selected_directories' => []] );
	$selected_dirs = isset( $settings['selected_directories'] ) && is_array( $settings['selected_directories'] ) ? $settings['selected_directories'] : [];

    if ( empty( $selected_dirs ) ) {
        // If no directories are selected, clear the index and return 0 count.
        kapl_update_pdf_index( [] );
        return 0;
    }

    $upload_dir_info   = wp_upload_dir();
    $uploads_base_path = trailingslashit( $upload_dir_info['basedir'] );
    $pdf_index         = [];
    $errors            = [];


    foreach ( $selected_dirs as $dir_slug ) {
        $scan_path = $uploads_base_path . $dir_slug;

        if ( ! is_dir( $scan_path ) ) {
            $errors[] = sprintf( __( 'Directory not found: %s', 'kiss-automated-pdf-linker' ), '<code>' . esc_html( $dir_slug ) . '</code>' );
            continue;
        }

        if ( ! is_readable( $scan_path ) ) {
            $errors[] = sprintf( __( 'Directory not readable: %s', 'kiss-automated-pdf-linker' ), '<code>' . esc_html( $dir_slug ) . '</code>' );
            continue;
        }

        try {
            $directory_iterator = new RecursiveDirectoryIterator( $scan_path, RecursiveDirectoryIterator::SKIP_DOTS );
            $file_iterator      = new RecursiveIteratorIterator( $directory_iterator, RecursiveIteratorIterator::LEAVES_ONLY );

            foreach ( $file_iterator as $fileinfo ) {
                if ( $fileinfo->isFile() && $fileinfo->isReadable() && strtolower( $fileinfo->getExtension() ) === 'pdf' ) {
                    $full_path       = $fileinfo->getPathname();
                    $relative_path   = str_replace( $uploads_base_path, '', $full_path );
                    $filename        = $fileinfo->getFilename();
                    $normalized_name = kapl_normalize_filename( $filename );

                    $pdf_index[] = [
                        'path'            => $relative_path,
                        'filename'        => $filename,
                        'normalized_name' => $normalized_name,
                    ];
                }
            }
        } catch ( Exception $e ) {
            $errors[] = sprintf( __( 'Error scanning directory %1$s: %2$s', 'kiss-automated-pdf-linker' ), '<code>' . esc_html( $dir_slug ) . '</code>', esc_html( $e->getMessage() ) );
            continue;
        }
    }
    // Sort the index alphabetically by relative path for consistency
    usort($pdf_index, function($a, $b) {
        return strcmp($a['path'], $b['path']);
    });
    // Save the index to the database
    $update_success = kapl_update_pdf_index( $pdf_index );

    if ( ! $update_success ) {
        $errors[] = __( 'Failed to save the PDF index to the database.', 'kiss-automated-pdf-linker' );
    }

    if ( ! empty( $errors ) ) {
        return new WP_Error( 'kapl_index_warnings', implode( '<br>', $errors ), count( $pdf_index ) );
    }

    // Return the count of indexed files
    return count( $pdf_index );


// ==========================================================================
// 6. Index Cache Management (Get/Set JSON Option)
// ==========================================================================

/**
 * Retrieves the PDF index data from the WordPress options table.
 *
 * Decodes the JSON string stored in the option.
 *
 * @since 2.0.0
 *
 * @return array|null An array containing the index data, or null if the option doesn't exist or JSON is invalid.
 */
function kapl_get_pdf_index() {
	$index_json = get_option( KAPL_INDEX_OPTION_NAME, null );
	if ( $index_json === null ) {
		return null; // Option doesn't exist
	}

	$index_data = json_decode( $index_json, true ); // Decode as associative array

	if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $index_data ) ) {
        // Log error if JSON is invalid
        error_log("KAPL: Error decoding PDF index JSON - " . json_last_error_msg());
		return null; // Invalid JSON or not an array
	}

	return $index_data;
}

/**
 * Updates the PDF index data in the WordPress options table.
 *
 * Encodes the index array as JSON and saves it. Ensures the option
 * does not autoload to prevent performance impact.
 *
 * @since 2.0.0
 *
 * @param array $index_data The array containing the PDF index data.
 * @return bool True on successful update/add, false on failure.
 */
function kapl_update_pdf_index( array $index_data ) {
	$index_json = wp_json_encode( $index_data ); // Use wp_json_encode for better compatibility

	if ( $index_json === false ) {
        error_log("KAPL: Failed to encode PDF index to JSON.");
		return false; // Failed to encode
	}

	// Use update_option, which handles adding or updating.
    // Set 'autoload' to 'no' to prevent loading this potentially large option on every page load.
	return update_option( KAPL_INDEX_OPTION_NAME, $index_json, 'no' );
}


// ==========================================================================
// 7. Shortcode Registration & Handler
// ==========================================================================

/**
 * Handles the [kiss_pdf] shortcode processing.
 *
 * Searches the cached PDF index for a file that fuzzily matches
 * the provided 'name' attribute. If a match meeting the similarity
 * threshold is found, it outputs an HTML link to that file.
 *
 * @since 2.0.0
 *
 * @param array|string $atts Shortcode attributes. Expected: ['name' => 'search term'].
 * @param string|null  $content The content enclosed within the shortcode (not used).
 * @param string       $tag     The shortcode tag name ('kiss_pdf').
 *
 * @return string HTML output for the shortcode (link or message).
 */
function kapl_shortcode_handler( $atts, $content = null, $tag = '' ) {
	// Normalize attribute keys to lowercase.
	$atts = array_change_key_case( (array) $atts, CASE_LOWER );

	// Define defaults and merge with user attributes.
	$atts = shortcode_atts(
		[
			'name' => '',
		],
		$atts,
		$tag
	);

	// Sanitize the search term.
	$search_name = sanitize_text_field( $atts['name'] );

	// Validate input: ensure 'name' is provided.
	if ( empty( $search_name ) ) {
		return '<em class="kapl-error">' . esc_html__( 'Shortcode error: Missing "name" attribute.', 'kiss-automated-pdf-linker' ) . '</em>';
	}

	// --- File Index Retrieval ---
	$pdf_index = kapl_get_pdf_index();

	// Handle cases where the index doesn't exist or is empty.
	if ( $pdf_index === null ) {
        // Log this? Maybe the index needs rebuilding.
		return '<em class="kapl-error">' . esc_html__( 'PDF index not found. Please ask an administrator to rebuild it.', 'kiss-automated-pdf-linker' ) . '</em>';
	}
    if ( empty( $pdf_index ) ) {
        return '<em>' . esc_html__( 'No PDF files found in the index.', 'kiss-automated-pdf-linker' ) . '</em>';
    }


	// --- Fuzzy Matching Logic ---
	$normalized_search_name = kapl_normalize_filename( $search_name );
	$best_match_item = null; // Stores the entire index item array of the best match.
	$highest_similarity = -1;

    // Get base upload URL.
    $upload_dir_info = wp_upload_dir();
    $uploads_base_url = trailingslashit( $upload_dir_info['baseurl'] );

	// Iterate through the indexed PDF files.
	foreach ( $pdf_index as $index_item ) {
        // Ensure the item has the expected structure
        if ( !isset($index_item['normalized_name']) || !isset($index_item['path']) || !isset($index_item['filename']) ) {
            continue; // Skip malformed items
        }

		$normalized_file_name = $index_item['normalized_name'];
		$similarity_percent = 0;

		// Calculate similarity.
		similar_text( $normalized_search_name, $normalized_file_name, $similarity_percent );

		// Check if this is a better match.
		if ( $similarity_percent >= KAPL_SIMILARITY_THRESHOLD && $similarity_percent > $highest_similarity ) {
			$highest_similarity = $similarity_percent;
			$best_match_item = $index_item;
		}
	}

	// --- Output Generation ---
	if ( $best_match_item !== null ) {
		// Construct the full URL using the base URL and the relative path from the index.
        // Ensure no double slashes if relative path starts with one (unlikely but possible).
		$file_url = $uploads_base_url . ltrim( $best_match_item['path'], '/' );

		// Use the original filename without extension as link text.
		$link_text = pathinfo( $best_match_item['filename'], PATHINFO_FILENAME );
		if ( empty( $link_text ) ) {
			$link_text = $best_match_item['filename']; // Fallback
		}

		// Return the formatted HTML link.
		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" class="kapl-pdf-link">%s</a>',
			esc_url( $file_url ),
			esc_html( $link_text )
		);
	} else {
		// No matching file found.
		return '<em>' . esc_html__( 'No matching PDF file found.', 'kiss-automated-pdf-linker' ) . '</em>';
	}
}


// ==========================================================================
// 8. Helper Functions
// ==========================================================================

/**
 * Normalizes a filename or product title into a “slug‑style” string so that
 * product titles like
 *   “3.5 Gram THCA Disposable Vape (Limited Run) – Pressure”
 * and file names like
 *   “3.5 Gram THCA Disposable Vape (Limited Run) – Pressure.pdf”
 * both become
 *   3-5-gram-thca-disposable-vape-limited-run-pressure
 *
 * Normalisation steps:
 *  1. Split camelCase words by inserting a space before any upper‑case letter
 *     that follows a lower‑case letter (e.g. “BlueBerry” → “Blue Berry”).
 *  2. Lower‑case the whole string.
 *  3. Strip the file extension, if present.
 *  4. Replace every run of non‑alphanumeric characters with a single dash.
 *  5. Trim leading/trailing dashes.
 *
 * @since 2.1.0
 *
 * @param string $filename Raw file name or product title.
 * @return string Slug‑style string suitable for matching.
 */
function kapl_normalize_filename( $filename ) {
    if ( ! is_string( $filename ) ) {
        return '';
    }

    // 1 ‑ split camelCase boundaries to ensure consistent word breaks.
    $filename = preg_replace( '/([a-z])([A-Z])/', '$1 $2', $filename );

    // 2 ‑ lower‑case for case‑insensitive matching.
    $filename = strtolower( $filename );

    // 3 ‑ remove the file extension, if any.
    $filename = pathinfo( $filename, PATHINFO_FILENAME );

    // 4 ‑ collapse all runs of non‑alphanumeric chars (spaces, punctuation,
    //     en/em dashes, parentheses, dots, etc.) to a single “‑”.
    $filename = preg_replace( '/[^a-z0-9]+/', '-', $filename );

    // 5 ‑ trim stray leading/trailing dashes.
    $filename = trim( $filename, '-' );

    error_log("KAPL: Normalized filename: " . $filename);

    return $filename;
}


// ==========================================================================
// 9. Activation/Deactivation Hooks
// ==========================================================================

/**
 * Executes actions when the plugin is activated.
 *
 * Sets up default settings if they don't exist.
 * Does NOT automatically build the index on activation, as it could be slow.
 *
 * @since 2.0.0
 */
function kapl_activate() {
	// Set default settings if the option doesn't exist yet
    if ( false === get_option( KAPL_SETTINGS_OPTION_NAME ) ) {
        update_option( KAPL_SETTINGS_OPTION_NAME, [
            'selected_directories' => [],
            'link_color' => '#0000FF', // Add default color
            'use_product_title_match' => false // Add default for new setting
        ]);
    }
    // Optionally, clear any old index from previous versions if names were different
    // delete_option('old_index_option_name');
}
register_activation_hook( __FILE__, 'kapl_activate' );

/**
 * Executes actions when the plugin is deactivated.
 *
 * Consider whether to remove settings or the index on deactivation.
 * Typically, settings are kept unless there's a specific "uninstall" routine.
 * We won't delete the index here, allowing reactivation without data loss.
 *
 * @since 2.0.0
 */
function kapl_deactivate() {
	// Clear scheduled actions if background processing was added (Phase 2)
    // wp_clear_scheduled_hook('kapl_background_index_hook');

    // No actions needed for Phase 1 deactivation (settings/index are kept).
}
register_deactivation_hook( __FILE__, 'kapl_deactivate' );


// ==========================================================================
// 10. Plugin Action Links (Settings link)
// ==========================================================================

/**
 * Adds a 'Settings' link to the plugin's entry on the Plugins page.
 *
 * @since 2.0.0
 * @filter plugin_action_links_{plugin_basename}
 *
 * @param array $links Existing action links for the plugin.
 * @return array Modified action links including the Settings link.
 */
function kapl_add_settings_link( $links ) {
	$settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url( admin_url( 'options-general.php?page=' . KAPL_SETTINGS_SLUG ) ),
        esc_html__( 'Settings', 'kiss-automated-pdf-linker' )
    );

	// Add the settings link before other links like "Deactivate"
	array_unshift( $links, $settings_link );

	return $links;
}

/**
 * Customize strains tabs
 */
add_filter( 'woocommerce_product_tabs', 'kapl_customize_strain_tab', 98 );
function kapl_customize_strain_tab( $tabs ) {
	global $product;
    $product_id = $product->get_id();

	$setting_enabled = get_option( KAPL_SETTINGS_OPTION_NAME, ['use_product_title_match' => false] );

	if ( get_field('product_strains', $product_id) && $setting_enabled['use_product_title_match'] ) {
		$tabs['strains']['callback'] = 'kapl_strain_tab_content';	// Custom description callback
	}

	return $tabs;
}

function kapl_strain_tab_content() {
	global $product;

	$product_id = $product->get_id();

	$disable_pdf_linking = get_field( 'disable_coas_pdf_links', $product_id );
	$strain_fields = get_field( 'product_strains', $product_id );

	if ( $disable_pdf_linking ) {
		echo $strain_fields;
		return;
	}

	$product_title = $product->get_name();
	
	$pdf_index = kapl_get_pdf_index();
	
	$upload_dir_info = wp_upload_dir();
	$uploads_base_url = trailingslashit( $upload_dir_info['baseurl'] );

	$can_link_pdfs = ( $pdf_index !== null && ! empty( $pdf_index ) );
	
	$normalized_product_title = '';
	if ( $can_link_pdfs && ! empty( $product_title ) ) {
		$normalized_product_title = kapl_normalize_filename( $product_title );
	}

	$p_tag_pattern = '/<p>(.*?)<\/p>/i';
	preg_match_all( $p_tag_pattern, $strain_fields, $matches );

	echo '<div class="product-strains">';

	if ( ! empty( $matches[1] ) ) {
		foreach ( $matches[1] as $line_content ) {
			$decoded_line_content = html_entity_decode( $line_content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$line = trim( strip_tags( $decoded_line_content ) );

			if ( empty( $line ) ) {
				continue;
			}
			
			// We still need to parse strain name and details for display purposes,
			// but matching will be done using product title.
			$strain_name_display = $line; // Default to full line if no separator
			$strain_details_display = '';
			
			$strain_split_pattern = '/^(.+?)([\s]*[^a-zA-Z0-9\\s].*)$/u';
			if ( preg_match( $strain_split_pattern, $line, $split_matches ) ) {
				$strain_name_display = trim( $split_matches[1] );
				$strain_details_display = $split_matches[2];
			} else {
				$pos = strpos( $line, '-' ); 
				if ( $pos !== false ) {
					$strain_name_display = trim( substr( $line, 0, $pos ) );
					$strain_details_display = substr( $line, $pos ); 
				}
			}

			echo '<p>';

			if ( $can_link_pdfs && ! empty( $normalized_product_title ) ) {
				$best_match_item = null;
				
				foreach ( $pdf_index as $index_item ) {
					if ( ! isset( $index_item['normalized_name'] ) || ! isset( $index_item['path'] ) ) {
						continue;
					}
					
					$normalized_file_name = $index_item['normalized_name'];
					
					// Check if the normalized product title is a substring of the normalized file name.
					if ( strpos( $normalized_file_name, $normalized_product_title ) !== false) {
						$best_match_item = $index_item;
						break; 
					}
				}
				
				if ( $best_match_item ) {
					$matched_pdf_url = $best_match_item['path'];
					$file_url = $uploads_base_url . ltrim( $matched_pdf_url, '/' );
					
					printf(
						'<a href="%s" target="_blank" rel="noopener noreferrer" class="kapl-pdf-link">%s</a>%s',
						esc_url( $file_url ),
						esc_html( $strain_name_display ),
						esc_html( $strain_details_display )
					);
				} else {
					echo esc_html( $line );
				}
			} else {
				echo esc_html( $line );
			}

			echo '</p>';
		}
	} else {
	    echo wp_kses_post( $strain_fields );
	}
	
	echo '</div>';
}
