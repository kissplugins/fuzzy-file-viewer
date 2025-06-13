# KISS Automated PDF Linker

**Contributors:** (Your Name or Company)
**Donate link:** <https://example.com/donate>
**Tags:** pdf, shortcode, link, automatic, files, uploads, index, search, fuzzy
**Requires at least:** 5.2
**Tested up to:** 6.5
**Requires PHP:** 7.4
**Stable tag:** 2.1.1
**License:** GPLv2 or later
**License URI:** <https://www.gnu.org/licenses/gpl-2.0.html>

Scans selected upload directories for PDF files and provides a shortcode `[kiss_pdf name="filename"]` to link to them using fuzzy matching.

## Description

The KISS Automated PDF Linker plugin provides an easy way to automatically link to PDF files stored within specific folders in your WordPress uploads directory.

Instead of manually creating links, you simply tell the plugin which top-level folders in `/wp-content/uploads/` to scan (e.g., `2024`, `coas`, `reports`). The plugin builds an index of all PDF files found within those selected folders and their subfolders.

Then, you can use the simple shortcode `[kiss_pdf name="some document name"]` in your posts or pages. The plugin uses fuzzy matching (approx. 50% similarity) to find the best matching PDF from its index based on the filename (ignoring case, spaces, dashes, underscores, and the `.pdf` extension) and automatically generates a download link.

**Features:**

* **Selective Scanning:** Choose which top-level folders within `/wp-content/uploads/` to scan via the Settings page.
* **Recursive Indexing:** Finds all `.pdf` files within the selected folders and their subdirectories.
* **PDF Index Cache:** Stores the list of found PDFs efficiently in the WordPress options table.
* **Manual Index Rebuild:** A button on the settings page allows you to update the index after adding/removing/renaming PDFs.
* **Simple Shortcode:** Use `[kiss_pdf name="your-file-name"]` to generate links automatically.
* **Fuzzy Matching:** Finds the correct PDF even if the `name` attribute isn't an exact match (uses `similar_text` with a ~50% threshold).
* **Settings Link:** Easy access to settings from the Plugins page.

This plugin is licensed under the GPL v2 or later. You can find the full license text at <https://www.gnu.org/licenses/gpl-2.0.html>.

## Installation

1.  **Upload the plugin files:**
    * Download the plugin zip file (or create a zip from the `kiss-automated-pdf-linker` folder).
    * Log in to your WordPress admin panel.
    * Navigate to `Plugins` -> `Add New`.
    * Click `Upload Plugin`.
    * Choose the downloaded zip file and click `Install Now`.
    * Alternatively, unzip the folder and upload `kiss-automated-pdf-linker` to the `/wp-content/plugins/` directory using FTP or your hosting file manager.
2.  **Activate the plugin:**
    * Navigate to `Plugins` in your WordPress admin panel.
    * Locate "KISS Automated PDF Linker" and click `Activate`.
3.  **Configure Settings:**
    * Navigate to `Settings` -> `KISS PDF Linker`.
    * Check the boxes next to the top-level upload folders you want to scan.
    * Click `Save Directory Selections`.
4.  **Build Initial Index:**
    * On the same settings page (`Settings` -> `KISS PDF Linker`), scroll down to the "PDF Index Management" section.
    * Click the `Rebuild PDF Index Now` button. Wait for the confirmation message.

## Usage

### Settings Page:

* Navigate to `Settings` -> `KISS PDF Linker`.
* **Directory Selection:** Check/uncheck the folders you want the plugin to scan. Remember to click `Save Directory Selections` after making changes.
* **Index Management:** Click `Rebuild PDF Index Now` whenever you add, remove, or rename PDF files within the selected directories to ensure the shortcode uses the latest information.

### Shortcode:

