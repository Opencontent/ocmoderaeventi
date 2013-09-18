{set-block scope=root variable=subject}
    {concat( "Un nuovo evento è in attesa di moderazione - ", $event.name|wash() )}
{/set-block}

L'evento {$event.name|wash()} è in attesa di moderazione:
http://{$hostname}{$event.url_alias|ezurl(no)}
