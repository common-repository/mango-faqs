jQuery(document).ready(function() {
	jQuery('.xbutt').click(function(){
		jango = jQuery(this).parent().next().next().children(".quid").html();
		jQuery("#deleteinput").val(jango);
		jQuery("#deleteform").submit();
	});
});