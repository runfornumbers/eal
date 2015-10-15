<?php
/*
 Plugin Name: E-Assessment Literacy
 Plugin URI: http:///
 Description: Plugin for E-Assessment Literacy. It delivers several custom post types (items, reviews) and setting pages.
 Version: 1.0
 Author: Andreas Thor
 EMail: thor@hft-leipzig.de
 */


/*
 * Definition of custom post types
 * All ids must be less than 20 characters!
 */
 
include_once 'includes/eal_item_sc.php';
include_once 'includes/eal_item_mc.php';

$GLOBALS["eal_itemtypes"] = [
		'eal_item_sc' => 'Single Choice',
		'eal_item_mc' => 'Multiple Choice'
];



/**
 * Add menu entries 
 * - items
 * - review
 */

add_action ('admin_menu', 'set_eal_admin_menu_entries');

function set_eal_admin_menu_entries () {
	
	
	
	
	
	/* remove standard menu entries */
	
	remove_menu_page( 'index.php' );                  //Dashboard
	remove_menu_page( 'edit.php' );                   //Posts
	remove_menu_page( 'upload.php' );                 //Media
	remove_menu_page( 'edit.php?post_type=page' );    //Pages
	remove_menu_page( 'edit-comments.php' );          //Comments
	remove_menu_page( 'themes.php' );                 //Appearance
	remove_menu_page( 'plugins.php' );                //Plugins
	remove_menu_page( 'users.php' );                  //Users
	remove_menu_page( 'tools.php' );                  //Tools
	remove_menu_page( 'options-general.php' );        //Settings	

	add_menu_page('eal_page_items', 'Items', 'administrator', 'eal_page_items', 'create_eal_page_items', '', 1);
	add_menu_page('eal_page_taxonomies', 'Taxonomies', 'administrator', 'eal_page_taxonomies', 'create_eal_page_taxonomies', '', 30);
	add_submenu_page( 'eal_page_taxonomies', 'Topic', 'Topic', 'edit_others_posts', 'edit-tags.php?taxonomy=topic');

}


// highlight the proper top level menu
add_action('parent_file', 'set_eal_taxonomies_menu_correction');
function set_eal_taxonomies_menu_correction($parent_file) {
	global $current_screen;
	$taxonomy = $current_screen->taxonomy;
	if ($taxonomy == 'topic')
		$parent_file = 'eal_page_taxonomies';
	return $parent_file;
}


/**
 * Page "Items" has
 * - Buttons to add new items of different types
 * - TODO: List of all items incl. bulk operations
 */

function create_eal_page_items () {
	
	$html  = '
		<div class="wrap">
			<form action="options.php" method="post" name="options">
				<h2>Items';
	
	foreach ($GLOBALS["eal_itemtypes"] as $id => $name) {
		$html .= '<a class="add-new-h2" href="post-new.php?post_type=' . $id . '">Add ' . $name . '</a>';
	}
	
	$html.= '	</h2>
			</form>
		</div>';
			
	echo $html;
}



function create_eal_page_taxonomies () {
	
}

/**
 * Add custom post types
 * - eal_item_mc1n: Multiple Choice 1 out of N
 * - eal_item_mcnm: Multiple Choice M out of N
 */
 
add_action( 'init', 'create_eal_items' );
function create_eal_items() {
	
	foreach ($GLOBALS["eal_itemtypes"] as $id => $name) {
		
// 		$currentmenupos++;
		register_post_type( $id,
				array(
						'labels' => array(
								'name' => $name,
								'singular_name' => $name,
								'add_new' => 'Add ' . $name,
								'add_new_item' => 'Add New ' . $name,
								'edit' => 'Edit',
								'edit_item' => 'Edit ' . $name,
								'new_item' => 'New ' . $name,
								'view' => 'View',
								'view_item' => 'View ' . $name,
								'search_items' => 'Search ' . $name,
								'not_found' => 'No Items found',
								'not_found_in_trash' => 'No Items found in Trash',
								'parent' => 'Parent Item'
						),
	
						'public' => true,
						'menu_position' => 2,
						'supports' => array( 'title'), // 'editor', 'comments'), // 'thumbnail', 'custom-fields' ),
						'taxonomies' => array( 'topic' ),
						'menu_icon' => plugins_url( 'images/image.png', __FILE__ ),
						'has_archive' => true,
						'show_in_menu'    => true,
						'register_meta_box_cb' => $id . '_add_meta_boxes'
				)
		);
		
		add_action ('save_post_' . $id, $id . "_save_post");
		
		
		
	}
}


add_action( 'init', 'create_eal_taxonomies', 0 );
function create_eal_taxonomies () {
	
	// Add new taxonomy, make it hierarchical (like categories)
	$labels = array (
			'name' => _x ( 'Topics', 'taxonomy general name' ),
			'singular_name' => _x ( 'Topic', 'taxonomy singular name' ),
			'search_items' => __ ( 'Search Topics' ),
			'all_items' => __ ( 'All Topics' ),
			'parent_item' => __ ( 'Parent Topic' ),
			'parent_item_colon' => __ ( 'Parent Topic:' ),
			'edit_item' => __ ( 'Edit Topic' ),
			'update_item' => __ ( 'Update Topic' ),
			'add_new_item' => __ ( 'Add New Topic' ),
			'new_item_name' => __ ( 'New Topic Name' ),
			'menu_name' => __ ( 'Topic' ) 
	);
	
	$args = array (
			'hierarchical' => true,
			'labels' => $labels,
			'show_ui' => true,
			'show_admin_column' => true,
			'query_var' => true,
			'rewrite' => array ( 'slug' => 'topic' ) 
	);
	
	register_taxonomy ( 'topic', $GLOBALS["eal_itemtypes"] , $args );		
}


// add_action ('add_meta_boxes_eal_item_mc', 'create_eal_edit_forms');
// function create_eal_edit_forms() {
	
// 	add_meta_box('wpt_events_location', 'Description', 'desc_editor', 'eal_item_sc', 'normal', 'default');

// }






?>