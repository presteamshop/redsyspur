{*
* NOTA SOBRE LA LICENCIA DE USO DEL SOFTWARE
* 
* El uso de este software está sujeto a las Condiciones de uso de software que
* se incluyen en el paquete en el documento "Aviso Legal.pdf". También puede
* obtener una copia en la siguiente url:
* http://www.redsys.es/wps/portal/redsys/publica/areadeserviciosweb/descargaDeDocumentacionYEjecutables
* 
* Redsys es titular de todos los derechos de propiedad intelectual e industrial
* del software.
* 
* Quedan expresamente prohibidas la reproducción, la distribución y la
* comunicación pública, incluida su modalidad de puesta a disposición con fines
* distintos a los descritos en las Condiciones de uso.
* 
* Redsys se reserva la posibilidad de ejercer las acciones legales que le
* correspondan para hacer valer sus derechos frente a cualquier infracción de
* los derechos de propiedad intelectual y/o industrial.
* 
* Redsys Servicios de Procesamiento, S.L., CIF B85955367
*}

{if $smarty.const._PS_VERSION_ >= 1.6}
	{if $show_saved==true}
	<p class="payment_module">
		<a id="aRedsysReference" style="padding-left: 15px" href="javascript:gotoRedsysReference();">	
			<img src="{$module_dir|escape:'htmlall'}img/tarjetas.png" alt="{l s='Conectar con el TPV' mod='redsys'}" height="48" />
			<span id="redsysRef_title" style="color: #333">{$payment_name} {if $show_brand==true} <img src="{$module_dir|escape:'htmlall'}views/templates/front/img/brands/{$card_brand}.jpg"/> {/if}</span>
			<span id="redsysRef_proc" style="display: none;">{l s='Procesando...' mod='redsys'}</span>
		</a>
	</p>
	{/if}
	<div class="row">
		<div class="col-xs-12">
			<p class="payment_module">
				<a id="aRedsysReference" style="padding-left: 15px" href="javascript:toggleRedsysForm();" title="{l s='Conectar con el TPV' mod='redsys'}">	
					<img src="{$module_dir|escape:'htmlall'}img/tarjetas.png" alt="{l s='Conectar con el TPV' mod='redsys'}" height="48" />
					{l s='Pago con tarjeta' mod='redsys'}
				</a>
			</p>
		</div>
	</div>
{else}
	{if $show_saved==true}
	<p class="payment_module">
		<a style="padding-left: 15px" href="javascript:gotoRedsysReference();">	
			<img src="{$module_dir|escape:'htmlall'}img/tarjetas.png" alt="{l s='Conectar con el TPV' mod='redsys'}" height="48" />
			<span id="redsysRef_title" style="color: #333">{$payment_name} {if $show_brand==true} <img src="{$module_dir|escape:'htmlall'}views/templates/front/img/brands/{$card_brand}.jpg"/> {/if}</span>
			<span id="redsysRef_proc" style="display: none;">{l s='Procesando...' mod='redsys'}</span>
		</a>
	</p>
	{/if}
	<p class="payment_module">
		<a style="padding-left: 15px" href="javascript:toggleRedsysForm();" title="{l s='Conectar con el TPV' mod='redsys'}">	
			<img src="{$module_dir|escape:'htmlall'}img/tarjetas.png" alt="{l s='Conectar con el TPV' mod='redsys'}" height="48" />
			{l s='Pago con tarjeta' mod='redsys'}
		</a>
	</p>
{/if}

{if $allow_ref==true}
	<form action="{$proc_ref_url|escape:'htmlall'}" method="post" id="redsys_ref" class="hidden">	
		<input type="hidden" name="merchant_order" value="{$merchant_order|escape:'htmlall'}" />
		<input type="hidden" name="idCart" value="{$idCart|escape:'htmlall'}" />
	</form>
{/if}

{include file="{$disk_path}/views/templates/front/paymentform.tpl"}