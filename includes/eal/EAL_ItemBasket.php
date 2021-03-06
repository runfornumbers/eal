<?php

require_once(__DIR__ . "/../class.CLA_RoleTaxonomy.php");

class EAL_ItemBasket {
	
	
	/**
	 * @return array of item ids
	 */
	public static function get (): array {
		$itemids = get_user_meta(get_current_user_id(), 'itembasket_' . RoleTaxonomy::getCurrentRoleDomain()["name"], true);
		if ($itemids == null) $itemids = array ();
		return self::set($itemids);	// set checks for deleted items
	}
	
	/**
	 * @param $itemids 
	 */
	private static function set (array $itemids): array {
		
		global $wpdb;
		
		// consider only existing (published/pending/draft) items
		if (count($itemids)>0) { 
			$join = join(", ", $itemids);	
			$sql  = "SELECT DISTINCT P.id FROM {$wpdb->prefix}eal_item I JOIN {$wpdb->prefix}posts P ON (P.ID = I.ID) 
				WHERE P.post_parent = 0 AND P.post_status IN ('publish', 'pending', 'draft') AND I.id IN ({$join}) 
				AND I.domain = '" . RoleTaxonomy::getCurrentRoleDomain()["name"] . "'";
			$itemids = $wpdb->get_col ($sql);
					
		}
		
		// convert into array of integers
		$result = array();
		foreach ($itemids as $id) {
			$result[] = intval($id);
		}
		
		update_user_meta (get_current_user_id(), 'itembasket_' . RoleTaxonomy::getCurrentRoleDomain()["name"], $result);
		return $result;
	}

	/**
	 * removes items from basket
	 * @param $remove array of item ids to be removed
	 */
	public static function remove (array $remove) {
		self::set (array_diff (self::get(), $remove));
	}
	
	/**
	 * adds items to the basket
	 * @param $add array of 
	 */
	public static function add (array $add) {
		self::set (array_merge(self::get(), $add));
	}
	
	
	/**
	 * 
	 * @return array of items
	 */
	public static function getItems (): array {
	
		// load all items from basket
		$items = [];
		foreach (self::get() as $item_id) {
				
			$post = get_post($item_id);
			if ($post == null) continue;
			if ($post->post_status == 'trash') continue;
				
			try {
				$items[$item_id] = DB_Item::loadFromDB($item_id, $post->post_type);
			} catch (Exception $e) {
				// FIXME: exception handling
			}
		}
		return $items;
	}
	
	
}
?>