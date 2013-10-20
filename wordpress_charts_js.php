<?php
/*
Plugin Name: WordPress Charts
Plugin URI: http://wordpress.org/plugins/wp-charts/
Description: Create amazing HTML5 charts easily in WordPress. A flexible and lightweight WordPress chart plugin including 6 customizable chart types (line, bar, pie, radar, polar area and doughnut types) as well as a fallback to provide support for older IE.  Incorporates the fantastic chart.js script : http://www.chartjs.org/.
Version: 0.6.6
Author:  Paul van Zyl
Author URI: http://profiles.wordpress.org/pushplaybang/
*/

/**
 * Copyright (c) 2013 Paul van Zyl. All rights reserved.
 *
 * Released under the GPLv2 license
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * **********************************************************************
 *
 */





// Add IE Fallback for HTML5 and canvas
// - - - - - - - - - - - - - - - - - - - - - - -
function wp_charts_html5_support () {
    echo '<!--[if lte IE 8]>';
    echo '<script src="'.plugins_url( '/js/excanvas.compiled.js', __FILE__ ).'"</script>';
    echo '<![endif]-->';
    echo '	<style>
    			/*wp_charts_js responsive canvas CSS override*/
    			.wp_charts_canvas {
    				width:100%!important;
    				max-width:100%;
    			}
    		</style>';
}

// Register Script
// - - - - - - - - - - - - - - - - - - - - - - -
function wp_charts_load_scripts() {

	if ( !is_Admin() ) {
		// WP Scripts
		wp_enqueue_script( 'jquery' );

		// Register plugin Scripts
		wp_register_script( 'charts-js', plugins_url('/js/Chart.min.js', __FILE__) );
		wp_register_script( 'wp-chart-functions', plugins_url('/js/functions.js', __FILE__),'jquery','', true );

		// Enqeue those suckers
		wp_enqueue_script( 'charts-js' );
		wp_enqueue_script( 'wp-chart-functions' );
	}

}

add_action( "wp_enqueue_scripts", "wp_charts_load_scripts" );
add_action('wp_head', 'wp_charts_html5_support');

// make sure there are the right number of colors in the colour array
// - - - - - - - - - - - - - - - - - - - - - - -
if ( !function_exists('wp_charts_compare_fill') ) {
	function wp_charts_compare_fill(&$measure,&$fill) {
		// only if the two arrays don't hold the same number of elements
		if (count($measure) != count($fill)) {
		    // handle if $fill is less than $measure
		    while (count($fill) < count($measure) ) {
		        $fill = array_merge( $fill, array_values($fill) );
		    }
		    // handle if $fill has more than $measure
		    $fill = array_slice($fill, 0, count($measure));
		}
	}
}

// color conversion function
// - - - - - - - - - - - - - - - - - - - - - - -
if (!function_exists( "wp_charts_hex2rgb" )) {
	function wp_charts_hex2rgb($hex) {
	   $hex = str_replace("#", "", $hex);

	   if(strlen($hex) == 3) {
	      $r = hexdec(substr($hex,0,1).substr($hex,0,1));
	      $g = hexdec(substr($hex,1,1).substr($hex,1,1));
	      $b = hexdec(substr($hex,2,1).substr($hex,2,1));
	   } else {
	      $r = hexdec(substr($hex,0,2));
	      $g = hexdec(substr($hex,2,2));
	      $b = hexdec(substr($hex,4,2));
	   }

	   $rgb = array($r, $g, $b);
	   return implode(",", $rgb); // returns the rgb values separated by commas
	}
}

if (!function_exists('wp_charts_trailing_comma')) {
	function wp_charts_trailing_comma($incrementor, $count, &$subject) {
		$stopper = $count - 1;
		if ($incrementor !== $stopper) {
			return $subject .= ',';
		}
	}
}

