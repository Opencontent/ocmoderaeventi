
{if $approvato}

	{set-block scope=root variable=subject}
		{concat( "L'evento '", $event.name|wash(), "' è stato approvato!" )}
	{/set-block}

La informiamo che l'evento "{$event.name|wash()}" è stato approvato e pubblicato!
	
{else}

	{set-block scope=root variable=subject}
		{concat( "L'evento '", $event.name|wash(), "' è in attesa di moderazione" )}
	{/set-block}
	
L'evento {$event.name|wash()} è stato inserito in coda di moderazione ed è in attesa di approvazione da parte di un moderatore.
	
{/if}


