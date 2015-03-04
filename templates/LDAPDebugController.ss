<% if $Options %>
	<h2>Connection options</h2>
	<ul>
	<% loop $Options %>
		<li>$Name: $Value</li>
	<% end_loop %>
	</ul>
<% end_if %>

<% if $Groups %>
	<h2>Groups</h2>
	<ul>
	<% loop $Groups %>
		<li>$DN</li>
	<% end_loop %>
	</ul>
<% end_if %>

