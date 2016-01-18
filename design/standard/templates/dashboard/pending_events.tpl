

{def $stato_id = ezini( 'Events', 'StatoDaModerareID', 'ocmoderaeventi.ini' )}
{def $root_node_id = ezini( 'NodeSettings', 'RootNode', 'content.ini' ) }

{def $counter = fetch( 'content', 'tree_count', hash( 'parent_node_id', $root_node_id,
                    'main_node_only', true(),
                    'class_filter_type', include,
                    'class_filter_array', ezini( 'Events', 'ClassIdentifiers', 'ocmoderaeventi.ini' ),
                    'attribute_filter', array( 'and', array( 'state', "=", $stato_id ) ) ) )}
                 
{if $counter|gt(0)}

<h2>{$counter} eventi da moderare</h2>

<div style="height: 300px;overflow-y: auto">
<table class="list" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <th>Titolo</th>
        <th>In attesa dal</th>
        <th>Creatore</th>
        {*<th class="tight">Rimuovi</th>*}
        <th class="tight">Modifica</th>
        <th class="tight">Approva</th>
    </tr>
    {foreach fetch( 'content', 'tree', hash( 'parent_node_id', $root_node_id,
                    'main_node_only', true(),
                    'class_filter_type', include,
                    'class_filter_array', ezini( 'Events', 'ClassIdentifiers', 'ocmoderaeventi.ini' ),
                    'sort_by', array( 'published', false() ),
                    'attribute_filter', array( 'and', array( 'state', "=", $stato_id ) ) )
                           ) as $event sequence array( 'bglight', 'bgdark' ) as $style}
        <tr class="{$style}">
            <td>
                <a href="{concat( '/content/view/full/', $event.main_node_id )|ezurl('no')}" target="_blank" title="{$event.name|wash()}">
                    {$event.name|wash()}
                </a>
            </td>            
            <td>
                {$event.object.published|l10n('shortdate')}
            </td>
            <td>
                {$event.object.owner.name|wash()}
            </td>
            {*<td>
                <center>
                <a href="{concat( '/moderazione/rimuovi/', $event.object.id )|ezurl('no')}" title="">
                    <img src={'trash.png'|ezimage} width="16" height="16" border="0" alt="{'Edit'|i18n( 'design/admin/dashboard/drafts' )}" />
                </a>
                </center>
            </td>*}
            <td>
                <center>
                <a href="{concat( '/content/edit/', $event.object.id, '/f/', $event.object.initial_language_code )|ezurl('no')}" title="">
                    <img src={'edit.png'|ezimage} width="16" height="16" border="0" alt="{'Edit'|i18n( 'design/admin/dashboard/drafts' )}" />
                </a>
                </center>
            </td>
            <td>
                <center>
                <a href="{concat( '/moderazione/approva/', $event.object.id )|ezurl('no')}" title="">
                    <img src={'add.png'|ezimage} width="16" height="16" border="0" alt="{'Edit'|i18n( 'design/admin/dashboard/drafts' )}" />
                </a>
                </center>
            </td>
        </tr>
        
    {/foreach}
</table>
</div>
{else}
<div class="warning message-warning">
<h4>Non ci sono eventi in attesa di moderazione.</h4>
</div>

{/if}