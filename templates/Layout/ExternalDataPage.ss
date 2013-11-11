<div class="row">
	<div class="large-9 push-3 columns typography">
		<h3>$Title <% if $Subhead %><small>$Subhead</small><% end_if %></h3>
		$Content
		<a href="$Link(view)" class="tiny button">Add</a>
        <% if ExternalDataList %>
        	<table width="100%">
                <thead>
                    <tr>
                        <th width="200">Title</th>
                        <th width="200">Name</th>
                        <th width="150">Email</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                	<% loop ExternalDataList %>
                        <tr>
                            <td>$Title</td>
                            <td>$Name</td>
                            <td>$Email</td>
                            <td><a href="$Top.Link(view)/$ID">view</a> <a href="$Top.Link(delete)/$ID">delete</a></td>
                        </tr>
                    <% end_loop %>	
                </tbody>
            </table>
        <% end_if %>
        
	</div>
	<div class="large-3 pull-9 columns">
		<% include SideBar %>
	</div>
</div>
