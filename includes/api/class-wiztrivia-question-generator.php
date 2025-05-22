// Add this function inside your question generation class

public function update_progress_transient( $generated, $total, $in_progress = true, $complete = false ) {
    set_transient( 'wiztrivia_question_gen_progress', array(
        'in_progress' => $in_progress,
        'complete'    => $complete,
        'generated'   => $generated,
        'total'       => $total,
        'message'     => $complete ? 'Generation complete!' : 'Generation in progress...',
    ), 60 * 10 ); // 10 minutes
}