// Chart Shortcode 1 - Core Shortcode with all options
// - - - - - - - - - - - - - - - - - - - - - - -
function wp_charts_shortcode( $atts ) {

	// Default Attributes
	// - - - - - - - - - - - - - - - - - - - - - - -
	extract( shortcode_atts(
		array(
			'type'             => 'pie',
			'title'            => 'chart',
			'canvaswidth'      => '625',
			'canvasheight'     => '625',
			'width'			   => '48%',
			'height'		   => 'auto',
			'margin'		   => '5px',
			'align'            => '',
			'class'			   => '',
			'labels'           => '',
			'data'             => '30,50,100',
			'datasets'         => '30,50,100 next 20,90,75',
			'colors'           => '#69D2E7,#E0E4CC,#F38630,#96CE7F,#CEBC17,#CE4264',
			'fillopacity'      => '0.7',
			'pointstrokecolor' => '#FFFFFF',
			'animation'		   => 'true',
			'scaleFontSize'    => '12',
			'scaleFontColor'   => '#666',
			'scaleOverride'    => 'false',
			'scaleSteps' 	   => 'null',
			'scaleStepWidth'   => 'null',
			'scaleStartValue'  => 'null'
		), $atts )
	);

	// prepare data
	// - - - - - - - - - - - - - - - - - - - - - - -
	$title    = str_replace(' ', '', $title);
	$data     = explode(',', str_replace(' ', '', $data));
	$datasets = explode("next", str_replace(' ', '', $datasets));
	// check that the colors are not an empty string
	if ($colors != "") {
		$colors   = explode(',', str_replace(' ','',$colors));
	} else {
		$colors = array('#69D2E7','#E0E4CC','#F38630','#96CE7F','#CEBC17','#CE4264');
	}

	(strpos($type, 'lar') !== false ) ? $type = 'PolarArea' : $type = ucwords($type);

	// output - covers Pie, Doughnut, and PolarArea
	// - - - - - - - - - - - - - - - - - - - - - - -
	$currentchart = '<div class="'.$align.' '.$class.' wp-chart-wrap" style="width:'.$width.';height:'.$height.';margin:'.$margin.';">';
	$currentchart .= '<canvas id="'.$title.'" height="'.$canvasheight.'" width="'.$canvaswidth.'" class="wp_charts_canvas"></canvas></div>
	<script>';

		// output Options
	$currentchart .= 'var '.$title.'Ops = {
		animation: '.$animation.',';

	if ($type !== 'Pie' && $type !== 'Doughnut' ) {
		$currentchart .=	'scaleFontSize: '.$scaleFontSize.',';
		$currentchart .=	'scaleFontColor: "'.$scaleFontColor.'",';
		$currentchart .=    'scaleOverride:'   .$scaleOverride.',';
		$currentchart .=    'scaleSteps:' 	   .$scaleSteps.',';
		$currentchart .=    'scaleStepWidth:'  .$scaleStepWidth.',';
		$currentchart .=    'scaleStartValue:' .$scaleStartValue;
	}

	// end options array
	$currentchart .= '}; ';

	// start the js arrays correctly depending on type
	if ($type == 'Line' || $type == 'Radar' || $type == 'Bar') {

		wp_charts_compare_fill($datasets, $colors);
		$total    = count($datasets);

		// output labels
		$currentchart .= 'var '.$title.'Data = {';
		$currentchart .= 'labels : [';
		$labelstrings = explode(',',$labels);
		for ($j = 0; $j < count($labelstrings); $j++ ) {
			$currentchart .= '"'.$labelstrings[$j].'"';
			wp_charts_trailing_comma($j, count($labelstrings), $currentchart);
		}
		$currentchart .= 	'],';
		$currentchart .= 'datasets : [';
	} else {
		wp_charts_compare_fill($data, $colors);
		$total = count($data);
		$currentchart .= 'var '.$title.'Data = [';
	}

		// create the javascript array of data and attr correctly depending on type
		for ($i = 0; $i < $total; $i++) {

			if ($type === 'Pie' || $type === 'Doughnut' || $type === 'PolarArea') {
				$currentchart .= '{
					value 	: '. $data[$i] .',
					color 	: '.'"'. $colors[$i].'"'.'
				}';

			} else if ($type === 'Bar') {
				$currentchart .= '{
					fillColor 	: "rgba('. wp_charts_hex2rgb( $colors[$i] ) .','.$fillopacity.')",
					strokeColor : "rgba('. wp_charts_hex2rgb( $colors[$i] ) .',1)",
					data 		: ['.$datasets[$i].']
				}';

			} else if ($type === 'Line' || $type === 'Radar') {
				$currentchart .= '{
					fillColor 	: "rgba('. wp_charts_hex2rgb( $colors[$i] ) .','.$fillopacity.')",
					strokeColor : "rgba('. wp_charts_hex2rgb( $colors[$i] ) .',1)",
					pointColor 	: "rgba('. wp_charts_hex2rgb( $colors[$i] ) .',1)",
					pointStrokeColor : "'.$pointstrokecolor.'",
					data 		: ['.$datasets[$i].']
				}';

			}  // end type conditional
			wp_charts_trailing_comma($i, $total, $currentchart);
		}

		// end the js arrays correctly depending on type
		if ($type == 'Line' || $type == 'Radar' || $type == 'Bar') {
			$currentchart .=	']};';
		} else {
			$currentchart .=	'];';
		}

		$currentchart .= 'var wpChart'.$title.$type.' = new Chart(document.getElementById("'.$title.'").getContext("2d")).'.$type.'('.$title.'Data,'.$title.'Ops);
	</script>';

	// return the final result
	// - - - - - - - - - - - - - - - - - - - - - - -
	return $currentchart;
}

