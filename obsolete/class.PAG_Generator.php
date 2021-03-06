<?php 

require_once ("../includes/eal/EAL_Item.php");
require_once ("class.PAG_Basket.php");
require_once ("class.PAG_Explorer.php");

class PAG_Generator {

	
	
	private $items;
	private $item_vectors;
	
	// each entry represents ONE specific category value
	private $MASK;
	private $min;
	private $max;
	
	private $overlap;
	
	private $maxPools;
	private $countPools;
	private $allPools;
	
	
	public $generatedPools;
	
	
	/**
	 *
	 * @param unknown $items
	 * @param unknown $min array of min values in order: (overall number, type_itemsc, type_itemmc, dim_FW, dim_KW, dim_PW, level_1, level_2, level_3, level_4, level_5, level_6)
	 * @param unknown $max array of max values (see above)
	 * @param array overlap determines min/max overlap of pools (min=$overlap[0], max=overlap[1])
	 */
	function __construct($items, $min, $max, $overlap, $topic1, $min_topic1, $max_topic1) {
	
		$this->maxPools = 10;
		$this->countPools = 0;
		$this->allPools = array();
		
		$this->items = array_values ($items);	// make sure items are indexed for 0 to n-1
			
		// generate MASKs
		$this->MASK = array(array(), array());
			for ($i=0; $i<count($min); $i++) {
			array_push ($this->MASK[0], 1 << $i);
		}
		for ($i=0; $i<count($topic1); $i++) {
			array_push ($this->MASK[1], 1 << $i);
		}
		
		$this->item_vectors = array(array (), array());
		$remaining = array();
		$size = count($this->items);
		foreach ($this->items as $idx => $item) {
			array_push ($remaining, $idx);	// reverse order since we pop later
			$vector = 1;	// = += $this->MASK[0]
			if ($item->type == "itemsc") $vector += $this->MASK[0][1];
			if ($item->type == "itemmc") $vector += $this->MASK[0][2];
			if ($item->level["FW"] > 0)  $vector += $this->MASK[0][3];
			if ($item->level["KW"] > 0)  $vector += $this->MASK[0][4];
			if ($item->level["PW"] > 0)  $vector += $this->MASK[0][5];
			for ($level=1; $level<=6; $level++) {
				if (($item->level["FW"]==$level) || ($item->level["KW"]==$level) || ($item->level["PW"]==$level))  $vector += $this->MASK[0][$level+5];
			}
			array_push($this->item_vectors[0], $vector);
				
			$vector = 0;
			$termids = PAG_Explorer::getValuesByKey("topic1", $item, true, true);
			for ($pos=0; $pos<count($topic1); $pos++) {
				if (in_array ($topic1[$pos], $termids)) $vector += $this->MASK[1][$pos];
			}
			array_push($this->item_vectors[1], $vector);
				
		}
	
		$values_current = array(array(), array());
		$values_remaining = array(array(), array());
		
		for ($idx=0; $idx<count($min); $idx++) {
			array_push ($values_current[0], 0);
			$val_remaining = 0;
			foreach ($remaining as $item) {
				if (($this->item_vectors[0][$item] & $this->MASK[0][$idx]) > 0) $val_remaining++;
			}
			array_push($values_remaining[0], $val_remaining);
		}
		
		for ($idx=0; $idx<count($topic1); $idx++) {
			array_push ($values_current[1], 0);
			$val_remaining = 0;
			foreach ($remaining as $item) {
				if (($this->item_vectors[1][$item] & $this->MASK[1][$idx]) > 0) $val_remaining++;
			}
			array_push($values_remaining[1], $val_remaining);
		}
		
	
		$this->min = array ($min, $min_topic1);
		$this->max = array ($max, $max_topic1);
		$this->overlap = $overlap;
	
		$this->addOneItem (array(), $remaining, $values_current, $values_remaining);
	
		
		$this->generatedPools = array ();
		foreach ($this->allPools as $pool) {
			$newPool = array();
			foreach ($pool as $item_pos) {
				array_push ($newPool, $this->items[$item_pos]->id);
			}
			array_push ($this->generatedPools, $newPool);
		}
		
	}
	
	
	/**
	 * 
	 * @param unknown $current array of item indexes, represents the currently generated pool 
	 * @param unknown $remaining array of item indexes that are not in $current, i.e., $current + $remaining = $items and $current * $remaining = 0 
	 * @param unknown $values_current
	 * @param unknown $values_remaining
	 */
	public function addOneItem ($current, $remaining, $values_current, $values_remaining) {
	
		if ($this->countPools >= $this->maxPools) return;
	
		/* check conditions */
		$allTrue = TRUE;
		
		// check for overlap
		foreach ($this->allPools as $p) {
			$inter = array_intersect($current, $p);
			
			if ($this->overlap[1] < count($inter)) {
				return;	// maximum overlap exceeded
			}
			
			if ($this->overlap[0] > count($inter)) {
				if ($this->overlap[0] > (count($inter) + count($remaining))) {
// 					return; 	// minimum overlap can not be reached anymore
				}
				$allTrue = FALSE;	// minimum overlap not yet reached
			}
		}
		
		
		
		for ($maskType=0; $maskType<2; $maskType++) {
			for ($idx=0; $idx<count($this->MASK[$maskType]); $idx++) {
	
				// Invalid pool -> Greater than maximum
				if ($this->max[$maskType][$idx] < $values_current[$maskType][$idx]) {
					return;	
				}
	
				if ($this->min[$maskType][$idx] > $values_current[$maskType][$idx]) { 	// minimum currently not yet reached
					// Prune search space -->  minimum can not be reached anamore
					if ($this->min[$maskType][$idx] > ($values_current[$maskType][$idx]+$values_remaining[$maskType][$idx]) ) {
						return; 	
					}
					$allTrue = FALSE;	// condition not yet satisfied (below minimum)
				}
			}
		}

		
		if ($allTrue) {
			// $current is a valid pool (according to min/max ranges)
			$this->countPools++;
			array_push ($this->allPools, $current);
			if ($this->countPools >= $this->maxPools) return;
		}
	
	
		while (count($remaining)>0) {
				
			
			// determin the best idx for next
			$bestIdx = 0;
			$bestMaskType = 0;
			$bestBenefit = -1;
			for ($maskType=0; $maskType<2; $maskType++) {	// only Topics considered!!!
				for ($idx=0; $idx<count($this->MASK[$maskType]); $idx++) {
					if (($values_remaining[$maskType][$idx]>0) && ($this->min[$maskType][$idx] > $values_current[$maskType][$idx])) {
						if ($bestBenefit < ($this->min[$maskType][$idx] - $values_current[$maskType][$idx])/$values_remaining[$maskType][$idx]) {
							$bestBenefit = ($this->min[$maskType][$idx] - $values_current[$maskType][$idx])/$values_remaining[$maskType][$idx];
							$bestIdx = $idx;
							$bestMaskType = $maskType;
						}
					}
				}
			}
				
			// determine first item where MASK[best_idx] ist set 
			foreach ($remaining as $key => $value) {
				if (($this->item_vectors[$bestMaskType][$value] & $this->MASK[$bestMaskType][$bestIdx]) > 0) {
					$toRemove = $key;
					break;
				}
			}
			
/*			
			$toRemove = -1;
			$bestScore = 0;
			foreach ($remaining as $key => $value) {
				
				$score = 0;
				for ($maskType=0; $maskType<2; $maskType++) {
					for ($idx=0; $idx<count($this->MASK[$maskType]); $idx++) {
				
						if (($this->item_vectors[$maskType][$value] & $this->MASK[$maskType][$idx]) > 0) {
							if ($values_current[$maskType][$idx] < $this->min[$maskType][$idx]) {
								$score = $score+1;
							} else {
								$score = $score-1;
							}
							
							if ($values_current[$maskType][$idx] >= $this->max[$maskType][$idx]) {
								$score = $score - 1000000;
							}
						}
					}
				}
				
				if (($score>$bestScore) || ($toRemove==-1)) {
					$toRemove = $key;
					$bestScore = $score;
				}
				
			}
*/			
// 			error_log("Current is " . print_r($current, true) . "; adding " . $remaining[$toRemove], 0);
// 			error_log("Current");
				
			
			$add = $remaining[$toRemove];	// item that will be added to the pool
			unset($remaining[$toRemove]);
				
			$remaining_new = (new ArrayObject($remaining))->getArrayCopy();
			$current_new = (new ArrayObject($current))->getArrayCopy();
				
			array_push($current_new, $add);
				
			$values_current_new = array(array(), array());
			$values_remaining_new = array(array(), array());
				
			for ($maskType=0; $maskType<2; $maskType++) {
				for ($idx=0; $idx<count($this->MASK[$maskType]); $idx++) {
		
					if (($this->item_vectors[$maskType][$add] & $this->MASK[$maskType][$idx]) > 0) {
						array_push ($values_current_new[$maskType], $values_current[$maskType][$idx]+1);
						array_push ($values_remaining_new[$maskType], $values_remaining[$maskType][$idx]-1);
						$values_remaining[$maskType][$idx] = $values_remaining[$maskType][$idx]-1;
					} else {
						array_push ($values_current_new[$maskType], $values_current[$maskType][$idx]);
						array_push ($values_remaining_new[$maskType], $values_remaining[$maskType][$idx]);
					}
				}
			}
			$this->addOneItem($current_new, $remaining_new, $values_current_new, $values_remaining_new);
		}
	
	
	
	
	}
	
	
	
	
	
	
	
	
	
	
	private static function minMaxField ($name, $max) {
		
		/* set/get values to/from Session Variable */
		$_SESSION['min_' . $name] = isset($_REQUEST['min_' . $name]) ? $_REQUEST['min_' . $name] : (isset($_SESSION['min_' . $name]) ? min ($_SESSION['min_' . $name], $max) : 0);
		$_SESSION['max_' . $name] = isset($_REQUEST['max_' . $name]) ? $_REQUEST['max_' . $name] : (isset($_SESSION['max_' . $name]) ? min ($_SESSION['max_' . $name], $max) : $max);
		
		/* generate HTML for min and max input */
		$html  = sprintf("<td style='vertical-align:top; padding-top:0px; padding-bottom:0px;'>");
		$html .= sprintf("<input style='width:5em' type='number' name='min_%s' min='0' max='%d' value='%d'/>", $name, $max, $_SESSION['min_' . $name]);
		$html .= sprintf("<input style='width:5em' type='number' name='max_%s' min='0' max='%d' value='%d'/>", $name, $max, $_SESSION['max_' . $name]);
		$html .= sprintf("</td>");
		return $html;
	}
	
	
	
