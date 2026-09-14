<?php
/**
 * Custom Post Type Registration
 */

declare(strict_types=1);

namespace Xkinstagram\Import;

final class PostType {
    public const POST_TYPE = 'xkinstagram_post';
    public const META_INSTAGRAM_ID = '_xkinstagram_instagram_id';
    public const META_PERMALINK = '_xkinstagram_permalink';
    public const META_MEDIA_TYPE = '_xkinstagram_media_type';
    public const META_CAROUSEL_MEDIA = '_xkinstagram_carousel_media';
    public const META_IMPORTED_AT = '_xkinstagram_imported_at';

    public static function register(): void {
        $labels = [
            'name' => _x('Instagram Posts', 'post type general name', 'xkinstagram'),
            'singular_name' => _x('Instagram Post', 'post type singular name', 'xkinstagram'),
            'menu_name' => _x('Instagram Posts', 'admin menu', 'xkinstagram'),
            'name_admin_bar' => _x('Instagram Post', 'add new on admin bar', 'xkinstagram'),
            'add_new' => _x('Add New', 'instagram post', 'xkinstagram'),
            'add_new_item' => __('Add New Instagram Post', 'xkinstagram'),
            'new_item' => __('New Instagram Post', 'xkinstagram'),
            'edit_item' => __('Edit Instagram Post', 'xkinstagram'),
            'view_item' => __('View Instagram Post', 'xkinstagram'),
            'all_items' => __('All Instagram Posts', 'xkinstagram'),
            'search_items' => __('Search Instagram Posts', 'xkinstagram'),
            'not_found' => __('No Instagram posts found.', 'xkinstagram'),
            'not_found_in_trash' => __('No Instagram posts found in Trash.', 'xkinstagram'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true,
            'rest_base' => 'xkinstagram-posts',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'menu_icon' => 'dashicons-camera',
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'revisions'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'instagram-posts', 'with_front' => false],
            'query_var' => true,
            'can_export' => true,
            'delete_with_user' => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    public static function flush_rewrite_rules(): void {
        flush_rewrite_rules();
    }
}