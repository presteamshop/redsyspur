{if str_replace(".", "", $smarty.const._PS_VERSION_) >= 1781}
	{extends file=$layout}

	{block name='content'}
		<p class="warning">
			{l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact our' mod='redsys'} 
			<a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' mod='redsys'}</a>.
		</p>
	{/block}
{else if $smarty.const._PS_VERSION_ >= 1.7}
	<!doctype html>
	<html lang="{$language.iso_code}">
	
	<head>
	  {block name='head'}
	    {include file='_partials/head.tpl'}
	  {/block}
	</head>
	
	<body>
	
	  {hook h='displayAfterBodyOpeningTag'}
	
	  <main>
	
	    <header id="header">
	      {block name='header'}
	        {include file='_partials/header.tpl'}
	      {/block}
	    </header>
	
	    <section id="wrapper">
	      <div class="container">
	
	        {block name='breadcrumb'}
	          {include file='_partials/breadcrumb.tpl'}
	        {/block}
	
	        {block name="content_wrapper"}
	          <div id="content-wrapper">
	            {block name="content"}
					<p class="warning">
						{l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact our' mod='redsys'} 
						<a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' mod='redsys'}</a>.
					</p>
	            {/block}
	          </div>
	        {/block}
	
	      </div>
	    </section>
	
	    <footer id="footer">
	      {block name="footer"}
	        {include file="_partials/footer.tpl"}
	      {/block}
	    </footer>
	
	  </main>
	
	  {hook h='displayBeforeBodyClosingTag'}
	
	  {block name='javascript_bottom'}
	    {include file="_partials/javascript.tpl" javascript=$javascript.bottom}
	  {/block}
	</body>
	</html>
{else}
	<p class="warning">
		{l s='We have noticed that there is a problem with your order. If you think this is an error, you can contact our' mod='redsys'} 
		<a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='customer service department.' mod='redsys'}</a>.
	</p>
{/if}