<h2>Eventi da moderare</h2>

{def $stato_id = ezini( 'Events', 'StatoDaModerareID', 'ocmoderaeventi.ini' )}
{def $root_node_id = ezini( 'NodeSettings', 'RootNode', 'content.ini' ) }

{def $counter = fetch( 'content', 'tree_count', hash( 'parent_node_id', $root_node_id,
                    'main_node_only', true(),
                    'attribute_filter', array( 'and', array( 'state', "=", $stato_id ) ) ) )}
                 
{if $counter|gt(0)}

<table class="list" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <th>Eventi</th>
        <th>In attesa dal</th>
        <th class="tight">Rimuovi</th>
        <th class="tight">Modifica</th>
        <th class="tight">Approva</th>
    </tr>
    {*foreach fetch( 'content', 'draft_version_list', hash( 'limit', $block.number_of_items ) ) as $draft sequence array( 'bglight', 'bgdark' ) as $style*}
    {foreach fetch( 'content', 'tree', hash( 'parent_node_id', $root_node_id,
                    'main_node_only', true(),
                    'sort_by', array( 'published', 'desc' ),
                    'attribute_filter', array( 'and', array( 'state', "=", $stato_id ) ) )
                           ) as $event sequence array( 'bglight', 'bgdark' ) as $style}
        <tr class="{$style}">
            <td>
                <a href="{concat( '/content/view/full/', $event.main_node_id, '/', $event.initial_language.locale, '/' )|ezurl('no')}" target="_blank" title="{$event.name|wash()}">
                    {$event.name|wash()}
                </a>
            </td>
            <td>
                {$event.object.published|l10n('shortdatetime')}
            </td>
            <td>
                <center>
                <a href="{concat( '/moderazione/rimuovi/', $event.object.id )|ezurl('no')}" title="">
                    <img src={'trash.png'|ezimage} width="16" height="16" border="0" alt="{'Edit'|i18n( 'design/admin/dashboard/drafts' )}" />
                </a>
                </center>
            </td>
            <td>
                <center>
                <a href="{concat( '/content/edit/', $event.object.id )|ezurl('no')}" title="">
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

{else}

<h4>Non ci sono eventi in attesa di moderazione.</h4>

{/if}