add_shortcode( 'wp_charts', 'wp_charts_shortcode' );


// WP Charts Widget
// - - - - - - - - - - - - - - - - - - - - - - -
class wp_charts_widget extends WP_Widget {

    /* constructor
    - - - - - - - - - */
    function wp_charts_widget() {
        parent::WP_Widget(false, $name = 'WordPress Charts');
    }

    /* Output the Widget
    - - - - - - - - - - */
    function widget($args, $instance) {
        extract( $args );
		// global $posttypes;
        $title          = isset($instance['title']) ? apply_filters('widget_title', $instance['title']) : "";
		$chartid        = $instance['chartid'];
		$pretext        = isset($instance['pretext'] ) ? apply_filters('widget_title', $instance['pretext']) : "";
		$chart_type     = $instance['chart_type'];
		$labels         = $instance['labels'];
		$data           = $instance['data'];
		$colors    		= $instance['colors'];
		$posttext        = isset($instance['posttext'] ) ? apply_filters('widget_title', $instance['posttext']) : "";

        // start widget
         echo $before_widget;

		// output the title
		if ( $title != "" ) {
			echo $before_title . $title . $after_title;
		}

        // output chart intro
        if ( !empty($pretext)) {
			echo wpautop($pretext);
		}

        // output the Chart
		echo do_shortcode(
			"[wp_charts
					title  = '$chartid'
					labels ='$labels'
					type   = '$chart_type'
					data   = '$data'
					colors = '$colors'
					width = '100%'
				]"
			);

		// output Chart Description
	    if ( !empty($posttext)) {
			echo wpautop($posttext);
		}

