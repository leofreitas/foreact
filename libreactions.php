<?php


 class Reactions{
 	function get_votes($post, $reaction){
    global $DB;

    $votes = $DB->count_records('foreact_reactions_votes', array('post' => $post, 'reaction' => $reaction ));


    return $votes;
}

function get_reaction_type($foreact){
    global $DB;
    print_r($foreact);
    $reactions = $DB->get_records('foreact_reactions', array('foreact'=> $foreact),null, 'reaction');
    //$reactions= $DB->get_records('foreact_reactions', array('foreact'=> $foreact), $sort='', $fields='reaction', $limitfrom=0, $limitnum=0) 
    $type = array();
    for ($i=1; $i <= sizeof($reactions); $i++) {
        $type[$i] = $DB->get_records('foreact_reactions_type', array('id'=> intval($reactions['60']->reaction)));
    }

    print_r($reactions['60']->reaction);
    //return $type;


}

function get_reaction_icon($type, $post, $idreaction){
    global $USER;
    $out = '';
    $foreact=$idreaction[0];
    $user=$USER->id;
    $idbutton =  $foreact.$post.$user;
    $out .='<hr>';
    print_r($type);
    for ($i=1; $i <= sizeof($type); $i++) { 
        if ($type[$i][$i]->type == 'fa') {
            
            $votes = $this->get_votes($post, $type[$i][$i]->id);
            $hasvote = $this->has_vote($post,$type[$i][$i]->id,$user);

            if($hasvote){
                $out .='<a id="btn'.$idbutton.$type[$i][$i]->id.'"class="btn btn-primary btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i][$i]->id.','.$votes.','.$hasvote.')">';
                $out .= '<i class="'.$type[$i][$i]->name.'" aria-hidden="true"></i>';
                $out .= '<i><br>'.$type[$i][$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i][$i]->id.'"> ('.$votes.')</i>';
                $out .='</a>';

    
            }else{
                $out .='<a id="btn'.$idbutton.$type[$i][$i]->id.'" class="btn btn-default btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i][$i]->id.','.$votes.','.$hasvote.')">';
                $out .= '<i class="'.$type[$i][$i]->name.'" aria-hidden="true"></i>';
                $out .= '<i ><br>'.$type[$i][$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i][$i]->id.'"> ('.$votes.')</i>';
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
                $out .= '<i id="'.$idbutton.$type[$i][$i]->id.'"> ('.$votes.')</i>';
                $out .='</a>';
            }else{
                $out .='<a id="btn'.$idbutton.$type[$i][$i]->id.'"class="btn btn-default btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i][$i]->id.','.$votes.','.$hasvote.')">';
            
                $out .='<span class="fa-stack">';
                $out .='<i class="'.$name[0].'"></i>';
                $out .='<i class="'.$name[1].'"></i>';
                $out .='</span>';
                $out .= '<i ><br>'.$type[$i][$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i][$i]->id.'"> ('.$votes.')</i>';
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
    };
    return $boo;
}

public function read_json_stack(){
    global $CFG;
    $stack = json_decode(file_get_contents($CFG->dirroot.'/mod/foreact/iconstack.json'), true);
    return $stack['Stack'];
}
public function stack_names(){
    global $CFG;
    $keys = array_keys($this->read_json_stack());
    return $keys;
}

public function add_new_icon(){
    global $DB;
    $stack = $this->read_json_stack();
    $keys = $this->stack_names();
    $record = new stdClass();
    for ($i=0; $i <=sizeof($keys) ; $i++) { 
    foreach ($stack[$keys[$i]] as $key => $value) {
        if(!$DB->record_exists('foreact_reactions_type', array('type'=>$value['type'], 'name' =>$value['name'] ))){
           $record->type=$value['type'];
           $record->name=$value['name'];
           $record->description=$value['description'];
           $DB->insert_record('foreact_reactions_type', $record);
        }
        }   
    }

}

public function add_new_stack($id,$reactions){
    global $DB;
    $stack = $this->read_json_stack();
    $keys = $this->stack_names();
    $record = new stdClass();
    $record->foreact = $id;
    $record->reaction= 3;
    $DB->insert_record('foreact_reactions',$record);
    $record2 = new stdClass();
    $record2->foreact = $id;
    $record2->reaction= 56;
    $DB->insert_record('foreact_reactions',$record2);
    foreach ($stack['Youtube'] as $key => $value) {
           
           
        }   

    
}

}
?>