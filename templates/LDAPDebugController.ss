<% if $Options %>
	<h2>Connection options</h2>
	<ul>
	<% loop $Options %>
		<li>$Name: $Value</li>
	<% end_loop %>
	</ul>
<% end_if %>

<h2>Default group</h2>
<% if $DefaultGroup %>
	$DefaultGroup
<% else %>
	<p>Users will not be added to any default groups. Set LDAPService.default_group to configure this.</p>
<% end_if %>

<h2>Mapped groups</h2>
<% if $MappedGroups %>
	<ul>
	<% loop $MappedGroups %>
		<li>$DN -> $Group.Title (Code: $Group.Code), Scope: $Scope</li>
	<% end_loop %>
	</ul>
<% else %>
	<p>There are no mapped groups. You can add some by going into the Security -> Groups section of the CMS.</p>
<% end_if %>

<% if $UsersSearchLocations %>
	<h2>Users search locations</h2>
	<ul>
	<% loop $UsersSearchLocations %>
		<li>$Value</li>
	<% end_loop %>
	</ul>
<% end_if %>

<% if $GroupsSearchLocations %>
	<h2>Groups search locations</h2>
	<ul>
	<% loop $GroupsSearchLocations %>
		<li>$Value</li>
	<% end_loop %>
	</ul>
<% end_if %>

<% if $Nodes %>
	<h2>Nodes (organizational units, containers, and domains)</h2>
	<ul>
	<% loop $Nodes %>
		<li>$DN</li>
	<% end_loop %>
	</ul>
<% end_if %>

<h2>Users</h2>

<p>$Users users were found in the directory.</p>

<% if $Groups %>
	<h2>Groups</h2>
	<ul>
	<% loop $Groups %>
		<li>$DN</li>
	<% end_loop %>
	</ul>
<% end_if %>
