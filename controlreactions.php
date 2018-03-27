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
$past_vote = null;
if($ireactions->has_any_vote($post,$user)){
	$reactionid = $ireactions->get_last_vote($post, $user);
        
}

if ($ireactions->has_any_vote($post,$user)) {
	$reaction_sql = $DB->get_record($table, array('post'=>$post,'user'=>$user),$fields='reaction',$strictness=IGNORE_MULTIPLE);


        $ireactions->delete_vote($post,$reaction_sql->reaction,$user);
        if ($reaction_sql->reaction!=$reaction) {
                $ireactions->add_vote($record);
        }
        

}else{
        $ireactions->add_vote($record);
}
$past_vote = $ireactions->get_votes($post, $reactionid);
$response = array('votes' => $ireactions->get_votes($post,$reaction),'had_vote'=>$vote,'last_vote'=>$reactionid,'past_vote'=>$past_vote);
echo json_encode($response);


?>
