<?php
/*
Plugin Name: Search Everything
Plugin URI: http://dancameron.org/wordpress/
Description: Adds search functionality with little setup. Including options to search pages, excerpts, attachments, drafts, comments, tags and custom fields (metadata). Also offers the ability to exclude specific pages and posts. Does not search password-protected content. 
Version: 4.0.2
Author: Dan Cameron
Author URI: http://dancameron.org/
*/

/*
This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, version 2.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
*/

//add filters based upon option settings

//logging
$logging = 0;

// Load language file
$locale = get_locale();
if ( !empty($locale) )
	load_textdomain('SearchEverything', ABSPATH . 'wp-content/plugins/' . dirname(plugin_basename(__FILE__)) .'/' . 'SE4'.$locale.'.mo');

function SE4_log($msg) {
	global $logging;
	if ($logging) {
		$fp = fopen("logfile.log","a+");
		$date = date("Y-m-d H:i:s ");
		$source = "search_everything_2 plugin: ";
		fwrite($fp, "\n\n".$date."\n".$source."\n".$msg);
		fclose($fp);
	}
	return true;
	}

//add filters based upon option settings
if ("true" == get_option('SE4_use_page_search')) {
	add_filter('posts_where', 'SE4_search_pages');
	SE4_log("searching pages");
	}
	
if ("true" == get_option('SE4_use_excerpt_search')) {
   add_filter('posts_where', 'SE4_search_excerpt');
   SE4_log("searching excerpts");
   }

if ("true" == get_option('SE4_use_comment_search')) {
	add_filter('posts_where', 'SE4_search_comments');
	add_filter('posts_join', 'SE4_comments_join');
	SE4_log("searching comments");
	}

if ("true" == get_option('SE4_use_draft_search')) {
	add_filter('posts_where', 'SE4_search_draft_posts');
	SE4_log("searching drafts");
	}

if ("true" == get_option('SE4_use_attachment_search')) {
	add_filter('posts_where', 'SE4_search_attachments');
	SE4_log("searching attachments");
	}

if ("true" == get_option('SE4_use_metadata_search')) {
	add_filter('posts_where', 'SE4_search_metadata');
	add_filter('posts_join', 'SE4_search_metadata_join');
	SE4_log("searching metadata");
	}

if ("true" == get_option('SE4_exclude_posts')) {
	add_filter('posts_where', 'SE4_exclude_posts');
	SE4_log("searching excluding posts");
	}


// - Depracated in 2.3
if ("true" == get_option('SE4_exclude_categories')) {
	add_filter('posts_where', 'SE4_exclude_categories');
	add_filter('posts_join', 'SE4_exclude_categories_join');
	SE4_log("searching excluding categories");
	}

// - Depracated
if ("true" == get_option('SE4_use_tag_search')) { 
	add_filter('posts_where', 'SE4_search_tag'); 
	add_filter('posts_join', 'SE4_search_tag_join'); 
       SE4_log("searching tag");
	}

//Duplicate fix provided by Tiago.Pocinho
	add_filter('posts_request', 'SE4_distinct');
function SE4_distinct($query){
	  global $wp_query;
	  if (!empty($wp_query->query_vars['s'])) {
	    if (strstr($where, 'DISTINCT')) {}
	    else {
	      $query = str_replace('SELECT', 'SELECT DISTINCT', $query);
	    }
	  }
	  return $query;
	}


function SE4_exclude_posts($where) {
	global $wp_query;
	if (!empty($wp_query->query_vars['s'])) {
		$excl_list = implode(',', explode(',', trim(get_option('SE4_exclude_posts_list'))));
		$where = str_replace('"', '\'', $where);
		$where = 'AND ('.substr($where, strpos($where, 'AND')+3).' )';
		$where .= ' AND (ID NOT IN ( '.$excl_list.' ))';
	}

	SE4_log("ex posts where: ".$where);
	return $where;
}




//exlude some categories from search - Depracated in 2.3
function SE4_exclude_categories($where) {
	global $wp_query;
	if (!empty($wp_query->query_vars['s'])) {
		$excl_list = implode(',', explode(',', trim(get_option('SE4_exclude_categories_list'))));
		$where = str_replace('"', '\'', $where);
		$where = 'AND ('.substr($where, strpos($where, 'AND')+3).' )';
		$where .= ' AND (c.category_id NOT IN ( '.$excl_list.' ))';
	}

	SE4_log("ex cats where: ".$where);
	return $where;
}

