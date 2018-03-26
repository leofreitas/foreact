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
    $keys = array_keys($reactions); 
    $type = array();
    foreach ($keys as $key => $value) {
    	$record = $DB->get_records('foreact_reactions_type', array('id'=> intval($value)));
    	array_push($type, $record[$value]);
    }

    return $type;


}

function get_reaction_icon($type, $post, $idreaction){
    global $USER;
    $out = '';
    $foreact=$idreaction[0];
    $user=$USER->id;
    $idbutton =  $foreact.$post;
    $out .='<hr>';

   
    for ($i=0; $i <= sizeof($type); $i++) { 
        if ($type[$i]->type == 'fa') {
            
            $votes = $this->get_votes($post, $type[$i]->id);
            $hasvote = $this->has_vote($post,$type[$i]->id,$user);

            if($hasvote){
                $out .='<a id="btn'.$idbutton.$type[$i]->id.'"class="btn btn-primary btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i]->id.','.$votes.','.$hasvote.')">';
                $out .= '<i class="'.$type[$i]->name.'" aria-hidden="true"></i>';
                $out .= '<i><br>'.$type[$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i]->id.'"> ('.$votes.')</i>';
                $out .='</a>';

    
            }else{
                $out .='<a id="btn'.$idbutton.$type[$i]->id.'" class="btn btn-default btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i]->id.','.$votes.','.$hasvote.')">';
                $out .= '<i class="'.$type[$i]->name.'" aria-hidden="true"></i>';
                $out .= '<i ><br>'.$type[$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i]->id.'"> ('.$votes.')</i>';
                $out .='</a>';
                
            }
            
        }elseif ($type[$i]->type == 'fa-stack') {

            $name =explode("|", $type[$i]->name);
            $votes = $this->get_votes($post, $type[$i]->id);
            $hasvote = $this->has_vote($post,$type[$i]->id,$user);
            if($hasvote){
                $out .='<a id="btn'.$idbutton.$type[$i]->id.'"class="btn btn-primary btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i]->id.','.$votes.','.$hasvote.')">';
            
                $out .='<span class="fa-stack">';
                $out .='<i class="'.$name[0].'"></i>';
                $out .='<i class="'.$name[1].'"></i>';
                $out .='</span>';
                $out .= '<i ><br>'.$type[$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i]->id.'"> ('.$votes.')</i>';
                $out .='</a>';
            }else{
                $out .='<a id="btn'.$idbutton.$type[$i]->id.'"class="btn btn-default btn-sm" onclick="vote('.$foreact.','.$user.','.$post.','.$type[$i]->id.','.$votes.','.$hasvote.')">';
            
                $out .='<span class="fa-stack">';
                $out .='<i class="'.$name[0].'"></i>';
                $out .='<i class="'.$name[1].'"></i>';
                $out .='</span>';
                $out .= '<i ><br>'.$type[$i]->description.'</i>';
                $out .= '<i id="'.$idbutton.$type[$i]->id.'"> ('.$votes.')</i>';
                $out .='</a>';
            } 
            

        }
        
    }
    return $out;

}
function has_any_vote($post,$user){
    global $DB;
    $boo=0;
    $table='foreact_reactions_votes';
    $conditions = array('post' => $post,'user'=>$user );
    if($DB->record_exists($table, $conditions)){
        $boo=1;
    };
    return $boo;
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

        if(!$DB->record_exists('foreact_reactions_type', array('type'=>$value['type'], 'name' =>$value['name'], 'description' =>$value['description'] ))){
           $record->type=$value['type'];
           $record->name=$value['name'];
           $record->description=$value['description'];
           $DB->insert_record('foreact_reactions_type', $record);
        }
        }   
    }

}

public function remove_old_icons($idforum){
    global $DB;
    $record = array('foreact' => $idforum);
    $DB->delete_records('foreact_reactions',$record);

}

public function add_new_stack($id,$reactions){
    global $DB;
    $stack = $this->read_json_stack();
    $keys = $this->stack_names();
    $name = $keys[$reactions];
    $this->set_default_stack($id,$name);
    $insert = new stdClass();
    $insert->foreact = $id;
    for ($i=0; $i <sizeof($stack[$name]) ; $i++) { 
      foreach ($stack[$name][$i] as $key => $value) {
    		$record[$key]=$value;
    	}
    	$idicon = $DB->get_record('foreact_reactions_type', $record,$fields='id',$strictness=IGNORE_MULTIPLE);
    	$insert->reaction = $idicon->id;
    	$DB->insert_record('foreact_reactions', $insert);
    }
  
}

private function set_default_stack($idforum,$name){
    global $DB;
    $table = 'foreact_stack';
    $insert = new stdClass();
    $insert->foreact = $idforum;
    $insert->stack = $name;
    if ($this->check_default_stack($table,$idforum)) {
        $id = $DB->get_record($table, array('foreact' => $idforum) ,$fields='id',$strictness=IGNORE_MULTIPLE);
        $insert->id = $id->id;
        $this->update_default_stack($table, $insert);
    }else{
        $DB->insert_record($table, $insert);
    }
}


private function update_default_stack($table, $record){
    global $DB;
    $DB->update_record($table, $record, $bulk=false);
}

private function check_default_stack($table, $idforum){
    global $DB;
    $boo=FALSE;
    if ($DB->record_exists($table, array('foreact'=> intval($idforum)))) {
        $boo=TRUE;
    }

    return $boo;
}

public function get_default_stack($id,array $iconoptions){
    global $DB;
    $stackname = $DB->get_record('foreact_stack', array('foreact'=>$id),$fields='stack',$strictness=IGNORE_MULTIPLE);
    return array_search($stackname->stack, $iconoptions);
}   

public function get_last_vote($post, $user){
    global $DB;
    $idreaction = $DB->get_record('foreact_reactions_votes', array('post'=>$post,'user'=>$user),$fields='reaction',$strictness=IGNORE_MULTIPLE);
    return $idreaction->reaction;   

}
}
?>