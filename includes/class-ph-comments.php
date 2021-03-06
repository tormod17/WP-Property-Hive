<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Comments
 *
 * Handle comments.
 *
 * @class    PH_Comments
 * @version  1.0.0
 * @package  PropertyHive/Classes/Products
 * @category Class
 * @author   PropertyHive
 */
class PH_Comments {

	/**
	 * Hook in methods.
	 */
	public static function init() {
		// Secure propertyhive notes
		add_filter( 'comments_clauses', array( __CLASS__, 'exclude_note_comments' ), 10, 1 );

		// Count comments
		add_filter( 'wp_count_comments', array( __CLASS__, 'wp_count_comments' ), 99, 2 );

		// Delete comments count cache whenever there is a new comment or a comment status changes
		add_action( 'wp_insert_comment', array( __CLASS__, 'delete_comments_count_cache' ) );
		add_action( 'wp_set_comment_status', array( __CLASS__, 'delete_comments_count_cache' ) );

		add_action( 'add_post_meta', array( __CLASS__, 'check_on_market_add' ), 10, 3 );
		add_action( 'update_post_meta', array( __CLASS__, 'check_on_market_update' ), 10, 4 );

		add_action( 'update_post_meta', array( __CLASS__, 'check_price_change' ), 10, 4 );
	}

	public static function check_price_change( $meta_id, $object_id, $meta_key, $meta_value )
	{
		if ( get_post_type($object_id) == 'property' && ( $meta_key == '_price' || $meta_key == '_rent' ) )
		{
			$original_value = get_post_meta( $object_id, $meta_key, TRUE );

			if ( $original_value != $meta_value )
			{
				$current_user = wp_get_current_user();

	            // Add note/comment to property
	            $comment = array(
	                'note_type' => 'action',
	                'action' => 'property_price_change',
	                'original_value' => $original_value,
	                'new_value' => $meta_value
	            );

	            $data = array(
	                'comment_post_ID'      => (int)$object_id,
	                'comment_author'       => $current_user->display_name,
	                'comment_author_email' => 'propertyhive@noreply.com',
	                'comment_author_url'   => '',
	                'comment_date'         => date("Y-m-d H:i:s"),
	                'comment_content'      => serialize($comment),
	                'comment_approved'     => 1,
	                'comment_type'         => 'propertyhive_note',
	            );
	            $comment_id = wp_insert_comment( $data );

	            update_post_meta( $object_id, '_price_change_date', date("Y-m-d H:i:s") );
			}
		}
	}

	public static function check_on_market_add( $object_id, $meta_key, $meta_value )
	{
		if ( get_post_type($object_id) == 'property' && $meta_key == '_on_market' )
		{
			if ( $meta_value == 'yes' )
			{
				$note_action = 'property_on_market';

				$current_user = wp_get_current_user();

	            // Add note/comment to property
	            $comment = array(
	                'note_type' => 'action',
	                'action' => $note_action,
	            );

	            $data = array(
	                'comment_post_ID'      => (int)$object_id,
	                'comment_author'       => $current_user->display_name,
	                'comment_author_email' => 'propertyhive@noreply.com',
	                'comment_author_url'   => '',
	                'comment_date'         => date("Y-m-d H:i:s"),
	                'comment_content'      => serialize($comment),
	                'comment_approved'     => 1,
	                'comment_type'         => 'propertyhive_note',
	            );
	            $comment_id = wp_insert_comment( $data );

	            update_post_meta( $object_id, '_on_market_change_date', date("Y-m-d H:i:s") );
			}
		}
	}

	public static function check_on_market_update( $meta_id, $object_id, $meta_key, $meta_value )
	{
		if ( get_post_type($object_id) == 'property' && $meta_key == '_on_market' )
		{
			$original_value = get_post_meta( $object_id, $meta_key, TRUE );

			if ( $original_value != $meta_value )
			{
				$note_action = 'property_off_market';
				if ($meta_value == 'yes')
				{
					$note_action = 'property_on_market';
				}

				$current_user = wp_get_current_user();

	            // Add note/comment to property
	            $comment = array(
	                'note_type' => 'action',
	                'action' => $note_action,
	            );

	            $data = array(
	                'comment_post_ID'      => (int)$object_id,
	                'comment_author'       => $current_user->display_name,
	                'comment_author_email' => 'propertyhive@noreply.com',
	                'comment_author_url'   => '',
	                'comment_date'         => date("Y-m-d H:i:s"),
	                'comment_content'      => serialize($comment),
	                'comment_approved'     => 1,
	                'comment_type'         => 'propertyhive_note',
	            );
	            $comment_id = wp_insert_comment( $data );

	            update_post_meta( $object_id, '_on_market_change_date', date("Y-m-d H:i:s") );
			}
		}
	}

	/**
	 * Exclude propertyhive notes from queries and RSS.
	 *
	 * This code should exclude propertyhive_note comments from queries. Some queries (like the recent comments widget on the dashboard) are hardcoded.
	 * @param  array $clauses
	 * @return array
	 */
	public static function exclude_note_comments( $clauses ) {
		//global $wpdb, $typenow;

		if ( is_admin() && function_exists( 'get_current_screen' ) )
		{
			$screen = get_current_screen();

			if ( isset($screen->id) && in_array( $screen->id, apply_filters( 'propertyhive_post_types_with_notes', array( 'property', 'contact', 'enquiry', 'appraisal', 'viewing', 'offer', 'sale' ) ) ) )
			{
				return $clauses; // Don't hide when viewing Property Hive record
			}
		}

		$clauses['where'] .= ' AND comment_type != "propertyhive_note"';   

		return $clauses;
	}

	/**
	 * Delete comments count cache whenever there is
	 * new comment or the status of a comment changes. Cache
	 * will be regenerated next time PH_Comments::wp_count_comments()
	 * is called.
	 *
	 * @return void
	 */
	public static function delete_comments_count_cache() {
		delete_transient( 'ph_count_comments' );
	}
	/**
	 * Remove propertyhive notes from wp_count_comments().
	 * @since  1.0.0
	 * @param  object $stats
	 * @param  int $post_id
	 * @return object
	 */
	public static function wp_count_comments( $stats, $post_id ) {
		global $wpdb;
		if ( 0 === $post_id ) {
			$stats = get_transient( 'ph_count_comments' );
			if ( ! $stats ) {
				$stats = array();
				$count = $wpdb->get_results( "SELECT comment_approved, COUNT( * ) AS num_comments FROM {$wpdb->comments} WHERE comment_type != 'propertyhive_note' GROUP BY comment_approved", ARRAY_A );
				$total = 0;
				$approved = array( '0' => 'moderated', '1' => 'approved', 'spam' => 'spam', 'trash' => 'trash', 'post-trashed' => 'post-trashed' );
				foreach ( (array) $count as $row ) {
					// Don't count post-trashed toward totals
					if ( 'post-trashed' != $row['comment_approved'] && 'trash' != $row['comment_approved'] ) {
						$total += $row['num_comments'];
					}
					if ( isset( $approved[ $row['comment_approved'] ] ) ) {
						$stats[ $approved[ $row['comment_approved'] ] ] = $row['num_comments'];
					}
				}
				$stats['total_comments'] = $total;
				$stats['all'] = $total;
				foreach ( $approved as $key ) {
					if ( empty( $stats[ $key ] ) ) {
						$stats[ $key ] = 0;
					}
				}
				$stats = (object) $stats;
				set_transient( 'ph_count_comments', $stats );
			}
		}
		return $stats;
	}
}

PH_Comments::init();