//join for excluding categories - Depracated in 2.3
function SE4_exclude_categories_join($join) {
	global $wp_query, $wpdb;

	if (!empty($wp_query->query_vars['s'])) {

		$join .= "LEFT JOIN $wpdb->post2cat AS c ON $wpdb->posts.ID = c.post_id";
	}
	SE4_log("category join: ".$join);
	return $join;
}


//search pages (except password protected pages provided by loops)
function SE4_search_pages($where) {
	global $wp_query;
	if (!empty($wp_query->query_vars['s'])) {
		
		$where = str_replace('"', '\'', $where);
		if ('true' == get_option('SE4_approved_pages_only')) { 
			$where = str_replace('post_type = \'post\' AND ', 'post_password = \'\' AND ', $where);
		}
		else { // < v 2.1
			$where = str_replace('post_type = \'post\' AND ', '', $where);
		}
	}

	SE4_log("pages where: ".$where);
	return $where;
}


//search excerpts provided by Dennis Turner
function SE4_search_excerpt($where) {
   global $wp_query;
   if (!empty($wp_query->query_vars['s'])) {
       $where = str_replace('"', '\'', $where);
       $where = str_replace(' OR (post_content LIKE \'%' .
$wp_query->query_vars['s'] . '%\'', ' OR (post_content LIKE \'%' .
$wp_query->query_vars['s'] . '%\') OR (post_excerpt LIKE \'%' .
$wp_query->query_vars['s'] . '%\'', $where);
   }

   SE4_log("excerpts where: ".$where);
   return $where;
}


//search drafts
function SE4_search_draft_posts($where) {
	global $wp_query;
	if (!empty($wp_query->query_vars['s'])) {
		$where = str_replace('"', '\'', $where);
		$where = str_replace(' AND (post_status = \'publish\'', ' AND (post_status = \'publish\' or post_status = \'draft\'', $where);
	}

	SE4_log("drafts where: ".$where);
	return $where;
}

//search attachments
function SE4_search_attachments($where) {
	global $wp_query;
	if (!empty($wp_query->query_vars['s'])) {
		$where = str_replace('"', '\'', $where);
		$where = str_replace(' AND (post_status = \'publish\'', ' AND (post_status = \'publish\' or post_status = \'attachment\'', $where);
		$where = str_replace('AND post_status != \'attachment\'','',$where);
	}

	SE4_log("attachments where: ".$where);
	return $where;
}

//search comments
function SE4_search_comments($where) {
global $wp_query, $wpdb;
	if (!empty($wp_query->query_vars['s'])) {
		$where .= " OR (comment_content LIKE '%" . $wpdb->escape($wp_query->query_vars['s']) . "%') ";
	}

	SE4_log("comments where: ".$where);

	return $where;
}

//join for searching comments
function SE4_comments_join($join) {
	global $wp_query, $wpdb;

	if (!empty($wp_query->query_vars['s'])) {

		if ('true' == get_option('SE4_approved_comments_only')) {
			$comment_approved = " AND comment_approved =  '1'";
  		} else {
			$comment_approved = '';
    	}

		$join .= "LEFT JOIN $wpdb->comments ON ( comment_post_ID = ID " . $comment_approved . ") ";
	}
	SE4_log("comments join: ".$join);
	return $join;
}

//search metadata
function SE4_search_metadata($where) {
	global $wp_query, $wpdb;
	if (!empty($wp_query->query_vars['s'])) {
		$where .= " OR meta_value LIKE '%" . $wpdb->escape($wp_query->query_vars['s']) . "%' ";
	}

	SE4_log("metadata where: ".$where);

	return $where;
}

//join for searching metadata
function SE4_search_metadata_join($join) {
	global $wp_query, $wpdb;

	if (!empty($wp_query->query_vars['s'])) {

		$join .= "LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id ";
	}
	SE4_log("metadata join: ".$join);
	return $join;
}



