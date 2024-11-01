// get a unique ID for the item
function ucgm_getUniqueID(the_object) {
	var the_ids = [];
    	
    jQuery(the_object).each(function() {
       	id_attr = jQuery(this).attr('id');
       	curr_id = id_attr.substr(id_attr.length - 1);
    	the_ids.push(parseInt(curr_id));
    });
    	
    var i = 1;
    	
    jQuery.each(the_ids, function(index, value) {
    	if (jQuery.inArray(i, the_ids) === -1) {
    		// not found, do nothing
    	} else {
    		// found
    		i = i + 1;
    	}
    });
    	
    return i;
}

jQuery(function($) {
	$('.color_picker').wpColorPicker();
	
	$('.update_preview').click(function() {
		var js_type          = $('input.ucgm_type_radio:checked').val();
		var js_border_rad    = $('input.br').val();
		var js_lin_repeat    = $('input.linear_repeat:checked').val();
		var js_lin_direction = $('select.ucgm_linear_direction').val();
		var js_lin_cusangle  = $('input.cusangle').val();
		var js_rad_repeat    = $('input.radial_repeat:checked').val();
		var js_rad_shape     = $('input.radial_shape:checked').val();
		var js_rad_size      = $('select.ucgm_radial_location').val();
		
		var color_array = [];
		var stops_array = [];
		
		$('.ucgm_dynamic_color_wrap').each(function() {
			var single_color = $(this).find('.color_picker').val();
			var color_stop   = $(this).find('.color_stop').val();
			
			if (single_color && color_stop) {
				color_array.push(single_color);
				stops_array.push(color_stop);
			}
		});
		
		if (js_type == undefined || js_type == '' || js_type == null) {
			js_type = 'linear';
		}
		
		$.ajax({
    		type: "POST",
    		url : myAjax.ajaxurl,
    		data: {
    			action        : 'update_preview',
    			colors        : color_array,
    			stops         : stops_array,
    			grad_type     : js_type,
    			border_rad    : js_border_rad,
    			lin_repeat    : js_lin_repeat,
    			lin_direction : js_lin_direction,
    			lin_cusangle  : js_lin_cusangle,
    			rad_repeat    : js_rad_repeat,
    			rad_shape     : js_rad_shape,
    			rad_size      : js_rad_size
    		}
    	}).done(function( msg ) {
    		$('#ucgm_preview_css').html(msg);
    	});
	});
	
	// add new item
	$('.add_color').live('click', function() {
		
		var numOfItems = jQuery('.ucgm_dynamic_color_wrap').length;
		numOfItems = numOfItems + 1;
		var new_itemID = ucgm_getUniqueID('.ucgm_dynamic_color_wrap');
		
		var appendthis = '<div class="ucgm_dynamic_color_wrap" id="item_' + new_itemID + '"><span class="ucgm_span ucgm_span_color"><input type="text" class="color_picker" name="ucgm_colors[' + new_itemID + '][color]"></span><span class="ucgm_span ucgm_span_percent">Stop Percent: <input min="0" max="360" type="number" class="color_stop" name="ucgm_colors[' + new_itemID + '][stop_percent]">%</span><span class="ucgm_span ucgm_span_remove"><input type="button" value="X" class="button remove_item"></span></div>';
		
		$('#ucgm_dynamic_colors').append(appendthis);
		$('.color_picker').wpColorPicker();
	});
	
	// remove single item
    $(".remove_item").live('click', function() {
        $(this).parents('.ucgm_dynamic_color_wrap').remove();
    });
    
    // type radio onchange handler
    $('input[type=radio].ucgm_type_radio').change(function() {
        if (this.value == 'linear') {
        	$('#ucgm_type_wrap__radial').hide();
        	$('#ucgm_type_wrap__linear').fadeIn();           
        } else if (this.value == 'radial') {
        	$('#ucgm_type_wrap__linear').hide();
            $('#ucgm_type_wrap__radial').fadeIn();
        }
    });
    
    // custom angle handler
    $('select.ucgm_linear_direction').change(function() {
    	if (this.value == 'custom') {
    		$('.custom_angle_wrap').fadeIn();
    	} else {
    		$('.custom_angle_wrap').hide();
    	}
    });
        
    // prevent 2 of the same values for stop percent  
    $("#post").submit(function(e) {
        var self = this;        
        
        var arr = [];
        $("input.color_stop").each(function() {
            var value = $(this).val();
            if (value) {
	            if (arr.indexOf(value) == -1) {
	                arr.push(value);
	            } else {
	                alert('Error: You cannot have two stop percentages that are the same.');
	                this.focus();
	                e.preventDefault();
	                return false;
	            }
        	}
        }); 
        return true;
    });
});