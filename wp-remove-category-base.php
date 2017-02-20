<?php
/**
 * Plugin Name: Remove Tag Base
 * Description: Removes the tag base slug from the tag archive permalinks.
 * Version:     1.0
 * Author:      Aleksandar Gubecka
 * Author URI:  https://github.com/aleksandargubecka
 * License:     GPL v3
 * 
 * Copyright (c) 2017, Aleksandar Gubecka
 * 
 * WP Remove Tag Base is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * WP Remove Tag Base is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have recieved a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses>.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // exit if accessed directly
}

if ( ! class_exists( 'Remove_Tag_Base' ) ) {
	class Remove_Tag_Base {
		
		function __construct() {
			add_action( 'init', array( $this, 'flush_rules' ), 999 );

			foreach ( array( 'created_post_tag', 'edited_post_tag', 'delete_post_tag' ) as $action ) {
				add_action( $action, array( $this, 'schedule_flush' ) );
			};
			
			add_filter( 'query_vars', array( $this, 'update_query_vars' ) );
			add_filter( 'tag_link', array( $this, 'remove_tag_base' ) );
			add_filter( 'request', array( $this, 'redirect_old_tag_url' ) );
			add_filter( 'tag_rewrite_rules', array( $this, 'add_tag_rewrite_rules' ) );

			register_activation_hook( __FILE__, array( $this, 'on_activation_and_deactivation' ) );
            register_deactivation_hook( __FILE__, array( $this, 'on_activation_and_deactivation' ) );
		}
		
		public function flush_rules() {
			if ( get_option( 'rtb_flush_rewrite_rules' ) ) {
				add_action( 'shutdown', 'flush_rewrite_rules' );
				delete_option( 'rtb_flush_rewrite_rules' );
			}
		}
		
		public function schedule_flush() {
			update_option( 'rtb_flush_rewrite_rules', 1 );
		}
		
		public function remove_tag_base( $permalink ) {
			$tag_base = get_option( 'tag_base' ) ? get_option( 'tag_base' ) : 'tag';
			
			// Remove initial slash, if there is one (the trailing slash is removed in the regex replacement and we don't want to end up short a slash)
			if ( '/' === substr( $tag_base, 0, 1 ) ) {
				$tag_base = substr( $tag_base, 1 );
			}
			
			$tag_base .= '/';
			
			return preg_replace( '`' . preg_quote( $tag_base, '`' ) . '`u', '', $permalink, 1 );
		}
		
		public function update_query_vars( $query_vars ) {
			$query_vars[] = 'rtb_tag_redirect';
			return $query_vars;
		}
		
		public function redirect_old_tag_url( $query_vars ) {
			if ( isset( $query_vars['rtb_tag_redirect'] ) ) {
				$tag_link = trailingslashit( get_option( 'home' ) ) . user_trailingslashit( $query_vars['rtb_tag_redirect'], 'tag' );
				wp_redirect( $tag_link, 301 );
				exit;
			}
			return $query_vars;
		}
		
		public function add_tag_rewrite_rules() {
			global $wp_rewrite;
			echo '<pre>';
			var_dump($wp_rewrite);
			echo '</pre>';
			$tag_rewrite = array();
			
			if ( function_exists( 'is_multisite' ) && is_multisite() && ! is_subdomain_install() && is_main_site() ) {
				$blog_prefix = 'blog/';
			} else {
				$blog_prefix = '';
			}
					
			foreach ( get_tags( array( 'hide_empty' => false ) ) as $tag ) {
				$tag_nicename = $tag->slug;

				$tag_rewrite[$blog_prefix . '(' . $tag_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?tag_name=$matches[1]&feed=$matches[2]';
				$tag_rewrite[$blog_prefix . '(' . $tag_nicename . ')/' . $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$'] = 'index.php?tag_name=$matches[1]&paged=$matches[2]';
				$tag_rewrite[$blog_prefix . '(' . $tag_nicename . ')/?$'] = 'index.php?tag_name=$matches[1]';
			}
			
			// Redirect support for `old` tag base
			$old_base = $wp_rewrite->get_tag_permastruct();
			$old_base = str_replace( '%tag%', '(.+)', $old_base );
			$old_base = trim( $old_base, '/' );
			
			$tag_rewrite[$old_base . '$'] = 'index.php?rtb_tag_redirect=$matches[1]';

			return $tag_rewrite;
		}

		public function on_activation_and_deactivation() {
			flush_rewrite_rules();
		}
	}

	new Remove_Tag_Base();
}
flush_rewrite_rules();