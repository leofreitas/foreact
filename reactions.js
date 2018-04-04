  function vote(foreact,user,post,reaction,votes,hasvote){
      cname =String(foreact)+String(post)+String(reaction);
      last_vote = String(foreact)+String(post);

      parentclass = document.getElementById(cname).parentNode.className;
      xmlhttp=new XMLHttpRequest();
      
          
      xmlhttp.onreadystatechange = function() {
          if (this.readyState==4 && this.status==200) {
  	       answer=JSON.parse(this.response);

             document.getElementById(cname).firstChild.data=' ('+answer.votes+')';
             document.getElementById('btn'+cname).className ='btn btn-primary btn-sm';
             if (answer.had_vote) {
              document.getElementById(last_vote+answer.last_vote).firstChild.data = ' ('+answer.past_vote+')';
              document.getElementById('btn'+last_vote+answer.last_vote).className ='btn btn-default btn-sm';
              document.getElementById('btn'+cname).className ='btn btn-default btn-sm';
             }else{
              document.getElementById(last_vote+answer.last_vote).firstChild.data = ' ('+answer.past_vote+')';
              document.getElementById('btn'+last_vote+answer.last_vote).className ='btn btn-default btn-sm';
             }
  	}
  }; 
          xmlhttp.open("GET","controlreactions.php?post="+post+"&reaction="+reaction+"&user="+user+"&pc="+parentclass,true);
          xmlhttp.send();
  }
