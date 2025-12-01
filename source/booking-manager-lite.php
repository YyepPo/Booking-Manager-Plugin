<?php
/**
 * Plugin Name: Booking Manager
 * Description: A booking manager plugin with a frontend booking form, admin booking list, and email notifications.
 * Version: 0.0.1
 * Author: Enes Pacarizi
 * Text Domain: booking-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Booking_Manager {

    const CPT = 'bm_booking';
    const NONCE_ACTION = 'bm_booking_nonce_action';

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_booking_meta' ], 10, 2 );

        add_shortcode( 'bm_booking_form', [ $this, 'booking_form_shortcode' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        add_action( 'wp_ajax_bm_submit_booking', [ $this, 'handle_ajax_booking' ] );
        add_action( 'wp_ajax_nopriv_bm_submit_booking', [ $this, 'handle_ajax_booking' ] );

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_post_bm_delete_booking', [ $this, 'admin_delete_booking' ] );

        register_activation_hook( __FILE__, [ __CLASS__, 'on_activation' ] );
        register_uninstall_hook( __FILE__, [ __CLASS__, 'on_uninstall' ] );
    }

    public static function on_activation() {
        $self = new self();
        $self->register_post_type();
        flush_rewrite_rules();
    }

    public static function on_uninstall() {
        $bookings = get_posts( [
            'post_type' => self::CPT,
            'numberposts' => -1,
            'post_status' => 'any',
        ] );
        foreach ( $bookings as $b ) {
            wp_delete_post( $b->ID, true );
        }
    }

    public function register_post_type() {
        $labels = [
            'name'               => 'Bookings',
            'singular_name'      => 'Booking',
            'menu_name'          => 'Bookings',
            'name_admin_bar'     => 'Booking',
            'add_new'            => 'Add Booking',
            'add_new_item'       => 'Add New Booking',
            'new_item'           => 'New Booking',
            'edit_item'          => 'Edit Booking',
            'view_item'          => 'View Booking',
            'all_items'          => 'All Bookings',
            'search_items'       => 'Search Bookings',
            'not_found'          => 'No bookings found',
            'not_found_in_trash' => 'No bookings found in Trash',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'has_archive'        => false,
            'show_in_menu'       => false, // we'll add our own menu
            'supports'           => [ 'title' ],
            'capability_type'    => 'post',
        ];

        register_post_type( self::CPT, $args );
    }

    public function register_meta_boxes() {
        add_meta_box( 'bm_booking_details', 'Booking Details', [ $this, 'render_meta_box' ], self::CPT, 'normal', 'high' );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'bm_save_meta', 'bm_meta_nonce' );

        $name    = get_post_meta( $post->ID, '_bm_name', true );
        $email   = get_post_meta( $post->ID, '_bm_email', true );
        $date    = get_post_meta( $post->ID, '_bm_date', true );
        $time    = get_post_meta( $post->ID, '_bm_time', true );
        $service = get_post_meta( $post->ID, '_bm_service', true );

        echo '<table class="form-table"><tbody>';
        echo $this->meta_row( 'Name', 'bm_name', $name );
        echo $this->meta_row( 'Email', 'bm_email', $email );
        echo $this->meta_row( 'Date (YYYY-MM-DD)', 'bm_date', $date );
        echo $this->meta_row( 'Time (HH:MM)', 'bm_time', $time );
        echo $this->meta_row( 'Service', 'bm_service', $service );
        echo '</tbody></table>';
    }

    private function meta_row( $label, $field, $value ) {
        $html  = '<tr><th><label for="' . esc_attr( $field ) . '">' . esc_html( $label ) . '</label></th><td>';
        $html .= '<input type="text" id="' . esc_attr( $field ) . '" name="' . esc_attr( $field ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
        $html .= '</td></tr>';
        return $html;
    }

    public function save_booking_meta( $post_id, $post ) {
        if ( $post->post_type !== self::CPT ) {
            return;
        }

        if ( ! isset( $_POST['bm_meta_nonce'] ) || ! wp_verify_nonce( $_POST['bm_meta_nonce'], 'bm_save_meta' ) ) {
            return;
        }

        $fields = [
            'bm_name'    => '_bm_name',
            'bm_email'   => '_bm_email',
            'bm_date'    => '_bm_date',
            'bm_time'    => '_bm_time',
            'bm_service' => '_bm_service',
        ];

        foreach ( $fields as $input => $meta_key ) {
            if ( isset( $_POST[ $input ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $input ] ) ) );
            }
        }
    }

    public function booking_form_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'title' => 'Book an appointment' ], $atts );

        ob_start();
        ?>
        <div class="bm-booking-wrap">
            <h3><?php echo esc_html( $atts['title'] ); ?></h3>
            <form id="bm-booking-form" method="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
                <input type="hidden" name="action" value="bm_submit_booking">
                <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>">

                <p><label>Name<br><input type="text" name="bm_name" required></label></p>
                <p><label>Email<br><input type="email" name="bm_email" required></label></p>
                <p><label>Date<br><input type="date" name="bm_date" required></label></p>
                <p><label>Time<br><input type="time" name="bm_time" required></label></p>
                <p><label>Service<br><input type="text" name="bm_service"></label></p>

                <p><button type="submit">Send booking</button></p>
                <div id="bm-message" role="status" aria-live="polite"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function enqueue_assets() {
        wp_enqueue_script( 'bm-frontend', plugin_dir_url( __FILE__ ) . 'bm-frontend.js', [ 'jquery' ], false, true );
        wp_localize_script( 'bm-frontend', 'bm_ajax', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
        ] );

        wp_enqueue_style( 'bm-frontend-style', plugin_dir_url( __FILE__ ) . 'bm-frontend.css' );
    }

    public function handle_ajax_booking() {
        if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), self::NONCE_ACTION ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ], 400 );
        }

        $name    = isset( $_REQUEST['bm_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bm_name'] ) ) : '';
        $email   = isset( $_REQUEST['bm_email'] ) ? sanitize_email( wp_unslash( $_REQUEST['bm_email'] ) ) : '';
        $date    = isset( $_REQUEST['bm_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bm_date'] ) ) : '';
        $time    = isset( $_REQUEST['bm_time'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bm_time'] ) ) : '';
        $service = isset( $_REQUEST['bm_service'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['bm_service'] ) ) : '';

        if ( empty( $name ) || empty( $email ) || empty( $date ) || empty( $time ) ) {
            wp_send_json_error( [ 'message' => 'Please fill required fields' ], 422 );
        }

        $post_id = wp_insert_post( [
            'post_title'  => sprintf( '%s — %s %s', $name, $date, $time ),
            'post_type'   => self::CPT,
            'post_status' => 'publish',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Unable to create booking' ], 500 );
        }

        update_post_meta( $post_id, '_bm_name', $name );
        update_post_meta( $post_id, '_bm_email', $email );
        update_post_meta( $post_id, '_bm_date', $date );
        update_post_meta( $post_id, '_bm_time', $time );
        update_post_meta( $post_id, '_bm_service', $service );

        $admin_email = get_option( 'admin_email' );
        $subject = "New booking from {$name} on {$date} at {$time}";
        $message = "Name: {$name}\nEmail: {$email}\nDate: {$date}\nTime: {$time}\nService: {$service}\n\nManage it here: " . admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        wp_mail( $admin_email, $subject, $message );

        wp_send_json_success( [ 'message' => 'Booking created. We will contact you soon.' ] );
    }

    public function add_admin_menu() {
        add_menu_page( 'Bookings', 'Bookings', 'manage_options', 'bm_bookings', [ $this, 'admin_bookings_page' ], 'dashicons-calendar-alt', 6 );
    }

    public function admin_bookings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['bm_bulk'] ) && ! empty( $_POST['bm_bulk_ids'] ) && check_admin_referer( 'bm_bulk_action', 'bm_bulk_nonce' ) ) {
            $ids = array_map( 'intval', (array) $_POST['bm_bulk_ids'] );
            foreach ( $ids as $id ) {
                wp_delete_post( $id, true );
            }
            echo '<div class="updated notice"><p>Deleted selected bookings.</p></div>';
        }

        $bookings = get_posts( [
            'post_type' => self::CPT,
            'numberposts' => -1,
            'post_status' => 'publish',
        ] );

        ?>
        <div class="wrap">
            <h1>Bookings</h1>

            <form method="post">
                <?php wp_nonce_field( 'bm_bulk_action', 'bm_bulk_nonce' ); ?>
                <p>
                    <button class="button button-primary" type="submit" name="bm_bulk" value="delete">Delete selected</button>
                </p>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:1em;"><input type="checkbox" id="bm-select-all"></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Service</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( ! empty( $bookings ) ) : foreach ( $bookings as $b ) :
                        $id = $b->ID;
                        $name = get_post_meta( $id, '_bm_name', true );
                        $email = get_post_meta( $id, '_bm_email', true );
                        $date = get_post_meta( $id, '_bm_date', true );
                        $time = get_post_meta( $id, '_bm_time', true );
                        $service = get_post_meta( $id, '_bm_service', true );
                        ?>
                        <tr>
                            <td><input type="checkbox" name="bm_bulk_ids[]" value="<?php echo esc_attr( $id ); ?>"></td>
                            <td><?php echo esc_html( $name ); ?></td>
                            <td><?php echo esc_html( $email ); ?></td>
                            <td><?php echo esc_html( $date ); ?></td>
                            <td><?php echo esc_html( $time ); ?></td>
                            <td><?php echo esc_html( $service ); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>">View</a>
                                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bm_delete_booking&booking_id=' . $id ), 'bm_delete_booking_' . $id ) ); ?>">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7">No bookings found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <script>
        (function(){
            var selectAll = document.getElementById('bm-select-all');
            if(selectAll){
                selectAll.addEventListener('change', function(e){
                    var checked = e.target.checked;
                    document.querySelectorAll('input[name="bm_bulk_ids[]"]').forEach(function(cb){ cb.checked = checked; });
                });
            }
        })();
        </script>
        <?php
    }

    public function admin_delete_booking() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized', '', 403 );
        }

        $id = isset( $_GET['booking_id'] ) ? intval( $_GET['booking_id'] ) : 0;
        if ( $id && wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'bm_delete_booking_' . $id ) ) {
            wp_delete_post( $id, true );
            wp_safe_redirect( admin_url( 'admin.php?page=bm_bookings' ) );
            exit;
        }

        wp_die( 'Invalid request', '', 400 );
    }
}

new Booking_Manager();
// For some reasons, css class is not loaded properly
function bm_enqueue_scripts() {
    wp_enqueue_style(
        'bm-frontend-css',
        plugin_dir_url(__FILE__) . 'bm-frontEnd.css',
        array(),
        time()
    );

    wp_enqueue_script(
        'bm-frontend-js',
        plugin_dir_url(__FILE__) . 'bm-frontend.js',
        array('jquery'),
        time(),
        true
    );

    wp_localize_script('bm-frontend-js', 'bm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'bm_enqueue_scripts');
