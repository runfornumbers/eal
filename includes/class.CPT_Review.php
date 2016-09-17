<?php

// TODO: Delete Review (in POst Tabelle) --> l�schen in Review-Tabelle

require_once ("class.CPT_Object.php");
require_once ("class.EAL_Review.php");

class CPT_Review extends CPT_Object {

	public $table_columns = array (
		'cb' => '<input type="checkbox" />', 
		'item_title' => 'Title', 
		'date' => 'Date', 
		'item_type' => 'Type', 
		'review_author' => 'Author Review', 
		'item_author' => 'Author Item', 
		'score' => 'Score', 
		'change_level' => 'Level', 
		'overall' => 'Overall'
	);
	

	/*
	 * #######################################################################
	 * post type registration; specification of page layout
	 * #######################################################################
	 */
	
	public function init($args = array()) {

		$this->menu_pos = -1;
		$this->type = "review";
		$this->label = "Item Review";
		parent::init(array ('supports' => false, 'taxonomies' => array()));
		
		
		

		// TODO: delete review
		
		add_filter('post_updated_messages', array ($this, 'WPCB_post_updated_messages') );
	}
	
	
	
	function add_bulk_actions() {
	
		global $post_type;
		if ($post_type != $this->type) return;
	
		parent::add_bulk_actions();
	
?>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery("select[name='action'] > option[value='add_to_basket']").remove();
					jQuery("select[name='action2'] > option[value='add_to_basket']").remove();
	      });
			</script>
			
<?php
	}
		
	
	
	public function WPCB_register_meta_box_cb () {
	
		global $review;
		
		$review = new EAL_Review();
		$review->load();
		
		if ($review->getItem()->domain != RoleTaxonomy::getCurrentDomain()["name"]) {
			wp_die ("Reviewed item does not belong to your current domain!");
		}
		
		add_meta_box('mb_item', 'Item: ' . $review->getItem()->title, array ($this, 'WPCB_mb_item'), $this->type, 'normal', 'default' );
		add_meta_box('mb_score', 'Fall- oder Problemvignette, Aufgabenstellung und Antwortoptionen', array ($this, 'WPCB_mb_score'), $this->type, 'normal', 'default' );
		add_meta_box('mb_level', 'Anforderungsstufe', array ($this, 'WPCB_mb_level'), $this->type, 'normal', 'default', array ('level' => $review->level, 'prefix' => 'review', 'default' => $review->getItem()->level, 'background' => 1 ));
		add_meta_box('mb_feedback', 'Feedback', array ($this, 'WPCB_mb_editor'), $this->type, 'normal', 'default', array ('name' => 'review_feedback', 'value' => $review->feedback));
		add_meta_box('mb_overall', 'Revisionsurteil', array ($this, 'WPCB_mb_overall'), $this->type, 'side', 'default');
	}
	
	
	
	

	
	public function WPCB_mb_item ($post, $vars) {
	
		global $review;
		if (!is_null($review->getItem())) {
			$html = $review->getItem()->getPreviewHTML();
			echo $html;
		}
	}
	
	
	

	
	public function WPCB_mb_score ($post, $vars) {
		
		global $review;
		
		$values = ["gut", "Korrektur", "ungeeignet"];
		
		
		$html_head = "<tr><th></th>";
		foreach (EAL_Review::$dimension2 as $k2 => $v2) {
			$html_head .= "<th style='padding:0.5em'>{$v2}</th>";
		}
		$html_head .= "</tr>";
			
		
?>
		<script>
			var $ = jQuery.noConflict();
			
			function setRowGood (e) {
				$(e).parent().parent().find("input").each ( function() {
 					if (this.value==1) this.checked = true;
				});
			}
		</script>


<?php 
		
		$html_rows = "";
		foreach (EAL_Review::$dimension1 as $k1 => $v1) {
			$html_rows .= "<tr><td valign='top'style='padding:0.5em'>{$v1}<br/><a onclick=\"setRowGood(this);\">(alle gut)</a></td>";
			foreach (EAL_Review::$dimension2 as $k2 => $v2) {
				$html_rows .= "<td style='padding:0.5em; border-style:solid; border-width:1px;'>";
				foreach ($values as $k3 => $v3) {
					$html_rows .= "<input type='radio' id='{$k1}_{$k2}_{k3}' name='review_{$k1}_{$k2}' value='" . ($k3+1) . "' " . (($review->score[$k1][$k2]==$k3+1)?"checked":"") . ">{$v3}<br/>";
				}
				$html_rows .= "</td>";
			}
			$html_rows .= "</tr>";
		}
				
		echo ("<table style='font-size:100%'>{$html_head}{$html_rows}</table>");
			
	}
	
	
	
