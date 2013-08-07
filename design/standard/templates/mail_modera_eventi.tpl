	{set-block scope=root variable=subject}
		{concat( "Un nuovo evento è in attesa di moderazione - ", $event_name )}
	{/set-block}

L'evento {$event_name} è in attesa di moderazione:
http://{$hostname}{concat('content/view/full/', $node_id )|ezurl(no)}
