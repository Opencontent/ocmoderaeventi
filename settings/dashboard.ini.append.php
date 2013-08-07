#?ini charset="utf-8"?

[DashboardSettings]
DashboardBlocks[]=events

# DashboardBlocks[]=wishlist

# [DashboardBlock_example]
# By default system will load /dashboard/[block_identifier].tpl template
# e.g if INI group is called [Dashboard_example] then system will load /dashboard/example.tpl file
# If Template variable is specified then it will be used instead of default location
# Template=example.tpl

# Priority determines the order in which the blocks will be rendered
# Priority=10

# List of policies, either in "<node_id>" or "<module>/<function>" form
# In case of node id, content/read is checked!
# PolicyList[]

# Controls how many items are fetched and displayed in the dashboard block
# Value available as a {$block.number_of_items} variable
# NumberOfItems=10

[DashboardBlock_events]
Priority=10
NumberOfItems=10
PolicyList[]=moderazione/approva
# PolicyList[]=moderazione/rimuovi
