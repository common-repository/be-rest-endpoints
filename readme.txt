=== BE REST Endpoints ===

Contributors:      ChopinBach
Plugin Name:       BE REST Endpoints
Plugin URI:        http://be-webdesign.com
Author URI:        http://be-webdesign.com
Author:            BE Webdesign
Version:           1.0.0
Tags:              WP REST API widgets, REST API widgets, sidebars, widgets, widget-areas
Donate Link:       http://be-webdesign.com
Requires at least: 4.4.0
Tested up to:      4.4.2
Stable tag:        4.4.0
License:           GPLv2 or later

== Description ==

Major features in BE REST Endpoints include:

Sidebars and Widget endpoints for the WP REST API v2.

More improvements will come.


== Disclaimer ==

This plugin has not been tested for all themes.

It is possible that this plugin may not work as intended depending on your theme or if a plugin is modifying the underlying Widgets API.

Themes that are built on top of _s (http://underscores.me) and default themes should work fine.

If you are having issues please contact us at (http://be-webdesign.com/contact/)

Also important to note is that this plugin is more of an experiment and should not be used on a production environment.

Improvements to the JSON schema and inference of schema data from widgets will need to be improved before this plugin is secure.

This plugin is mainly just an illustration of one possibility for a widgets and sidebars endpoint in the WP REST API v2.


== Installation ==

Install WP REST API v2 and activate it. (https://wordpress.org/plugins/rest-api/)

Upload the BE REST Endpoints plugin to your site, *Activate it.*

1, 2, 3: You're done!

Endpoints can be accessed at /wp-json/be/v1/widgets/ and /wp-json/be/v1/sidebars

Lets talk about more interesting stuff now!
To see a particular widget instance or sidebar make a request like this.
'text-2' will serve as our example widget ID and 'sidebar-1' for our sidebar-id.

Note: text-2 would already have to exist.

**For a widget instance:**
GET /wp-json/be/v1/widgets/text-2

**For a sidebar:**
GET /wp-json/be/v1/sidebars/sidebar-1

Cool!!! Now you can make that JavaScript based theme you've been wanting to do.
Now for more interesting stuff!

Lets create a widget via the WP REST API! First, there are a couple of query parameters to go over.

**widget_base**      -> is the type of widget you want to create.  *REQUIRED PARAMETER.*

**sidebar_id**       -> is the id of the sidebar you want to place the widget into. *REQUIRED PARAMETER.*

**sidebar_position** -> is the numeric position of where you want to place the widget in the sidebar.
	sidebar_position does not use array index base numbers instead if you want your widget first use 1.
	if you want it second 2. If you want it 10th, use 10.  This parameter defaults to 1.
	If left empty your widget will automatically default to first in the sidebar.

Now lets do some REST requests.

**Create a text widget in sidebar-1:**

POST /wp-json/be/v1/widgets/?widget_base=text&sidebar_id=sidebar-1

**Create a tag cloud widget in sidebar-1 after our text widget we just made:**

POST /wp-json/be/v1/widgets/?widget_base=tag_cloud&sidebar_id=sidebar-1&sidebar_position=2

Now we are cooking. But wait our new awesome widgets are just empty shells :(
Now we need to update the actual widget instances. Knowledge of how your widgets work comes in very handy here.

You can do these tests in twenty sixteen because it has multiple sidebars.
If you have a theme that supports multiple sidebars feel free to use that just make sure the sidebar IDs match up.

The instance of the widget is what holds it's dynamic data. You must look at the code to know the values that need to be updated.
When you create a widget you will notice that you are returned a series of instances.
The parameters within these indexes are used by the instance.

Lets create a calendar widget.

**Create calendar widget:**

POST /wp-json/be/v1/widgets/?widget_base=calendar&sidebar_id=sidebar-1

We are returned the widget instance. A JSON object that would look like this.

{

		2 - {

			"title": ""

		},

		"_multiwidget": 1

}

So at the numeric index 2 matching calendar-2 we see that this is a simple widget.
It only has the option to have the instance of its title modified. So lets do it.

**POST /wp-json/be/v1/widgets/calendar-2?title=Made by REST API**

Voila! Are calendar widget has a fancy new title. What if we need to move its position though since we want it at the bottom of our sidebar.

**POST /wp-json/be/v1/widgets/calendar-2?sidebar_position=3**

There it is at the end of the sidebar. But wait what I really wanted was for it to be in the other sidebar.  No problem.

**POST /wp-json/be/v1/widgets/calendar-2?sidebar_id=sidebar-2**

Done.

So you also combine these as well lets take our text widget we created and move it to the after the calendar in sidebar-2.

**POST /wp-json/be/v1/widgets/text-2?title=Moved&text=Yes+it+moved&sidebar_id=sidebar-2&sidebar_position=2**

I forgot that I didn't want to do any of this. Luckily, we have delete as well. Lets clean up.

**Delete our widgets:**

DELETE wp-json/be/v1/widgets/calendar-2
DELETE wp-json/be/v1/widgets/text-2
DELETE wp-json/be/v1/widgets/tag_cloud-2

The unique IDs for widgets will incrementally increase. If you create three text widgets you would have text-2, text-3, text-4.
So now, you could write an administrative tool that utilizes the widgets and sidebars endpoints!

If you have any questions feel free to contact us at (http://be-webdesign.com/contact/)


== Changelog ==

No changes yet.


== Frequently Asked Questions ==

No questions have been frequently asked yet!


== Donations ==

Coming soon, maybe.
