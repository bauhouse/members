# Members Extension (Unofficial Release)

A Symphony CMS extension for creating a members-based community. This extension is currently being used to manage the new [Symphony CMS](http://symphony-cms.com/) community site, blog, and forum, which are all running on a single install of Symphony 2. All credit for creating the Members extension goes to Alistair Kearney ([pointybeard](http://github.com/pointybeard)). I am merely releasing this extension, unofficially, as a GitHub repository.

* [Members Extension](http://github.com/bauhouse/members/tree)

> While not officially released by the developers, I asked for the Members extension and Alistair was kind enough to supply it. At the time, there were some patches needing to be pushed to the integration branch that prevented the release of the extension. With the release of 2.0.3 ... the extension seems to be in working order, but I haven't fully tested it.

I say "unofficially" not only because I didn't develop it, but also because I haven't fully tested the extension and it lacks documentation. I'll pull together what I know here:

### Known Issues

1. The extension will throw an error when navigating to Members : Roles before configuring preferences in Members : Preferences.

	Fatal error: Call to undefined method Symphony::database() in /Users/stephen/Sites/sym/members-ext/extensions/members/extension.driver.php on line 200

2. There is a CSS bug related to help text below the fieldset legend elements on the Members Roles and Preferences pages in Firefox. The fix is available from the [integration branch](http://github.com/symphony/symphony-2/tree/integration) of the symphony-2 repository.

### Dependencies

The Members extension is dependent on the [Advanced Symphony Database Connector (ASDC)](http://github.com/pointybeard/asdc/tree/master) and the [ConfigurationAccessor](http://github.com/bauhouse/library/blob/47b6ac0da7bf790840aafb033fad6645347e3108/lib/class.configurationaccessor.php) class from a Symphony Extensions Library. Install these in the `extensions` directory.

    extensions/asdc
    extensions/library/lib/class.configurationaccessor.php

### Installation

These can be installed with Git by cloning the GitHub repositories:

    cd extensions
    git clone git://github.com/bauhouse/members.git
    git clone git://github.com/pointybeard/asdc.git
    git clone git://github.com/bauhouse/library.git

Or install them as submodules:

    git submodule add git://github.com/bauhouse/members.git extensions/members
    git submodule add git://github.com/pointybeard/asdc.git extensions/asdc
    git submodule add git://github.com/bauhouse/library.git extensions/library
        
### Configuration

#### Create a Members Section

The Members extension provides 3 new fields. The important 2 are "Member Role" and "Member username & password", both of which need to be added to a members section, along with an Email address field, which will be specified as the email address to which the "Forgot Password" process will use to retrieve a password for a user. The Member Link field is for linking entries to a member account, similar to a Select Box Link, in effect.

For example, in the context of the [Symphony CMS](http://symphony-cms.com/) site, there is a Members section that contains the Member Role and Member Username/Password fields (as well as email address and other information). Each forum Discussion entry has a Member Link field, stating which Member it belongs to. In the backend this is manifested as a textfield with the Member "username" in it. But when sending via an Event, send the ID of the Member entry itself.

#### Set Member Section Preferences

The Members extension adds a "Members" menu with two menu items: Roles and Preferences. (As stated previously, don't first navigate to Roles or you will encounter the error mentioned above.) Once the members section has been created, first set the preferences in Members : Preferences. The select box menu for Member Section automatically filters only those sections that contain a Member Username/Password field and a Member Role field. Click the "Save Changes" button to save the Members Section. This will enable the Email Address select box, to select the corresponding email input field. Click the "Save Changes" button again to save the Email Address field preference. 

<a href="http://www.flickr.com/photos/bauhouse/3702896731/" title="members_extension_prefs by bauhouse, on Flickr"><img src="http://farm4.static.flickr.com/3451/3702896731_3a7d0b6a3b_o.png" width="786" height="1066" alt="members_extension_prefs" /></a>

#### Creating Roles and Permissions

Navigate to Members : Roles to view the default "Guest" role. This default role cannot be deleted. However, the preferences can be set for the Name of the role, Event Level Permissions, when any events exist, Page Level Permissions, Operations, and the  Email Template for successful member registration.

<a href="http://www.flickr.com/photos/bauhouse/3703704062/" title="members_extension_roles by bauhouse, on Flickr"><img src="http://farm4.static.flickr.com/3425/3703704062_a6b7a78d53_o.png" width="786" height="1066" alt="members_extension_roles" /></a>

### Create Login Form

Then, to allow logins, use a utility like this one for a login form:

	<?xml version="1.0" encoding="UTF-8"?>
	<xsl:stylesheet version="1.0"
		xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
	
	<xsl:template name="login-panel">
		<xsl:param name="member" select="/data/events/member-login-info"/>
		<form id="login-panel" action="{$current-url}" method="post">
			<fieldset>
				<ul>
					<xsl:choose>
						<xsl:when test="$member/@logged-in = 'true'">
							<li>
								<a href=""><xsl:value-of select="$member/name"/></a>
								<xsl:text> </xsl:text>
								<a href="?member-action=logout">(Logout)</a>
							</li>
						</xsl:when>
						<xsl:otherwise>
							<li><input name="username" value="username"/></li>
							<li><input name="password" type="password" value="password"/></li>
							<li><input id="submit" type="submit" name="member-action[login]" value="Log In"/></li>
						</xsl:otherwise>
					</xsl:choose>
				</ul>
			</fieldset>
		</form>
	</xsl:template>
	
	</xsl:stylesheet>

Notice that you don't need to explicitly add any event to your pages. The Members extension uses delegates to validate logins based on the existence of `member-action[login]` in the form's post data.

### Members: Forgot Password Event

The Members extension includes a Members: Forgot Password event that can be attached to a page. The event provides a description that includes the following example form and example response:

#### Example Form


	<form action="" method="post">
		<input name="member-email-address" type="text"/>
		<input name="action[member-retrieve-password]" value="go" type="submit"/>
	</form>

#### Example Response

	<forgot-password sent="true">Email sent</forgot-password>

### Member Registration Form

To create a Member Registration form, navigate to Blueprints : Components and click on the "Create New" button next to "Events". Type a name for the event in the Name field, such as "Register Member" and select the members section from the Source select box. Select the Send Email option in Filter Rules and click the "Create Event" button to create the "Register Member" event. The event provides the following usage instructions in the event description:

#### Success and Failure XML Examples

When saved successfully, the following XML will be returned:

	<register-member result="success" type="create | edit">
	  <message>Entry [created | edited] successfully.</message>
	</register-member>

When an error occurs during saving, due to either missing or invalid fields, the following XML will be returned:

	<register-member result="error">
	  <message>Entry encountered errors when saving.</message>
	  <field-name type="invalid | missing" />
	  ...
	</register-member>

The following is an example of what is returned if any filters fail:

	<register-member result="error">
	  <message>Entry encountered errors when saving.</message>
	  <filter name="admin-only" status="failed" />
	  <filter name="send-email" status="failed">Recipient username was invalid</filter>
	  ...
	</register-member>

#### Example Front-end Form Markup

This is an example of the form markup you can use on your frontend:

	<form method="post" action="" enctype="multipart/form-data">
	  <input name="MAX_FILE_SIZE" type="hidden" value="5242880" />
	  <label>Full Name
		<input name="fields[full-name]" type="text" />
	  </label>
	  <label>First Name
		<input name="fields[first-name]" type="text" />
	  </label>
	  <label>Last Name
		<input name="fields[last-name]" type="text" />
	  </label>
	  <div class="group">
		<label>Username
		  <input name="fields[username][username]" type="text" />
		</label>
		<label>Password
		  <input name="fields[username][password]" type="password" />
		</label>
	  </div>
	  <label>Role
		<select name="fields[role]">
		  <option value="1">Guest</option>
		</select>
	  </label>
	  <label>Email
		<input name="fields[email]" type="text" />
	  </label>
	  <input name="action[register-member]" type="submit" value="Submit" />
	</form>

To edit an existing entry, include the entry ID value of the entry in the form. This is best as a hidden field like so:

	<input name="id" type="hidden" value="23" />

To redirect to a different location upon a successful save, include the redirect location in the form. This is best as a hidden field like so, where the value is the URL to redirect to:

	<input name="redirect" type="hidden" value="http://home/sym/clac/wireframes/001/success/" />

#### Send Email Filter

The send email filter, upon the event successfully saving the entry, takes input from the form and send an email to the desired recipient. This filter currently does not work with the "Allow Multiple" option. The following are the recognised fields:

	send-email[from]
	send-email[subject] // Optional
	send-email[body]
	send-email[recipient] // list of comma author usernames.

All of these fields can be set dynamically using the exact field name of another field in the form as shown below in the example form:

	<form action="" method="post">
	  <fieldset>
		<label>Name <input type="text" name="fields[author]" value="" /></label>
		<label>Email <input type="text" name="fields[email]" value="" /></label>
		<label>Message <textarea name="fields[message]" rows="5" cols="21"></textarea></label>
		<input name="send-email[from]" value="fields[email]" type="hidden" />
		<input name="send-email[subject]" value="You are being contacted" type="hidden" />
		<input name="send-email[body]" value="fields[message]" type="hidden" />
		<input name="send-email[recipient]" value="fred" type="hidden" />
		<input id="submit" type="submit" name="action[save-contact-form]" value="Send" />
	  </fieldset>
	</form>

For any discussion regarding this extension, please refer to the [Members Extension (Unofficial Release)](http://symphony-cms.com/community/discussions/24100/) discussion on the Symphony CMS site.

### Change Log

Version 1.0.2 - 25 August 2009

* Add page permissions to XML for default member role (bauhouse)
* Fix 403 Forbidden error pages (michael-e / bauhouse)
* Roles page now returns error if member section is not set (lewiswharf)
* Checks for ConfigurationAccessor class on install (lewiswharf)
* Send welcome email on creation only: processEventData() returns false if entry_id doesn't exist. Fixes Issue #6 (lewiswharf)

Version 1.0.1 - 13 August 2009

* Fix page alerts for editing Member Roles. Fixes Issue #5 (phoque)
* Make the __call() method public for PHP 5.3.0 compatibility. Fixes Issue #2 (tonyarnold)
* Added $member-id param to pool (lewiswharf)
* Change case of title attribute on the 'Create a New Role' button to 
sentence case, for consistency (bauhouse)
* Added documentation to README (bauhouse)

Unofficially Released - 4 July 2009

* Released on GitHub by bauhouse (Stephen Bau)

Version 1.0 - 12 April 2008

* Developed by Symphony Team
