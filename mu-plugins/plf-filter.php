<?php
/*
  Plugin Name: plugin load filter [plf-filter]
  Description: Dynamically activated only plugins that you have selected in each page. [Note] plf-filter has been automatically installed / deleted by Activate / Deactivate of "load filter plugin".
  Version: 2.2.0
  Plugin URI: http://celtislab.net/wp_plugin_load_filter
  Author: enomoto@celtislab
  Author URI: http://celtislab.net/
  License: GPLv2
*/

/***************************************************************************
 * pluggable.php defined function overwrite 
 * pluggable.php read before the query_posts () is processed by the current user undetermined
 **************************************************************************/
if ( !function_exists('wp_get_current_user') ) :
/**
 * Retrieve the current user object.
 * @return WP_User Current user WP_User object
 */
function wp_get_current_user() {
	if ( ! function_exists( 'wp_set_current_user' ) )
		return 0;

    global $current_user;
	get_currentuserinfo();

	return $current_user;
}
endif;

if ( !function_exists('get_userdata') ) :
/**
 * Retrieve user info by user ID.
 * @param int $user_id User ID
 * @return WP_User|bool WP_User object on success, false on failure.
 */
function get_userdata( $user_id ) {
	return get_user_by( 'id', $user_id );
}
endif;

if ( !function_exists('get_user_by') ) :
/**
 * Retrieve user info by a given field
 * @param string $field The field to retrieve the user with. id | slug | email | login
 * @param int|string $value A value for $field. A user ID, slug, email address, or login name.
 * @return WP_User|bool WP_User object on success, false on failure.
 */
function get_user_by( $field, $value ) {
	$userdata = WP_User::get_data_by( $field, $value );

	if ( !$userdata )
		return false;

	$user = new WP_User;
	$user->init( $userdata );

	return $user;
}
endif;

if ( !function_exists('is_user_logged_in') ) :
/**
 * Checks if the current visitor is a logged in user.
 * @return bool True if user is logged in, false if not logged in.
 */
function is_user_logged_in() {
	if ( ! function_exists( 'wp_set_current_user' ) )
		return false;
        
	$user = wp_get_current_user();

	if ( ! $user->exists() )
		return false;

	return true;
}
endif;

/***************************************************************************
 * Plugin Load Filter( Admin, Desktop, Mobile, Page 4types filter) 
 **************************************************************************/

$plugin_load_filter = new Plf_filter();

class Plf_filter {
    
    private $filter = array();  //Plugin Load Filter Setting option data
    private $cache;

    function __construct() {    
        $this->filter = get_option('plf_option');
        $this->cache  = null;
        if(!empty($this->filter)){
            add_filter('pre_option_active_plugins', array(&$this, 'active_plugins'));
            add_filter('pre_option_jetpack_active_modules', array(&$this, 'active_jetmodules'));
            add_filter('pre_option_celtispack_active_modules', array(&$this, 'active_celtismodules'));
            add_action('wp_loaded', array(&$this, 'cache_post_type'), 1);
        }
    }
    
    //Active plugins Filter
    function active_plugins( $default = false) {
        return $this->plf_filter( 'active_plugins', $default);
    }

    //Jetpack module Filter
    function active_jetmodules( $default = false) {
        return $this->plf_filter( 'jetpack_active_modules', $default);
    }

    //Celtispack module Filter
    function active_celtismodules( $default = false) {
        return $this->plf_filter( 'celtispack_active_modules', $default);
    }

    //Post Format Type, Custom Post Type Data Cache for parse request
    function cache_post_type() {  
        if (!is_admin() || ( defined('DOING_AJAX') && DOING_AJAX ) || (defined('DOING_CRON') && DOING_CRON))
            return;    

        global $wp;
        $public_query_vars = (!empty($wp->public_query_vars))? $wp->public_query_vars : array();;
        $post_type_query_vars = array();
        foreach ( get_post_types( array(), 'objects' ) as $post_type => $t ){
            if ( $t->query_var )
                $post_type_query_vars[$t->query_var] = $post_type;
        }
        $queryable_post_types = get_post_types( array('publicly_queryable' => true) );

        $data = get_option('plf_queryvars');
        if(!empty($post_type_query_vars) && !empty($queryable_post_types)){
            $data['public_query_vars']    = $public_query_vars;
            $data['post_type_query_vars'] = $post_type_query_vars;
            $data['queryable_post_types'] = $queryable_post_types;
            update_option('plf_queryvars', $data);
        }
        else if(!empty($data['post_type_query_vars']) || !empty($data['queryable_post_types'])){
            delete_option('plf_queryvars');
        }
    }

