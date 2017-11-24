<?php


 class Reactions{
 	function get_votes($post, $reaction){
    global $DB;

    $votes = $DB->count_records('foreact_reactions_votes', array('post' => $post, 'reaction' => $reaction ));


    return $votes;
}

function get_reaction_type($foreact){
    global $DB;

    $reactions = $DB->get_records('foreact_reactions', array('foreact'=> $foreact),null, 'reaction');
    $type = array();
    for ($i=1; $i <= sizeof($reactions); $i++) {
        $type[$i] = $DB->get_records('foreact_reactions_type', array('id'=> $reactions[$i]->reaction));
    }
    
    return $type;


}

function get_reaction_icon($type, $post, $idreaction){
    $out = '';
    $foreact=$idreaction[0];
    $user=$idreaction[1];
    $idbutton =  $foreact.$post.$user;
    $out .='<hr>';
    for ($i=1; $i <= sizeof($type); $i++) { 
        if ($type[$i][$i]->type == 'fa') {
            
            $votes = $this->get_votes($post, $type[$i][$i]->id);
            $hasvote = $this->has_vote($post,$type[$i][$i]->id,$user);

            if($hasvote){
                $out .='<a id="btn'.$idbutton.$type[$i][$i]->id.'"class="btn btn-primary btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i][$i]->id.','.$votes.','.$hasvote.')">';
                $out .= '<i class="'.$type[$i][$i]->name.'" aria-hidden="true"></i>';
                $out .= '<i><br>'.$type[$i][$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i][$i]->id.'"> '.$votes.'</i>';
                $out .='</a>';

    
            }else{
                $out .='<a id="btn'.$idbutton.$type[$i][$i]->id.'" class="btn btn-default btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i][$i]->id.','.$votes.','.$hasvote.')">';
                $out .= '<i class="'.$type[$i][$i]->name.'" aria-hidden="true"></i>';
                $out .= '<i ><br>'.$type[$i][$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i][$i]->id.'"> '.$votes.'</i>';
                $out .='</a>';
                
            }
            
        }elseif ($type[$i][$i]->type == 'fa-stack') {

            $name =explode("|", $type[$i][$i]->name);
            $votes = $this->get_votes($post, $type[$i][$i]->id);
            $hasvote = $this->has_vote($post,$type[$i][$i]->id,$user);
            if($hasvote){
                $out .='<a id="btn'.$idbutton.$type[$i][$i]->id.'"class="btn btn-primary btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i][$i]->id.','.$votes.','.$hasvote.')">';
            
                $out .='<span class="fa-stack">';
                $out .='<i class="'.$name[0].'"></i>';
                $out .='<i class="'.$name[1].'"></i>';
                $out .='</span>';
                $out .= '<i ><br>'.$type[$i][$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i][$i]->id.'"> '.$votes.'</i>';
                $out .='</a>';
            }else{
                $out .='<a id="btn'.$idbutton.$type[$i][$i]->id.'"class="btn btn-default btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i][$i]->id.','.$votes.','.$hasvote.')">';
            
                $out .='<span class="fa-stack">';
                $out .='<i class="'.$name[0].'"></i>';
                $out .='<i class="'.$name[1].'"></i>';
                $out .='</span>';
                $out .= '<i ><br>'.$type[$i][$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i][$i]->id.'"> '.$votes.'</i>';
                $out .='</a>';
            } 
            

        }
        
    }
    return $out;

}
function has_vote($post,$reaction,$user){
    global $DB;
    $boo=0;
    $table='foreact_reactions_votes';
    $conditions = array('post' => $post, 'reaction'=>$reaction,'user'=>$user );
    if($DB->record_exists($table, $conditions)){
        $boo=1;
    }
    return $boo;
}


}
?>