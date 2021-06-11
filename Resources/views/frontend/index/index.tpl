{extends file='parent:frontend/index/index.tpl'}

{nocache}
	{block name='frontend_index_before_page' prepend}
		{if $nnwebVoucherLinkMessage}
			{assign var="messageType" value="success"}
			{if $nnwebVoucherLinkMessageType != ''}
				{assign var="messageType" value=$nnwebVoucherLinkMessageType}
			{/if}
		    {include file="frontend/_includes/messages.tpl" type="{$messageType}" content="{$nnwebVoucherLinkMessage}" borderRadius=false}
		{/if}
	{/block}
{/nocache}
