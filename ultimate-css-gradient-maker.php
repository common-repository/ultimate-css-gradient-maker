<?php
/*
 Plugin Name: Ultimate CSS Gradient Maker
 Description: Wrap any page or post content in a completely customizable css background gradient, quickly and easily
 Version:     1.3
 Author:      Corporate Zen
 Author URI:  http://www.corporatezen.com/
 License:     GPL3
 License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 
 Ultimate CSS Gradient Maker is free software: you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation, either version 2 of the License, or
 any later version.
 
 Ultimate CSS Gradient Maker is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU General Public License for more details.
 
 You should have received a copy of the GNU General Public License
 along with Responsive Food and Drink Menu. If not, see https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) or die( 'Error: Direct access to this code is not allowed.' );

// de-activate hook
function ucgm_deactivate_plugin() {
    // clear the permalinks to remove our post type's rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ucgm_deactivate_plugin' );


// activation hook
function ucgm_active_plugin() {
    // trigger our function that registers the custom post type
    ucgm_setup_post_type();
    
    // clear the permalinks after the post type has been registered
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ucgm_active_plugin' );

// Our custom post type function
function ucgm_setup_post_type() {
    
    $labels = array(
        'name'                => 'Gradient',
        'singular_name'       => 'Gradient',
        'menu_name'           => 'Gradients',
        'all_items'           => 'All Gradients',
        'view_item'           => 'View Gradient',
        'add_new_item'        => 'Add New Gradient',
        'add_new'             => 'Add New',
        'edit_item'           => 'Edit Gradient',
        'update_item'         => 'Update Gradient',
        'search_items'        => 'Search Gradients',
        'not_found'           => 'Not Found',
        'not_found_in_trash'  => 'Not found in Trash'
    );
    
    $args = array(
        'labels'             => $labels,
        'menu_icon'          => 'dashicons-star-half',
        'description'        => 'description here',
        'public'             => true,
        'publicly_queryable' => false,
        'show_in_nav_menus'  => true,
        'capability_type'    => 'post',
        'map_meta_cap'       => true,
        'menu_position'      => 999,
        'hierarchical'       => false,
        'rewrite'            => array('slug' => 'menu', 'with_front' => false),
        'query_var'          => false,
        'delete_with_user'   => false,
        'supports'           => array( 'title' ),
        'show_in_rest'       => true,
        'rest_base'          => 'pages',
        'rest_controller_class' => 'WP_REST_Posts_Controller'
    );
    
    register_post_type( 'ucgm_gradient_cpt', $args );
}
add_action( 'init', 'ucgm_setup_post_type' );

// Add the custom columns to the view all list on the admin side
add_filter( 'manage_posts_columns', 'ucgm_set_custom_edit_columns' );
function ucgm_set_custom_edit_columns($columns) {
    $columns['shortcode'] = 'Shortcode <br><i>(use this to display your info anywhere on your site)</i>';
    
    return $columns;
}

// Add the data to the custom columns on the view all list on the admin side
add_action( 'manage_posts_custom_column' , 'ucgm_custom_column', 10, 2 );
function ucgm_custom_column( $column, $post_id ) {
    switch ( $column ) {
        case 'shortcode' :
            echo '[ucgm p=' . $post_id . ']';
            break;
    }
}

function ucgm_admin_enqueue($hook) {

    global $post;
    
    if ($post->post_type != 'ucgm_gradient_cpt')  {
        return;
    }
    
    wp_enqueue_style( 'wp-color-picker');
    wp_enqueue_script( 'wp-color-picker');
    
    wp_enqueue_style('ucgm_admin_style', plugins_url( '/css/ucgm_admin.css', __FILE__ ) );
    
    wp_register_script('ucgm_admin_js', plugins_url( '/js/ucgm_admin.js', __FILE__ ), array('jquery'));
    wp_localize_script('ucgm_admin_js', 'myAjax', array('ajaxurl' => admin_url( 'admin-ajax.php' )));
    wp_enqueue_script('ucgm_admin_js');
}
add_action( 'admin_enqueue_scripts', 'ucgm_admin_enqueue' );

// register meta boxes
function ucgm_add_metaboxes() {
    add_meta_box('ucgm_shortcode', 'Shortcode', 'ucgm_fill_shortcode_metabox', 'ucgm_gradient_cpt', 'normal', 'high');
    add_meta_box('ucgm_colors', 'Colors', 'ucgm_fill_colors_metabox', 'ucgm_gradient_cpt', 'normal', 'default');
    add_meta_box('ucgm_type', 'Type', 'ucgm_fill_type_metabox', 'ucgm_gradient_cpt', 'normal', 'default');
    add_meta_box('ucgm_settings', 'Settings', 'ucgm_fill_settings_metabox', 'ucgm_gradient_cpt', 'normal', 'default');
}
add_action( 'add_meta_boxes', 'ucgm_add_metaboxes' );

function ucgm_fill_shortcode_metabox() {
    global $post;
    echo '<div>Wrap this shortcode around whatever content you want to display your gradient behind. For example:<br><br> [ucgm p=' . $post->ID . ']This content will have a gradient behind it![/ucgm]</div>';
}

// fill colors box
function ucgm_fill_colors_metabox() {
    global $post;
    
    $colors = get_post_meta( $post->ID, 'ucgm_colors', true );
    if (empty($colors)) {
        $colors = array();
    }
    $color_item_id = 0;
    
    $color_css = '';
    $gradient_css = '';
    $nonempty_colors = array();
    
    // built array of all non empty colors
    foreach ($colors as $color) {
        if (!empty($color['color']) && !empty($color['stop_percent'])) {
            $nonempty_colors[] = $color;
        }
    }
    
    $color_count = count($nonempty_colors);
    $i = 0;
    
    $fallback_css = '';
    foreach ($nonempty_colors as $color) {
        $i = $i + 1;
        
        // fallback for if the gradient breaks or browser doesn't support it
        if ($i == 1) {
            $fallback_css = 'background-color: ' . $color['color'] . ';';
        }
        
        $color_css .= ' ' . $color['color'] . ' ' . $color['stop_percent'] . '%';
        if ($i != $color_count) {
            $color_css .= ',';
        }
    }
    
    $settings = get_post_meta( $post->ID, 'ucgm_settings', true );
    
    if (isset($settings['type']) && $settings['type'] == 'linear') { // process settings for linear
        if (isset($settings['linear_repeat']) && $settings['linear_repeat'] == '1') {
            $base = 'repeating-linear-gradient';
        } else {
            $base = 'linear-gradient';
        }
        
        $linear_direction = 'to bottom';
        if (isset($settings['linear_direction'])) {
            switch ($settings['linear_direction']) {
                case 'to_top':
                    $linear_direction = 'to top';
                    break;
                case 'to_bottom_left':
                    $linear_direction = 'to bottom left';
                    break;
                case 'to_bottom_right':
                    $linear_direction = 'to bottom right';
                    break;
                case 'to_top_left':
                    $linear_direction = 'to top left';
                    break;
                case 'to_top_right':
                    $linear_direction = 'to top right';
                    break;
                case 'to_right':
                    $linear_direction = 'to right';
                    break;
                case 'to_left':
                    $linear_direction = 'to left';
                    break;
                case 'custom':
                    if (isset($settings['linear_custom_angle'])) {
                        $linear_direction = $settings['linear_custom_angle'] . 'deg';
                    }
                    break;
            }
        }
        
        $gradient_css = 'background: ' . $base . '(' . $linear_direction . ', ' . $color_css . ');';
        
        
    } else if (isset($settings['type']) && $settings['type'] == 'radial') { // process settings for radial
        
        if (isset($settings['radial_repeat']) && $settings['radial_repeat'] == '1') {
            $base = 'repeating-radial-gradient';
        } else {
            $base = 'radial-gradient';
        }
        
        $radial_shape = 'ellipse';
        if (isset($settings['radial_shape']) && $settings['radial_shape'] == 'circle') {
            $radial_shape = 'circle';
        }
        
        $radial_size = 'farthest-corner';
        if (isset($settings['radial_location'])) {
            switch ($settings['radial_location']) {
                case 'closest-corner':
                    $radial_size = 'closest-corner';
                    break;
                case 'farthest-side':
                    $radial_size = 'farthest-side';
                    break;
                case 'closest-side':
                    $radial_size = 'closest-side';
                    break;
            }
        }
        
        $gradient_css = 'background: ' . $base . '(' . $radial_shape . ' ' . $radial_size . ', ' . $color_css . ');';
    }
    
    echo '<style>#ucgm_preview {' . $gradient_css . '}</style>';
    
    echo '<div class="not_preview_div_wrap">';
        echo '<p><input type="button" class="button-primary button add_color" value="Add Color" /></p>';
        
        echo '<p><i>The stop percentages should go from smallest to largest. For example, 25% | 50% | 75%. Not following this rule may cause your gradient to look strange.</i></p>';
        
        echo '<div id="ucgm_dynamic_colors">';
        foreach ($colors as $color) { 
            $color_item_id = $color_item_id + 1;
            echo '<div class="ucgm_dynamic_color_wrap" id="item_' . $color_item_id . '">';
                echo '<span class="ucgm_span ucgm_span_color"><input type="text" class="color_picker" value="' . $color['color'] . '" name="ucgm_colors[' . $color_item_id . '][color]"></span>';
                echo '<span class="ucgm_span ucgm_span_percent">Stop Percent: <input step="1" min="0" max="100" value="' . $color['stop_percent'] . '" type="number" class="color_stop" name="ucgm_colors[' . $color_item_id . '][stop_percent]">%</span>';
                echo '<span class="ucgm_span ucgm_span_remove"><input type="button" value="X" class="remove_item button"></span>';
            echo '</div>';
        }
        echo '</div>';
        
        echo '<br><br>';
        echo '<input type="button" class="button button-primary update_preview" id="" value="Update Preview">';
    echo '</div>';
    
    echo '<div class="clear"></div>';
    
    echo '<div class="preview_div_wrap">';
        echo '<div id="ucgm_preview_css"></div>';
        echo '<div id="ucgm_preview"><h1>Your gradient preview</h1></div>';
    echo '</div>';
    
    echo '<div class="clear"></div>';
}

// fill type box
function ucgm_fill_type_metabox() {
    global $post;
    
    $settings = get_post_meta( $post->ID, 'ucgm_settings', true );
    
    echo '<div id="ucgm_type_wrap">';
        echo '<span class="ucgm_type_span ucgm_type_span_linear">Linear</span><input required ' . (isset($settings['type']) && $settings['type'] == 'linear' ? 'checked' : '') . ' type="radio" class="ucgm_type_radio" value="linear" name="ucgm_settings[type]">';
        echo '<span class="ucgm_type_span ucgm_type_span_radial" >Radial</span><input ' . (isset($settings['type']) && $settings['type'] == 'radial' ? 'checked' : '') . ' type="radio" class="ucgm_type_radio" value="radial" name="ucgm_settings[type]">';
        
        $type_display_linear = 'display: none;';
        $type_display_radial = 'display: none;';
        if (isset($settings['type']) && $settings['type'] == 'linear') {
            $type_display_linear = '';
        }
        
        if (isset($settings['type']) && $settings['type'] == 'radial') {
            $type_display_radial = '';
        }
        
        ////////////////////////// LINEAR //////////////////////////
        
        $linear_repeat_yes = '';
        $linear_repeat_no = 'checked';
        
        if (isset($settings['linear_repeat']) && $settings['linear_repeat'] == '1' ) {
            $linear_repeat_yes = 'checked';
            $linear_repeat_no = '';
        }               
        
        echo '<div id="ucgm_type_wrap__linear" style="' . $type_display_linear . '">';
            echo '<p>Repeat?</p>';
            echo '<span class="ucgm_type_span ucgm_type_span_linear">Yes</span><input ' . $linear_repeat_yes . ' type="radio" value="1" class="linear_repeat" name="ucgm_settings[linear_repeat]">';
            echo '<span class="ucgm_type_span ucgm_type_span_radial">No</span><input  ' . $linear_repeat_no . '  type="radio" value="0" class="linear_repeat" name="ucgm_settings[linear_repeat]">';
            
            echo '<p>Direction</p>';
            echo '<select name="ucgm_settings[linear_direction]" class="ucgm_linear_direction">
                    <option ' . (isset($settings['linear_direction']) && $settings['linear_direction'] == 'to_bottom' ? 'selected' : '') . ' value="to_bottom">Top to Bottom</option>
                    <option ' . (isset($settings['linear_direction']) && $settings['linear_direction'] == 'to_top' ? 'selected' : '') . ' value="to_top">Bottom to Top</option>
                    <option ' . (isset($settings['linear_direction']) && $settings['linear_direction'] == 'to_bottom_left' ? 'selected' : '') . ' value="to_bottom_left">Top Right to Bottom Left</option>
                    <option ' . (isset($settings['linear_direction']) && $settings['linear_direction'] == 'to_bottom_right' ? 'selected' : '') . ' value="to_bottom_right">Top Left to Bottom Right</option>
                    <option ' . (isset($settings['linear_direction']) && $settings['linear_direction'] == 'to_top_left' ? 'selected' : '') . ' value="to_top_left">Bottom Right to Top Left</option>
                    <option ' . (isset($settings['linear_direction']) && $settings['linear_direction'] == 'to_top_right' ? 'selected' : '') . ' value="to_top_right">Bottom Left to Top Right</option>
                    <option ' . (isset($settings['linear_direction']) && $settings['linear_direction'] == 'to_right' ? 'selected' : '') . ' value="to_right">Left to Right</option>
                    <option ' . (isset($settings['linear_direction']) && $settings['linear_direction'] == 'to_left' ? 'selected' : '') . ' value="to_left">Right to Left</option>
                    <option ' . (isset($settings['linear_direction']) && $settings['linear_direction'] == 'custom' ? 'selected' : '') . ' value="custom">Custom Angle</option>
                  </select>';
            
            echo '<div class="custom_angle_wrap" style="' . (isset($settings['linear_direction']) && $settings['linear_direction'] == 'custom' ? '' : 'display:none;') . '">';
                echo '<p>Custom Angle</p>';
                echo '<input type="number" class="cusangle" step="1" min="0" max="360" value="' . (isset($settings['linear_custom_angle']) ? $settings['linear_custom_angle'] : '') . '" name="ucgm_settings[linear_custom_angle]"> degrees';
            echo '</div>';
        echo '</div><!-- #ucgm_type_wrap__linear -->'; // end of #ucgm_type_wrap__linear
        
        ////////////////////////// RADIAL //////////////////////////
        
        $radial_repeat_yes = '';
        $radial_repeat_no = 'checked';
        
        if (isset($settings['radial_repeat']) && $settings['radial_repeat'] == '1' ) {
            $radial_repeat_yes = 'checked';
            $radial_repeat_no = '';
        }
        
        echo '<div id="ucgm_type_wrap__radial" style="' . $type_display_radial . '">';
            echo '<p>Repeat?</p>';
            echo '<span class="ucgm_type_span ucgm_type_span_linear">Yes</span><input type="radio" ' . $radial_repeat_yes . ' value="1" class="radial_repeat" name="ucgm_settings[radial_repeat]">';
            echo '<span class="ucgm_type_span ucgm_type_span_radial">No</span><input type="radio"  ' . $radial_repeat_no .  ' value="0" class="radial_repeat" name="ucgm_settings[radial_repeat]">';
            
            echo '<p>Shape?</p>';
            echo '<span class="ucgm_type_span ucgm_type_span_linear">Ellipse</span><input class="radial_shape" ' . (!isset($settings['radial_shape']) || $settings['radial_shape'] == 'ellipse' ? 'checked' : '') . ' type="radio" value="ellipse" name="ucgm_settings[radial_shape]">';
            echo '<span class="ucgm_type_span ucgm_type_span_radial">Circle</span><input class="radial_shape" ' . (isset($settings['radial_shape']) && $settings['radial_shape'] == 'circle' ? 'checked' : '') . ' type="radio" value="circle" name="ucgm_settings[radial_shape]">';
            
            echo '<p>Style</p>';
            echo '<select class="ucgm_radial_location" name="ucgm_settings[radial_location]">
                    <option ' . (isset($settings['radial_location']) && $settings['radial_location'] == 'farthest-corner' ? 'selected' : '') . ' value="farthest-corner">Style 1</option>
                    <!--<option ' . (isset($settings['radial_location']) && $settings['radial_location'] == 'closest-corner' ? 'selected' : '') . ' value="closest-corner">Style 2</option>-->
                    <!--<option ' . (isset($settings['radial_location']) && $settings['radial_location'] == 'farthest-side' ? 'selected' : '') . ' value="farthest-side">Style 3</option>-->
                    <option ' . (isset($settings['radial_location']) && $settings['radial_location'] == 'closest-side' ? 'selected' : '') . ' value="closest-side">Style 2</option>
                  </select>';
        echo '</div><!-- #ucgm_type_wrap__radial -->'; // end of #ucgm_type_wrap__radial
          
    echo '</div><!-- .ucgm_type_wrap -->'; // end of .ucgm_type_wrap
    
    echo '<input type="button" class="button button-primary update_preview" value="Update Preview">';
          
}

// fill settings box
function ucgm_fill_settings_metabox() {
    global $post;
    
    $settings = get_post_meta( $post->ID, 'ucgm_settings', true );
    
    echo '<div id="ucgm_settings_wrap">';
    
    echo '<div class="ucgm_setting_wrap"><span class="ucgm_set_span">Border Radius:     </span><input value="' . (isset($settings['border_radius']) ? $settings['border_radius'] : '0') . '" type="number" step="1" min="0" max="999" class="br" name="ucgm_settings[border_radius]">px</div>';
    echo '<div class="ucgm_setting_wrap"><span class="ucgm_set_span">Top/Bottom Padding:</span><input value="' . (isset($settings['topbottom_padding']) ? $settings['topbottom_padding'] : '10') . '" type="number" step="1" min="0" max="999" class="tbp" name="ucgm_settings[topbottom_padding]">px</div>';
    echo '<div class="ucgm_setting_wrap"><span class="ucgm_set_span">Left/Right Padding:</span><input value="' . (isset($settings['leftright_padding']) ? $settings['leftright_padding'] : '10') . '" type="number" step="1" min="0" max="999" class="lrp" name="ucgm_settings[leftright_padding]">px</div>';
    echo '<div class="ucgm_setting_wrap"><span class="ucgm_set_span">Top/Bottom Margin:</span><input value="' . (isset($settings['topbottom_margin']) ? $settings['topbottom_margin'] : '0') . '" type="number" step="1" min="0" max="999" class="tbm" name="ucgm_settings[topbottom_margin]">px</div>';
    echo '<div class="ucgm_setting_wrap"><span class="ucgm_set_span">Left/Right Margin:</span><input value="' . (isset($settings['leftright_margin']) ? $settings['leftright_margin'] : '0') . '" type="number" step="1" min="0" max="999" class="lrm" name="ucgm_settings[leftright_margin]">px</div>';
    
    echo '</div>';
}

// handles saving the metadata
function ucgm_save_dynamic_metadata($post_id, $post) {
    
    global $post;
    
    if ($post->post_type != 'ucgm_gradient_cpt') {
        return $post_id;
    }
    
    if ( !current_user_can( 'edit_post', $post_id )) {
        return $post_id;
    }
    
    $colors = ( isset( $_POST['ucgm_colors'] ) ? $_POST['ucgm_colors'] : array() );
    array_walk ( $colors, function ( &$value, &$key ) {
        $value['color']        = sanitize_hex_color( $value['color'] );
        $value['stop_percent'] = sanitize_text_field( $value['stop_percent'] );
    });
    update_post_meta( $post_id, 'ucgm_colors', $colors);
    
    $settings = ( isset( $_POST['ucgm_settings'] ) ? $_POST['ucgm_settings'] : array() );
    update_post_meta( $post_id, 'ucgm_settings', $settings);
}
add_action('save_post', 'ucgm_save_dynamic_metadata', 2, 2);

// shortcode handler
function ucgm_shortcode($atts = [], $content = null) {
    
    $post_id = (isset($atts['p']) ? $atts['p'] : 0);
    // no ID given, do nothing
    if ($post_id == 0) {
        return $content;
    }
    
    $colors = get_post_meta( $post_id, 'ucgm_colors', true );
    $color_css = '';
    $nonempty_colors = array();
    
    // built array of all non empty colors
    if (!empty($colors)) {
        foreach ($colors as $color) {
            if (!empty($color['color']) && !empty($color['stop_percent'])) {
                $nonempty_colors[] = $color;
            }
        }
    }
    
    $color_count = count($nonempty_colors);
    $i = 0;
    
    $fallback_css = '';
    foreach ($nonempty_colors as $color) {
        $i = $i + 1;
        
        // fallback for if the gradient breaks or browser doesn't support it
        if ($i == 1) {
            $fallback_css = 'background-color: ' . $color['color'] . ';';
        }
        
        $color_css .= ' ' . $color['color'] . ' ' . $color['stop_percent'] . '%';
        if ($i != $color_count) {
            $color_css .= ',';
        }
    }
    
    $settings = get_post_meta( $post_id, 'ucgm_settings', true );   
    
    if (isset($settings['type']) && $settings['type'] == 'linear') { // process settings for linear
        if (isset($settings['linear_repeat']) && $settings['linear_repeat'] == '1') {
            $base = 'repeating-linear-gradient';
        } else {
            $base = 'linear-gradient';
        }
        
        $linear_direction = 'to bottom';
        if (isset($settings['linear_direction'])) {
            switch ($settings['linear_direction']) {
                case 'to_top':
                    $linear_direction = 'to top';
                    break;
                case 'to_bottom_left':
                    $linear_direction = 'to bottom left';
                    break;
                case 'to_bottom_right':
                    $linear_direction = 'to bottom right';
                    break;
                case 'to_top_left':
                    $linear_direction = 'to top left';
                    break;
                case 'to_top_right':
                    $linear_direction = 'to top right';
                    break;
                case 'to_right':
                    $linear_direction = 'to right';
                    break;
                case 'to_left':
                    $linear_direction = 'to left';
                    break;
                case 'custom':
                    if (isset($settings['linear_custom_angle'])) {
                        $linear_direction = $settings['linear_custom_angle'] . 'deg';
                    }                   
                    break;
            }
        }
        
        $gradient_css = 'background: ' . $base . '(' . $linear_direction . ', ' . $color_css . ');';
        
        
    } else if (isset($settings['type']) && $settings['type'] == 'radial') { // process settings for radial
        
        if (isset($settings['radial_repeat']) && $settings['radial_repeat'] == '1') {
            $base = 'repeating-radial-gradient';
        } else {
            $base = 'radial-gradient';
        }
        
        $radial_shape = 'ellipse';
        if (isset($settings['radial_shape']) && $settings['radial_shape'] == 'circle') {
            $radial_shape = 'circle';
        }
        
        $radial_size = 'farthest-corner';
        if (isset($settings['radial_location'])) {
            switch ($settings['radial_location']) {
                case 'closest-corner':
                    $radial_size = 'closest-corner';
                    break;
                case 'farthest-side':
                    $radial_size = 'farthest-side';
                    break;
                case 'closest-side':
                    $radial_size = 'closest-side';
                    break;
            }
        }
        
        $gradient_css = 'background: ' . $base . '(' . $radial_shape . ' ' . $radial_size . ', ' . $color_css . ');';
    } else {
        return $content; // if no type is checked, don't do anything
    }       
    
    $style = '<style>
.ucgm_content_' . $post_id . ' {
    overflow: hidden;
    border-radius: '  . (isset($settings['border_radius']) ? $settings['border_radius'] : '') . 'px;
    padding-left: '   . (isset($settings['leftright_padding']) ? $settings['leftright_padding'] : '10') . 'px;
    padding-right: '  . (isset($settings['leftright_padding']) ? $settings['leftright_padding'] : '10') . 'px;
    padding-top: '    . (isset($settings['topbottom_padding']) ? $settings['topbottom_padding'] : '10') . 'px;
    padding-bottom: ' . (isset($settings['topbottom_padding']) ? $settings['topbottom_padding'] : '10') . 'px;
    margin-left: '    . (isset($settings['leftright_margin']) ? $settings['leftright_margin'] : '0') . 'px;
    margin-right: '   . (isset($settings['leftright_margin']) ? $settings['leftright_margin'] : '0') . 'px;
    margin-top: '     . (isset($settings['topbottom_margin']) ? $settings['topbottom_margin'] : '0') . 'px;
    margin-bottom: '  . (isset($settings['topbottom_margin']) ? $settings['topbottom_margin'] : '0') . 'px;
    ' . $fallback_css . '
    ' . $gradient_css . '
}
</style>';
    
    // always return
    return $style . '<div class="ucgm_content_' . $post_id . '">' . $content . '<div style="clear:both !important;"></div></div>';
}
add_shortcode('ucgm', 'ucgm_shortcode');

// AJAX
add_action("wp_ajax_update_preview", "ucgm_update_admin_preview");
function ucgm_update_admin_preview() {
    
    $colors = $_REQUEST['colors'];
    $stops = $_REQUEST['stops'];
    $j = 0;
    $mainarr = array();
    foreach ($colors as $color) {
        $mainarr[$j] = array('color' => $colors[$j] , 'stop_percent' => $stops[$j]);
        $j++;
    }
    
    $color_css = '';
    $color_count = count($mainarr);
    $i = 0;
    
    $fallback_css = '';
    foreach ($mainarr as $color) {
        $i = $i + 1;
        
        // fallback for if the gradient breaks or browser doesn't support it
        if ($i == 1) {
            $fallback_css = 'background-color: ' . $color['color'] . ';';
        }
        
        $color_css .= ' ' . $color['color'] . ' ' . $color['stop_percent'] . '%';
        if ($i != $color_count) {
            $color_css .= ',';
        }
    }
    
    if (!isset($_REQUEST['grad_type']) || $_REQUEST['grad_type'] == 'linear') {
        // process linear
        if (isset($_REQUEST['lin_repeat']) && $_REQUEST['lin_repeat'] == '1') {
            $base = 'repeating-linear-gradient';
        } else {
            $base = 'linear-gradient';
        }
        
        $linear_direction = 'to bottom';
        if (isset($_REQUEST['lin_direction'])) {
            switch ($_REQUEST['lin_direction']) {
                case 'to_top':
                    $linear_direction = 'to top';
                    break;
                case 'to_bottom_left':
                    $linear_direction = 'to bottom left';
                    break;
                case 'to_bottom_right':
                    $linear_direction = 'to bottom right';
                    break;
                case 'to_top_left':
                    $linear_direction = 'to top left';
                    break;
                case 'to_top_right':
                    $linear_direction = 'to top right';
                    break;
                case 'to_right':
                    $linear_direction = 'to right';
                    break;
                case 'to_left':
                    $linear_direction = 'to left';
                    break;
                case 'custom':
                    if (isset($_REQUEST['lin_cusangle'])) {
                        $linear_direction = $_REQUEST['lin_cusangle'] . 'deg';
                    }
                    break;
            }
        }
        
        $gradient_css = 'background: ' . $base . '(' . $linear_direction . ', ' . $color_css . ');';
        
    } else if ( $_REQUEST['grad_type'] == 'radial') {
        // process radial
        if (isset($_REQUEST['rad_repeat']) && $_REQUEST['rad_repeat'] == '1') {
            $base = 'repeating-radial-gradient';
        } else {
            $base = 'radial-gradient';
        }
        
        $radial_shape = 'ellipse';
        if (isset($_REQUEST['rad_shape']) && $_REQUEST['rad_shape'] == 'circle') {
            $radial_shape = 'circle';
        }
        
        $radial_size = 'farthest-corner';
        if (isset($_REQUEST['rad_size'])) {
            switch ($_REQUEST['rad_size']) {
                case 'closest-corner':
                    $radial_size = 'closest-corner';
                    break;
                case 'farthest-side':
                    $radial_size = 'farthest-side';
                    break;
                case 'closest-side':
                    $radial_size = 'closest-side';
                    break;
            }
        }
        
        $gradient_css = 'background: ' . $base . '(' . $radial_shape . ' ' . $radial_size . ', ' . $color_css . ');';
    } else {
        return;
    }
    
    echo '<style>#ucgm_preview {' . $gradient_css . '}</style>';
    exit();
    return;
}
?>