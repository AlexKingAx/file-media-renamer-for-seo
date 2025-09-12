=== File Media Renamer for SEO ===
Contributors: alex-web
Donate link: https://filemediarenamerwp.com/
Tags: rename media files, image titles, alt text, bulk rename images, image seo
Requires at least: 4.0
Tested up to: 6.8
Stable tag: 0.7.1
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Rename media files with SEO-friendly names, auto-update references, alt/title sync, and 301 redirects — fast and safe.

== Description ==

File Media Renamer for SEO is a lightweight and fast WordPress plugin designed to improve your website's SEO by allowing you to rename media files directly from the WordPress Media Library.
The plugin automatically updates all references to renamed files throughout your website, ensuring no broken links or missing images.

The plugin focuses on safe renaming, performance, and SEO best practices, with features to keep your media metadata aligned with new filenames and preserve search equity via 301 redirects.

== Features ==

= Individual File Renaming =
* Rename any media file directly from the WordPress Media Library
* Convert file names to SEO-optimized formats automatically
* Update all references to the renamed file across your website
* Rename all thumbnail sizes along with the main file
* Built-in rename history with quick undo

= Bulk Renaming =
* Rename multiple files at once via Media Library bulk actions
* Sequential naming (e.g., new-name-1, new-name-2) for consistent batches
* Real-time progress and batch processing up to 50 files

= Automatic Content Updates =
* Updates references in posts, pages, custom post types, and metadata
* Handles serialized data structures
* Efficient background processing via WordPress cron
* Automatic cache clearing after operations

= SEO Enhancements =
* Optionally update media titles to match the new file name
* Automatically update alt text attributes for better accessibility and SEO
* Converts hyphens/underscores to readable titles

= Redirect Management =
* Creates 301 redirects from old file URLs to new ones
* Stores redirects in a dedicated database table for performance
* Handles redirects for all thumbnail sizes

= Advanced Features =
* Prevents filename conflicts by generating unique names
* Validates files before renaming for safety

== Installation ==

1. Download the plugin as a ZIP file.
2. Go to **Plugins → Add New** in your WordPress dashboard.
3. Click **Upload Plugin** and select the ZIP file.
4. Click **Install Now** and then **Activate**.
5. The plugin will automatically create the necessary database tables.

== Usage ==

= Individual File Renaming =
1. Go to **Media → Library**.
2. Click on a file to open the attachment details.
3. Enter the new SEO name in the **SEO Name** field.
4. Click **Save SEO Name** — all references will update automatically.

= Bulk Renaming =
1. Switch the Media Library to **List view**.
2. Select multiple files and choose **Rename** from **Bulk actions**.
3. Enter a base name and click **Start Rename** to process all selected files.

= History and Undo =
* View the rename history for any file in the attachment details.
* Click any previous name to revert instantly (last 2 versions kept).

== Settings ==

Find settings under **Media → FMR Settings**:

* **Rename Title** — Automatically update the media title when renaming files
* **Rename Alt Text** — Automatically update the alt text attribute when renaming files

== Technical Details ==

* Custom table: `wp_fmrseo_redirects` for redirect mappings
* Background processing for large operations (WordPress cron)
* File validation and conflict prevention
* Optimized database queries and cache management

== Requirements ==

* WordPress 4.0 or higher
* PHP 7.0 or higher
* Write permissions for the uploads directory

== Frequently Asked Questions ==

= Will renaming files break my website? =
No. The plugin updates all references site-wide and creates 301 redirects from old URLs to new ones.

= Can I revert to an old file name? =
Yes. Each file maintains a short rename history so you can revert quickly.

= Does it work with thumbnails? =
Yes. All image sizes are renamed and redirected together with the main file.

= Do redirects impact SEO? =
301 redirects preserve SEO value and help search engines understand the change.

== Screenshots ==

1. Rename media files directly from the library
2. Bulk rename interface with progress indicator
3. SEO settings panel

== Changelog ==

= 0.7.1 =
* Initial public release

== Upgrade Notice ==

= 0.7.1 =
First stable release of File Media Renamer for SEO. Includes safe renaming, automatic reference updates, redirects, and bulk features.