    //parse_request Action Hook for Custom Post Type query add 
    function parse_request( &$args ) {
        if (did_action( 'plugins_loaded' ) === 0 ) {
            $data = get_option('plf_queryvars');
            if(!empty($data['post_type_query_vars']) && !empty($data['queryable_post_types'])){
                $post_type_query_vars = $data['post_type_query_vars'];
                $queryable_post_types = $data['queryable_post_types'];

                $args->public_query_vars = $data['public_query_vars'];
                if ( isset( $args->matched_query ) ) {
                    parse_str($args->matched_query, $perma_query_vars);
                }
                foreach ( $args->public_query_vars as $wpvar ) {
                    if ( isset( $args->extra_query_vars[$wpvar] ) )
                        $args->query_vars[$wpvar] = $args->extra_query_vars[$wpvar];
                    elseif ( isset( $_POST[$wpvar] ) )
                        $args->query_vars[$wpvar] = $_POST[$wpvar];
                    elseif ( isset( $_GET[$wpvar] ) )
                        $args->query_vars[$wpvar] = $_GET[$wpvar];
                    elseif ( isset( $perma_query_vars[$wpvar] ) )
                        $args->query_vars[$wpvar] = $perma_query_vars[$wpvar];

                    if ( !empty( $args->query_vars[$wpvar] ) ) {
                        if ( ! is_array( $args->query_vars[$wpvar] ) ) {
                            $args->query_vars[$wpvar] = (string) $args->query_vars[$wpvar];
                        } else {
                            foreach ( $args->query_vars[$wpvar] as $vkey => $v ) {
                                if ( !is_object( $v ) ) {
                                    $args->query_vars[$wpvar][$vkey] = (string) $v;
                                }
                            }
                        }
                        if ( isset($post_type_query_vars[$wpvar] ) ) {
                            $args->query_vars['post_type'] = $post_type_query_vars[$wpvar];
                            $args->query_vars['name'] = $args->query_vars[$wpvar];
                        }
                    }
                }

                // Limit publicly queried post_types to those that are publicly_queryable
                if ( isset( $args->query_vars['post_type']) ) {
                    if ( ! is_array( $args->query_vars['post_type'] ) ) {
                        if ( ! in_array( $args->query_vars['post_type'], $queryable_post_types ) )
                            unset( $args->query_vars['post_type'] );
                    } else {
                        $args->query_vars['post_type'] = array_intersect( $args->query_vars['post_type'], $queryable_post_types );
                    }
                }
            }
        }
    }

    //Plugin Load Filter Main (active plugins/modules filtering)
    function plf_filter( $option, $default = false) {    
        if ( defined( 'WP_SETUP_CONFIG' ) )
            return false;

        //Admin mode exclude
        if(is_admin())
            return false;

        if ( ! defined( 'WP_INSTALLING' ) ) {
            // prevent non-existent options from triggering multiple queries
            $notoptions = wp_cache_get( 'notoptions', 'options' );
            if ( isset( $notoptions[$option] ) )
                return apply_filters( 'default_option_' . $option, $default );

            $alloptions = wp_load_alloptions();
            if ( isset( $alloptions[$option] ) ) {
                $value = $alloptions[$option];
            } else {
                $value = wp_cache_get( $option, 'options' );

                if ( false === $value ) {
                    $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );

                    // Has to be get_row instead of get_var because of funkiness with 0, false, null values
                    if ( is_object( $row ) ) {
                        $value = $row->option_value;
                        wp_cache_add( $option, $value, 'options' );
                    } else { // option does not exist, so we must cache its non-existence
                        $notoptions[$option] = true;
                        wp_cache_set( 'notoptions', $notoptions, 'options' );

                        /** This filter is documented in wp-includes/option.php */
                        return apply_filters( 'default_option_' . $option, $default );
                    }
                }
            }
        } else {
            return false;
        }

        $filter = $this->filter;
        //get_option is called many times, intermediate processing data to cache
        $keyid = md5('plf_'. (string)wp_is_mobile(). $_SERVER['REQUEST_URI']);
        if(!empty($this->cache[$keyid][$option])){
            return apply_filters( 'option_' . $option, $this->cache[$keyid][$option]);
        }

