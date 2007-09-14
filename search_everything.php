<?php
/*
Plugin Name: Search Everything
Plugin URI: http://dancameron.org/wordpress/
Description: Adds search functionality with little setup. Including options to search pages, excerpts, attachments, drafts, comments, tags and custom fields (metadata). Also offers the ability to exclude specific pages and posts. Does not search password-protected content. 
Version: 3.9.9.5
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
	load_textdomain('SearchEverything', ABSPATH . 'wp-content/plugins/' . dirname(plugin_basename(__FILE__)) .'/' . 'SE3'.$locale.'.mo');

function SE3_log($msg) {
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
if ("true" == get_option('SE3_use_page_search')) {
	add_filter('posts_where', 'SE3_search_pages');
	SE3_log("searching pages");
	}
	
if ("true" == get_option('SE3_use_excerpt_search')) {
   add_filter('posts_where', 'SE3_search_excerpt');
   SE3_log("searching excerpts");
   }

if ("true" == get_option('SE3_use_comment_search')) {
	add_filter('posts_where', 'SE3_search_comments');
	add_filter('posts_join', 'SE3_comments_join');
	SE3_log("searching comments");
	}

if ("true" == get_option('SE3_use_draft_search')) {
	add_filter('posts_where', 'SE3_search_draft_posts');
	SE3_log("searching drafts");
	}

if ("true" == get_option('SE3_use_attachment_search')) {
	add_filter('posts_where', 'SE3_search_attachments');
	SE3_log("searching attachments");
	}

if ("true" == get_option('SE3_use_metadata_search')) {
	add_filter('posts_where', 'SE3_search_metadata');
	add_filter('posts_join', 'SE3_search_metadata_join');
	SE3_log("searching metadata");
	}

if ("true" == get_option('SE3_exclude_posts')) {
	add_filter('posts_where', 'SE3_exclude_posts');
	SE3_log("searching excluding posts");
	}



if ("true" == get_option('SE3_exclude_categories')) {
	add_filter('posts_where', 'SE3_exclude_categories');
	add_filter('posts_join', 'SE3_exclude_categories_join');
	SE3_log("searching excluding categories");
	}

//Tag Search provided by Thu Tu 
if ("true" == get_option('SE3_use_tag_search')) { 
	add_filter('posts_where', 'SE3_search_tag'); 
	add_filter('posts_join', 'SE3_search_tag_join'); 
       SE3_log("searching tag");
	}

//Duplicate fix provided by Tiago.Pocinho
	add_filter('posts_request', 'SE3_distinct');
function SE3_distinct($query){
	  global $wp_query;
	  if (!empty($wp_query->query_vars['s'])) {
	    if (strstr($where, 'DISTINCT')) {}
	    else {
	      $query = str_replace('SELECT', 'SELECT DISTINCT', $query);
	    }
	  }
	  return $query;
	}

//exlude some posts from search
function SE3_exclude_posts($where) {
	global $wp_query;
	if (!empty($wp_query->query_vars['s'])) {
		$excl_list = implode(',', explode(',', trim(get_option('SE3_exclude_posts_list'))));
		$where = str_replace('"', '\'', $where);
		$where = 'AND ('.substr($where, strpos($where, 'AND')+3).' )';
		$where .= ' AND (ID NOT IN ( '.$excl_list.' ))';
	}

	SE3_log("ex posts where: ".$where);
	return $where;
}



//exlude some categories from search
function SE3_exclude_categories($where) {
	global $wp_query;
	if (!empty($wp_query->query_vars['s'])) {
		$excl_list = implode(',', explode(',', trim(get_option('SE3_exclude_categories_list'))));
		$where = str_replace('"', '\'', $where);
		$where = 'AND ('.substr($where, strpos($where, 'AND')+3).' )';
		$where .= ' AND (c.category_id NOT IN ( '.$excl_list.' ))';
	}

	SE3_log("ex cats where: ".$where);
	return $where;
}

//join for excluding categories
function SE3_exclude_categories_join($join) {
	global $wp_query, $wpdb;

	if (!empty($wp_query->query_vars['s'])) {

		$join .= "LEFT JOIN $wpdb->post2cat AS c ON $wpdb->posts.ID = c.post_id";
	}
	SE3_log("category join: ".$join);
	return $join;
}


//search pages (except password protected pages provided by loops)
function SE3_search_pages($where) {
	global $wp_query;
	if (!empty($wp_query->query_vars['s'])) {
		$where = str_replace('"', '\'', $where);
		if (strstr($where, 'post_type')) { // >= v 2.1
			$where = str_replace('post_type = \'post\' AND ', 'post_password = \'\' AND ', $where);
		}
		else { // < v 2.1
			$where = str_replace(' AND (post_status = \'publish\'', ' AND (post_status = \'publish\' or post_status = \'static\'', $where);
		}
	}

	SE3_log("pages where: ".$where);
	return $where;
}



//search excerpts provided by Dennis Turner
function SE3_search_excerpt($where) {
   global $wp_query;
   if (!empty($wp_query->query_vars['s'])) {
       $where = str_replace('"', '\'', $where);
       $where = str_replace(' OR (post_content LIKE \'%' .
$wp_query->query_vars['s'] . '%\'', ' OR (post_content LIKE \'%' .
$wp_query->query_vars['s'] . '%\') OR (post_excerpt LIKE \'%' .
$wp_query->query_vars['s'] . '%\'', $where);
   }

   SE3_log("excerpts where: ".$where);
   return $where;
}


//search drafts
function SE3_search_draft_posts($where) {
	global $wp_query;
	if (!empty($wp_query->query_vars['s'])) {
		$where = str_replace('"', '\'', $where);
		$where = str_replace(' AND (post_status = \'publish\'', ' AND (post_status = \'publish\' or post_status = \'draft\'', $where);
	}

	SE3_log("drafts where: ".$where);
	return $where;
}

//search attachments
function SE3_search_attachments($where) {
	global $wp_query;
	if (!empty($wp_query->query_vars['s'])) {
		$where = str_replace('"', '\'', $where);
		$where = str_replace(' AND (post_status = \'publish\'', ' AND (post_status = \'publish\' or post_status = \'attachment\'', $where);
		$where = str_replace('AND post_status != \'attachment\'','',$where);
	}

	SE3_log("attachments where: ".$where);
	return $where;
}

//search comments
function SE3_search_comments($where) {
global $wp_query, $wpdb;
	if (!empty($wp_query->query_vars['s'])) {
		$where .= " OR (comment_content LIKE '%" . $wpdb->escape($wp_query->query_vars['s']) . "%') ";
	}

	SE3_log("comments where: ".$where);

	return $where;
}

//join for searching comments
function SE3_comments_join($join) {
	global $wp_query, $wpdb;

	if (!empty($wp_query->query_vars['s'])) {

		if ('true' == get_option('SE3_approved_comments_only')) {
			$comment_approved = " AND comment_approved =  '1'";
  		} else {
			$comment_approved = '';
    	}

		$join .= "LEFT JOIN $wpdb->comments ON ( comment_post_ID = ID " . $comment_approved . ") ";
	}
	SE3_log("comments join: ".$join);
	return $join;
}

//search metadata
function SE3_search_metadata($where) {
	global $wp_query, $wpdb;
	if (!empty($wp_query->query_vars['s'])) {
		$where .= " OR meta_value LIKE '%" . $wpdb->escape($wp_query->query_vars['s']) . "%' ";
	}

	SE3_log("metadata where: ".$where);

	return $where;
}

//join for searching metadata
function SE3_search_metadata_join($join) {
	global $wp_query, $wpdb;

	if (!empty($wp_query->query_vars['s'])) {

		$join .= "LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id ";
	}
	SE3_log("metadata join: ".$join);
	return $join;
}

//search tag
function SE3_search_tag($where) {
	global $wp_query, $wpdb;
	if (!empty($wp_query->query_vars['s'])) {
		$where .= " OR tag_name LIKE '%" . $wpdb->escape($wp_query->query_vars['s']) . "%' ";
	}

	SE3_log("tag where: ".$where);

	return $where;
}

//join for searching tag on Jerome's Keywords Plugin
function SE3_search_tag_join($join) {
	global $wp_query, $wpdb;

	if (!empty($wp_query->query_vars['s'])) {

		$join .= "LEFT JOIN wp_jkeywords ON $wpdb->posts.ID = wp_jkeywords.post_id ";
	}
	SE3_log("tag join: ".$join);
	return $join;
}

//build admin interface
function SE3_option_page() {

global $wpdb, $table_prefix;

	if ( isset($_POST['SE3_update_options']) ) {

		$errs = array();

		if ( !empty($_POST['exclude_categories']) ) {
			update_option('SE3_exclude_categories', "true");
		} else {
			update_option('SE3_exclude_categories', "false");
		}

		if ( !empty($_POST['exclude_categories_list']) ) {
			update_option('SE3_exclude_categories_list', $_POST['exclude_categories_list']);
		} else {
			update_option('SE3_exclude_categories_list', "");
		}

		if ( !empty($_POST['exclude_posts']) ) {
			update_option('SE3_exclude_posts', "true");
		} else {
			update_option('SE3_exclude_posts', "false");
		}

		if ( !empty($_POST['exclude_posts_list']) ) {
			update_option('SE3_exclude_posts_list', $_POST['exclude_posts_list']);
		} else {
			update_option('SE3_exclude_posts_list', "");
		}

		if ( !empty($_POST['search_pages']) ) {
			update_option('SE3_use_page_search', "true");
		} else {
			update_option('SE3_use_page_search', "false");
		}

		if ( !empty($_POST['search_comments']) ) {
			update_option('SE3_use_comment_search', "true");
		} else {
			update_option('SE3_use_comment_search', "false");
		}

		if ( !empty($_POST['appvd_comments']) ) {
			update_option('SE3_approved_comments_only', "true");
		} else {
			update_option('SE3_approved_comments_only', "false");
		}
		
		if ( !empty($_POST['search_excerpt']) ) {
	           update_option('SE3_use_excerpt_search', "true");
	    } else {
	           update_option('SE3_use_excerpt_search', "false");
	    }

		if ( !empty($_POST['search_drafts']) ) {
			update_option('SE3_use_draft_search', "true");
		} else {
			update_option('SE3_use_draft_search', "false");
		}

		if ( !empty($_POST['search_attachments']) ) {
			update_option('SE3_use_attachment_search', "true");
		} else {
			update_option('SE3_use_attachment_search', "false");
		}

		if ( !empty($_POST['search_metadata']) ) {
			update_option('SE3_use_metadata_search', "true");
		} else {
			update_option('SE3_use_metadata_search', "false");
		}
		
		if ( !empty($_POST['search_tag']) ) {
			update_option('SE3_use_tag_search', "true");
		} else {
			update_option('SE3_use_tag_search', "false");
		}
		
		if ( empty($errs) ) {
			echo '<div id="message" class="updated fade"><p>Search Options Saved!</p></div>';
		} else {
			echo '<div id="message" class="error fade"><ul>';
			foreach ( $errs as $name => $msg ) {
				echo '<li>'.wptexturize($msg).'</li>';
			}
			echo '</ul></div>';
	 }
	} // End if update

	//set up option checkbox values
	if ('true' == get_option('SE3_exclude_categories')) {
		$exclude_categories = 'checked="true"';
	} else {
		$exclude_categories = '';
	}

	if ('true' == get_option('SE3_exclude_posts')) {
		$exclude_posts = 'checked="true"';
	} else {
		$exclude_posts = '';
	}


### NEW with v.3.9.1 ##################################
	if ('true' == get_option('SE3_exclude_pages')) {
		$exclude_pages = 'checked="true"';
	} else {
		$exclude_pages = '';
	}
### NEW with v.3.9.1 ##################################


	if ('true' == get_option('SE3_use_page_search')) {
		$page_search = 'checked="true"';
	} else {
		$page_search = '';
	}

	if ('true' == get_option('SE3_use_comment_search')) {
		$comment_search = 'checked="true"';
	} else {
		$comment_search = '';
	}

	if ('true' == get_option('SE3_approved_comments_only')) {
		$appvd_comment = 'checked="true"';
	} else {
		$appvd_comment = '';
	}
	
	if ('true' == get_option('SE3_use_excerpt_search')) {
	    $excerpt_search = 'checked="true"';
	} else {
	    $excerpt_search = '';
	}
	
	if ('true' == get_option('SE3_use_draft_search')) {
		$draft_search = 'checked="true"';
	} else {
		$draft_search = '';
	}

	if ('true' == get_option('SE3_use_attachment_search')) {
		$attachment_search = 'checked="true"';
	} else {
		$attachment_search = '';
	}

	if ('true' == get_option('SE3_use_metadata_search')) {
		$metadata_search = 'checked="true"';
	} else {
		$metadata_search = '';
	}

	if ('true' == get_option('SE3_use_tag_search')) {
		$tag_search = 'checked="true"';
	} else {
		$tag_search = '';
	}

	?>

	<div class="wrap" id="SE3_options_panel">
	<h2>Search Everything (SE) Version: 3.9.9</h2>
	<p><?php _e('The options selected below will be used in every search query on this site; in addition to the built-in post search.','SearchEverything'); ?></p>
    
    </div>
    
	<div class="wrap SE3">
	<h2>SE Search Options</h2>
     <p>Use this form to configure your search options.</p>
	<form id="SE_form" method="post" action="">
     <fieldset>
      <legend>Search Options Form</legend>
         <p><input type="checkbox" id="exclude_posts" name="exclude_posts" value="<?php echo get_option('SE3_exclude_posts'); ?>" <?php echo $exclude_posts; ?> />
       <label for="exclude_posts"><?php _e('Exclude some post or page IDs','SearchEverything'); ?></label><br />
       <label for="exclude_posts_list" class="SE_text_label"><?php _e('List of IDs to exclude (example: 1, 5, 9)','SearchEverything'); ?></label><br />
         <input type="text" size="40" class="SE_text_input" id="exclude_posts_list" name="exclude_posts_list" value="<?php echo get_option('SE3_exclude_posts_list');?>" /></p>

         <p><input type="checkbox" id="exclude_categories" name="exclude_categories" value="<?php echo get_option('SE3_exclude_categories'); ?>" <?php echo $exclude_categories; ?> /> 
       <label for="exclude_categories"><?php _e('Exclude some category IDs','SearchEverything'); ?></label><br />
       <label for="exclude_categories_list" class="SE_text_label"><?php _e('List of category IDs to exclude (example: 1, 4)','SearchEverything'); ?></label><br />
         <input type="text" size="40" class="SE_text_input" id="exclude_categories_list" name="exclude_categories_list" value="<?php echo get_option('SE3_exclude_categories_list');?>" /></p>

         <p><input type="checkbox" id="search_pages" name="search_pages" value="<?php echo get_option('SE3_use_page_search'); ?>" <?php echo $page_search; ?> />
       <label for="search_pages"><?php _e('Search every page (non-password protected)','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_comments" name="search_comments" value="<?php echo get_option('SE3_use_comment_search'); ?>" <?php echo $comment_search; ?> />
       <label for="search_comments"><?php _e('Search every comment','SearchEverything'); ?></label><br />
         <input type="checkbox" class="SE_text_input" id="appvd_comments" name="appvd_comments" value="<?php echo get_option('SE3_approved_comments_only'); ?>" <?php echo $appvd_comment; ?> />
       <label for="appvd_comments"><?php _e('Search approved comments only?','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_excerpt" name="search_excerpt" value="<?php echo get_option('SE3_use_excerpt_search'); ?>" <?php echo $excerpt_search; ?> />
       <label for="search_excerpt"><?php _e('Search every excerpt','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_drafts" name="search_drafts" value="<?php echo get_option('SE3_use_draft_search'); ?>" <?php echo $draft_search; ?> />
       <label for="search_drafts"><?php _e('Search every draft','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_attachments" name="search_attachments" value="<?php echo get_option('SE3_use_attachment_search'); ?>" <?php echo $attachment_search; ?> />
       <label for="search_attachments"><?php _e('Search every attachment','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_metadata" name="search_metadata" value="<?php echo get_option('SE3_use_metadata_search'); ?>" <?php echo $metadata_search; ?> />
       <label for="search_metadata"><?php _e('Search custom fields (metadata)','SearchEverything'); ?></label></p>

         <p><input type="checkbox" id="search_tag" name="search_tag" value="<?php echo get_option('SE3_use_tag_search'); ?>" <?php echo $tag_search; ?> />
       <label for="search_tag"><?php _e('Search keywords/tags - <small>Jerome\'s Keywords only</small>','SearchEverything'); ?></label></p>

    	<p class="submit"><input type="submit" name="SE3_update_options" class="SE3_btn" value="Save Search Options"/><br />
	      <span class="SE_notice">Important:</span> <?php _e('You may have to click Save Search Options twice before it sticks.','SearchEverything'); ?></p>

     </fieldset>
    </form>
    </div>
	<div class="wrap SE3">
	<h2>SE Search Form</h2>
     <p>Use this search form to run a live search test.</p>
	 
      <fieldset>
       <legend>Site Search</legend>
        <form method="get" id="searchform" action="<?php bloginfo('home'); ?>"><p class="srch submit">
		 <label for="s">Enter search terms<br /></label>
          <input type="text" class="srch-txt" value="<?php echo wp_specialchars($s, 1); ?>" name="s" id="s" />
		  <input type="submit" class="SE3_btn" id="searchsubmit" value="Run Test Search" /></p>
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
	       <li><strong>2.3 Compatibility</strong></li>
	       <li>More meta data fuctions.</li>
           <li>Search WP 2.3 tags.</li>
			<li>Search Bookmarks.</li>
	       <li>&#8230;anything else you want to add.</li>
         </ul>
        <br/><p>The current project home is at <a href="http://scatter3d.com/">scatter3d.com</a>. If you want to contribute <a href="mailto:dancameron@gmail.com">e-mail me</a> your modifications.<br/> Donations are accepted.</p>
       <p class="sig">Respectfully,<br />
       <a href="http://dancameron.org/">Dan Cameron</a></p>
	</div>

	<?php
}	//end SE3_option_page

function SE3_add_options_panel() {
	add_options_page('Search', 'Search Everything', 'edit_plugins', 'SE3_options_page', 'SE3_option_page');
}
add_action('admin_menu', 'SE3_add_options_panel');

//styling options page
function SE3_options_style() {
	?>
<style type="text/css" media="screen">
  div.SE3 p.submit, div.SE3 form p.submit, div.SE3 p.submit input { text-align:left; } 
  #SE3_options_panel p.submit { text-align:left; }
  form#searchform label, form#searchform input, form#SE_form label, form#SE_form input { margin-left:10px; }
  input.SE3_btn { cursor:pointer; margin-left:5px; }
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

add_action('admin_head', 'SE3_options_style');

?>