// 	public function WPCB_mb_level ($post, $vars) {
	
// 		global $review;
	
// 		echo ("	<table style='font-size:100%'>
// 				<tr><th align='left'>Einordnung Autor</th><th style='padding-left:3em;'></th><th align='left'>Einordnung Review</th></tr>
// 				<tr><td style='border-style:solid; border-width:1px;'>");
// 		parent::WPCB_mb_level($post, array ('args' => array ('level' => $review->getItem()->level, 'disabled' => 'disabled')));
// 		echo ("	</td><td style='padding-left:3em;'></td><td style='border-style:solid; border-width:1px;''>");
// 		parent::WPCB_mb_level($post, array ('args' => array ('level' => $review->level, 'prefix' => 'review')));
		
// 		parent::WPCB_mb_level($post, array ('args' => array ('level' => $review->level, 'prefix' => 'review', 'default' => $review->getItem()->level, 'background' => 1 )));
		
// 		echo ("</td></tr></table>");
// 	}
	
	

	
// 	public function WPCB_mb_feedback ($post, $vars) {
	
// 		global $review;
	
// 		$editor_settings = array(
// 				'media_buttons' => false,	// no media buttons
// 				'teeny' => true,			// minimal editor
// 				'quicktags' => false,		// hides Visual/Text tabs
// 				'textarea_rows' => 3,
// 				'tinymce' => true
// 		);
	
// 		$html = wp_editor(wpautop(stripslashes($review->feedback)) , 'review_feedback', $editor_settings );
// 		echo $html;
// 	}
	
	

	public function WPCB_mb_overall ($post, $vars) {
	
		global $review;
?>
		<script>
			var $ = jQuery.noConflict();
			
			function setAccept () {
				if (confirm('Sollen alle Bewertungen auf "gut" gesetzt werden?')) {
					$(document).find("#mb_score").find("input").each ( function() {
	 					if (this.value==1) this.checked = true;
					});
				}
			}
		</script>


<?php 
		printf ("<input type='hidden' id='item_id' name='item_id'  value='%d'>", $review->item_id);
		print  ("<table style='font-size:100%'>");
		printf ("<tr><td><input type='radio' id='review_overall_0' name='review_overall' value='1' %s onclick='setAccept();'>Item akzeptiert</td></tr>", (($review->overall==1) ? "checked" : ""));
		printf ("<tr><td><input type='radio' id='review_overall_1' name='review_overall' value='2' %s>Item &uuml;berarbeiten</td></tr>", (($review->overall==2) ? "checked" : ""));
		printf ("<tr><td><input type='radio' id='review_overall_2' name='review_overall' value='3' %s>Item abgelehnt</td></tr>", (($review->overall==3) ? "checked" : ""));
		print  ("</table>");
	}
	
	
	
	
	public function WPCB_posts_fields ( $array ) {
		global $wp_query, $wpdb;
		if ($wp_query->query["post_type"] == $this->type) {
			$array .= ", I.title as item_title";
			$array .= ", I.type as item_type";
			$array .= ", UI.user_login as item_author";
			$array .= ", UI.id as item_author_id";
			$array .= ", UR.user_login as review_author";				
			$array .= ", UR.id as review_author_id";
			$array .= ", ABS(COALESCE(I.level_FW,0)-COALESCE(R.level_FW,0))+ABS(COALESCE(I.level_KW,0)-COALESCE(R.level_KW,0))+ABS(COALESCE(I.level_PW,0)-COALESCE(R.level_PW,0)) AS change_level";
			$array .= ", R.overall";
				
		}
		return $array;
	}
	
	
	
	public function WPCB_posts_join ($join) {
		global $wp_query, $wpdb;
		if ($wp_query->query["post_type"] == $this->type) {
			$join .= " JOIN {$wpdb->prefix}eal_{$this->type} AS R ON (R.id = {$wpdb->posts}.ID) ";
			$join .= " JOIN {$wpdb->prefix}eal_item AS I ON (I.id = R.item_id AND I.domain = '" . RoleTaxonomy::getCurrentDomain()["name"] . "')";
			$join .= " JOIN {$wpdb->posts} AS postitem ON (I.id = postitem.id) ";
			$join .= " JOIN {$wpdb->users} UI ON (UI.id = postitem.post_author) ";
			$join .= " JOIN {$wpdb->users} UR ON (UR.id = {$wpdb->posts}.post_author) ";
		}
		return $join;
	}
	
	
	public function WPCB_posts_orderby($orderby_statement) {
	
		global $wp_query, $wpdb;
	
		// 		$orderby_statement = parent::WPCB_posts_orderby($orderby_statement);
	
		if ($wp_query->query["post_type"] == $this->type) {
			if ($wp_query->get( 'orderby' ) == "Title")		 	$orderby_statement = "I.title " . $wp_query->get( 'order' );
			if ($wp_query->get( 'orderby' ) == "Date")		 	$orderby_statement = "{$wpdb->posts}.post_date " . $wp_query->get( 'order' );
			if ($wp_query->get( 'orderby' ) == "Type") 			$orderby_statement = "I.type " . $wp_query->get( 'order' );
			if ($wp_query->get( 'orderby' ) == "Author Review")	$orderby_statement = "UR.user_login " . $wp_query->get( 'order' );
			if ($wp_query->get( 'orderby' ) == "Author Item") 	$orderby_statement = "UI.user_login " . $wp_query->get( 'order' );
			if ($wp_query->get( 'orderby' ) == "Level") 		$orderby_statement = "change_level " . $wp_query->get( 'order' );
			if ($wp_query->get( 'orderby' ) == "Overall") 		$orderby_statement = "R.overall " . $wp_query->get( 'order' );
		}
	
		return $orderby_statement;
	}
	
	
	
	public function WPCB_posts_where($where) {
	
		global $wp_query, $wpdb;
	
		$where = parent::WPCB_posts_where($where);
	
		if ($wp_query->query["post_type"] == $this->type) {
			if (isset($_REQUEST["item_id"])) {
				$where .= " AND R.item_id = {$_REQUEST['item_id']}";
			}
		}

		return $where;
	}
	
	