        //Before plugins loaded, it does not use conditional branch such as is_home, to set wp_query, wp in temporary query
        if(empty($GLOBALS['wp_the_query'])){
            $GLOBALS['wp_the_query'] = new WP_Query();
            $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
            $GLOBALS['wp_rewrite'] = new WP_Rewrite();
            $GLOBALS['wp'] = new WP();
            //Post Format, Custom Post Type support
            add_action('parse_request', array(&$this, 'parse_request'));
            $GLOBALS['wp']->parse_request('');
            $GLOBALS['wp']->query_posts();
        }
        //Only available display pages (login, cron, ajax request ... excluded)
        //downloadmanager plugin downloadlink request [home]/?wpdmact=XXXXXX  exclude home GET query
        global $wp_query;
        if((is_home() || is_front_page() || is_archive() || is_search() || is_singular()) == false 
                || (is_home() && !empty($_GET))
                || (is_singular() && empty($wp_query->post))){
            return false;
        }

        $us_value = maybe_unserialize( $value );
        $new_value = array();
        foreach ( $us_value as $item ) {
            $unload = false;
            //Jetpack module slug / Celtiapack module slug /  Plugin php file name
            if($option === 'jetpack_active_modules'){
                $p_key = 'jetpack_module/' . $item;
            }
            elseif($option === 'celtispack_active_modules'){
                $p_key = 'celtispack_module/' . str_replace( '.php', '', basename( $item) );        
            }
            else {
                $p_key = $item;
            }
            //admin mode filter
            if(!empty($filter['_admin']['plugins'])){
                if(in_array($p_key, array_map("trim", explode(',', $filter['_admin']['plugins']))))
                    $unload = true;
            }
            //desktop/mobile device filter
            if(!$unload){
                if(wp_is_mobile()){
                    if(!empty($filter['_desktop']['plugins'])){
                        if(in_array($p_key, array_map("trim", explode(',', $filter['_desktop']['plugins']))))
                            $unload = true;    
                    }
                }
                else {
                    if(!empty($filter['_mobile']['plugins'])){
                        if(in_array($p_key, array_map("trim", explode(',', $filter['_mobile']['plugins']))))
                            $unload = true;    
                    }
                }
            }
            //page filter
            if(!$unload){
                if(!empty($filter['_pagefilter']['plugins'])){
                    if(in_array($p_key, array_map("trim", explode(',', $filter['_pagefilter']['plugins']))))
                        $unload = true;

                    $pgfopt = false;
                    if(is_singular()){
                        if(is_object($wp_query->post)){
                            $opt = get_post_meta( $wp_query->post->ID, '_plugin_load_filter', true );
                            if(!empty($opt) && $opt['filter'] === 'include'){
                                $pgfopt = true;
                                if(in_array($p_key, array_map("trim", explode(',', $opt['plugins']))))
                                    $unload = false;
                                else {
                                    //Enable plugin because plugin module is selected
                                    if(strpos($p_key, 'jetpack/') !== false && strpos($opt['plugins'], 'jetpack_module/') !== false)
                                        $unload = false;
                                    else if(strpos($p_key, 'celtispack/') !== false && strpos($opt['plugins'], 'celtispack_module/') !== false)
                                        $unload = false;
                                }
                            }
                        }
                    }
                    if($pgfopt === false){
                        $type = false;
                        if(is_home() || is_front_page())
                            $type = 'home';
                        elseif(is_archive())
                            $type = 'archive';
                        elseif(is_search())
                            $type = 'search';
                        elseif(is_attachment())
                            $type = 'attachment';
                        elseif(is_page())
                            $type = 'page';
                        elseif(is_single()){ //Post & Custom Post
                            $type = get_post_type( $wp_query->post);
                            if($type === 'post'){
                                $fmt = get_post_format( $wp_query->post);
                                $type = ($fmt === 'standard' || $fmt == false)? 'post' : "post-$fmt";
                            }
                        }
                        if($type !== false && !empty($filter['group'][$type]['plugins'])){
                            if(in_array($p_key, array_map("trim", explode(',', $filter['group'][$type]['plugins']))))
                                $unload = false;
                            else {
                                if(strpos($p_key, 'jetpack/') !== false && strpos($filter['group'][$type]['plugins'], 'jetpack_module/') !== false)
                                    $unload = false;
                                else if(strpos($p_key, 'celtispack/') !== false && strpos($filter['group'][$type]['plugins'], 'celtispack_module/') !== false)
                                    $unload = false;
                            }
                        }
                    }
                }
            }
            if(!$unload) {
                $new_value[] = $item;
            }
        }
        $this->cache[$keyid][$option] = $new_value;
        return apply_filters( 'option_' . $option, $new_value );
    }
}
