<?php
require_once('../../config.php');
require_once('libreactions.php');

$table = 'foreact_reactions_votes';
$post =intval($_REQUEST['post']);
$reaction =intval($_REQUEST['reaction']);
$user = intval($_REQUEST['user']);

$ireactions = new Reactions();

if ($ireactions->has_vote($post,$reaction,$user)) {
        $where = array('post' => $post,'reaction'=> $reaction,'user'=>$user );
        $DB->delete_records($table, $where);
}else{
        $record = new stdClass();
        $record->post = $post;
        $record->reaction = $reaction;
        $record->user = $user;
        $DB->insert_record($table, $record, false, false);
}

$response = array('votes' => $ireactions->get_votes($post,$reaction),'has_vote'=>$ireactions->has_vote($post,$reaction,$user) );
echo json_encode($response);

?>
