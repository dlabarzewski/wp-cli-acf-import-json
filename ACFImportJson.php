<?php
/*
* Plugin Name: ACFImportJson
* Plugin URI: https://github.com/dlabarzewski/wp-cli-acf-import-json
* Description: Allows to import acf json using wp cli 
* Version: 0.0.1
* Author: Dawid Labarzewski
* License: GPL2+
* License URI: https://www.gnu.org/licenses/gpl-2.0.txt
* Requires PHP: 8.0
*/


if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Adds a wp cli command to sync all ACF field groups. Use it like this:
 *
 * "wp acf-import-json --json_file=/var/www/html/acf.json"
 *
 */
class ACFImportJson
{
    /**
     * Init
     */
    public static function init()
    {
        add_action('acf/init', [__CLASS__, 'add_wp_cli_command']);
    }

    /**
     * Conditional to check if inside WP_CLI
     *
     * @return boolean
     */
    private static function is_wp_cli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }

    /**
     * Add the WP_CLI command
     *
     * @return void
     */
    public static function add_wp_cli_command(): void
    {
        if (self::is_wp_cli()) {
            \WP_CLI::add_command('acf-import-json', [__CLASS__, 'wp_cli_import_json']);
        }
    }

    /**
     * Import ACF field groups from local file to database
     *
     * ## OPTIONS
     *
     * [--json_file=<json_file>]
     * : The path to the json file.
     *
     * @subcommand import
     * @synopsis [--json_file=<json_file>]
     */
    public static function wp_cli_import_json($args, $assoc_args): void
    {
        try {

            extract($assoc_args);

            if (!isset($json_file)) {
                \WP_CLI::error('Import file empty.');
                return;
            }

            // Read JSON.
            $json = file_get_contents($json_file);
            $json = json_decode($json, true);

            // Check if empty.
            if (! $json || ! is_array($json)) {
                \WP_CLI::error('Import file empty.');
                return;
            }

            acf_include('includes/admin/admin-internal-post-type-list.php');
            if (!class_exists('ACF_Admin_Internal_Post_Type_List')) {
                \WP_CLI::error('Some required ACF classes could not be found. Please update ACF to the latest version.');
            }

            // Ensure $json is an array of posts.
            if (isset($json['key'])) {
                $json = array($json);
            }

            // Remember imported post ids.
            $ids = array();

            // Loop over json.
            foreach ($json as $to_import) {
                // Search database for existing post.
                $post_type = acf_determine_internal_post_type($to_import['key']);
                $post      = acf_get_internal_post_type_post($to_import['key'], $post_type);

                if ($post) {
                    $to_import['ID'] = $post->ID;
                }

                // Import the post.
                $to_import = acf_import_internal_post_type($to_import, $post_type);

                // Append message.
                $ids[] = $to_import['ID'];
            }

            // Count number of imported posts.
            $total = count($ids);

            // Generate text.
            $text = sprintf('Imported %s items', $total);

            // Add notice.
            \WP_CLI::success($text);
        } catch (\Throwable $ex) {
            \WP_CLI::error($ex->getMessage());
        }
    }
}
/**
 * Initialize the plugin
 */
ACFImportJson::init();
