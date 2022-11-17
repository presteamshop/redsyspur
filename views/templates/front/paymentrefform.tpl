<link rel="stylesheet" type="text/css"
	href="{$this_path|escape:'htmlall'}/views/templates/front/css/estilos.css" />
<script src="{$redsys_domain}"></script>
<script>
    window.addEventListener('load', function() {
        {if $smarty.const._PS_VERSION_ >= 1.7}
            if($("#conditions-to-approve input[type=checkbox]").size()>0 && !$("#conditions-to-approve input[type=checkbox]")[0].checked){
                $("#boton-ref").hide();
                
                const conditions_to_approve = $("#conditions-to-approve input[type=checkbox]")[0];
                conditions_to_approve.addEventListener('click', function(){
                    if (conditions_to_approve.checked) {
                        $("#boton-ref").show();
                    }
                    else {
                        $("#boton-ref").hide();
                    }
                });

            }
        {/if}
	});

	function redsysPayRef(){
		document.getElementById("redsys-ref-err-term").style["display"]="none";
		
		{if $smarty.const._PS_VERSION_ >= 1.7}
			if($("#conditions-to-approve input[type=checkbox]").size()>0 && !$("#conditions-to-approve input[type=checkbox]")[0].checked){
				document.getElementById("redsys-ref-err-term").style["display"]="block";
				return;
			}
		{/if}
		
		document.getElementById("procesando-ref").style["display"]="block";
		document.getElementById("boton-ref").style["display"]="none";
		
	    $.ajax({
	        url: "{$ref_url}",
	        type: "POST",
	        data: {
		    	"idCart":"{$idCart}",
				"merchant_order":"{$merchant_order}"
	    	},
	        dataType: 'json',
	        success: function (data) {
	            if(data.redir==true)
	            	window.location.href=data.url;
	            else{
	            	$('<iframe id="redsysModalDialog" src="'+data.url+'" frameborder="no" />').dialog({
            		   title: "{l s='Autenticación de titular' mod='redsys'}",
            		   autoOpen: true,
            		   minWidth: 900,
            		   minHeight: 600,
            		   modal: true,
            		   draggable: false,
            		   resizable: false,
            		   close: function(event, ui) { $(this).remove();},
            		   overlay: {
            		       opacity: 0.6,
            		       background: "black"}
            		}).width(860).height(580);

		            $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("text-align","center");
		            $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("background-image","none");
		            $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("background","none");
		            $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("border","none");
		            $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').children("button").remove();
	            }
	        },
	        error: function (request, status, error){
	        	window.location.href="{$url_ko}"
	        }
	    });
    }
</script>

<div id="redsysRefForm" {if $smarty.const._PS_VERSION_
	< 1.7}style="display: none;"{/if}>
	
	<div id="procesando-ref" class="cardinfo-merchant-data" style="display: none;"><button disabled id="botn" value="" style="width: 225px; height:40px; background-color: rgb(148, 182, 189); font-weight: 900;; color: white; padding: 10px; border: 0px; border-radius: 5px;margin-bottom:25px;position:relative;top:8px;">{l s='PROCESANDO' mod='redsys'}...</button></div>
	<button id="boton-ref" onclick="redsysPayRef()" style='{$btn_style}'>{l s=$btn_text mod='redsys'}</button>
	<span id="redsys-ref-err-term" style="color: red; font-size: small; display: none;">{l s='Ha de aceptar los términos y condiciones para continuar' mod='redsys'}</span>
</div>