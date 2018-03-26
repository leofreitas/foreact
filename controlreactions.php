<?php
require_once('../../config.php');
require_once('libreactions.php');

$table = 'foreact_reactions_votes';
$post =intval($_REQUEST['post']);
$reaction =intval($_REQUEST['reaction']);
$user = intval($_REQUEST['user']);

$ireactions = new Reactions();
$record = new stdClass();
$record->post = $post;
$record->reaction = $reaction;
$record->user = $user;

$vote = $ireactions->has_vote($post,$reaction,$user);
$reactionid = 0;
if($ireactions->has_any_vote($post,$user)){
	$reactionid = $ireactions->get_last_vote($post, $user);
}

if ($ireactions->has_any_vote($post,$user)) {
		$reaction_sql = $DB->get_record($table, array('post'=>$post,'user'=>$user),$fields='reaction',$strictness=IGNORE_MULTIPLE);

		$where = array('post' => $post,'reaction'=> $reaction_sql->reaction,'user'=>$user );

        $DB->delete_records($table, $where);

        $DB->insert_record($table, $record, false, false);

}else{
        $record = new stdClass();
        $record->post = $post;
        $record->reaction = $reaction;
        $record->user = $user;
        $DB->insert_record($table, $record, false, false);
}

$response = array('votes' => $ireactions->get_votes($post,$reaction),'had_vote'=>$vote,'last_vote'=>$reactionid);
echo json_encode($response);


?>
