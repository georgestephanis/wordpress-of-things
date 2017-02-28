<?php

/*
 * Plugin Name: Weather Report Widget
 * Plugin URI: http://github.com/georgestephanis/wordpress-of-things/
 * Description: A widget and rest api endpoint to let Internet of Things devices report in what the weather is looking like!
 * Author: George Stephanis
 * Version: 0.1-dev
 * Author URI: https://stephanis.info
 */

add_action( 'rest_api_init', function () {
	register_rest_route( 'wordpress-of-things/v1', '/weather-station/(?P<id>[a-z\d\-]+)', array(
		'methods' => WP_REST_Server::READABLE,
		'callback' => function( $data ) {
			$all = get_option( 'Weather_Report_Widget_Data', array() );
			if ( empty( $all[ $data['id'] ] ) ) {
				return new WP_Error( 'missing', __( 'No weather station found with that ID.', 'weather-report-widget' ), array( 'status' => 404 ) );
			}
			return $all[ $data['id'] ];
		},
	) );

	register_rest_route( 'wordpress-of-things/v1', '/weather-station/(?P<id>[a-z\d\-]+)', array(
		'methods' => WP_REST_Server::EDITABLE,
		'args' => array(
			'temperature' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return is_numeric( $param );
				}
			),
			'humidity' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return is_numeric( $param );
				}
			)
		),
		'callback' => function( $data ) {
			return Weather_Report_Widget::save_data( $data['id'], array(
				'temperature' => $data['temperature'],
				'humidity'    => $data['humidity'],
			) );
		},
		'permission_callback' => function () {
			return current_user_can( 'edit_theme_options' );
		},
	) );
} );

add_action( 'widgets_init', function(){
	register_widget( 'Weather_Report_Widget' );
} );

class Weather_Report_Widget extends WP_Widget {

	/**
	 * Sets up the widgets name etc
	 */
	public function __construct() {
		$widget_ops = array(
			'class_name'  => 'weather_report_widget',
			'description' => __( 'Display current weather conditions at a given Internet of Things sensor.', 'weather-report-widget' ),
		);
		parent::__construct( 'weather_report_widget', __( 'Weather Report', 'weather-report-widget' ), $widget_ops );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( $instance, array(
			'title'      => '',
			'secret_key' => null,
			'updated'    => null,
		) );

		$data = get_option( 'Weather_Report_Widget_Data' );
		if ( empty( $data[ $instance['secret_key'] ] ) ) {
			return;
		}
		$report = $data[ $instance['secret_key'] ];

		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		?>

		<figure class="weather-report">
			<p class="temp"><?php     echo esc_html( sprintf( __( '%s°',            'weather-report-widget' ), number_format(   $report['temperature'], 1 ) ) ); ?></p>
			<p class="humidity"><?php echo esc_html( sprintf( __( 'Humidity: %s%%',  'weather-report-widget' ),  number_format(   $report['humidity'], 0 ) ) ); ?></p>
			<p class="updated"><?php  echo esc_html( sprintf( __( 'Updated %s ago', 'weather-report-widget' ), human_time_diff( $report['updated'] ) ) ); ?></p>
		</figure>
		<style>
			.weather-report {
				background-color: rgba( 255, 255, 255, 0.7 );
				padding: 3em;
			}
			.weather-report p {
				margin: 0.5em 0 0;
				padding: 0;
				line-height: 1;
				color: rgba( 0, 0, 0, 0.55 );
			}
			.weather-report .temp {
				margin-top: 0;
				font-size: 5em;
				font-weight: 900;
				color: #111;
			}
			.weather-report .humidity {
				font-size: 1.2em;
				font-weight: 600;
			}
			.weather-report .updated {
				font-size: 0.9em;
			}
		</style>

		<?php
		echo $args['after_widget'];
	}

	/**
	 * Back-end widget form.
	 *
	 * @param array $instance Previously saved values from database.
	 * @return string|void
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( $instance, array(
			'title'      => '',
			'secret_key' => self::generate_uuid_v4(),
		) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'weather-report-widget' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" placeholder="<?php esc_attr_e( 'Current Weather Conditions...', 'weather-report-widget' ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'secret_key' ) ); ?>"><?php esc_attr_e( 'Secret Key:', 'weather-report-widget' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'secret_key' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'secret_key' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['secret_key'] ); ?>" readonly>
			<em><?php esc_html_e( 'This is the key that you will paste into your IoT code' ); ?></em>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title']      = ( ! empty( $new_instance['title'] ) )      ? strip_tags( $new_instance['title'] )              : '';
		$instance['secret_key'] = ( ! empty( $new_instance['secret_key'] ) ) ? self::sanitize_key( $new_instance['secret_key'] ) : null;

		return $instance;
	}

	public static function sanitize_key( $id ) {
		return preg_replace('/[^a-z\d\-]/i', '', $id );
	}

	/**
	 * Generate a UUID using the v4 algorithm.
	 *
	 * From http://php.net/manual/en/function.uniqid.php#94959
	 *
	 * @return string Generated UUID.
	 */
	public static function generate_uuid_v4() {
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			// 16 bits for "time_mid"
			mt_rand( 0, 0xffff ),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand( 0, 0x0fff ) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand( 0, 0x3fff ) | 0x8000,
			// 48 bits for "node"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

	// Save the data from a weather station.
	public static function save_data( $id, $report = array() ) {
		$id   = self::sanitize_key( $id );
		$report = wp_parse_args( $report, array(
			'temperature' => null,
			'humidity'    => null,
			'updated'     => time(),
		) );

		$data = get_option( 'Weather_Report_Widget_Data', array() );

		$data[ $id ] = $report;

		return update_option( 'Weather_Report_Widget_Data', $data );
	}
}