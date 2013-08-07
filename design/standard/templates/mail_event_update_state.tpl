
{if $approvato}

	{set-block scope=root variable=subject}
		{concat( "L'evento '", $event_name, "' è stato approvato!" )}
	{/set-block}

La informiamo che l'evento "{$event_name}" è stato approvato e pubblicato!
	
{else}

	{set-block scope=root variable=subject}
		{concat( "L'evento '", $event_name, "' è in attesa di moderazione" )}
	{/set-block}
	
L'evento {$event_name} è stato inserito in coda di moderazione ed è in attesa di approvazione da parte di un moderatore.
	
{/if}


