=== KISS Fuzzy File Viewer ===
Contributors:      KISS Plugins 
Tags:              directory, files, shortcode, download, viewer, admin, cache, uploads 
Requires at least: 5.2 
Tested up to:      6.5 
Requires PHP:      7.2 
Stable tag:        1.0.1 
License:           GPLv2 or later 
License URI:       https://www.gnu.org/licenses/gpl-2.0.html 

Scans the /wp-content/uploads/coas/ directory, lists files on an admin page, and provides a shortcode [coas name="filename"] to link to files with fuzzy matching.

== Description ==

The KISS Fuzzy File Viewer plugin provides a simple way to manage and display links to files stored in a specific subdirectory (`/wp-content/uploads/coas/`) of your WordPress uploads folder.

Features:

* **Admin Viewer:** Adds a settings page under "Settings" -> "COAS Viewer" that lists all files currently in the `/wp-content/uploads/coas/` directory.
* **Download Shortcode:** Includes a `[coas name="your-file-name"]` shortcode to easily create download links on the frontend.
* **Fuzzy Matching:** The shortcode uses approximate string matching (similarity > 80%) to find the correct file, even if the `name` attribute isn't an exact match (ignores case, dashes, underscores, spaces, and file extensions during comparison).
* **Caching:** The directory file list is cached for 12 hours using WordPress transients to improve performance.
* **Cache Management:** A "Clear Cache" button on the admin page allows manual refreshing of the file list.

This plugin is licensed under the GPL v2 or later. You can find the full license text at https://www.gnu.org/licenses/gpl-2.0.html.

== Installation ==

1.  **Upload the plugin files:**
    * Download the plugin zip file.
    * Log in to your WordPress admin panel.
    * Navigate to `Plugins` -> `Add New`.
    * Click `Upload Plugin`.
    * Choose the downloaded zip file and click `Install Now`.
    * Alternatively, unzip the plugin folder and upload it to the `/wp-content/plugins/` directory using FTP or your hosting file manager.
2.  **Activate the plugin:**
    * Navigate to `Plugins` in your WordPress admin panel.
    * Locate "COAS Directory Viewer" and click `Activate`.
3.  **Create the directory (if it doesn't exist):**
    * Using FTP or your hosting file manager, navigate to `/wp-content/uploads/`.
    * Create a new folder named `coas`.
    * Ensure this directory is writable by the web server.
4.  **Upload files:**
    * Upload the files you want to manage into the newly created `/wp-content/uploads/coas/` directory.

== Usage ==

**Admin Page:**

* Navigate to `Settings` -> `COAS Viewer`.
* You will see a list of files currently in the `/wp-content/uploads/coas/` directory.
* Use the "Clear Cache & Refresh List" button if you've recently added or removed files and want to see the changes immediately without waiting for the cache to expire.

**Shortcode:**

* Use the shortcode `[coas name="filename"]` in your posts, pages, or widgets.
* Replace `"filename"` with a descriptive name related to the file you want to link to.
* The plugin will look for a file in `/wp-content/uploads/coas/` whose name is similar to the provided `name`.
* **Example:** If you have a file named `My Important Document 2024.pdf` in the `coas` directory, you could use:
    `[coas name="important doc 2024"]`
    or
    `[coas name="myimportantdocument"]`
* The shortcode will output an HTML link like:
    `<a href=".../wp-content/uploads/coas/My%20Important%20Document%202024.pdf" target="_blank" rel="noopener">My Important Document 2024</a>`
* If no sufficiently similar file is found, it will output:
    `<em>No matching file found.</em>`

== Frequently Asked Questions ==

= The shortcode says "No matching file found" but the file is there! =

* Check the spelling in the `name` attribute. While matching is fuzzy, it still needs to be reasonably close (approx. 80% similar).
* Clear the cache via `Settings` -> `COAS Viewer` -> `Clear Cache & Refresh List`. The plugin might be using an outdated list.
* Ensure the file is directly inside `/wp-content/uploads/coas/` and not in a subfolder.
* Verify file permissions on the server allow the webserver to read the directory and files.

= Can I change the directory name from 'coas'? =

Not directly via settings in this version. You would need to modify the `COAS_TARGET_DIR_SLUG` constant within the `coas-directory-viewer.php` file.

= How is the similarity calculated? =

It uses PHP's `similar_text()` function to compare the normalized `name` attribute with the normalized filenames (lowercase, no spaces/dashes/underscores, no extension).

== Screenshots ==

1.  The admin settings page showing the file list and clear cache button.
2.  Example of the shortcode output on a frontend page.

== Changelog ==

= 1.0.1 - 2025-04-28 =
* Fix: Ensure correct trailing slash in generated file URLs.

= 1.0.0 - 2025-MM-DD =
* Initial release.
* Admin page for viewing files in `/wp-content/uploads/coas/`.
* `[coas name="..."]` shortcode with fuzzy matching.
* Transient caching for file list.
* Cache clearing functionality.

== Upgrade Notice ==

(No upgrade notices yet.)