//build admin interface
function SE4_option_page() {
	global $wpdb, $table_prefix;
		if($_POST['action'] == "save") {
echo "<div class=\"updated fade\" id=\"limitcatsupdatenotice\"><p>" . __("Search Everything Options  <strong>Updated</strong>.") . "</p></div>";

		update_option("SE4_exclude_categories", $_POST["exclude_categories"]);
		update_option("SE4_exclude_categories_list", $_POST["exclude_categories_list"]);
		update_option("SE4_exclude_posts", $_POST["exclude_posts"]);
		update_option("SE4_exclude_posts_list", $_POST["exclude_posts_list"]);
		update_option("SE4_use_page_search", $_POST["search_pages"]);
		update_option("SE4_use_comment_search", $_POST["search_comments"]);
		update_option("SE4_approved_comments_only", $_POST["appvd_comments"]);
		update_option("SE4_approved_pages_only", $_POST["appvd_pages"]);
		update_option("SE4_use_excerpt_search", $_POST["search_excerpt"]);
		update_option("SE4_use_draft_search", $_POST["search_drafts"]);
		update_option("SE4_use_attachment_search", $_POST["search_attachments"]);
		update_option("SE4_use_metadata_search", $_POST["search_metadata"]);
		update_option("search_tag", $_POST["SE4_use_tag_search"]);
		
			$SE4_exclude_categories   = get_option("SE4_exclude_categories");
			$SE4_exclude_categories_list   = get_option("SE4_exclude_categories_list");
			$SE4_exclude_posts   = get_option("SE4_exclude_posts");
			$SE4_exclude_posts_list   = get_option("SE4_exclude_posts_list");
			$SE4_use_page_search   = get_option("SE4_use_page_search");
			$SE4_use_comment_search   = get_option("SE4_use_comment_search");
			$SE4_approved_comments_only   = get_option("SE4_approved_comments_only");
			$SE4_approved_pages_only   = get_option("SE4_approved_pages_only");
			$SE4_use_excerpt_search   = get_option("SE4_use_excerpt_search");
			$SE4_use_draft_search   = get_option("SE4_use_draft_search");
			$SE4_use_attachment_search   = get_option("SE4_use_attachment_search");
			$SE4_use_metadata_search   = get_option("SE4_use_metadata_search");
			$search_tag   = get_option("search_tag");

		}
	?>

	<div class="wrap" id="SE4_options_panel">
	<h2>Search Everything (SE) Version: 4</h2>
	<p><?php _e('The options selected below will be used in every search query on this site; in addition to the built-in post search.','SearchEverything'); ?></p>
    
    </div>
    
	<div class="wrap SE4">
	<h2>SE Search Options</h2>
     <p>Use this form to configure your search options.</p>
		<form method="post">
	    <fieldset class="options">
      <legend>Search Options Form</legend>
         <p><input type="checkbox" id="exclude_posts" name="exclude_posts" value="true"  <?php if(get_option('SE4_exclude_posts') == 'true') { echo 'checked="true"'; } ?> />
       <label for="exclude_posts"><?php _e('Exclude some post or page IDs','SearchEverything'); ?></label><br />
       &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label for="exclude_posts_list" class="SE_text_label"><?php _e('Comma separated Post IDs (example: 1, 5, 9)','SearchEverything'); ?></label><br />
         &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="text" size="50" class="SE_text_input" id="exclude_posts_list" name="exclude_posts_list" value="<?php echo get_option('SE4_exclude_posts_list');?>" /></p>

         <p><input type="checkbox" id="exclude_categories" name="exclude_categories" 
	value="true"  <?php if(get_option('SE4_exclude_categories') == 'true') { echo 'checked="true"'; } ?> /> 
       <label for="exclude_categories"><?php _e('Exclude some category IDs (Wordpress 2.2 Only)','SearchEverything'); ?></label><br />
       &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<label for="exclude_categories_list" class="SE_text_label"><?php _e('Comma separated category IDs (example: 1, 4)','SearchEverything'); ?></label><br />
         &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="text" size="50" class="SE_text_input" id="exclude_categories_list" name="exclude_categories_list" value="<?php echo get_option('SE4_exclude_categories_list');?>" /></p>

         <p><input type="checkbox" id="search_pages" name="search_pages"
	value="true"  <?php if(get_option('SE4_use_page_search') == 'true') { echo 'checked="true"'; } ?>  />
       <label for="search_pages"><?php _e('Search every page (non-password protected)','SearchEverything'); ?></label></p>
		<p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" class="SE_text_input" id="appvd_pages" name="appvd_pages" value="true"  <?php if(get_option('SE4_approved_pages_only') == 'true') { echo 'checked="true"'; } ?>
      <label for="appvd_pages"><?php _e('Search approved pages only?','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_comments" name="search_comments" 
	value="true"  <?php if(get_option('SE4_use_comment_search') == 'true') { echo 'checked="true"'; } ?> />
       <label for="search_comments"><?php _e('Search every comment','SearchEverything'); ?></label></p>
          
		 <p>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="checkbox" class="SE_text_input" id="appvd_comments" name="appvd_comments" value="true"  <?php if(get_option('SE4_approved_comments_only') == 'true') { echo 'checked="true"'; } ?>
       <label for="appvd_comments"><?php _e('Search approved comments only?','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_excerpt" name="search_excerpt" value="true"  <?php if(get_option('SE4_use_excerpt_search') == 'true') { echo 'checked="true"'; } ?> />
       <label for="search_excerpt"><?php _e('Search every excerpt','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_drafts" name="search_drafts" value="true"  <?php if(get_option('SE4_use_draft_search') == 'true') { echo 'checked="true"'; } ?> 
       <label for="search_drafts"><?php _e('Search every draft','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_attachments" name="search_attachments" value="true"  <?php if(get_option('SE4_use_attachment_search') == 'true') { echo 'checked="true"'; } ?> />
       <label for="search_attachments"><?php _e('Search every attachment','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_metadata" name="search_metadata" value="true"  <?php if(get_option('SE4_use_metadata_search') == 'true') { echo 'checked="true"'; } ?> />
       <label for="search_metadata"><?php _e('Search every custom field (metadata)','SearchEverything'); ?></label></p>

   		</fieldset>
		<fieldset class="options">
		<div class="submit">
		<input type="hidden" name="action" value="save" />
		<input type="submit" value="<?php _e('Update Options »') ?>" />
		</div>
		</fieldset>
    </form>
    </div>
	<div class="wrap SE4">
	<h2>SE Search Form</h2>
     <p>Use this search form to run a live search test.</p>
	 
      <fieldset>
       <legend>Site Search</legend>
        <form method="get" id="searchform" action="<?php bloginfo('home'); ?>"><p class="srch submit">
		 <label for="s">Enter search terms<br /></label>
          <input type="text" class="srch-txt" value="<?php echo wp_specialchars($s, 1); ?>" name="s" id="s" />
		  <input type="submit" class="SE4_btn" id="searchsubmit" value="Run Test Search" /></p>
      </form>
	 </fieldset>
     </div>
	<div class="wrap">
     <h2>SE Project Information</h2>
       <p>The development since Version One has primarily come from the WordPress community and as a Search Everything user myself, I&#8217;m grateful for their dedicated and continued support:</p>
         <ul class="SE_lists">
	       <li><a href="http://kinrowan.net/">Cori Schlegel</a></li>
		   <li><a href="http://green-beast.com/">Mike Cherim</a></li>
           <li><a href="http://alexking.org/">Alex King</a></li>
           <li><a href="http://blog.saddey.net/">Saddy</a></li>
           <li><a href="http://www.reaper-x.com/">Reaper</a></li>
           
           <li>Alakhnor</li>
           <li>Uli Iserloh</li>
         </ul>
       <p>If you&#8217;d like to contribute there&#8217;s a lot to do:</p>
         <ul class="SE_lists">
	       <li><strong>Category Exclusion for new 2.3 Taxonomy Schema</strong></li>
	       <li>More meta data functions.</li>
           <li>Searching tags (WP 2.3).</li>
			<li>Search Bookmarks.</li>
	       <li>&#8230;anything else you want to add.</li>
         </ul>
        <br/><p>The current project home is at <a href="http://scatter3d.com/">scatter3d.com</a>. If you want to contribute <a href="mailto:dancameron@gmail.com">e-mail me</a> your modifications.<br/> Donations are accepted.</p>
       <p class="sig">Respectfully,<br />
       <a href="http://dancameron.org/">Dan Cameron</a></p>
	</div>

	<?php
}	//end SE4_option_page

function SE4_add_options_panel() {
	add_options_page('Search', 'Search Everything', 7, 'manage_search', 'SE4_option_page', 'SE4_option_page');
}
add_action('admin_menu', 'SE4_add_options_panel');

//styling options page
function SE4_options_style() {
	?>
<style type="text/css" media="screen">
  div.SE4 p.submit, div.SE4 form p.submit, div.SE4 p.submit input { text-align:left; } 
  #SE4_options_panel p.submit { text-align:left; }
  form#searchform label, form#searchform input, form#SE_form label, form#SE_form input { margin-left:10px; }
  input.SE4_btn { cursor:pointer; margin-left:5px; }
  form legend { font-weight:bold; color:navy; }
  p.srch { margin:0; margin-bottom:20px; } 
  p.submit input.srch-txt { background-color:#f4f4f4; background-image:none; border:1px solid #999; padding:6px; }
  p.submit input.srch-txt:focus, p.submit input.srch-txt:active { background-color:#fff; background-image:none; border:1px solid #111; padding:6px; }
  p.sig { margin-left:25px; }
  span.SE_notice { color:#cd0000; font-weight:bold; padding-left:10px; }
  label.SE_text_label { cursor:text; } 
  form#SE_form label.SE_text_label, form#SE_form input.SE_text_input { margin-left:38px; }
  ul.SE_lists li { list-style-type:square; }
</style>
<?php
}

add_action('admin_head', 'SE4_options_style');

?>