	public static function createPage () {
	
		$items = PAG_Basket::loadAllItemsFromBasket ();

		$html  = sprintf("<form  enctype='multipart/form-data' action='admin.php?page=generator' method='post'><table class='form-table'><tbody'>");
		
		$html .= sprintf("<tr><th style='padding-top:0px; padding-bottom:0px;'><label>%s</label></th>", "Number of Items");
		$html .= PAG_Generator::minMaxField("number", count($items));
		$html .= sprintf("</tr>");
		
		$html .= sprintf("<tr><td style='vertical-align:top; padding-top:0px; padding-bottom:0px;'><label>%s</label></td>", "Overlap");
		$html .= PAG_Generator::minMaxField("overlap", count($items));
		$html .= sprintf("</tr>");
		$html .= sprintf("<tr><td style='vertical-align:top; padding-top:0px; padding-bottom:0px;'><button type='button' onclick='
				
				if (this.innerText == \"Select ...\") {
					this.innerText = \"Close\";
					this.parentNode.nextSibling.firstChild.style.display=\"block\";
				} else {
					this.innerText = \"Select ...\";
					this.parentNode.nextSibling.firstChild.style.display=\"none\";
				}
				
				'>Select ...</button></td>");
		$html .= ("<td><div style='display:none'>");
		foreach ($items as $i) $html .= sprintf ("<input type='checkbox' name='overlap_items[]' value='%d'><label style='vertical-align:top'>%s</label><br/>", $i->id, $i->title);
		$html .= sprintf("</div></td></tr>");
		
		// Min / Max for all categories
		$categories = array ("type", "dim", "level", "topic1");
		foreach ($categories as $category) {
			
			$html .= sprintf("<tr><th style='padding-bottom:0.5em;'><label>%s</label></th></tr>", EAL_Item::$category_label[$category]);
			foreach (PAG_Explorer::groupBy ($category, $items, NULL, true, true) as $catval => $catitems) {
				
				$html .= sprintf("<tr><td style='padding-top:0px; padding-bottom:0px;'><label>%s</label></td>", ($category == "topic1") ? get_term($catval, RoleTaxonomy::getCurrentRoleDomain()["name"])->name : EAL_Item::$category_value_label[$category][$catval]);
				$html .= PAG_Generator::minMaxField($category . "_" . $catval, count($catitems));
				$html .= sprintf("</tr>");
			}
			
		}
		
		$html .= sprintf ("<tr><th><button type='submit' name='action' value='generate'>Generate</button></th><tr>");
		$html .= sprintf ("</tbody></table></form></div>");			

		
		
		// (re-)generate pools
		if ($_REQUEST['action'] == 'generate') {
			
			$topic1 = array();
			$min_topic1 = array();
			$max_topic1 = array(); 
			
			foreach (PAG_Explorer::groupBy ("topic1", $items, NULL, true, true) as $catval => $catitems) {
				array_push($topic1, $catval);
				array_push($min_topic1, $_SESSION["min_topic1" . "_" . $catval]);
				array_push($max_topic1, $_SESSION["max_topic1" . "_" . $catval]);
			}
			
			$pool = new PAG_Generator(
				$items,
				array (	$_SESSION['min_number'], $_SESSION['min_type_itemsc'], $_SESSION['min_type_itemmc'], 
						$_SESSION['min_dim_FW'], $_SESSION['min_dim_KW'], $_SESSION['min_dim_PW'],
						$_SESSION['min_level_1'], $_SESSION['min_level_2'], $_SESSION['min_level_3'], $_SESSION['min_level_4'], $_SESSION['min_level_5'], $_SESSION['min_level_6']),
				array (	$_SESSION['max_number'], $_SESSION['max_type_itemsc'], $_SESSION['max_type_itemmc'],
						$_SESSION['max_dim_FW'], $_SESSION['max_dim_KW'], $_SESSION['max_dim_PW'],
						$_SESSION['max_level_1'], $_SESSION['max_level_2'], $_SESSION['max_level_3'], $_SESSION['max_level_4'], $_SESSION['max_level_5'], $_SESSION['max_level_6']),
				array ( $_SESSION['min_overlap'], $_SESSION['max_overlap']),
				$topic1, $min_topic1, $max_topic1
			);
			
			$_SESSION['generated_pools'] = $pool->generatedPools;
		}
		
		
		print ("<div class='wrap'>");
		print ("<h1>Task Pool Generator</h1><br/>");
		print ($html);
		
		// show pools (from last generation; stored in Session variable)
		if (isset ($_SESSION['generated_pools'])) {
// 			print_r ($_SESSION['generated_pools']);
				
			print ("<br/><h2>Generated Task Pools</h2>");
			printf ("<table cellpadding='10px' class='widefat fixed' style='table-layout:fixed; width:%dem; background-color:rgba(0, 0, 0, 0);'>", 6+2*count($items)); 
			print ("<col width='6em;' />");
			
			foreach ($items as $item) {
				print ("<col width='2em;' />");
			}
			
			foreach ($_SESSION['generated_pools'] as $pool) {
				print ("<tr valign='middle'>");
				$s = "View";
				$href = "admin.php?page=view&itemids=" . join (",", $pool);
				printf ("<td style='overflow: hidden; padding:0px; padding-bottom:0.5em; padding-top:0.5em; padding-left:1em' ><a href='%s' class='button'>View</a></td>", $href);
				
				// http://localhost/wordpress/wp-admin/admin.php?page=view&itemids=458,307,307,106
				foreach ($items as $item) {
					
					$symbol = "";
					$link = "";
					if (in_array($item->id, $pool)) {
						$link = sprintf ("onClick='document.location.href=\"admin.php?page=view&itemid=%s\";'", $item->id);
						if ($item->type == "itemsc") $symbol = "<span class='dashicons dashicons-marker'></span>";	
						if ($item->type == "itemmc") $symbol = "<span class='dashicons dashicons-forms'></span>";	
					}
					
					
					printf ("<td %s valign='bottom' style='overflow: hidden; padding:0px; padding-top:0.83em;' >%s</td>", $link, $symbol /*(in_array($item->id, $pool) ? "X" : "")*/);
				}
				print ("</tr>");
			}
			print ("</table>");
			
			
		}
			
				
		
	}
	

}
?>