* Use the shortcode `[kiss_pdf name="filename"]` in your posts, pages, or widgets where you want a link to a PDF to appear.
* Replace `"filename"` with a descriptive name related to the PDF file you want to link to (you don't need the `.pdf` extension).
* The plugin will search its index for a PDF file whose name (ignoring case, spaces, etc.) is the closest match to your provided `name`.
* **Example:** If you have a file named `Company Report Q1 2024_final.pdf` located within one of your selected scan directories, you could use:
    ```
    [kiss_pdf name="company report q1 2024"]
    ```
    or even just
    ```
    [kiss_pdf name="report q1"]
    ```
    (depending on other filenames in the index and the similarity threshold).
* The shortcode will output an HTML link like:
    `<a href=".../wp-content/uploads/reports/2024/Company%20Report%20Q1%202024_final.pdf" target="_blank" rel="noopener noreferrer">Company Report Q1 2024_final</a>`
* If no sufficiently similar file is found in the index, it will output:
    `<em>No matching PDF file found.</em>`
* If the index hasn't been built yet, it will output:
    `<em class="kapl-error">PDF index not found. Please ask an administrator to rebuild it.</em>`

## Frequently Asked Questions

### The shortcode says "No matching PDF file found" but the file exists!

* **Rebuild the Index:** Have you clicked the `Rebuild PDF Index Now` button on the settings page (`Settings` -> `KISS PDF Linker`) since adding/modifying the file? The shortcode relies entirely on the saved index.
* **Check Scanned Directories:** Is the folder containing the PDF (or one of its parent folders at the top level of `/uploads/`) selected in the plugin settings?
* **Check File Extension:** Is the file definitely a `.pdf` (case-insensitive)? The plugin only indexes PDF files.
* **Check Similarity:** Is the `name` you provided in the shortcode reasonably similar (at least ~50%) to the actual filename (ignoring extension, case, spaces, etc.)? Try making the `name` more specific.
* **Check Permissions:** Ensure the web server has read permissions for the directories and the PDF file itself.

### Can I change the similarity threshold?

Not via the settings in this version. You would need to modify the `KAPL_SIMILARITY_THRESHOLD` constant within the `kiss-automated-pdf-linker.php` file.

### How is the similarity calculated?

It uses PHP's `similar_text()` function to compare the normalized `name` attribute from the shortcode with the normalized filenames stored in the index.

## Screenshots

1.  The admin settings page showing directory selection checkboxes.
2.  The admin settings page showing the index rebuild button and status.
3.  Example of the shortcode output on a frontend page.

*(Note: You would typically embed actual images here in Markdown using `![Alt text](URL/path_to_image.png)`)*

## Changelog

### 2.1.1 - 2025-05-01

* Enhancement: More robust folder scanning and detailed error messages during index rebuild.
### 2.0.0 - 2025-04-28

* MAJOR REFACTOR: Renamed plugin to "KISS Automated PDF Linker".
* Feature: Added Settings page using Settings API (`Settings` -> `KISS PDF Linker`).
* Feature: User can select top-level upload directories to scan via checkboxes.
* Feature: Scanning is now recursive within selected directories.
* Feature: Indexing is limited to `.pdf` files (case-insensitive).
* Feature: Replaced transient cache with JSON index stored in `wp_options` (non-autoloaded).
* Feature: Added manual "Rebuild Index" button on settings page.
* Feature: Added "Settings" link on the Plugins listing page.
* Enhancement: Switched to function prefix `kapl_`.
* Enhancement: Updated PHPDoc comments and code structure.
* Change: Shortcode renamed to `[kiss_pdf]`.
* Change: Increased minimum PHP requirement to 7.4.

### 1.0.2 - 2025-04-28

* Enhancement: Added PHPDoc comments throughout the code (as COAS Directory Viewer).
* Change: Lowered fuzzy matching similarity threshold from 80% to 50% (as COAS Directory Viewer).

### 1.0.1 - 2025-04-28

* Fix: Ensure correct trailing slash in generated file URLs (as COAS Directory Viewer).

### 1.0.0 -
