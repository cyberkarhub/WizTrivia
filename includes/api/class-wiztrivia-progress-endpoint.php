<?php
// File: includes/api/class-wiztrivia-progress-endpoint.php

if ( ! defined( 'ABSPATH' ) ) exit;

class WizTrivia_Progress_Endpoint {

    public static function register() {
        register_rest_route( 'wiztrivia/v1', '/progress', array(
            'methods'  => 'GET',
            'callback' => array( __CLASS__, 'get_progress' ),
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            }
        ));
    }

    public static function get_progress( $request ) {
        // You should store progress in the database or transient during generation.
        $progress = get_transient( 'wiztrivia_question_gen_progress' );
        if ( ! $progress ) {
            $progress = array(
                'in_progress' => false,
                'complete'    => false,
                'generated'   => 0,
                'total'       => 0,
                'message'     => 'No job running.',
            );
        }
        return rest_ensure_response( $progress );
    }
}

add_action( 'rest_api_init', array( 'WizTrivia_Progress_Endpoint', 'register' ) );