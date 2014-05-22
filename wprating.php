<?php

/*
  Plugin Name:    WP Rating
  Plugin URI:     http://blog.pucp.edu.pe/plugins
  Description:    Plugin to add five star rating, works with multisite.
  Version:        0.1
  Author:         Rom&aacute;n Huerta
  Author URI:     https://plus.google.com/+RománHuerta/posts
  License:        GPLv2 or later
  License URI: 	http://www.gnu.org/licenses/gpl-2.0.html

  Copyright 2014 Román Huerta (email : rhuerta@pucp.edu.pe)

 */

define('R_SESSION_TIME', 1800);
define('R_SESSION_NAME', 'WPRATING');

function rating_create_tables($table_name) {
    global $wpdb;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        post_id bigint(20) NOT NULL,
        voted_hash varchar(32) NOT NULL
    );";
    dbDelta($sql);
}

function rating_drop_tables($table_name) {
    global $wpdb;

    $sql = "DROP TABLE IF EXISTS $table_name;";
    $wpdb->query($sql);
}

function rating_activate($networkwide) {
    global $wpdb;

    if (function_exists('is_multisite') && is_multisite()) {

        if ($networkwide) {
            $old_blog = $wpdb->blogid;

            $blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blogids as $blog_id) {
                switch_to_blog($blog_id);
                _rating_activate();
            }
            switch_to_blog($old_blog);
            return;
        }
    }
    _rating_activate();
}

register_activation_hook(__FILE__, 'rating_activate');

function _rating_activate() {

    // Create term metadata table if necessary
    global $wpdb;
    $type = 'rating';
    $table_name = $wpdb->prefix . $type;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
        rating_create_tables($table_name);
    }
}

function rating_deactivate($networkwide) {
    global $wpdb;

    if (function_exists('is_multisite') && is_multisite()) {
        if ($networkwide) {
            $old_blog = $wpdb->blogid;

            $blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            foreach ($blogids as $blog_id) {
                switch_to_blog($blog_id);
                _rating_deactivate();
            }
            switch_to_blog($old_blog);
            return;
        }
    }
    _rating_deactivate();
}

register_deactivation_hook(__FILE__, 'rating_deactivate');

function _rating_deactivate() {

    // Create term metadata table if necessary
    global $wpdb;
    $type = 'rating';
    $table_name = $wpdb->prefix . $type;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
        rating_drop_tables($table_name);
    }
}

function add_rating_links($content) {
    global $wpdb;
    
    if (get_post_type() != 'post') {
        return $content;
    }
    $post_id = get_the_ID();
    $star_numbers = 0;
    $votes_numbers = 0;
    post_rating($post_id, 'get', $star_numbers, $votes_numbers);
    $rating_links = '';
    for ($i = 1; $i <= 5; $i++) {
        $star_class = '';
        if ($i <= round($star_numbers)) {
            $star_class = ' star-on';
        }
        $rating_links .= "<a class='star $star_class' value='{$post_id}_{$i}'></a>";
    }
    
    $rating_tablename = $wpdb->prefix.'rating';
    if ($current_user = get_current_user_id()){
        $voted_hash = md5($current_user);
    }
    else{
        $voted_hash = md5(get_session_id());
    }
    $already_voted = $wpdb->get_var( "SELECT COUNT(*) FROM $rating_tablename WHERE post_id = $post_id AND voted_hash = '$voted_hash'");
    
    $stars_class = 'rating-stars';
    if ($already_voted){
        $stars_class = 'rating-stars-already';
    }
    
    $rating_links = "<div>"
            . "<div class='$stars_class'>$rating_links</div>"
            . "<div class='current-rating'>"
            . "Puntuaci&oacute;n: <span class='rating'>$star_numbers</span> / "
            . "Votos: <span class='votes'>$votes_numbers</span>"
            . "</div>"
            . "</div>";

    return "<div>$content</div>$rating_links";
}

add_filter('the_content', 'add_rating_links');

function register_plugin_styles() {
    wp_register_style('wprating', plugins_url('wprating/css/wprating.css'));
    wp_enqueue_style('wprating');
}

add_action('wp_enqueue_scripts', 'register_plugin_styles');

function register_plugin_scripts() {
    wp_register_script('wprating', plugins_url('wprating/js/wprating.js'), array('jquery'));
    wp_enqueue_script('wprating');
    wp_localize_script('wprating', 'WPRating', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('my-special-string')
    ));
}

add_action('wp_enqueue_scripts', 'register_plugin_scripts');

function post_rating($post_id, $action = 'get', &$rating = 0, &$votes = 0) {
    switch ($action) {
        case 'update':
            add_post_meta($post_id, 'rating', $rating, true) or update_post_meta($post_id, 'rating', $rating);
            add_post_meta($post_id, 'votes', $votes, true) or update_post_meta($post_id, 'votes', $votes);
            break;
        case 'delete':
            delete_post_meta($post_id, 'rating');
            delete_post_meta($post_id, 'votes');
            break;
        case 'get':
            $rating = get_post_meta($post_id, 'rating', true);
            if (empty($rating)) {
                add_post_meta($post_id, 'rating', 0, true);
                $rating = 0;
            }
            $votes = get_post_meta($post_id, 'votes', true);
            if (empty($votes)) {
                add_post_meta($post_id, 'votes', 0, true);
                $votes = 0;
            }
            break;
        default:
            return false;
            break;
    }
}

// The function that handles the AJAX request
function add_rating_vote_callback() {
    global $wpdb;
    check_ajax_referer('my-special-string', 'security');
    
    $rating_tablename = $wpdb->prefix.'rating';
    if ($current_user = get_current_user_id( )){
        $voted_hash = md5($current_user);
    }
    else{
        $voted_hash = md5(get_session_id());
    }
    $already_voted = $wpdb->get_var( "SELECT COUNT(*) FROM $rating_tablename WHERE post_id = {$_POST['post_id']} AND voted_hash = '$voted_hash'");
    
    if ($already_voted){
        echo json_encode(array('error' => __('Already voted.')));
        die();
    }
    
    $rating = 0;
    $votes = 0;
    post_rating($_POST['post_id'], 'get', $rating, $votes);
    $rating = round((($rating * $votes) + $_POST['rating_vote']) / ($votes + 1), 2);
    $votes++;
    post_rating($_POST['post_id'], 'update', $rating, $votes);
    $rows_affected = $wpdb->insert("$rating_tablename",array('post_id' => $_POST['post_id'], 'voted_hash' => $voted_hash));
    header("Content-Type: application/json");
    echo json_encode(array('rating' => $rating, 'votes' => $votes), JSON_FORCE_OBJECT);
    die();
}

if (is_admin()) {
    add_action('wp_ajax_add_rating_vote', 'add_rating_vote_callback');
    add_action('wp_ajax_nopriv_add_rating_vote', 'add_rating_vote_callback');
}

function register_session() {
    session_name(R_SESSION_NAME);
    session_start();
    if (!isset($_SESSION['last_access'])){
        $_SESSION['last_access'] = time();
    }
    if (($_SESSION['last_access'] + R_SESSION_TIME) < time()){
        $_SESSION['last_access'] = time();
        session_regenerate_id();
    }
    else{
        $_SESSION['last_access'] = time();
    }
}

function get_session_id() {
    session_name(R_SESSION_NAME);
    session_start();
    return session_id();
}
add_action('init','register_session');