<% if $Options %>
	<h2>Connection options</h2>
	<ul>
	<% loop $Options %>
		<li>$Name: $Value</li>
	<% end_loop %>
	</ul>
<% end_if %>

<% if $SearchLocations %>
	<h2>Search locations</h2>
	<ul>
	<% loop $SearchLocations %>
		<li>$Value</li>
	<% end_loop %>
	</ul>
<% end_if %>

<p>$Users users were found in the directory.</p>

<% if $Groups %>
	<h2>Groups</h2>
	<ul>
	<% loop $Groups %>
		<li>$DN</li>
	<% end_loop %>
	</ul>
<% end_if %>