// 	public function WPCB_manage_posts_columns($columns) {
// 		return array_merge($columns, array('type' => 'Typ', 'review_author' => 'Author Review', 'item_author' => 'Author Item', 'score' => 'Score', 'level' => 'Level', 'overall' => 'Overall'));
// // 		return array_merge(parent::WPCB_manage_posts_columns($columns), array('item_author' => 'Author Item', 'item_title' => 'item_title', 'type' => 'Typ', 'Punkte' => 'Punkte', 'Reviews' => 'Reviews', 'LO' => 'LO', 'Difficulty' => 'Difficulty'));
// 	}
	
	
	public function WPCB_post_row_actions($actions, $post){
	
		if ($post->post_type != $this->type) return $actions;
	
		unset ($actions['inline hide-if-no-js']);			// remove "Quick Edit"
		$actions['view'] = "<a href='admin.php?page=view&itemid={$post->ID}'>View</a>"; // add "View"
	
		if (!RoleTaxonomy::canEditReviewPost($post)) {		// "Edit" & "Trash" only if editable by user
			unset ($actions['edit']);
			unset ($actions['trash']);
		}
	
		return $actions;
	}
	
	

	
	
	
	
	public function WPCB_post_updated_messages ( $messages ) {
	
		global $post, $post_ID;
		$messages[$this->type] = array(
				0 => '',
				1 => sprintf( __("{$this->label} updated. <a href='%s'>View {$this->label}</a>"), esc_url( get_permalink($post_ID) ) ),
				2 => __('Custom field updated.'),
				3 => __('Custom field deleted.'),
				4 => __("{$this->label} updated."),
				5 => isset($_GET['revision']) ? sprintf( __("{$this->label} restored to revision from %s"), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6 => sprintf( __("{$this->label} published. <a href='%s'>View {$this->label}</a>"), esc_url( get_permalink($post_ID) ) ),
				7 => __("{$this->label} saved."),
				8 => sprintf( __("{$this->label} submitted. <a target='_blank' href='%s'>Preview {$this->label}</a>"), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
				9 => sprintf( __("{$this->label} scheduled for: <strong>%1$s</strong>. <a target='_blank' href='%2$s'>View {$this->label}</a>"), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
				10 => sprintf( __("{$this->label} draft updated. <a target='_blank' href='%s'>Preview {$this->label}</a>"), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
				);
		return $messages;
	}
	
	
}

?>