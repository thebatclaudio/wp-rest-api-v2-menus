<?php
/*
Plugin Name: WP-REST-API V2 Menus FORKED
Version: 0.5
Description: Adding menus endpoints on WP REST API v2
Author: Claudio La Barbera
Author URI: https://thebatclaud.io
*/

/**
 * Get all registered menus
 * @return array List of menus with slug and description
 */
function wp_api_v2_menus_get_all_menus () {
    $menus = get_terms('nav_menu', array('hide_empty' => true ) );

    foreach($menus as $key => $menu) {
        // check if there is acf installed
        if( class_exists('acf') ) {
            $fields = get_fields($menu);
            if(!empty($fields)) {
                foreach($fields as $field_key => $item) {
                    // add all acf custom fields
                    $menus[$key]->$field_key = $item;
                }
            }
        }
    }

    return $menus;
}

/**
 * Get all locations
 * @return array List of locations
 **/

function wp_api_v2_menu_get_all_locations () {
    return get_nav_menu_locations();
}

/**
 * Get menu's data from his id
 * @param  array $data WP REST API data variable
 * @return object Menu's data with his items
 */
function wp_api_v2_locations_get_menu_data ( $data ) {
    $menu = new stdClass;

    // this could be replaced with `if (has_nav_menu($data['id']))`
    if (($locations = get_nav_menu_locations()) && isset($locations[$data['id']])) {
        $menu->items = wp_api_v2_menus_get_menu_items($locations[$data['id']]);
    } else {
        $menu->items = [];
        $menu->error = "No location has been found. Please ensure you passed an existing location ID or location slug.";
    }

    return $menu;
}

/**
 * Retrieve items for a specific menu
 * @return array List of menu items
 */
function wp_api_v2_menus_get_menu_items($id_or_slug) {
    if (is_int($id_or_slug)) {
        $id = $id_or_slug;
    } else {
        $id = wp_get_nav_menu_object($id_or_slug);
    }
    $menu_items = wp_get_nav_menu_items($id);

    // wordpress does not group child menu items with parent menu items
    $child_items = [];
    // pull all child menu items into separate object
    foreach ($menu_items as $key => $item) {
        if ($item->menu_item_parent) {
            array_push($child_items, $item);
            unset($menu_items[$key]);
        }
    }

    // push child items into their parent item in the original object
    foreach ($menu_items as $item) {
        foreach ($child_items as $key => $child) {
            if ($child->menu_item_parent == $item->post_name) {
                if (!$item->child_items) $item->child_items = [];
                array_push($item->child_items, $child);
                unset($child_items[$key]);
            }
        }
    }

    // check if there is acf installed
    if (class_exists('acf')) {
        foreach ($menu_items as $menu_key => $menu_item) {
            $fields = get_fields($menu_item->ID);
            if (!empty($fields)) {
                foreach ($fields as $field_key => $item) {
                    // add all acf custom fields
                    $menu_items[$menu_key]->$field_key = $item;
                }
            }
        }
    }
    return $menu_items;
}

/**
 * Get menu's data from his id.
 *    It ensures compatibility for previous versions when this endpoint
 *    was allowing locations id in place of menus id)
 *
 * @param  array $data WP REST API data variable
 * @return object Menu's data with his items
 */
function wp_api_v2_menus_get_menu_data ( $data ) {
    // This ensure retro compatibility with versions `<= 0.5` when this endpoint
    //   was allowing locations id in place of menus id
    if (has_nav_menu($data['id'])) {
        $menu = wp_api_v2_locations_get_menu_data($data);
    } else if (is_nav_menu($data['id'])) {
        $menu = new stdClass;
        $menu->items = wp_api_v2_menus_get_menu_items($data['id']);
    } else {
        $menu = new stdClass;
        $menu->items = [];
        $menu->error = "No menu has been found. Please ensure you passed an existing menu ID, menu slug, location ID or location slug.";
    }

    return $menu;
}

add_action('rest_api_init', function () {
    register_rest_route('menus/v1', '/menus', array(
        'methods' => 'GET',
        'callback' => 'wp_api_v2_menus_get_all_menus',
    ) );

    register_rest_route('menus/v1', '/menus/(?P<id>[a-zA-Z0-9_-]+)', array(
        'methods' => 'GET',
        'callback' => 'wp_api_v2_menus_get_menu_data',
    ) );

    register_rest_route('menus/v1', '/locations/(?P<id>[a-zA-Z0-9_-]+)', array(
        'methods' => 'GET',
        'callback' => 'wp_api_v2_locations_get_menu_data',
    ) );

    register_rest_route('menus/v1', '/locations', array(
        'methods' => 'GET',
        'callback' => 'wp_api_v2_menu_get_all_locations',
    ) );
} );