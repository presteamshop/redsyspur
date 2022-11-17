<link rel="stylesheet" type="text/css"
	href="{$this_path|escape:'htmlall'}/views/templates/front/css/estilos.css" />
<script src="{$redsys_domain}"></script>
<script>
	function toggleRedsysForm(){
		$("#redsysForm").slideToggle();
	}
	function gotoRedsysReference(){
		$("#aRedsysReference").click(function( event ) {
			  event.preventDefault();
		});

		$("#redsysRef_title").hide();
		$("#redsysRef_proc").show();

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
	            }

	            $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("text-align","center");
	            $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("background-image","none");
	            $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("background","none");
	            $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("border","none");
	            $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').children("button").remove();
	        },
	        error: function (request, status, error){
	        	window.location.href="{$url_ko}"
	        }
	    });
	}
</script>

<div id="redsysForm" {if $smarty.const._PS_VERSION_
	< 1.7}style="display: none;"{/if}>
	<div class="col-md-12">
		<div id="insite-form-container" class="form-container">
		</div>

		{if $smarty.const._PS_VERSION >= 1.7}
			<span id="redsys-error-tarjeta-nueva" style="color: red; font-size: small;">{l s='Ha de aceptar los términos y condiciones para continuar' mod='redsys'}</span>
		{/if}

		<table style="margin: 0 auto;" id="checkboxGuardar">
			<tr>
			</tr>
			<tr>
				<td>
					<input type="checkbox" id="check-guardar" name="check-guardar"/>
				</td>
				<td id="guardar-tarj">
					<label for="check-guardar">{l s='Guardar tarjeta para futuras compras en esta tienda' mod='redsys'}</label>
				</td>
			</tr>
		</table>
	</div>
	<input type="hidden" id="token"></input>
	<input type="hidden" id="errorCode"></input>
	<script>
		<!-- Petición de carga de iframes con estilos para el input-->
		var timeOutRedsyspur = setInterval(function () {
			if (typeof getInSiteForm !== typeof undefined) {
				getInSiteForm('insite-form-container','{$btn_style}','{$body_style}','{$form_style}','{$form_text_style}','{l s=$btn_text mod='redsys'}','{$merchant_fuc}','{$merchant_term}','{$merchant_order}');
				initRedsyspur();

				clearTimeout(timeOutRedsyspur);
			}
		}, 500);

		function cargaValoresBrowser3DS() {

			var valores3DS = new Object();

			//browserJavaEnabled
			valores3DS.browserJavaEnabled = navigator.javaEnabled();

			//browserJavascriptEnabled
			valores3DS.browserJavascriptEnabled = true;

			//browserLanguage
			var userLang = navigator.language || navigator.userLanguage;
			valores3DS.browserLanguage = userLang;

			//browserColorDepth
			valores3DS.browserColorDepth = screen.colorDepth;

			//browserScreenHeight
			//browserScreenWidth
			var myWidth = 0,
				myHeight = 0;
			if (typeof window.innerWidth == "number") {
				//Non-IE
				myWidth = window.innerWidth;
				myHeight = window.innerHeight;
			} else if (
				document.documentElement &&
				(document.documentElement.clientWidth ||
				document.documentElement.clientHeight)
			) {
				//IE 6+ in 'standards compliant mode'
				myWidth = document.documentElement.clientWidth;
				myHeight = document.documentElement.clientHeight;
			} else if (
				document.body &&
				(document.body.clientWidth || document.body.clientHeight)
			) {
				//IE 4 compatible
				myWidth = document.body.clientWidth;
				myHeight = document.body.clientHeight;
			}
			valores3DS.browserScreenHeight = myHeight;
			valores3DS.browserScreenWidth = myWidth;

			//browserTZ
			var d = new Date();
			valores3DS.browserTZ = d.getTimezoneOffset();

			//browserUserAgent
			valores3DS.browserUserAgent = navigator.userAgent;

			var valores3DSstring = JSON.stringify(valores3DS);

			return valores3DSstring;
		}

        function idOperOK() {
            $.ajax({
                url: "{$proc_url}",
                type: "POST",
                data: {
                    {if $allow_ref==true}
                        "save":document.getElementById("check-guardar").checked,
                    {/if}
                    "idOper":document.getElementById("token").value,
                    "idCart":"{$idCart}",
					"merchant_order":"{$merchant_order}",
                    "valores3DS":cargaValoresBrowser3DS()
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
                    }
                    $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("text-align","center");
                    $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("background-image","none");
                    $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("background","none");
                    $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').css("border","none");
                    $('#redsysModalDialog').siblings('div.ui-dialog-titlebar').children("button").remove();
                },
                error: function (request, status, error){
                    window.location.href="{$url_ko}"
                }
            });
        }

        function idOperKO() {
            window.location.href="{$url_ko}";
        }

		function merchantValidation() {

			//Insertar validaciones si fuera necesario.
			return true;
		}

		<!-- Listener de recepción de ID de operacion-->

        window.addEventListener("message", function receiveMessage(event) {
            storeIdOper(event, "token", "errorCode", merchantValidation);
            if (document.getElementById("token").value != "") {
                idOperOK();
            }
        });

    	window.addEventListener('load', function() {
			initRedsyspur();
		});

		function initRedsyspur() {
			loadRedsysForm();
			if (jQuery.ui !== 'undefined'){
				$.getScript("{$this_path|escape:'htmlall'}/views/templates/front/js/jquery-ui.min.js");
				$('head').append( $('<link rel="stylesheet" type="text/css" />').attr('href', '{$this_path|escape:'htmlall'}/views/templates/front/css/\jquery-ui.min.css') );
			}
			$("#redsys-hosted-pay-button").css("height","auto");
			$("#redsys-hosted-pay-button").css("min-height","250px");
			{if $allow_ref!=true}
				$("#checkboxGuardar").hide();
			{/if}
			{if $smarty.const._PS_VERSION_ >= 1.7}
				if($("#conditions-to-approve input[type=checkbox]").size()>0 && !$("#conditions-to-approve input[type=checkbox]")[0].checked){
					$("#redsys-hosted-pay-button").hide();
					$("#redsys-error-tarjeta-nueva").show();
					$("#checkboxGuardar").hide();

					const conditions_to_approve = $("#conditions-to-approve input[type=checkbox]")[0];
					conditions_to_approve.addEventListener('click', function(){
						if (conditions_to_approve.checked) {
							$("#redsys-hosted-pay-button").show();
							$("#redsys-error-tarjeta-nueva").hide();
							{if $allow_ref == true}
								$("#checkboxGuardar").show();
							{/if}
						}
						else {
							$("#redsys-hosted-pay-button").hide();
							$("#redsys-error-tarjeta-nueva").show();
							$("#checkboxGuardar").hide();
						}
					});

				}
			{/if}
		}
	</script>
</div>