<div class="row">
	<div class="large-9 push-3 columns typography">
		<h3>$Title <% if $Subhead %><small>$Subhead</small><% end_if %></h3>
		$Content
		
        <% if ExternalDataItem %>
        	<% with ExternalDataItem %>
                <table>
                    <thead>
                        <tr>
                            <th width="200">Field</th>
                            <th width="200">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                       	<tr>
                            <td>Title</td>
                            <td>$Title</td>
                        </tr>
                        <tr>
                            <td>Name</td>
                            <td>$Name</td>
                        </tr>
                        <tr>
                            <td>Email</td>
                            <td>$Email</td>
                        </tr>
                    </tbody>
                </table>
            <% end_with %>
        <% end_if %>
        $DetailForm
	</div>
	<div class="large-3 pull-9 columns">
		<% include SideBar %>
	</div>
</div>
