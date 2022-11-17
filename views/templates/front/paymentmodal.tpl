<link rel="stylesheet" type="text/css"
	href="{$this_path|escape:'htmlall'}/views/templates/front/css/estilos.css" />
<script src="{$url_modal}"></script>

<button id="btnShowModal" class="btn btn-primary center-block"> PAGAR </button>

<script>
    window.addEventListener('load', function() {
        {if $smarty.const._PS_VERSION_ >= 1.7}
            if($("#conditions-to-approve input[type=checkbox]").size()>0 && !$("#conditions-to-approve input[type=checkbox]")[0].checked){
                $("#btnShowModal").hide();
                
                const conditions_to_approve = $("#conditions-to-approve input[type=checkbox]")[0];
                conditions_to_approve.addEventListener('click', function(){
                    if (conditions_to_approve.checked) {
                        $("#btnShowModal").show();
                    }
                    else {
                        $("#btnShowModal").hide();
                    }
                });

            }
        {/if}

        document.getElementById("btnShowModal").addEventListener("click", function(){
            var requestParams = {
                FormData: {
                    Ds_SignatureVersion: '{$Ds_SignatureVersion}',
                    Ds_MerchantParameters: '{$Ds_MerchantParameters}',
                    Ds_Signature: '{$Ds_Signature}'
                },
                ReturnFunction: 'getPaymentResponse',
                Environment : '{$environment_modal}'
            };
            initPayment(requestParams);

            $('div.kill_box > img').on('click', function(){
                window.location.href = '{$url_ko}';
            });
        });

        window.addEventListener('message', function(event) {
            parsePaymentResponse(event);
        });
    });

    function getPaymentResponse(response){
        window.location.href = response.ReturnURL;
    }
</script>