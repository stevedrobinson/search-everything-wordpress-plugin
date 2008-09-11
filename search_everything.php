<?php
/*
Plugin Name: Search Everything
Plugin URI: http://dancameron.org/wordpress/
Description: Adds search functionality with little setup. Including options to search pages, excerpts, attachments, drafts, comments, tags and custom fields (metadata). Also offers the ability to exclude specific pages and posts. Does not search password-protected content.
Version: 4.7.6.2
Author: Dan Cameron
Author URI: http://dancameron.org/
*/

/*
This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, version 2.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
*/

if ( !defined('WP_CONTENT_DIR') )
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define('SE_ABSPATH', WP_CONTENT_DIR.'/plugins/' . dirname(plugin_basename(__FILE__)) . '/');

$SE = new SearchEverything();
//add filters based upon option settings

Class SearchEverything {

	var $login = false;
	var $options;
	var $wp_ver23;
	var $wp_ver25;

	function SearchEverything(){
		global $wp_version;
		$this->wp_ver23 = ($wp_version >= '2.3');
		$this->wp_ver25 = ($wp_version >= '2.5');
		$this->options = get_option('SE4_options');

		if (is_admin()) {
			include ( SE_ABSPATH  . 'SE-Admin.php' );
			$SEAdmin = new SearchEverythingAdmin();
		}

		//add filters based upon option settings
		if ("true" == $this->options['SE4_use_tag_search']) {
			add_filter('posts_where', array(&$this, 'SE4_search_tags'));
			add_filter('posts_join', array(&$this, 'SE4_terms_join'));
			$this->SE4_log("searching tags");
		}

		if ("true" == $this->options['SE4_use_category_search']) {
			add_filter('posts_where', array(&$this, 'SE4_search_categories'));
			add_filter('posts_join', array(&$this, 'SE4_terms_join'));
			$this->SE4_log("searching categories");
		}

		if ("true" == $this->options['SE4_use_page_search']) {
			add_filter('posts_where', array(&$this, 'SE4_search_pages'));
			$this->SE4_log("searching pages");
		}

		if ("true" == $this->options['SE4_use_excerpt_search']) {
			add_filter('posts_where', array(&$this, 'SE4_search_excerpt'));
			$this->SE4_log("searching excerpts");
		}

		if ("true" == $this->options['SE4_use_comment_search']) {
			add_filter('posts_where', array(&$this, 'SE4_search_comments'));
			add_filter('posts_join', array(&$this, 'SE4_comments_join'));
			$this->SE4_log("searching comments");
		}

		if ("true" == $this->options['SE4_use_draft_search']) {
			add_filter('posts_where', array(&$this, 'SE4_search_draft_posts'));
			$this->SE4_log("searching drafts");
		}

		if ("true" == $this->options['SE4_use_attachment_search']) {
			add_filter('posts_where', array(&$this, 'SE4_search_attachments'));
			$this->SE4_log("searching attachments");
		}

		if ("true" == $this->options['SE4_use_metadata_search']) {
			add_filter('posts_where', array(&$this, 'SE4_search_metadata'));
			add_filter('posts_join', array(&$this, 'SE4_search_metadata_join'));
			$this->SE4_log("searching metadata");
		}

		if ("true" == $this->options['SE4_exclude_posts']) {
			add_filter('posts_where', array(&$this, 'SE4_exclude_posts'));
			$this->SE4_log("searching excluding posts");
		}

		if ("true" == $this->options['SE4_exclude_categories']) {
			add_filter('posts_where', array(&$this, 'SE4_exclude_categories'));
			add_filter('posts_join', array(&$this, 'SE4_exclude_categories_join'));
			$this->SE4_log("searching excluding categories");
		}
		
		
		// Add registration of bo_revisions hook handler
		// right before the following line already existant
		add_filter('posts_where', array(&$this, 'SE4_no_revisions'));
		
		
		//Duplicate fix provided by Tiago.Pocinho
		add_filter('posts_request', array(&$this, 'SE4_distinct'));
	}

	// Exclude post revisions
	function SE4_no_revisions($where) {
	  global $wp_query;
	  if (!empty($wp_query->query_vars['s'])) {
	    $where = 'AND (' . substr($where, strpos($where, 'AND')+3) . ') AND post_type != \'revision\'';
	  }
	  return $where;
	}
	
	// Logs search into a file
	function SE4_log($msg) {

		if ($this->logging) {
			$fp = fopen("logfile.log","a+");
			if ( !$fp ) { echo 'unable to write to log file!'; }
			$date = date("Y-m-d H:i:s ");
			$source = "search_everything plugin: ";
			fwrite($fp, "\n\n".$date."\n".$source."\n".$msg);
			fclose($fp);
		}
		return true;
	}

	//Duplicate fix provided by Tiago.Pocinho
	function SE4_distinct($query){
		global $wp_query;
		if (!empty($wp_query->query_vars['s'])) {
			if (strstr($where, 'DISTINCT')) {
			} else {
				$query = str_replace('SELECT', 'SELECT DISTINCT', $query);
			}
		}
		return $query;
	}

	function SE4_exclude_posts($where) {
		global $wp_query;
		if (!empty($wp_query->query_vars['s'])) {
			$excl_list = implode(',', explode(',', trim($this->options['SE4_exclude_posts_list'])));
			$where = str_replace('"', '\'', $where);
			$where = 'AND ('.substr($where, strpos($where, 'AND')+3).' )';
			$where .= ' AND (ID NOT IN ( '.$excl_list.' ))';
		}

		$this->SE4_log("ex posts where: ".$where);
		return $where;
	}

	//search pages (except password protected pages provided by loops)
	function SE4_search_pages($where) {
		global $wp_query;
		if (!empty($wp_query->query_vars['s'])) {

			$where = str_replace('"', '\'', $where);
			if ('true' == $this->options['SE4_approved_pages_only']) {
				$where = str_replace('post_type = \'post\' AND ', 'post_password = \'\' AND ', $where);
			}
			else { // < v 2.1
				$where = str_replace('post_type = \'post\' AND ', '', $where);
			}
		}

		$this->SE4_log("pages where: ".$where);
		return $where;
	}

	//search excerpts provided by Dennis Turner, fixed by GvA
	function SE4_search_excerpt($where) {
		global $wp_query;
		if (!empty($wp_query->query_vars['s'])) {
				$where = str_replace('"', '\'', $where);
				$where = str_replace(' OR (wp_posts.post_content LIKE \'%' . 
				$wp_query->query_vars['s'] . '%\'', ' OR (wp_posts.post_content LIKE \'%' . 
				$wp_query->query_vars['s'] . '%\') OR (wp_posts.post_excerpt LIKE \'%' . 
				$wp_query->query_vars['s'] . '%\'', $where);
		}

		$this->SE4_log("excerpts where: ".$where);
		return $where;
	}
	
	
	//search drafts
	function SE4_search_draft_posts($where) {
		global $wp_query;
		if (!empty($wp_query->query_vars['s'])) {
			$where = str_replace('"', '\'', $where);
			$where = str_replace(' AND (post_status = \'publish\'', ' AND (post_status = \'publish\' or post_status = \'draft\'', $where);
		}

		$this->SE4_log("drafts where: ".$where);
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

		$this->SE4_log("attachments where: ".$where);
		return $where;
	}

	//search comments
	function SE4_search_comments($where) {
	global $wp_query, $wpdb;
		if (!empty($wp_query->query_vars['s'])) {
			if ('true' == $this->options['SE4_approved_comments_only']) {
				$comment_approved = " AND c.comment_approved =  '1'";
	  		} else {
				$comment_approved = '';
	  		}

			if ($this->wp_ver23) {
				$where .= " OR ( c.comment_post_ID = ".$wpdb->posts . ".ID " . $comment_approved . " AND c.comment_content LIKE '%" . $wpdb->escape($wp_query->query_vars['s']) . "%') ";
	  		}
		}

		$this->SE4_log("comments where: ".$where);

		return $where;
	}

	//search metadata
	function SE4_search_metadata($where) {
		global $wp_query, $wpdb;
		if (!empty($wp_query->query_vars['s'])) {
			if ($this->wp_ver23)
				$where .= " OR (m.meta_value LIKE '%" . $wpdb->escape($wp_query->query_vars['s']) . "%') ";
			else
				$where .= " OR meta_value LIKE '%" . $wpdb->escape($wp_query->query_vars['s']) . "%' ";
		}

		$this->SE4_log("metadata where: ".$where);

		return $where;
	}

	//search tags
	function SE4_search_tags($where) {
	global $wp_query, $wpdb;
		if (!empty($wp_query->query_vars['s'])) {
			//$where .= " OR ( tter.slug LIKE '%" . $wpdb->escape($wp_query->query_vars['s']) . "%') ";
			$where .= " OR ( tter.name LIKE '%" . str_replace(' ', '-',$wpdb->escape($wp_query->query_vars['s'])) . "%') ";
			}

		$this->SE4_log("tags where: ".$where);

		return $where;
	}

	//search categories
	function SE4_search_categories ( $where ) {

		global $wp_query, $wpdb;

		if ( ! empty($wp_query->query_vars['s']) ) {
			$where .= " OR ( tter.slug LIKE '%" . sanitize_title_with_dashes( $wp_query->query_vars['s'] ) . "%') ";
		}

		$this->SE4_log("categories where: ".$where);

		return $where;

	}

	//exlude some categories from search
	function SE4_exclude_categories($where) {
		global $wp_query, $wpdb;
		if (!empty($wp_query->query_vars['s'])) {
			if (trim($this->options['SE4_exclude_categories_list']) != '') {
				$excl_list = implode("','", explode(',', "'".trim($this->options['SE4_exclude_categories_list'])."'" ));
				$where = str_replace('"', '\'', $where);
				$where = 'AND ('.substr($where, strpos($where, 'AND')+3).' )';
				if ($this->wp_ver23)
					$where .= " AND ( ctax.term_id NOT IN ( ".$excl_list." ))";
					else
					$where .= ' AND (c.category_id NOT IN ( '.$excl_list.' ))';
			}
		}

		$this->SE4_log("ex cats where: ".$where);
		return $where;
	}

	//join for excluding categories - Deprecated in 2.3
	function SE4_exclude_categories_join($join) {
		global $wp_query, $wpdb;

		if (!empty($wp_query->query_vars['s'])) {

			if ($this->wp_ver23) {
							$join .= " LEFT JOIN $wpdb->term_relationships AS crel ON ($wpdb->posts.ID = crel.object_id) LEFT JOIN $wpdb->term_taxonomy AS ctax ON (ctax.taxonomy = 'category' AND crel.term_taxonomy_id = ctax.term_taxonomy_id) LEFT JOIN $wpdb->terms AS cter ON (ctax.term_id = cter.term_id) ";
				  		} else {
							$join .= "LEFT JOIN $wpdb->post2cat AS c ON $wpdb->posts.ID = c.post_id";
						}
		}
		$this->SE4_log("category join: ".$join);
		return $join;
	}

	//join for searching comments
	function SE4_comments_join($join) {
		global $wp_query, $wpdb;

		if (!empty($wp_query->query_vars['s'])) {

			if ($this->wp_ver23) {
				$join .= " LEFT JOIN $wpdb->comments AS c ON ( comment_post_ID = ID " . $comment_approved . ") ";
				} else {

				if ('true' == $this->options['SE4_approved_comments_only']) {
					$comment_approved = " AND comment_approved =  '1'";
		  		} else {
					$comment_approved = '';
				}

				$join .= "LEFT JOIN $wpdb->comments ON ( comment_post_ID = ID " . $comment_approved . ") ";

		    	}
	    	}

		$this->SE4_log("comments join: ".$join);
		return $join;
	}

	//join for searching metadata
	function SE4_search_metadata_join($join) {
		global $wp_query, $wpdb;

		if (!empty($wp_query->query_vars['s'])) {

			if ($this->wp_ver23)
				$join .= " LEFT JOIN $wpdb->postmeta AS m ON ($wpdb->posts.ID = m.post_id) ";
			else
				$join .= "LEFT JOIN $wpdb->postmeta ON $wpdb->posts.ID = $wpdb->postmeta.post_id ";
		}
		$this->SE4_log("metadata join: ".$join);
		return $join;
	}

	//join for searching tags
	function SE4_terms_join($join) {
		global $wp_query, $wpdb;

		if (!empty($wp_query->query_vars['s'])) {

			// if we're searching for categories
			if ( $this->options['SE4_use_category_search'] ) {
				$on[] = "ttax.taxonomy = 'category'";
			}

			// if we're searching for tags
			if ( $this->options['SE4_use_tag_search'] ) {
				$on[] = "ttax.taxonomy = 'post_tag'";
			}

			// build our final string
			$on = ' ( ' . implode( ' OR ', $on ) . ' ) ';

			$join .= " LEFT JOIN $wpdb->term_relationships AS trel ON ($wpdb->posts.ID = trel.object_id) LEFT JOIN $wpdb->term_taxonomy AS ttax ON ( " . $on . " AND trel.term_taxonomy_id = ttax.term_taxonomy_id) LEFT JOIN $wpdb->terms AS tter ON (ttax.term_id = tter.term_id) ";
			}

		$this->SE4_log("tags join: ".$join);
		return $join;
	}
}

?>