		// end wdget
    	echo $after_widget;

    }

    /* Update the Widget
    - - - - - - - - - - - - */
    function update($new_instance, $old_instance) {
    	$instance = $old_instance;
		$instance['title'] = ($new_instance['title']);
		$instance['chartid'] = ($new_instance['chartid']);
		$instance['pretext']        = strip_tags($new_instance['pretext']);
		$instance['chart_type'] = ($new_instance['chart_type']);
		$instance['labels'] = ($new_instance['labels']);
		$instance['data'] = ($new_instance['data']);
		$instance['colors'] = ($new_instance['colors']);
		$instance['posttext']        = strip_tags($new_instance['posttext']);
        return $instance;
    }

    /* Widget Form
    - - - - - - - - - */
    function form($instance) {
		$title          = isset( $instance['title'] ) 		? esc_attr($instance['title']) 			: "";
		$pretext        = isset( $instance['pretext'] ) 	? esc_attr($instance['pretext']) 		: "";
		$chartid        = isset( $instance['chartid'] ) 	? esc_attr($instance['chartid']) 		: "";
		$chart_type     = isset( $instance['chart_type'] ) 	? esc_attr($instance['chart_type'])		: "";
		$labels         = isset( $instance['labels'] ) 		? esc_attr($instance['labels']) 		: "";
		$data           = isset( $instance['data'] ) 		? esc_attr($instance['data']) 			: "";
		$colors         = isset( $instance['colors'] ) 		? esc_attr($instance['colors']) 		: "";
		$posttext       = isset( $instance['posttext'] ) 	? esc_attr($instance['posttext']) 		: "";

        ?>
        <!-- Widget title -->
        <p>
          <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Widget Title:', 'nona'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
        </p>

		<!-- Chart ID -->
		<p>
          <label for="<?php echo $this->get_field_id('chartid'); ?>"><?php _e('Chart Title:', 'nona'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('chartid'); ?>" name="<?php echo $this->get_field_name('chartid'); ?>" type="text" value="<?php echo $chartid; ?>" />
          <small><strong>IMPORTANT!</strong> Your Chart must have a unique title to be indentified by, this title <strong>WILL NOT</strong> be displayed.</small>
        </p>

        <!-- PreText -->
        <p>
          <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Introduction:', 'nona'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('pretext'); ?>" name="<?php echo $this->get_field_name('pretext'); ?>" type="text" value="<?php echo $pretext; ?>" />
        </p>

        <!-- type -->
        <p>
			<label for="<?php echo $this->get_field_id('chart_type'); ?>"><?php _e('Type', 'nona'); ?></label>
			<select name="<?php echo $this->get_field_name('chart_type'); ?>" id="<?php echo $this->get_field_id('chart_type'); ?>" class="widefat">
				<?php
				$options = array('Pie', 'Doughnut', 'Radar', 'line', 'Bar', 'PolarArea');
				foreach ($options as $option) {
					echo '<option value="' . $option . '" id="' . $option . '"', $chart_type == $option ? ' selected="selected"' : '', '>', $option, '</option>';
				}
			?>
		</select>
        </p>
        <!-- labels -->
		<p>
          <label for="<?php echo $this->get_field_id('labels'); ?>"><?php _e('Labels, separated by commas:', 'nona'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('labels'); ?>" name="<?php echo $this->get_field_name('labels'); ?>" type="text" value="<?php echo $labels; ?>" />
        </p>

        <!-- data & datasets -->
		<p>
          <label for="<?php echo $this->get_field_id('data'); ?>"><?php _e('Data or Datasets', 'nona'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('data'); ?>" name="<?php echo $this->get_field_name('data'); ?>" type="text" value="<?php echo $data; ?>" />
          <small>If you're using the <strong><em>bar, line</em></strong> or <strong><em>radar</em></strong> chart type, you must write your comparative datasets divided by the <strong><em>next</em></strong> keyword eg: 0,0,0 next 0,0,0 etc.</small>
        </p>

        <!-- Custom Colors -->
        <p>
          <label for="<?php echo $this->get_field_id('colors'); ?>"><?php _e('Colors, separated by commas:', 'nona'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('colors'); ?>" name="<?php echo $this->get_field_name('colors'); ?>" type="text" value="<?php echo $colors; ?>" />
        </p>
        <!-- Post Text -->
        <p>
          <label for="<?php echo $this->get_field_id('posttext'); ?>"><?php _e('Chart Description:', 'nona'); ?></label>
          <input class="widefat" id="<?php echo $this->get_field_id('posttext'); ?>" name="<?php echo $this->get_field_name('posttext'); ?>" type="text" value="<?php echo $posttext; ?>" />
        </p>

    <?php } // End Form Function

} // End Class

add_action('widgets_init', create_function('', 'return register_widget("wp_charts_widget");'));





