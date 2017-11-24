function vote(foreact,user,post,reaction,votes,hasvote){
    cname =String(foreact)+String(post)+String(user)+String(reaction);

    parentclass = document.getElementById(cname).parentNode.className;
    xmlhttp=new XMLHttpRequest();
        
    xmlhttp.onreadystatechange = function() {
        if (this.readyState==4 && this.status==200) {
	       answer=JSON.parse(this.response);
           document.getElementById(cname).firstChild.data=' '+answer.votes;
           if (answer.has_vote) {
            document.getElementById('btn'+cname).className ='btn btn-primary btn-sm';
           }else{
            document.getElementById('btn'+cname).className ='btn btn-default btn-sm';
           }
	}
}; 
        xmlhttp.open("GET","controlreactions.php?post="+post+"&reaction="+reaction+"&user="+user+"&pc="+parentclass,true);
        xmlhttp.send();
}
