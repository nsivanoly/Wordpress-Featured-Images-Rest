<?php
/**
 * Plugin Name: Wordpress Featured Images Rest
 * Description: This plugin adds a featured_image field to the post object that contains the available image sizes and urls, allowing you to get this information without making a second request.
 * Author: N Sivanoly
 * Author URI: https://github.com/nsivanoly
 * Version: 1.0.0
 * Plugin URI: https://github.com/nsivanoly/Advanced-Custom-Fields-Advanced-Relationship-REST
*/


add_action( 'init', 'featured_images_rest_init', 12 );
/**
 * Register our enhanced better_featured_image field to all public post types
 * that support post thumbnails.
 *
 * @since  1.0.0
 */
function featured_images_rest_init() {

	$post_types = get_post_types( array( 'public' => true ), 'objects' );

	foreach ( $post_types as $post_type ) {

		$post_type_name     = $post_type->name;
		$show_in_rest       = ( isset( $post_type->show_in_rest ) && $post_type->show_in_rest ) ? true : false;
		$supports_thumbnail = post_type_supports( $post_type_name, 'thumbnail' );

		// Only proceed if the post type is set to be accessible over the REST API
		// and supports featured images.
		if ( $show_in_rest && $supports_thumbnail ) {

			// Compatibility with the REST API v2 beta 9+
			if ( function_exists( 'register_rest_field' ) ) {
				register_rest_field( $post_type_name,
					'featured_image',
					array(
						'get_callback' => 'rest_api_featured_images_get_field',
						'schema'       => null,
					)
				);
			} elseif ( function_exists( 'register_api_field' ) ) {
				register_api_field( $post_type_name,
					'featured_image',
					array(
						'get_callback' => 'rest_api_featured_images_get_field',
						'schema'       => null,
					)
				);
			}
		}
	}
}


/*
*  wpn_featured_get_attachment
*
*  This function will return an array of attachment data
*
*  @type	function
*  @date	5/01/2015
*  @since	5.1.5
*
*  @param	$post (mixed) either post ID or post object
*  @return	(array)
*/

function wpn_featured_get_attachment( $post ) {

    // post
    $post = get_post($post);


    // bail early if no post
    if( !$post ) return false;


    // vars
    $thumb_id = 0;
    $id = $post->ID;
    $a = array(
        'ID'			=> $id,
        'id'			=> $id,
        'title'       	=> $post->post_title,
        'filename'    	=> wp_basename( $post->guid ),
        'url'			=> wp_get_attachment_url( $id ),
        'alt'			=> get_post_meta($id, '_wp_attachment_image_alt', true),
        'author'		=> $post->post_author,
        'description'	=> $post->post_content,
        'caption'		=> $post->post_excerpt,
        'name'			=> $post->post_name,
        'date'			=> $post->post_date_gmt,
        'modified'		=> $post->post_modified_gmt,
        'mime_type'		=> $post->post_mime_type,
        'type'			=> wpn_maybe_get( explode('/', $post->post_mime_type), 0, '' ),
        'icon'			=> wp_mime_type_icon( $id )
    );


    // video may use featured image
    if( $a['type'] === 'image' ) {

        $thumb_id = $id;
        $src = wp_get_attachment_image_src( $id, 'full' );

        $a['url'] = $src[0];
        $a['width'] = $src[1];
        $a['height'] = $src[2];


    } elseif( $a['type'] === 'audio' || $a['type'] === 'video' ) {

        // video dimentions
        if( $a['type'] == 'video' ) {

            $meta = wp_get_attachment_metadata( $id );
            $a['width'] = wpn_maybe_get($meta, 'width', 0);
            $a['height'] = wpn_maybe_get($meta, 'height', 0);

        }


        // feature image
        if( $featured_id = get_post_thumbnail_id($id) ) {

            $thumb_id = $featured_id;

        }

    }


    // sizes
    if( $thumb_id ) {

        // find all image sizes
        if( $sizes = get_intermediate_image_sizes() ) {

            $a['sizes'] = array();

            foreach( $sizes as $size ) {

                // url
                $src = wp_get_attachment_image_src( $thumb_id, $size );

                // add src
                $a['sizes'][ $size ] = $src[0];
                $a['sizes'][ $size . '-width' ] = $src[1];
                $a['sizes'][ $size . '-height' ] = $src[2];

            }

        }

    }


    // return
    return $a;

}

/*
*  wpn_maybe_get
*
*  This function will return a var if it exists in an array
*
*  @type	function
*  @date	9/12/2014
*  @since	5.1.5
*
*  @param	$array (array) the array to look within
*  @param	$key (key) the array key to look for. Nested values may be found using '/'
*  @param	$default (mixed) the value returned if not found
*  @return	$post_id (int)
*/

function wpn_maybe_get( $array = array(), $key = 0, $default = null ) {

    return isset( $array[$key] ) ? $array[$key] : $default;

}


/**
 * Return the better_featured_image field.
 *
 * @since   1.0.0
 *
 * @param   object  $object      The response object.
 * @param   string  $field_name  The name of the field to add.
 * @param   object  $request     The WP_REST_Request object.
 *
 * @return  object|null
 */
function rest_api_featured_images_get_field( $object, $field_name, $request ) {

	// Only proceed if the post has a featured image.
	if ( ! empty( $object['featured_media'] ) ) {
		$image_id = (int)$object['featured_media'];
	} elseif ( ! empty( $object['featured_image'] ) ) {
		// This was added for backwards compatibility with < WP REST API v2 Beta 11.
		$image_id = (int)$object['featured_image'];
	} else {
		return null;
	}

    $featured_image = wpn_featured_get_attachment($image_id);  //  use ACF for the consistency [ACF is Mandatory]

	return apply_filters( 'better_rest_api_featured_image', $featured_image, $image_id );
}
