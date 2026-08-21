<?php
/**
 * Plugin Name: Bespoke Bike Builder — Responsive Assets Loader
 * Description: Loads the Group 1 responsive layer (progress bar, breakpoint tile columns, sticky mobile nav, expandable summary, Cockpit width/stem split, touch-target sizing) for the Bespoke Bike Builder plugin, using confirmed real class names from builder.css. Standalone companion plugin — does not modify the main plugin file.
 * Version: 1.1
 * Author: Fareed M. Rifaideen
 * Requires Plugins: bespoke-bike-builder
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    $slug = 'bespoke-bike-builder';
    $base_url = trailingslashit(WP_PLUGIN_URL) . $slug . '/';
    $base_path = trailingslashit(WP_PLUGIN_DIR) . $slug . '/';

    $css_rel = 'assets/css/builder-responsive.css';
    $js_rel  = 'assets/js/builder-responsive.js';

    if (file_exists($base_path . $css_rel)) {
        wp_enqueue_style(
            'bbb-builder-responsive',
            $base_url . $css_rel,
            array(),
            filemtime($base_path . $css_rel)
        );
    }

    if (file_exists($base_path . $js_rel)) {
        wp_enqueue_script(
            'bbb-builder-responsive',
            $base_url . $js_rel,
            array(),
            filemtime($base_path . $js_rel),
            true
        );
    }
}, 20);
